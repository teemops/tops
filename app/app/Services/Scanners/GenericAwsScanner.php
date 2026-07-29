<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;
use Aws\AwsClientInterface;
use Aws\Sdk;

/**
 * Collects data for any AWS service without per-service PHP.
 *
 * This replaces the hand-written dispatch tables that used to exist one per service. The
 * SDK already models every operation of every service, so the allowed method names come
 * from the service's own API model rather than a list somebody has to remember to extend:
 * a tasks.json naming an operation AWS does not have still fails loudly, and one naming
 * an operation we simply had not wired up before now just works.
 *
 * Services needing behaviour the model cannot express subclass this and override invoke()
 * or executeApiCall() — see S3Scanner and IamScanner, the only two that do.
 */
class GenericAwsScanner extends AwsSecurityScanner
{
    /**
     * The SDK manifest key (endpoint prefix) for this service's client.
     * Subclasses for a fixed service set this; the generic case takes it from the
     * registry via the constructor.
     */
    protected string $clientKey = '';

    /** Human-readable service name used in exception messages and error logs. */
    protected string $label = '';

    public function __construct(
        string $roleArn,
        string $externalId,
        string $region = 'us-east-1',
        ?string $clientKey = null,
        ?string $label = null
    ) {
        parent::__construct($roleArn, $externalId, $region);

        if ($clientKey !== null) {
            $this->clientKey = $clientKey;
        }

        if ($this->clientKey === '') {
            throw new \InvalidArgumentException(
                'GenericAwsScanner needs an AWS SDK client key; pass one or set $clientKey in a subclass.'
            );
        }

        $this->label = $label ?? ($this->label ?: strtoupper($this->clientKey));
    }

    /**
     * Whether the installed SDK models this operation for this service.
     *
     * Answers from the bundled API model alone — no credentials, no network — so
     * scan:validate-rules can check every method a tasks.json names before anyone runs
     * a scan with it. Returns false for a service the SDK does not provide at all.
     */
    public static function supportsOperation(string $clientKey, string $method): bool
    {
        try {
            $client = (new Sdk())->createClient($clientKey, [
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => false,
            ]);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $client->getApi()->hasOperation(ucfirst($method));
    }

    /**
     * Execute one AWS API call and return the raw SDK response as an array.
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $client = $this->createClient($this->clientKey, $credentials, $region);

        // Operations are PascalCase in the API model; tasks.json writes them camelCase,
        // matching how the SDK's own magic methods are called.
        $operation = ucfirst($method);

        if (!$client->getApi()->hasOperation($operation)) {
            throw new \InvalidArgumentException("Unknown {$this->label} method: {$method}");
        }

        return $this->callApi(
            $this->label,
            $method,
            $params,
            fn () => $this->invoke($client, $method, $operation, $params),
            ['region' => $region]
        );
    }

    /**
     * Perform the call. Split out from executeApiCall so subclasses can wrap it while
     * staying inside callApi()'s error handling — an error a subclass translates into a
     * finding must not also be logged as a scan failure.
     */
    protected function invoke(AwsClientInterface $client, string $method, string $operation, array $params): array
    {
        if ($client->getApi()->hasPaginator($operation)) {
            return $this->paginate($client, $operation, $params);
        }

        return $client->execute($client->getCommand($operation, $params))->toArray();
    }

    /**
     * Walk every page of a paginated operation and flatten it into one response-shaped
     * array.
     *
     * Before this, every list call returned only its first page — so an account with more
     * than a page of users, buckets or instances was silently scanned in part and the
     * remainder reported as clean.
     *
     * List-valued keys (Users, Buckets, TableNames, ...) accumulate across pages. Every
     * other key takes its value from the last page, so pagination bookkeeping left in the
     * merged result (IsTruncated, NextToken) describes the end of the walk rather than
     * falsely claiming from page one that more data is waiting.
     */
    private function paginate(AwsClientInterface $client, string $operation, array $params): array
    {
        $merged = [];

        foreach ($client->getPaginator($operation, $params) as $page) {
            foreach ($page->toArray() as $key => $value) {
                if (is_array($value) && array_is_list($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = array_merge($merged[$key], $value);
                    continue;
                }

                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
