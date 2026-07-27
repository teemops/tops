<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;

class RdsScanner extends AwsSecurityScanner
{
    /**
     * Execute RDS API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $rdsClient = $this->createClient('rds', $credentials, $region);

        return $this->callApi('RDS', $method, $params, fn () => match ($method) {
            'describeDBInstances' => $rdsClient->describeDBInstances($params)->toArray(),
            'describeDBSnapshots' => $rdsClient->describeDBSnapshots($params)->toArray(),
            'describeDBClusterSnapshots' => $rdsClient->describeDBClusterSnapshots($params)->toArray(),
            'describeDBClusters' => $rdsClient->describeDBClusters($params)->toArray(),
            'describeDBSubnetGroups' => $rdsClient->describeDBSubnetGroups($params)->toArray(),
            'describeDBParameterGroups' => $rdsClient->describeDBParameterGroups($params)->toArray(),
            'describeDBParameters' => $rdsClient->describeDBParameters($params)->toArray(),
            'describeDBSecurityGroups' => $rdsClient->describeDBSecurityGroups($params)->toArray(),
            'describeDBClusterParameterGroups' => $rdsClient->describeDBClusterParameterGroups($params)->toArray(),
            'describeDBClusterParameters' => $rdsClient->describeDBClusterParameters($params)->toArray(),
            'describeEventSubscriptions' => $rdsClient->describeEventSubscriptions($params)->toArray(),
            default => throw new \InvalidArgumentException("Unknown RDS method: {$method}"),
        }, ['region' => $region]);
    }
}
