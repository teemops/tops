<?php

namespace App\Services\Scanners;

use Aws\AwsClientInterface;
use Aws\Iam\Exception\IamException;

/**
 * IAM needs one thing the generic scanner cannot express: a missing account password
 * policy is reported as an error, but that absence is itself the insecure condition
 * CIS 1.8/1.9 test for. Everything else comes from GenericAwsScanner.
 */
class IamScanner extends GenericAwsScanner
{
    protected string $clientKey = 'iam';

    protected string $label = 'IAM';

    /**
     * Translate "no password policy configured" into the engine's error marker so the
     * rules' "$data === false" branch fires, mirroring how RulesEngine records failed
     * per-item actions.
     *
     * This deliberately sits inside invoke() rather than wrapping executeApiCall(): the
     * expected absence must not travel through callApi()'s catch, which would log a
     * scan error on every scan of a correctly-detected misconfiguration. Any other IAM
     * error is rethrown so it is logged and retried as the genuine failure it is.
     */
    protected function invoke(AwsClientInterface $client, string $method, string $operation, array $params): array
    {
        try {
            return parent::invoke($client, $method, $operation, $params);
        } catch (IamException $e) {
            if ($method === 'getAccountPasswordPolicy' && $e->getAwsErrorCode() === 'NoSuchEntity') {
                return ['__error__' => true, 'error_message' => 'No account password policy configured'];
            }

            throw $e;
        }
    }
}
