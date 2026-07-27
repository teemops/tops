<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;

class LambdaScanner extends AwsSecurityScanner
{
    /**
     * Execute Lambda API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $lambdaClient = $this->createClient('lambda', $credentials, $region);

        return $this->callApi('Lambda', $method, $params, fn () => match ($method) {
            'listFunctions' => $lambdaClient->listFunctions($params)->toArray(),
            'getFunctionUrlConfig' => $lambdaClient->getFunctionUrlConfig($params)->toArray(),
            'getPolicy' => $lambdaClient->getPolicy($params)->toArray(),
            default => throw new \InvalidArgumentException("Unknown Lambda method: {$method}"),
        }, ['region' => $region]);
    }
}
