<?php

namespace Tests\Unit;

use App\Services\RulesEngine\RulesEngine;
use Tests\TestCase;

class RulesEngineActionParamsTest extends TestCase
{
    /**
     * Regression test: tasks.json declares fallback action params under the
     * file-level "config.defaults" block (see rules/tasks/s3/tasks.json), not
     * per-task. buildActionParams() only ever looked at $taskConfig['defaults'],
     * so for any action name that doesn't happen to contain "Bucket"/"User"/"Role"
     * (e.g. getPublicAccessBlock) it fell through to "Could not determine params"
     * and was called with no Bucket param at all.
     */
    public function test_action_without_bucket_in_name_resolves_bucket_param_from_defaults(): void
    {
        $rulesEngine = new RulesEngine();
        $method = new \ReflectionMethod(RulesEngine::class, 'buildActionParams');
        $method->setAccessible(true);

        $item = ['Name' => 'my-test-bucket', 'CreationDate' => '2026-01-01T00:00:00Z'];

        // Mirrors what executeScan() now merges into $taskConfig from the
        // file-level "config.defaults" before calling executeTask().
        $taskConfig = [
            'defaults' => [
                'actions' => [
                    'params' => ['Bucket' => "\$item['Name']"],
                ],
            ],
        ];

        $params = $method->invoke($rulesEngine, $item, $taskConfig, 'getPublicAccessBlock', null);

        $this->assertSame(['Bucket' => 'my-test-bucket'], $params);
    }

    public function test_s3_tasks_json_declares_defaults_at_file_level_config(): void
    {
        $rulesEngine = new RulesEngine();
        $tasks = $rulesEngine->loadTasks('s3');

        $this->assertArrayHasKey('defaults', $tasks['config']);
        $this->assertSame(
            ['Bucket' => "\$item['Name']"],
            $tasks['config']['defaults']['actions']['params']
        );

        // The per-task config (as it appears under "tasks") never carries its
        // own "defaults" key — executeScan() is responsible for merging the
        // file-level default in before actions are executed.
        $listBucketsTaskConfig = $tasks['tasks'][0]['listBuckets'];
        $this->assertArrayNotHasKey('defaults', $listBucketsTaskConfig);
    }
}
