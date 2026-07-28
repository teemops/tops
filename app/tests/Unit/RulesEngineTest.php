<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Services\RulesEngine\RulesEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RulesEngineTest extends TestCase
{
    use RefreshDatabase;

    private RulesEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RulesEngine();
    }

    /**
     * Call one of the engine's private helpers.
     */
    private function invoke(string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod(RulesEngine::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->engine, $args);
    }

    public function test_load_tasks_throws_when_the_file_is_missing(): void
    {
        File::shouldReceive('exists')->once()->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tasks file not found');

        $this->engine->loadTasks('nope');
    }

    public function test_load_tasks_throws_on_invalid_json(): void
    {
        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn('{broken');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid JSON in tasks file');

        $this->engine->loadTasks('iam');
    }

    /**
     * Every tasks.json shipped with the app parses and declares a start task.
     */
    #[DataProvider('serviceProvider')]
    public function test_the_shipped_tasks_files_load(string $service): void
    {
        $tasks = $this->engine->loadTasks($service);

        $this->assertArrayHasKey('config', $tasks);
        $this->assertArrayHasKey('tasks', $tasks);
        $this->assertNotEmpty($tasks['config']['start'] ?? null);
    }

    public static function serviceProvider(): array
    {
        return [
            'iam' => ['iam'],
            's3' => ['s3'],
            'ec2' => ['ec2'],
            'rds' => ['rds'],
        ];
    }

    public function test_get_items_key_reads_the_first_items_key(): void
    {
        $this->assertEquals('Users', $this->invoke('getItemsKey', [['items' => ['Users' => []]]]));
        $this->assertNull($this->invoke('getItemsKey', [[]]));
        $this->assertNull($this->invoke('getItemsKey', [['items' => []]]));
    }

    public function test_get_resource_id_prefers_the_configured_key_field(): void
    {
        $item = ['Name' => 'my-bucket', 'UserName' => 'ignored'];

        $this->assertEquals('my-bucket', $this->invoke('getResourceId', [$item, 'Name', 'bucket']));
    }

    public function test_get_resource_id_falls_back_to_common_id_fields(): void
    {
        $this->assertEquals('alice', $this->invoke('getResourceId', [['UserName' => 'alice'], null, null]));
        $this->assertEquals('Admin', $this->invoke('getResourceId', [['RoleName' => 'Admin'], null, null]));
        $this->assertEquals('i-123', $this->invoke('getResourceId', [['InstanceId' => 'i-123'], null, null]));
        $this->assertEquals('db-1', $this->invoke('getResourceId', [['DBInstanceIdentifier' => 'db-1'], null, null]));
    }

    public function test_get_resource_id_returns_null_when_nothing_matches(): void
    {
        $this->assertNull($this->invoke('getResourceId', [['Unrecognised' => 'x'], null, null]));
    }

    public function test_evaluate_params_resolves_php_expressions(): void
    {
        $params = $this->invoke('evaluateParams', [
            ['UserName' => "\$item['UserName']"],
            ['UserName' => 'alice'],
        ]);

        $this->assertSame(['UserName' => 'alice'], $params);
    }

    public function test_evaluate_params_resolves_plain_field_names(): void
    {
        $params = $this->invoke('evaluateParams', [
            ['Bucket' => 'Name'],
            ['Name' => 'my-bucket'],
        ]);

        $this->assertSame(['Bucket' => 'my-bucket'], $params);
    }

    public function test_evaluate_params_passes_through_literal_values(): void
    {
        $params = $this->invoke('evaluateParams', [
            ['MaxItems' => 100, 'OnlyAttached' => true],
            [],
        ]);

        $this->assertSame(['MaxItems' => 100, 'OnlyAttached' => true], $params);
    }

    public function test_evaluate_params_drops_params_that_resolve_to_nothing(): void
    {
        $params = $this->invoke('evaluateParams', [
            ['UserName' => 'MissingField'],
            ['Name' => 'alice'],
        ]);

        $this->assertSame([], $params);
    }

    public function test_get_field_value_understands_dot_and_bracket_notation(): void
    {
        $item = ['RoleName' => 'Admin'];

        $this->assertEquals('Admin', $this->invoke('getFieldValue', ['RoleName', $item]));
        $this->assertEquals('Admin', $this->invoke('getFieldValue', ['item.RoleName', $item]));
        $this->assertEquals('Admin', $this->invoke('getFieldValue', ["\$item['RoleName']", $item]));
        $this->assertNull($this->invoke('getFieldValue', ['Nope', $item]));
    }

    public function test_build_action_params_prefers_the_action_config(): void
    {
        $params = $this->invoke('buildActionParams', [
            ['UserName' => 'alice'],
            ['defaults' => ['actions' => ['params' => ['UserName' => "\$item['Other']"]]]],
            'getUser',
            ['UserName' => "\$item['UserName']"],
        ]);

        $this->assertSame(['UserName' => 'alice'], $params);
    }

    /**
     * With no config at all, params are inferred from well-known item fields.
     */
    #[DataProvider('inferredParamsProvider')]
    public function test_build_action_params_infers_params_from_the_item(array $item, string $action, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('buildActionParams', [$item, [], $action, null]));
    }

    public static function inferredParamsProvider(): array
    {
        return [
            'user name' => [['UserName' => 'alice'], 'listMFADevices', ['UserName' => 'alice']],
            'role name' => [['RoleName' => 'Admin'], 'listRolePolicies', ['RoleName' => 'Admin']],
            'bucket name' => [['BucketName' => 'b1'], 'getBucketAcl', ['Bucket' => 'b1']],
            'name for a user action' => [['Name' => 'alice'], 'getUser', ['UserName' => 'alice']],
            'name for a role action' => [['Name' => 'Admin'], 'getRole', ['RoleName' => 'Admin']],
            'name for a bucket action' => [['Name' => 'b1'], 'getBucketAcl', ['Bucket' => 'b1']],
            'nothing inferable' => [['Arn' => 'arn:aws:s3:::b1'], 'getPublicAccessBlock', []],
        ];
    }

    public function test_extract_items_flattens_ec2_reservations_into_instances(): void
    {
        $reservations = [
            ['Instances' => [['InstanceId' => 'i-1'], ['InstanceId' => 'i-2']]],
            ['Instances' => [['InstanceId' => 'i-3']]],
        ];

        $items = $this->invoke('extractItems', [$reservations, ['items' => ['Reservations' => []]], 'ec2']);

        $this->assertCount(3, $items);
        $this->assertEquals(['i-1', 'i-2', 'i-3'], array_column($items, 'InstanceId'));
    }

    public function test_extract_items_skips_reservations_without_instances(): void
    {
        $items = $this->invoke('extractItems', [
            [['ReservationId' => 'r-1']],
            ['items' => ['Reservations' => []]],
            'ec2',
        ]);

        $this->assertSame([], $items);
    }

    public function test_extract_items_leaves_other_services_untouched(): void
    {
        $buckets = [['Name' => 'b1'], ['Name' => 'b2']];

        $this->assertSame($buckets, $this->invoke('extractItems', [$buckets, ['items' => ['Buckets' => []]], 's3']));
    }

    public function test_store_scan_detail_persists_the_raw_response(): void
    {
        $scan = Scan::factory()->create();

        $this->invoke('storeScanDetail', [
            $scan,
            's3',
            'bucket',
            'my-bucket',
            'getBucketAcl',
            ['Owner' => ['ID' => 'abc']],
            'my-bucket',
            'us-east-1',
        ]);

        $detail = ScanDetail::where('scan_id', $scan->id)->firstOrFail();

        $this->assertEquals('s3', $detail->service);
        $this->assertEquals('bucket', $detail->resource_type);
        $this->assertEquals('my-bucket', $detail->resource_id);
        $this->assertEquals('getBucketAcl', $detail->api_method);
        $this->assertEquals('my-bucket', $detail->parent_resource_id);
        $this->assertEquals('us-east-1', $detail->region);
        $this->assertEquals(['Owner' => ['ID' => 'abc']], $detail->raw_data);
    }

    public function test_get_scanner_rejects_an_unknown_service(): void
    {
        $scan = Scan::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown service: dynamodb');

        $this->invoke('getScanner', ['dynamodb', $scan]);
    }

    /**
     */
    #[DataProvider('scannerForServiceProvider')]
    public function test_get_scanner_returns_the_scanner_for_each_service(string $service, string $expectedClass): void
    {
        $scan = Scan::factory()->create();

        $this->assertInstanceOf($expectedClass, $this->invoke('getScanner', [$service, $scan]));
    }

    public static function scannerForServiceProvider(): array
    {
        return [
            'iam' => ['iam', \App\Services\Scanners\IamScanner::class],
            's3' => ['s3', \App\Services\Scanners\S3Scanner::class],
            'ec2' => ['ec2', \App\Services\Scanners\Ec2Scanner::class],
            'rds' => ['rds', \App\Services\Scanners\RdsScanner::class],
        ];
    }
}
