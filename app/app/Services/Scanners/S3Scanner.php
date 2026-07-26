<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

class S3Scanner extends AwsSecurityScanner
{
    /**
     * Execute S3 API calls based on method name
     * Returns raw AWS SDK response (array)
     * 
     * For bucket-specific calls, automatically determines and uses the bucket's region
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        // For bucket-specific calls, get the bucket's region first
        // Note: getBucketLocation itself doesn't need region lookup (it works from any region)
        $bucketSpecificMethods = ['getPublicAccessBlock', 'getBucketEncryption', 'getBucketVersioning', 'getBucketAcl'];
        
        if (in_array($method, $bucketSpecificMethods) && isset($params['Bucket'])) {
            // Get the bucket's region
            $bucketRegion = $this->getBucketRegion($params['Bucket'], $credentials);
            if ($bucketRegion) {
                $region = $bucketRegion;
                Log::debug("Using bucket region for S3 call", [
                    'method' => $method,
                    'bucket' => $params['Bucket'],
                    'region' => $region,
                ]);
            }
        }
        
        // Use provided region or default
        $s3Client = $this->createClient('s3', $credentials, $region);

        try {
            return match ($method) {
                'listBuckets' => $s3Client->listBuckets($params)->toArray(),
                'getBucketLocation' => $s3Client->getBucketLocation($params)->toArray(),
                'getPublicAccessBlock' => $s3Client->getPublicAccessBlock($params)->toArray(),
                'getBucketEncryption' => $s3Client->getBucketEncryption($params)->toArray(),
                'getBucketVersioning' => $s3Client->getBucketVersioning($params)->toArray(),
                'getBucketAcl' => $s3Client->getBucketAcl($params)->toArray(),
                default => throw new \InvalidArgumentException("Unknown S3 method: {$method}"),
            };
        } catch (\Exception $e) {
            Log::error("S3 API call failed: {$method}", [
                'error' => $e->getMessage(),
                'params' => $params,
                'region' => $region,
            ]);
            throw $e;
        }
    }

    /**
     * Get the region for an S3 bucket
     * Returns null if unable to determine (will use default)
     */
    private function getBucketRegion(string $bucketName, array $credentials): ?string
    {
        try {
            // GetBucketLocation requires the client's configured region to match the
            // bucket's actual region (SigV4 signing fails with AuthorizationHeaderMalformed
            // otherwise) — a chicken-and-egg problem when we don't yet know the region.
            // determineBucketRegion() is built for exactly this: it issues a HeadBucket
            // call and reads the region from the (possibly redirected) response, working
            // regardless of the client's configured region.
            $s3Client = $this->createClient('s3', $credentials);

            return $s3Client->determineBucketRegion($bucketName);
        } catch (\Exception $e) {
            Log::warning("Failed to get bucket region", [
                'bucket' => $bucketName,
                'error' => $e->getMessage(),
            ]);
            // Return null to use default region
            return null;
        }
    }
}
