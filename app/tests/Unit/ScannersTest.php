<?php

namespace Tests\Unit;

use App\Services\Scanners\Ec2Scanner;
use App\Services\Scanners\IamScanner;
use App\Services\Scanners\RdsScanner;
use App\Services\Scanners\S3Scanner;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The per-service scanners are thin dispatch tables over the AWS SDK. These tests
 * cover the dispatch behaviour that does not require talking to AWS: which method
 * names are accepted, and that unknown ones fail loudly instead of silently.
 */
class ScannersTest extends TestCase
{
    private const ROLE_ARN = 'arn:aws:iam::123456789012:role/TestRole';

    private const CREDENTIALS = [
        'AccessKeyId' => 'AKIAEXAMPLE',
        'SecretAccessKey' => 'secret',
        'SessionToken' => 'token',
    ];

    /**
     * Every scanner rejects a method it does not know about.
     */
    #[DataProvider('unknownMethodProvider')]
    public function test_an_unknown_method_is_rejected(string $scannerClass, string $expectedMessage): void
    {
        $scanner = new $scannerClass(self::ROLE_ARN, 'external-id');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $scanner->executeApiCall('doSomethingUnsupported', self::CREDENTIALS);
    }

    public static function unknownMethodProvider(): array
    {
        return [
            'iam' => [IamScanner::class, 'Unknown IAM method: doSomethingUnsupported'],
            's3' => [S3Scanner::class, 'Unknown S3 method: doSomethingUnsupported'],
            'ec2' => [Ec2Scanner::class, 'Unknown EC2 method: doSomethingUnsupported'],
            'rds' => [RdsScanner::class, 'Unknown RDS method: doSomethingUnsupported'],
        ];
    }

    /**
     * Each scanner is an AwsSecurityScanner and therefore carries the shared
     * assumeRole/region behaviour.
     */
    #[DataProvider('scannerClassProvider')]
    public function test_scanners_extend_the_base_scanner(string $scannerClass): void
    {
        $scanner = new $scannerClass(self::ROLE_ARN, 'external-id', 'eu-west-1');

        $this->assertInstanceOf(\App\Services\AwsSecurityScanner::class, $scanner);
    }

    public static function scannerClassProvider(): array
    {
        return [
            'iam' => [IamScanner::class],
            's3' => [S3Scanner::class],
            'ec2' => [Ec2Scanner::class],
            'rds' => [RdsScanner::class],
        ];
    }

    /**
     * The region-aware scanners accept a per-call region; IAM (a global service)
     * deliberately does not take one.
     */
    public function test_only_regional_scanners_accept_a_region_argument(): void
    {
        foreach ([S3Scanner::class, Ec2Scanner::class, RdsScanner::class] as $regional) {
            $method = new \ReflectionMethod($regional, 'executeApiCall');
            $this->assertEquals(
                4,
                $method->getNumberOfParameters(),
                "{$regional}::executeApiCall should accept a region"
            );
        }

        $iam = new \ReflectionMethod(IamScanner::class, 'executeApiCall');
        $this->assertEquals(3, $iam->getNumberOfParameters());
    }
}
