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
     */
    public function executeApiCall(string $method, array $credentials, array $params = []): array
    {
        $s3Client = $this->createClient('s3', $credentials);

        try {
            return match ($method) {
                'listBuckets' => $s3Client->listBuckets($params)->toArray(),
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
            ]);
            throw $e;
        }
    }
}
