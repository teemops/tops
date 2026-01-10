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
    protected function assumeRole(): array
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
    protected function createClient(string $service, array $credentials): mixed
    {
        $config = [
            'version' => 'latest',
            'region' => $this->region,
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
     * Scan S3 buckets for security issues
     */
    public function scanS3(array $credentials): array
    {
        $findings = [];
        $s3Client = $this->createClient('s3', $credentials);

        try {
            $buckets = $s3Client->listBuckets();

            foreach ($buckets['Buckets'] as $bucket) {
                $bucketName = $bucket['Name'];

                // Check bucket public access
                try {
                    $publicAccess = $s3Client->getPublicAccessBlock([
                        'Bucket' => $bucketName,
                    ]);

                    // If PublicAccessBlock is not configured, bucket might be public
                    if (!isset($publicAccess['PublicAccessBlockConfiguration'])) {
                        $findings[] = [
                            'severity' => 'high',
                            'service' => 's3',
                            'resource_type' => 'bucket',
                            'resource_id' => $bucketName,
                            'finding_type' => 'public_access',
                            'title' => 'S3 Bucket Missing Public Access Block',
                            'description' => "Bucket '{$bucketName}' does not have Public Access Block configured, which may allow public access.",
                            'remediation' => 'Enable Public Access Block on the bucket to prevent accidental public access.',
                        ];
                    }
                } catch (\Exception $e) {
                    // PublicAccessBlock might not be configured
                    $findings[] = [
                        'severity' => 'high',
                        'service' => 's3',
                        'resource_type' => 'bucket',
                        'resource_id' => $bucketName,
                        'finding_type' => 'public_access',
                        'title' => 'S3 Bucket Public Access Block Not Configured',
                        'description' => "Bucket '{$bucketName}' does not have Public Access Block configured.",
                        'remediation' => 'Enable Public Access Block on the bucket.',
                    ];
                }

                // Check bucket encryption
                try {
                    $encryption = $s3Client->getBucketEncryption([
                        'Bucket' => $bucketName,
                    ]);

                    if (!isset($encryption['ServerSideEncryptionConfiguration'])) {
                        $findings[] = [
                            'severity' => 'medium',
                            'service' => 's3',
                            'resource_type' => 'bucket',
                            'resource_id' => $bucketName,
                            'finding_type' => 'encryption',
                            'title' => 'S3 Bucket Not Encrypted',
                            'description' => "Bucket '{$bucketName}' does not have server-side encryption enabled.",
                            'remediation' => 'Enable server-side encryption (SSE) on the bucket.',
                        ];
                    }
                } catch (\Exception $e) {
                    // Encryption not configured
                    $findings[] = [
                        'severity' => 'medium',
                        'service' => 's3',
                        'resource_type' => 'bucket',
                        'resource_id' => $bucketName,
                        'finding_type' => 'encryption',
                        'title' => 'S3 Bucket Encryption Not Configured',
                        'description' => "Bucket '{$bucketName}' does not have encryption configured.",
                        'remediation' => 'Enable server-side encryption (SSE) on the bucket.',
                    ];
                }

                // Check bucket versioning
                try {
                    $versioning = $s3Client->getBucketVersioning([
                        'Bucket' => $bucketName,
                    ]);

                    if (!isset($versioning['Status']) || $versioning['Status'] !== 'Enabled') {
                        $findings[] = [
                            'severity' => 'low',
                            'service' => 's3',
                            'resource_type' => 'bucket',
                            'resource_id' => $bucketName,
                            'finding_type' => 'versioning',
                            'title' => 'S3 Bucket Versioning Not Enabled',
                            'description' => "Bucket '{$bucketName}' does not have versioning enabled.",
                            'remediation' => 'Enable versioning on the bucket to protect against accidental deletion.',
                        ];
                    }
                } catch (\Exception $e) {
                    // Versioning check failed
                }
            }
        } catch (\Exception $e) {
            Log::error('S3 scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $findings;
    }

    /**
     * Scan IAM for security issues
     */
    public function scanIam(array $credentials): array
    {
        $findings = [];
        $iamClient = $this->createClient('iam', $credentials);

        try {
            // Check for users without MFA
            $users = $iamClient->listUsers();
            foreach ($users['Users'] as $user) {
                $mfaDevices = $iamClient->listMFADevices([
                    'UserName' => $user['UserName'],
                ]);

                if (empty($mfaDevices['MFADevices'])) {
                    $findings[] = [
                        'severity' => 'high',
                        'service' => 'iam',
                        'resource_type' => 'user',
                        'resource_id' => $user['UserName'],
                        'finding_type' => 'mfa',
                        'title' => 'IAM User Without MFA',
                        'description' => "IAM user '{$user['UserName']}' does not have MFA enabled.",
                        'remediation' => 'Enable MFA for the IAM user to enhance security.',
                    ];
                }
            }

            // Check for access keys older than 90 days
            foreach ($users['Users'] as $user) {
                try {
                    $accessKeys = $iamClient->listAccessKeys([
                        'UserName' => $user['UserName'],
                    ]);

                    foreach ($accessKeys['AccessKeyMetadata'] as $key) {
                        $keyAge = now()->diffInDays($key['CreateDate']);
                        if ($keyAge > 90) {
                            $findings[] = [
                                'severity' => 'medium',
                                'service' => 'iam',
                                'resource_type' => 'access_key',
                                'resource_id' => $key['AccessKeyId'],
                                'finding_type' => 'old_access_key',
                                'title' => 'IAM Access Key Older Than 90 Days',
                                'description' => "Access key '{$key['AccessKeyId']}' for user '{$user['UserName']}' is {$keyAge} days old.",
                                'remediation' => 'Rotate the access key regularly (every 90 days).',
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Continue with next user
                }
            }
        } catch (\Exception $e) {
            Log::error('IAM scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $findings;
    }

    /**
     * Scan EC2 instances for security issues
     */
    public function scanEc2(array $credentials): array
    {
        $findings = [];
        $ec2Client = $this->createClient('ec2', $credentials);

        try {
            $instances = $ec2Client->describeInstances();

            foreach ($instances['Reservations'] as $reservation) {
                foreach ($reservation['Instances'] as $instance) {
                    $instanceId = $instance['InstanceId'];

                    // Check for public IP addresses
                    if (isset($instance['PublicIpAddress'])) {
                        $findings[] = [
                            'severity' => 'medium',
                            'service' => 'ec2',
                            'resource_type' => 'instance',
                            'resource_id' => $instanceId,
                            'finding_type' => 'public_ip',
                            'title' => 'EC2 Instance with Public IP',
                            'description' => "EC2 instance '{$instanceId}' has a public IP address: {$instance['PublicIpAddress']}.",
                            'remediation' => 'Consider using a NAT Gateway or removing the public IP if not needed.',
                        ];
                    }

                    // Check security groups for overly permissive rules
                    if (isset($instance['SecurityGroups'])) {
                        foreach ($instance['SecurityGroups'] as $sg) {
                            // This is a simplified check - in production, you'd analyze the security group rules
                            $findings[] = [
                                'severity' => 'low',
                                'service' => 'ec2',
                                'resource_type' => 'security_group',
                                'resource_id' => $sg['GroupId'],
                                'finding_type' => 'security_group_review',
                                'title' => 'EC2 Security Group Review Recommended',
                                'description' => "Security group '{$sg['GroupId']}' attached to instance '{$instanceId}' should be reviewed for least privilege access.",
                                'remediation' => 'Review and restrict security group rules to only necessary ports and sources.',
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('EC2 scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $findings;
    }

    /**
     * Scan RDS databases for security issues
     */
    public function scanRds(array $credentials): array
    {
        $findings = [];
        $rdsClient = $this->createClient('rds', $credentials);

        try {
            $dbInstances = $rdsClient->describeDBInstances();

            foreach ($dbInstances['DBInstances'] as $dbInstance) {
                $dbId = $dbInstance['DBInstanceIdentifier'];

                // Check if encryption is enabled
                if (!isset($dbInstance['StorageEncrypted']) || !$dbInstance['StorageEncrypted']) {
                    $findings[] = [
                        'severity' => 'high',
                        'service' => 'rds',
                        'resource_type' => 'db_instance',
                        'resource_id' => $dbId,
                        'finding_type' => 'encryption',
                        'title' => 'RDS Instance Not Encrypted',
                        'description' => "RDS instance '{$dbId}' does not have encryption enabled.",
                        'remediation' => 'Enable encryption at rest for the RDS instance.',
                    ];
                }

                // Check if publicly accessible
                if (isset($dbInstance['PubliclyAccessible']) && $dbInstance['PubliclyAccessible']) {
                    $findings[] = [
                        'severity' => 'critical',
                        'service' => 'rds',
                        'resource_type' => 'db_instance',
                        'resource_id' => $dbId,
                        'finding_type' => 'public_access',
                        'title' => 'RDS Instance Publicly Accessible',
                        'description' => "RDS instance '{$dbId}' is publicly accessible, which is a security risk.",
                        'remediation' => 'Disable public accessibility and use VPC security groups for access control.',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('RDS scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $findings;
    }

    /**
     * Run a full security scan
     */
    public function runFullScan(): array
    {
        $allFindings = [];
        
        try {
            $credentials = $this->assumeRole();

            // Scan different services
            $allFindings = array_merge($allFindings, $this->scanS3($credentials));
            $allFindings = array_merge($allFindings, $this->scanIam($credentials));
            $allFindings = array_merge($allFindings, $this->scanEc2($credentials));
            $allFindings = array_merge($allFindings, $this->scanRds($credentials));
        } catch (\Exception $e) {
            Log::error('Full scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $allFindings;
    }
}

