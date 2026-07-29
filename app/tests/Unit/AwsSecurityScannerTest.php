<?php

namespace Tests\Unit;

use App\Services\AwsSecurityScanner;
use Aws\Ec2\Ec2Client;
use Aws\Iam\IamClient;
use Aws\Rds\RdsClient;
use Aws\Result;
use Aws\S3\S3Client;
use Tests\TestCase;

/**
 * Exposes the protected client/call helpers so they can be exercised directly.
 */
class ExposedAwsSecurityScanner extends AwsSecurityScanner
{
    public function exposedCreateClient(string $service, array $credentials, ?string $region = null): mixed
    {
        return $this->createClient($service, $credentials, $region);
    }

    public function exposedCallApi(string $serviceLabel, string $method, array $params, callable $call, array $context = []): array
    {
        return $this->callApi($serviceLabel, $method, $params, $call, $context);
    }
}

/**
 * Returns a canned describeRegions() response instead of calling AWS.
 */
class StubRegionsScanner extends AwsSecurityScanner
{
    public array $regionNames = ['us-east-1', 'eu-west-2'];

    protected function createClient(string $service, array $credentials, ?string $region = null): mixed
    {
        return new class ($this->regionNames) {
            public function __construct(private array $regionNames)
            {
            }

            public function describeRegions(array $params = []): Result
            {
                return new Result([
                    'Regions' => array_map(fn ($name) => ['RegionName' => $name], $this->regionNames),
                ]);
            }
        };
    }
}

/**
 * Fails on client creation, to exercise the fallback region list.
 */
class FailingScanner extends AwsSecurityScanner
{
    protected function createClient(string $service, array $credentials, ?string $region = null): mixed
    {
        throw new \RuntimeException('STS unavailable');
    }
}

class AwsSecurityScannerTest extends TestCase
{
    private const ROLE_ARN = 'arn:aws:iam::123456789012:role/TestRole';

    private const CREDENTIALS = [
        'AccessKeyId' => 'AKIAEXAMPLE',
        'SecretAccessKey' => 'secret',
        'SessionToken' => 'token',
    ];

    /**
     * Each supported service maps to its matching AWS SDK client.
     */
    public function test_create_client_returns_the_client_for_each_service(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id');

        $this->assertInstanceOf(S3Client::class, $scanner->exposedCreateClient('s3', self::CREDENTIALS));
        $this->assertInstanceOf(IamClient::class, $scanner->exposedCreateClient('iam', self::CREDENTIALS));
        $this->assertInstanceOf(Ec2Client::class, $scanner->exposedCreateClient('ec2', self::CREDENTIALS));
        $this->assertInstanceOf(RdsClient::class, $scanner->exposedCreateClient('rds', self::CREDENTIALS));
    }

    /**
     * A service name the SDK does not provide is rejected rather than silently
     * returning null.
     */
    public function test_create_client_rejects_a_service_the_sdk_does_not_provide(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not provided by the AWS SDK for PHP');

        $scanner->exposedCreateClient('notarealawsservice', self::CREDENTIALS);
    }

    /**
     * Any service the installed SDK models can be built, without this class carrying a
     * list of them. Before the registry, createClient matched on a hardcoded seven, so
     * adding a service meant editing PHP and importing another client class.
     */
    public function test_create_client_builds_services_beyond_the_original_seven(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id');

        $this->assertInstanceOf(
            \Aws\DynamoDb\DynamoDbClient::class,
            $scanner->exposedCreateClient('dynamodb', self::CREDENTIALS)
        );
        // Endpoint prefixes are not always guessable from the service name, which is why
        // tasks.json declares the client key explicitly.
        $this->assertInstanceOf(
            \Aws\ElasticLoadBalancingV2\ElasticLoadBalancingV2Client::class,
            $scanner->exposedCreateClient('elasticloadbalancingv2', self::CREDENTIALS)
        );
    }

    /**
     * The constructor region is used when no per-call region is given.
     */
    public function test_create_client_uses_the_constructor_region_by_default(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id', 'ap-southeast-2');

        $client = $scanner->exposedCreateClient('ec2', self::CREDENTIALS);

        $this->assertEquals('ap-southeast-2', $client->getRegion());
    }

    /**
     * A per-call region overrides the constructor region.
     */
    public function test_create_client_prefers_the_per_call_region(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id', 'ap-southeast-2');

        $client = $scanner->exposedCreateClient('ec2', self::CREDENTIALS, 'eu-central-1');

        $this->assertEquals('eu-central-1', $client->getRegion());
    }

    /**
     * The default region is us-east-1 when the constructor omits it.
     */
    public function test_scanner_defaults_to_us_east_1(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id');

        $client = $scanner->exposedCreateClient('ec2', self::CREDENTIALS);

        $this->assertEquals('us-east-1', $client->getRegion());
    }

    /**
     * callApi passes through the wrapped call's result untouched.
     */
    public function test_call_api_returns_the_wrapped_result(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id');

        $result = $scanner->exposedCallApi('EC2', 'describeInstances', [], fn () => ['Reservations' => []]);

        $this->assertEquals(['Reservations' => []], $result);
    }

    /**
     * callApi logs and rethrows so callers still see AWS failures.
     */
    public function test_call_api_rethrows_the_underlying_exception(): void
    {
        $scanner = new ExposedAwsSecurityScanner(self::ROLE_ARN, 'external-id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AccessDenied');

        $scanner->exposedCallApi('IAM', 'listUsers', [], function () {
            throw new \RuntimeException('AccessDenied');
        });
    }

    /**
     * getAvailableRegions flattens the API response to region names.
     */
    public function test_get_available_regions_returns_region_names(): void
    {
        $scanner = new StubRegionsScanner(self::ROLE_ARN, 'external-id');

        $this->assertEquals(['us-east-1', 'eu-west-2'], $scanner->getAvailableRegions(self::CREDENTIALS));
    }

    /**
     * A failed describeRegions call falls back to the hardcoded region list.
     */
    public function test_get_available_regions_falls_back_when_the_call_fails(): void
    {
        $scanner = new FailingScanner(self::ROLE_ARN, 'external-id');

        $regions = $scanner->getAvailableRegions(self::CREDENTIALS);

        $this->assertContains('us-east-1', $regions);
        $this->assertContains('ap-southeast-2', $regions);
        $this->assertCount(8, $regions);
    }

    /**
     * assumeRole logs and rethrows when STS rejects the request.
     */
    public function test_assume_role_rethrows_sts_failures(): void
    {
        $scanner = new AwsSecurityScanner('not-a-valid-arn', 'external-id');

        $this->expectException(\Exception::class);

        $scanner->assumeRole();
    }
}
