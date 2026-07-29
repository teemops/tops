<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Services\RulesEngine\RulesEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Records every call it is asked to make and replays canned responses, standing in for
 * a real AWS client so the collection path can be driven end to end.
 */
class RecordingScanner
{
    /** @var array<int, array{method: string, params: array}> */
    public array $calls = [];

    public function __construct(private array $responses)
    {
    }

    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $this->calls[] = ['method' => $method, 'params' => $params];

        return $this->responses[$method] ?? [];
    }
}

/**
 * Drives RulesEngine::executeTask() with the real shipped tasks.json definitions, so the
 * item extraction, resource identification, param building and detail storage are all
 * exercised together against the actual response shapes AWS returns.
 *
 * These are the shapes the pilot services were chosen for — a list of bare strings, and
 * a doubly-nested list — because neither could be expressed as JSON before this work.
 */
class RulesEngineCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function runTask(Scan $scan, string $service, string $taskName, RecordingScanner $scanner, ?string $region = 'us-east-1'): void
    {
        $engine = new RulesEngine();
        $tasks = $engine->loadTasks($service);

        $taskConfig = null;
        foreach ($tasks['tasks'] as $group) {
            if (isset($group[$taskName])) {
                $taskConfig = $group[$taskName];
            }
        }
        $this->assertNotNull($taskConfig, "{$service} has no task {$taskName}");

        if (!isset($taskConfig['defaults']) && isset($tasks['config']['defaults'])) {
            $taskConfig['defaults'] = $tasks['config']['defaults'];
        }

        $method = new \ReflectionMethod(RulesEngine::class, 'executeTask');
        $method->setAccessible(true);
        $method->invoke($engine, $scan, $scanner, $service, $taskName, $taskConfig, [], $region);
    }

    /**
     * DynamoDB listTables returns bare table names. Each must become a resource in its
     * own right and be passed back into the per-table describe calls.
     */
    public function test_a_list_of_bare_strings_becomes_identified_resources(): void
    {
        $scan = Scan::factory()->create();
        $scanner = new RecordingScanner([
            'listTables' => ['TableNames' => ['orders', 'customers']],
            'describeTable' => ['Table' => ['TableName' => 'x']],
            'describeContinuousBackups' => ['ContinuousBackupsDescription' => []],
        ]);

        $this->runTask($scan, 'dynamodb', 'listTables', $scanner);

        // The table name is the resource id, with no field to read it from.
        $this->assertEqualsCanonicalizing(
            ['orders', 'customers'],
            ScanDetail::where('scan_id', $scan->id)
                ->where('api_method', 'listTables')
                ->whereNotNull('resource_id')
                ->pluck('resource_id')
                ->all()
        );

        // And it is threaded through to each follow-up call as TableName.
        $describeParams = collect($scanner->calls)
            ->where('method', 'describeTable')
            ->pluck('params')
            ->all();

        $this->assertEqualsCanonicalizing(
            [['TableName' => 'orders'], ['TableName' => 'customers']],
            $describeParams
        );
    }

    /**
     * Scalar items are wrapped for the JSON column rather than crashing on storage.
     */
    public function test_a_scalar_item_is_stored_as_json(): void
    {
        $scan = Scan::factory()->create();
        $scanner = new RecordingScanner(['listTables' => ['TableNames' => ['orders']]]);

        $this->runTask($scan, 'dynamodb', 'listTables', $scanner);

        $detail = ScanDetail::where('scan_id', $scan->id)
            ->where('api_method', 'listTables')
            ->where('resource_id', 'orders')
            ->first();

        $this->assertSame(['Value' => 'orders'], $detail->raw_data);
    }

    /**
     * SQS pairs a scalar item list with a literal array parameter — getQueueAttributes
     * returns nothing useful unless AttributeNames is passed as ["All"].
     */
    public function test_a_literal_array_param_survives_alongside_a_scalar_item(): void
    {
        $scan = Scan::factory()->create();
        $url = 'https://sqs.us-east-1.amazonaws.com/123456789012/my-queue';
        $scanner = new RecordingScanner([
            'listQueues' => ['QueueUrls' => [$url]],
            'getQueueAttributes' => ['Attributes' => ['QueueArn' => 'arn:aws:sqs:us-east-1:123456789012:my-queue']],
        ]);

        $this->runTask($scan, 'sqs', 'listQueues', $scanner);

        $call = collect($scanner->calls)->firstWhere('method', 'getQueueAttributes');

        $this->assertSame([
            'QueueUrl' => $url,
            'AttributeNames' => ['All'],
        ], $call['params']);
    }

    /**
     * EC2 instances sit two levels down, under Reservations[].Instances[]. This was the
     * one nested shape the engine knew about, by name; it is now a declared path.
     */
    public function test_a_nested_item_path_is_flattened(): void
    {
        $scan = Scan::factory()->create();
        $scanner = new RecordingScanner([
            'describeInstances' => [
                'Reservations' => [
                    ['Instances' => [['InstanceId' => 'i-1'], ['InstanceId' => 'i-2']]],
                    ['Instances' => [['InstanceId' => 'i-3']]],
                ],
            ],
        ]);

        $this->runTask($scan, 'ec2', 'describeInstances', $scanner);

        $this->assertEqualsCanonicalizing(
            ['i-1', 'i-2', 'i-3'],
            ScanDetail::where('scan_id', $scan->id)
                ->where('api_method', 'describeInstances')
                ->whereNotNull('resource_id')
                ->pluck('resource_id')
                ->all()
        );
    }

    /**
     * IAM users are keyed by UserName. The task used to declare "Name", which no IAM
     * user item has — only a hardcoded list of well-known field names made it work, and
     * that list is gone.
     */
    public function test_iam_users_are_identified_by_their_declared_key(): void
    {
        $scan = Scan::factory()->create();
        $scanner = new RecordingScanner([
            'listUsers' => ['Users' => [['UserName' => 'alice', 'Arn' => 'arn:aws:iam::1:user/alice']]],
        ]);

        $this->runTask($scan, 'iam', 'listUsers', $scanner, null);

        $this->assertSame(
            ['alice'],
            ScanDetail::where('scan_id', $scan->id)
                ->where('api_method', 'listUsers')
                ->whereNotNull('resource_id')
                ->pluck('resource_id')
                ->all()
        );

        $call = collect($scanner->calls)->firstWhere('method', 'listMFADevices');
        $this->assertSame(['UserName' => 'alice'], $call['params']);
    }

    /**
     * A failing action is recorded as an error marker against its resource so rules
     * testing for absent configuration still have something to evaluate, and the
     * remaining actions still run.
     */
    public function test_a_failing_action_is_recorded_without_stopping_the_others(): void
    {
        $scan = Scan::factory()->create();

        $scanner = new class (['listTables' => ['TableNames' => ['orders']]]) extends RecordingScanner {
            public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
            {
                if ($method === 'describeTable') {
                    throw new \RuntimeException('AccessDenied');
                }

                return parent::executeApiCall($method, $credentials, $params, $region);
            }
        };

        $this->runTask($scan, 'dynamodb', 'listTables', $scanner);

        $failed = ScanDetail::where('scan_id', $scan->id)
            ->where('api_method', 'describeTable')
            ->first();

        $this->assertNotNull($failed);
        $this->assertTrue($failed->raw_data['__error__']);

        // The second action still ran.
        $this->assertNotNull(
            ScanDetail::where('scan_id', $scan->id)->where('api_method', 'describeContinuousBackups')->first()
        );
    }
}
