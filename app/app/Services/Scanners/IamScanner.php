<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;
use Aws\Iam\IamClient;
use Illuminate\Support\Facades\Log;

class IamScanner extends AwsSecurityScanner
{
    /**
     * Execute IAM API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = []): array
    {
        $iamClient = $this->createClient('iam', $credentials);

        try {
            return match ($method) {
                'listUsers' => $iamClient->listUsers($params)->toArray(),
                'listRoles' => $iamClient->listRoles($params)->toArray(),
                'getUser' => $iamClient->getUser($params)->toArray(),
                'listMFADevices' => $iamClient->listMFADevices($params)->toArray(),
                'listAccessKeys' => $iamClient->listAccessKeys($params)->toArray(),
                'listUserPolicies' => $iamClient->listUserPolicies($params)->toArray(),
                'listGroupsForUser' => $iamClient->listGroupsForUser($params)->toArray(),
                'listAttachedUserPolicies' => $iamClient->listAttachedUserPolicies($params)->toArray(),
                'getRole' => $iamClient->getRole($params)->toArray(),
                'getRolePolicy' => $iamClient->getRolePolicy($params)->toArray(),
                'listRolePolicies' => $iamClient->listRolePolicies($params)->toArray(),
                'listAttachedRolePolicies' => $iamClient->listAttachedRolePolicies($params)->toArray(),
                default => throw new \InvalidArgumentException("Unknown IAM method: {$method}"),
            };
        } catch (\Exception $e) {
            Log::error("IAM API call failed: {$method}", [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }
}
