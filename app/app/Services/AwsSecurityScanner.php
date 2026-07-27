<?php

namespace App\Services;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\Ec2\Ec2Client;
use Aws\Rds\RdsClient;
use Aws\Sts\StsClient;
use Illuminate\Support\Facades\Log;

class AwsSecurityScanner
{
    protected string $roleArn;
    protected string $externalId;
    protected string $region;

    public function __construct(string $roleArn, string $externalId, string $region = 'us-east-1')
    {
        $this->roleArn = $roleArn;
        $this->externalId = $externalId;
        $this->region = $region;
    }

    /**
     * Assume the IAM role in the customer's AWS account
     */
    public function assumeRole(): array
    {
        $stsClient = new StsClient([
            'version' => 'latest',
            'region' => $this->region,
        ]);

        try {
            $result = $stsClient->assumeRole([
                'RoleArn' => $this->roleArn,
                'RoleSessionName' => 'teemops-scan-' . time(),
                'ExternalId' => $this->externalId,
            ]);

            return [
                'AccessKeyId' => $result['Credentials']['AccessKeyId'],
                'SecretAccessKey' => $result['Credentials']['SecretAccessKey'],
                'SessionToken' => $result['Credentials']['SessionToken'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to assume AWS role', [
                'role_arn' => $this->roleArn,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create AWS client with assumed role credentials
     */
    protected function createClient(string $service, array $credentials, ?string $region = null): mixed
    {
        $config = [
            'version' => 'latest',
            'region' => $region ?? $this->region,
            'credentials' => new Credentials(
                $credentials['AccessKeyId'],
                $credentials['SecretAccessKey'],
                $credentials['SessionToken']
            ),
        ];

        return match ($service) {
            's3' => new S3Client($config),
            'iam' => new IamClient($config),
            'ec2' => new Ec2Client($config),
            'rds' => new RdsClient($config),
            default => throw new \InvalidArgumentException("Unknown service: {$service}"),
        };
    }

    /**
     * Get available AWS regions
     */
    public function getAvailableRegions(array $credentials): array
    {
        try {
            $ec2Client = $this->createClient('ec2', $credentials);
            $result = $ec2Client->describeRegions();
            
            $regions = [];
            foreach ($result['Regions'] as $region) {
                $regions[] = $region['RegionName'];
            }
            
            return $regions;
        } catch (\Exception $e) {
            Log::error('Failed to get AWS regions', [
                'error' => $e->getMessage(),
            ]);
            // Return default regions if API call fails
            return [
                'us-east-1',
                'us-east-2',
                'us-west-1',
                'us-west-2',
                'eu-west-1',
                'eu-central-1',
                'ap-southeast-1',
                'ap-southeast-2',
            ];
        }
    }

    /**
     * Run an AWS SDK call and apply the log-and-rethrow error handling shared by
     * every Scanner's executeApiCall(). $call should perform the actual SDK
     * invocation (including an "unknown method" throw for unmatched methods).
     * $context is merged into the error log (e.g. ['region' => $region]).
     */
    protected function callApi(string $serviceLabel, string $method, array $params, callable $call, array $context = []): array
    {
        try {
            return $call();
        } catch (\Exception $e) {
            Log::error("{$serviceLabel} API call failed: {$method}", $context + [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }
}

