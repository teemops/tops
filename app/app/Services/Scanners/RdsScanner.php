<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;
use Aws\Rds\RdsClient;
use Illuminate\Support\Facades\Log;

class RdsScanner extends AwsSecurityScanner
{
    /**
     * Execute RDS API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = []): array
    {
        $rdsClient = $this->createClient('rds', $credentials);

        try {
            return match ($method) {
                'describeDBInstances' => $rdsClient->describeDBInstances($params)->toArray(),
                'describeDBSnapshots' => $rdsClient->describeDBSnapshots($params)->toArray(),
                'describeDBClusters' => $rdsClient->describeDBClusters($params)->toArray(),
                default => throw new \InvalidArgumentException("Unknown RDS method: {$method}"),
            };
        } catch (\Exception $e) {
            Log::error("RDS API call failed: {$method}", [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }
}
