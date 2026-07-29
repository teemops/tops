<?php

namespace App\Services\Scanners;

use Illuminate\Support\Facades\Log;

/**
 * S3 needs one thing the generic scanner cannot express: bucket-specific calls must be
 * signed against the bucket's own region, which is not known until we ask. Everything
 * else — which operations exist, pagination — comes from GenericAwsScanner.
 */
class S3Scanner extends GenericAwsScanner
{
    protected string $clientKey = 's3';

    protected string $label = 'S3';

    /**
     * Bucket-scoped calls fail with AuthorizationHeaderMalformed unless the client is
     * configured for the bucket's region, so resolve it before delegating.
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        // getBucketLocation is deliberately absent: it works from any region and is one
        // of the ways the region gets resolved in the first place.
        $bucketSpecificMethods = ['getPublicAccessBlock', 'getBucketEncryption', 'getBucketVersioning', 'getBucketAcl', 'getBucketLogging'];

        if (in_array($method, $bucketSpecificMethods) && isset($params['Bucket'])) {
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

        return parent::executeApiCall($method, $credentials, $params, $region);
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
