<?php

namespace App\Services\RulesEngine;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Models\ScanResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;

class FindingsEngine
{
    protected ConditionEvaluator $evaluator;

    public function __construct(ConditionEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    /**
     * Load rules.json file
     */
    public function loadRules(string $ruleset = 'basic'): array
    {
        $rulesPath = base_path("rules/rulesets/{$ruleset}.json");
        
        if (!File::exists($rulesPath)) {
            throw new \Exception("Rules file not found: {$rulesPath}");
        }
        
        $rulesJson = File::get($rulesPath);
        $rules = json_decode($rulesJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in rules file: " . json_last_error_msg());
        }
        
        return $rules['rules'] ?? [];
    }

    /**
     * Evaluate scan and create findings
     */
    public function evaluateScan(Scan $scan, array $rulesets = ['basic']): void
    {
        // Load all rules from specified rulesets
        $allRules = [];
        foreach ($rulesets as $ruleset) {
            try {
                $rules = $this->loadRules($ruleset);
                $allRules = array_merge($allRules, $rules);
            } catch (\Exception $e) {
                Log::warning("Failed to load ruleset: {$ruleset}", [
                    'scan_id' => $scan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Get all scan details for this scan
        $scanDetails = ScanDetail::where('scan_id', $scan->id)->get();
        
        // Group scan details by service and method
        $detailsByServiceMethod = $scanDetails->groupBy(function ($detail) {
            return "{$detail->service}:{$detail->api_method}";
        });

        // Evaluate each rule, remembering which findings this scan raised so the
        // reconciliation pass can tell "still failing" from "examined and now fine".
        $this->raisedThisScan = [];

        foreach ($allRules as $rule) {
            $this->evaluateRule($scan, $rule, $scanDetails, $detailsByServiceMethod);
        }

        $this->resolveFixedFindings($scan, $allRules, $detailsByServiceMethod);
    }

    /**
     * Identity hashes raised by the scan currently being evaluated.
     *
     * @var array<string, true>
     */
    private array $raisedThisScan = [];

    /**
     * Close findings whose resource this scan examined and did not flag.
     *
     * The whole safety of this rests on one distinction: a finding is only resolved when
     * there is **positive evidence** the scan looked at that exact resource under that
     * exact rule — a scan_detail for the same service, api_method and resource_id — and
     * raised nothing. Absence of a detail means the resource was not examined (a
     * service-scoped scan, a failed region, a denied permission), and the finding is left
     * strictly alone.
     *
     * That is why "the resource was deleted" is not handled here. It can only be inferred
     * from an absent resource inside a successful enumeration, and a half-collected region
     * currently reports as complete — so the inference would close findings nobody looked
     * at. Deferred until per-(service, region) accounting is trustworthy.
     *
     * @param array<int, array<string, mixed>> $rules
     * @param Collection<string, Collection<int, ScanDetail>> $detailsByServiceMethod
     */
    private function resolveFixedFindings(Scan $scan, array $rules, Collection $detailsByServiceMethod): void
    {
        // Which (service, api_method, resource_id) triples this scan actually examined.
        $examined = [];
        foreach ($rules as $rule) {
            $service = $rule['service'] ?? null;
            $method = $rule['method'] ?? null;
            $findingType = $rule['rule'] ?? null;

            if ($service === null || $method === null || $findingType === null) {
                continue;
            }

            foreach ($detailsByServiceMethod->get("{$service}:{$method}", collect()) as $detail) {
                $resourceId = $detail->resource_id ?? 'unknown';
                $examined[] = ScanResult::identityHash(
                    $scan->organization_id,
                    $scan->aws_account_id,
                    $service,
                    $detail->resource_type ?? 'unknown',
                    $resourceId,
                    $findingType,
                );
            }
        }

        $examined = array_values(array_unique($examined));
        if ($examined === []) {
            return;
        }

        // Examined, and this scan raised nothing for it — the problem is fixed.
        $fixed = array_values(array_diff($examined, array_keys($this->raisedThisScan)));
        if ($fixed === []) {
            return;
        }

        foreach (array_chunk($fixed, 500) as $chunk) {
            ScanResult::query()
                ->where('organization_id', $scan->organization_id)
                ->whereIn('identity_hash', $chunk)
                ->where('status', '!=', 'resolved')
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolution_reason' => ScanResult::REASON_FIXED,
                    'last_seen_at' => now(),
                    'last_seen_scan_id' => $scan->id,
                ]);
        }
    }

    /**
     * Evaluate a single rule against scan details
     */
    private function evaluateRule(
        Scan $scan,
        array $rule,
        Collection $scanDetails,
        Collection $detailsByServiceMethod
    ): void {
        $service = $rule['service'] ?? null;
        $method = $rule['method'] ?? null;
        $condition = $rule['condition'] ?? null;
        
        if (!$service || !$method || !$condition) {
            Log::warning("Invalid rule configuration", [
                'rule' => $rule['rule'] ?? 'unknown',
                'scan_id' => $scan->id,
            ]);
            return;
        }

        // Find scan details that match this rule's service and method
        $matchingDetails = $scanDetails->filter(function ($detail) use ($service, $method) {
            return $detail->service === $service && $detail->api_method === $method;
        });

        if ($matchingDetails->isEmpty()) {
            return;
        }

        // Evaluate condition for each matching detail
        foreach ($matchingDetails as $detail) {
            $rawData = $detail->raw_data;
            
            // Handle special case: if raw_data is the response structure, extract the relevant part
            // For example, listMFADevices returns { MFADevices: [...] }
            // We need to pass the whole structure to the evaluator
            
            try {
                $conditionMet = $this->evaluator->evaluate($condition, $rawData);
                
                if ($conditionMet) {
                    // Create finding
                    $this->createFinding($scan, $rule, $detail);
                }
            } catch (\Exception $e) {
                Log::warning("Rule evaluation failed", [
                    'scan_id' => $scan->id,
                    'rule' => $rule['rule'] ?? 'unknown',
                    'detail_id' => $detail->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create a finding from a rule evaluation
     */
    private function createFinding(Scan $scan, array $rule, ScanDetail $detail): void
    {
        // Determine resource type and ID
        $resourceType = $detail->resource_type ?? 'unknown';
        $resourceId = $detail->resource_id ?? 'unknown';
        
        // For some rules, we might need to extract additional info from raw_data
        // For example, access key age calculations
        $title = $this->buildTitle($rule, $detail);
        $description = $this->buildDescription($rule, $detail);
        
        $findingType = $rule['rule'] ?? 'unknown';
        $identity = ScanResult::identityHash(
            $scan->organization_id,
            $scan->aws_account_id,
            $rule['service'],
            $resourceType,
            $resourceId,
            $findingType,
        );

        // Remember it was raised, so the reconciliation pass does not read this scan's
        // silence about other resources as "fixed".
        $this->raisedThisScan[$identity] = true;

        $existing = ScanResult::where('organization_id', $scan->organization_id)
            ->where('identity_hash', $identity)
            ->first();

        // Content always comes from the scan that just looked. Status does not: it is the
        // user's, and a scan may only contradict it with evidence.
        $content = [
            'severity' => $rule['severity'] ?? 'medium',
            'title' => $title,
            'description' => $description,
            'remediation' => $this->buildRemediation($rule, $detail),
            'last_seen_at' => now(),
            'last_seen_scan_id' => $scan->id,
        ];

        if ($existing) {
            // A finding someone marked resolved that is still failing gets reopened —
            // the claim was contradicted, and saying so is the point of scanning again.
            // "Ignored" is a standing instruction rather than a claim, so it stands.
            if ($existing->status === 'resolved') {
                $content['status'] = 'open';
                $content['resolved_at'] = null;
                $content['resolution_reason'] = null;
            }

            $existing->update($content);

            return;
        }

        ScanResult::create($content + [
            'scan_id' => $scan->id,
            'organization_id' => $scan->organization_id,
            'aws_account_id' => $scan->aws_account_id,
            'identity_hash' => $identity,
            'service' => $rule['service'],
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'finding_type' => $findingType,
            'status' => 'open',
            'first_seen_at' => now(),
        ]);
    }

    /**
     * Build finding title
     */
    private function buildTitle(array $rule, ScanDetail $detail): string
    {
        $ruleName = $rule['name'] ?? $rule['rule'] ?? 'Security Finding';
        $resourceId = $detail->resource_id ?? 'resource';
        
        // For some rules, add context to title
        if (strpos($rule['rule'] ?? '', 'access_key') !== false) {
            // Extract access key ID from raw_data if available
            $rawData = $detail->raw_data;
            if (isset($rawData['AccessKeyMetadata'][0]['AccessKeyId'])) {
                $keyId = $rawData['AccessKeyMetadata'][0]['AccessKeyId'];
                return "{$ruleName} - {$keyId}";
            }
        }
        
        return "{$ruleName} - {$resourceId}";
    }

    /**
     * Build finding description
     */
    private function buildDescription(array $rule, ScanDetail $detail): string
    {
        $baseDescription = $rule['description'] ?? 'Security issue detected';
        $resourceId = $detail->resource_id ?? 'resource';
        
        // Add context to description
        $description = str_replace('{resource}', $resourceId, $baseDescription);
        
        // For access key age rules, add age information
        if (strpos($rule['rule'] ?? '', 'access_key') !== false && strpos($rule['condition'] ?? '', 'days') !== false) {
            $rawData = $detail->raw_data;
            if (isset($rawData['AccessKeyMetadata'][0]['CreateDate'])) {
                $createDate = new \DateTime($rawData['AccessKeyMetadata'][0]['CreateDate']);
                $age = now()->diffInDays($createDate);
                $description .= " The access key is {$age} days old.";
            }
        }
        
        return $description;
    }

    /**
     * Build remediation steps.
     *
     * Remediation text belongs to the rule, in the ruleset JSON alongside its condition.
     * It used to live in a PHP array keyed by rule id here, which meant a contributor
     * could add a perfectly good rule and silently get the generic fallback below until
     * somebody remembered to edit this class. Rules without their own text still get the
     * fallback, as they always did.
     */
    private function buildRemediation(array $rule, ScanDetail $detail): ?string
    {
        return ($rule['remediation'] ?? '')
            ?: 'Review and remediate the security issue according to AWS best practices.';
    }
}
