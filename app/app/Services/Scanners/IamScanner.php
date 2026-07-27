<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;
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

        // Log params for debugging (especially for methods that require parameters)
        if (in_array($method, ['getUser', 'listMFADevices', 'listAccessKeys', 'listUserPolicies', 'listGroupsForUser', 'listAttachedUserPolicies', 'getRole', 'listRolePolicies', 'listAttachedRolePolicies'])) {
            Log::info("IAM API call with params", [
                'method' => $method,
                'params' => $params,
                'params_count' => count($params),
                'params_empty' => empty($params),
                'has_username' => isset($params['UserName']),
                'username_value' => $params['UserName'] ?? 'NOT SET',
                'has_rolename' => isset($params['RoleName']),
                'rolename_value' => $params['RoleName'] ?? 'NOT SET',
            ]);
        }

        return $this->callApi('IAM', $method, $params, fn () => match ($method) {
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
        });
    }
}
