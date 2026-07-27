<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;

class KmsScanner extends AwsSecurityScanner
{
    /**
     * Execute KMS API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $kmsClient = $this->createClient('kms', $credentials, $region);

        return $this->callApi('KMS', $method, $params, fn () => match ($method) {
            'listKeys' => $kmsClient->listKeys($params)->toArray(),
            'describeKey' => $kmsClient->describeKey($params)->toArray(),
            'getKeyRotationStatus' => $kmsClient->getKeyRotationStatus($params)->toArray(),
            default => throw new \InvalidArgumentException("Unknown KMS method: {$method}"),
        }, ['region' => $region]);
    }
}
