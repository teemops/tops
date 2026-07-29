<?php

namespace Tests\Unit;

use App\Services\Scanners\GenericAwsScanner;
use App\Services\Scanners\IamScanner;
use App\Services\Scanners\S3Scanner;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Data collection now runs through one generic scanner that validates method names
 * against the AWS SDK's own API model instead of a hand-maintained dispatch table.
 * These tests cover the behaviour that does not require talking to AWS: which service
 * and method names are accepted, and that unknown ones fail loudly rather than silently.
 */
class ScannersTest extends TestCase
{
    private const ROLE_ARN = 'arn:aws:iam::123456789012:role/TestRole';

    private const CREDENTIALS = [
        'AccessKeyId' => 'AKIAEXAMPLE',
        'SecretAccessKey' => 'secret',
        'SessionToken' => 'token',
    ];

    private function scannerFor(string $clientKey, string $label): GenericAwsScanner
    {
        return new GenericAwsScanner(self::ROLE_ARN, 'external-id', 'us-east-1', $clientKey, $label);
    }

    /**
     * A method the service does not have is rejected before any network call, with the
     * service named so a bad tasks.json is obvious from the message alone.
     */
    #[DataProvider('unknownMethodProvider')]
    public function test_an_unknown_method_is_rejected(string $clientKey, string $label): void
    {
        $scanner = $this->scannerFor($clientKey, $label);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown {$label} method: doSomethingUnsupported");

        $scanner->executeApiCall('doSomethingUnsupported', self::CREDENTIALS);
    }

    public static function unknownMethodProvider(): array
    {
        return [
            'iam' => ['iam', 'IAM'],
            's3' => ['s3', 'S3'],
            'ec2' => ['ec2', 'EC2'],
            'rds' => ['rds', 'RDS'],
            'kms' => ['kms', 'KMS'],
        ];
    }

    /**
     * The bespoke subclasses inherit the same rejection, via their own labels.
     */
    public function test_subclasses_reject_unknown_methods_with_their_own_label(): void
    {
        foreach ([[S3Scanner::class, 'S3'], [IamScanner::class, 'IAM']] as [$class, $label]) {
            $scanner = new $class(self::ROLE_ARN, 'external-id');

            try {
                $scanner->executeApiCall('doSomethingUnsupported', self::CREDENTIALS);
                $this->fail("{$class} should reject an unknown method");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame("Unknown {$label} method: doSomethingUnsupported", $e->getMessage());
            }
        }
    }

    /**
     * A service the installed SDK does not provide fails when the client is built,
     * rather than producing an empty scan.
     */
    public function test_an_unknown_service_is_rejected(): void
    {
        $scanner = $this->scannerFor('notarealawsservice', 'Nope');

        $this->expectException(\InvalidArgumentException::class);

        $scanner->executeApiCall('listThings', self::CREDENTIALS);
    }

    /**
     * The generic scanner is useless without a client key, so refuse to build one
     * instead of failing later inside a queue job.
     */
    public function test_a_scanner_without_a_client_key_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GenericAwsScanner(self::ROLE_ARN, 'external-id');
    }

    /**
     * supportsOperation answers from the bundled API model, with no credentials and no
     * network — this is what lets scan:validate-rules check a tasks.json offline.
     */
    #[DataProvider('knownMethodProvider')]
    public function test_declared_methods_exist_on_the_service_api(string $clientKey, string $method): void
    {
        $this->assertTrue(
            GenericAwsScanner::supportsOperation($clientKey, $method),
            "{$clientKey}::{$method} should be a known operation"
        );
    }

    public static function knownMethodProvider(): array
    {
        return [
            'iam listUsers' => ['iam', 'listUsers'],
            'iam getAccountSummary' => ['iam', 'getAccountSummary'],
            'iam getAccountPasswordPolicy' => ['iam', 'getAccountPasswordPolicy'],
            's3 listBuckets' => ['s3', 'listBuckets'],
            's3 getBucketEncryption' => ['s3', 'getBucketEncryption'],
            'ec2 describeInstances' => ['ec2', 'describeInstances'],
            'ec2 describeFlowLogs' => ['ec2', 'describeFlowLogs'],
            'rds describeDBInstances' => ['rds', 'describeDBInstances'],
            'cloudtrail describeTrails' => ['cloudtrail', 'describeTrails'],
            'cloudtrail getTrailStatus' => ['cloudtrail', 'getTrailStatus'],
            'lambda listFunctions' => ['lambda', 'listFunctions'],
            'lambda getFunctionUrlConfig' => ['lambda', 'getFunctionUrlConfig'],
            'kms listKeys' => ['kms', 'listKeys'],
            'kms getKeyRotationStatus' => ['kms', 'getKeyRotationStatus'],
        ];
    }

    public function test_supports_operation_rejects_unknown_methods_and_services(): void
    {
        $this->assertFalse(GenericAwsScanner::supportsOperation('kms', 'doSomethingUnsupported'));
        $this->assertFalse(GenericAwsScanner::supportsOperation('notarealawsservice', 'listThings'));
    }

    /**
     * Every scanner carries the shared assumeRole/region behaviour.
     */
    public function test_scanners_extend_the_base_scanner(): void
    {
        foreach ([S3Scanner::class, IamScanner::class] as $class) {
            $scanner = new $class(self::ROLE_ARN, 'external-id', 'eu-west-1');

            $this->assertInstanceOf(\App\Services\AwsSecurityScanner::class, $scanner);
            $this->assertInstanceOf(GenericAwsScanner::class, $scanner);
        }
    }

    /**
     * Region is now a uniform per-call argument rather than something only some scanners
     * accept — whether a service is scanned per region is declared in its tasks.json and
     * enforced by ScanTypesService, not by the shape of this signature.
     */
    public function test_every_scanner_accepts_a_per_call_region(): void
    {
        foreach ([GenericAwsScanner::class, S3Scanner::class, IamScanner::class] as $class) {
            $method = new \ReflectionMethod($class, 'executeApiCall');
            $this->assertEquals(4, $method->getNumberOfParameters(), "{$class}::executeApiCall should accept a region");
        }
    }
}
