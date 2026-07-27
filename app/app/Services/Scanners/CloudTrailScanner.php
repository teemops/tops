<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;

class CloudTrailScanner extends AwsSecurityScanner
{
    /**
     * Execute CloudTrail API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $cloudTrailClient = $this->createClient('cloudtrail', $credentials, $region);

        return $this->callApi('CloudTrail', $method, $params, fn () => match ($method) {
            'describeTrails' => $cloudTrailClient->describeTrails($params)->toArray(),
            'getTrailStatus' => $cloudTrailClient->getTrailStatus($params)->toArray(),
            'getEventSelectors' => $cloudTrailClient->getEventSelectors($params)->toArray(),
            default => throw new \InvalidArgumentException("Unknown CloudTrail method: {$method}"),
        }, ['region' => $region]);
    }
}
