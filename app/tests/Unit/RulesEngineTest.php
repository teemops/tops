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

    public function test_get_items_path_reads_the_first_items_key(): void
    {
        $this->assertEquals('Users', $this->invoke('getItemsPath', [['items' => ['Users' => []]]]));
        $this->assertNull($this->invoke('getItemsPath', [[]]));
        $this->assertNull($this->invoke('getItemsPath', [['items' => []]]));
    }

    public function test_get_items_path_prefers_an_explicit_declaration(): void
    {
        $this->assertEquals(
            'Reservations.Instances',
            $this->invoke('getItemsPath', [[
                'itemsPath' => 'Reservations.Instances',
                'items' => ['Reservations' => []],
            ]])
        );
    }

    public function test_get_resource_id_uses_the_configured_key_field(): void
    {
        $item = ['Name' => 'my-bucket', 'UserName' => 'ignored'];

        $this->assertEquals('my-bucket', $this->invoke('getResourceId', [$item, 'Name']));
    }

    /**
     * A scalar item is its own identifier — SQS listQueues returns queue URLs and
     * DynamoDB listTables returns table names, with no object to read a field from.
     */
    public function test_get_resource_id_handles_scalar_items(): void
    {
        $this->assertEquals(
            'https://sqs.us-east-1.amazonaws.com/123456789012/my-queue',
            $this->invoke('getResourceId', ['https://sqs.us-east-1.amazonaws.com/123456789012/my-queue', null])
        );
        $this->assertEquals('my-table', $this->invoke('getResourceId', ['my-table', 'TableName']));
    }

    /**
     * There is no guessing from well-known field names any more: a task whose declared
     * key does not match its items is a tasks.json bug and must surface as one.
     */
    public function test_get_resource_id_returns_null_when_the_declared_key_is_absent(): void
    {
        $this->assertNull($this->invoke('getResourceId', [['Unrecognised' => 'x'], null]));
        $this->assertNull($this->invoke('getResourceId', [['UserName' => 'alice'], 'Name']));
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
     * With nothing declared, the call is made with no params rather than guessing at
     * them from the item's field names.
     */
    public function test_build_action_params_declares_nothing_when_nothing_is_configured(): void
    {
        $this->assertSame([], $this->invoke('buildActionParams', [['UserName' => 'alice'], [], 'listMFADevices', null]));
        $this->assertSame([], $this->invoke('buildActionParams', [['Name' => 'b1'], [], 'getBucketAcl', null]));
    }

    /**
     * A scalar item is addressable as "$item" in a params expression, which is how the
     * two-step list-then-describe services (SQS, DynamoDB) pass their identifier along.
     */
    public function test_build_action_params_resolves_a_scalar_item(): void
    {
        $params = $this->invoke('buildActionParams', [
            'https://sqs.us-east-1.amazonaws.com/123456789012/q',
            [],
            'getQueueAttributes',
            ['QueueUrl' => '$item', 'AttributeNames' => ['All']],
        ]);

        $this->assertSame([
            'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/q',
            'AttributeNames' => ['All'],
        ], $params);
    }

    public function test_extract_items_walks_a_nested_path(): void
    {
        $result = [
            'Reservations' => [
                ['Instances' => [['InstanceId' => 'i-1'], ['InstanceId' => 'i-2']]],
                ['Instances' => [['InstanceId' => 'i-3']]],
            ],
        ];

        $items = $this->invoke('extractItems', [$result, ['itemsPath' => 'Reservations.Instances']]);

        $this->assertCount(3, $items);
        $this->assertEquals(['i-1', 'i-2', 'i-3'], array_column($items, 'InstanceId'));
    }

    public function test_extract_items_skips_containers_missing_the_nested_key(): void
    {
        $items = $this->invoke('extractItems', [
            ['Reservations' => [['ReservationId' => 'r-1']]],
            ['itemsPath' => 'Reservations.Instances'],
        ]);

        $this->assertSame([], $items);
    }

    public function test_extract_items_reads_a_single_level_list(): void
    {
        $buckets = [['Name' => 'b1'], ['Name' => 'b2']];

        $this->assertSame($buckets, $this->invoke('extractItems', [['Buckets' => $buckets], ['items' => ['Buckets' => []]]]));
    }

    /**
     * Responses that list bare strings rather than objects must survive extraction —
     * these were a TypeError before, which is what kept services like DynamoDB and SQS
     * out of reach of a JSON-only definition.
     */
    public function test_extract_items_handles_lists_of_scalars(): void
    {
        $items = $this->invoke('extractItems', [
            ['TableNames' => ['orders', 'customers']],
            ['items' => ['TableNames' => []]],
        ]);

        $this->assertSame(['orders', 'customers'], $items);
    }

    public function test_extract_items_returns_nothing_when_no_path_is_declared(): void
    {
        $this->assertSame([], $this->invoke('extractItems', [['SummaryMap' => ['x' => 1]], []]));
    }

    public function test_item_raw_data_wraps_scalars_for_json_storage(): void
    {
        $this->assertSame(['Value' => 'my-table'], $this->invoke('itemRawData', ['my-table']));
        $this->assertSame(['Name' => 'b1'], $this->invoke('itemRawData', [['Name' => 'b1']]));
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

    public function test_get_scanner_rejects_a_service_with_no_registry_definition(): void
    {
        $scan = Scan::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown service: notarealservice');

        $this->invoke('getScanner', ['notarealservice', $scan]);
    }

    /**
     * Services get the generic scanner unless their tasks.json names a bespoke one.
     * s3 and iam are the only two that do, and they must keep getting theirs — the
     * generic scanner cannot resolve bucket regions or read an absent password policy.
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
            'ec2' => ['ec2', \App\Services\Scanners\GenericAwsScanner::class],
            'rds' => ['rds', \App\Services\Scanners\GenericAwsScanner::class],
            'kms' => ['kms', \App\Services\Scanners\GenericAwsScanner::class],
            'cloudtrail' => ['cloudtrail', \App\Services\Scanners\GenericAwsScanner::class],
        ];
    }
}
