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

        // Evaluate each rule
        foreach ($allRules as $rule) {
            $this->evaluateRule($scan, $rule, $scanDetails, $detailsByServiceMethod);
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
        
        // Check if finding already exists (avoid duplicates)
        $existing = ScanResult::where('scan_id', $scan->id)
            ->where('service', $rule['service'])
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('finding_type', $rule['rule'] ?? 'unknown')
            ->first();
        
        if ($existing) {
            return; // Finding already exists
        }
        
        ScanResult::create([
            'scan_id' => $scan->id,
            'severity' => $rule['severity'] ?? 'medium',
            'service' => $rule['service'],
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'finding_type' => $rule['rule'] ?? 'unknown',
            'title' => $title,
            'description' => $description,
            'remediation' => $this->buildRemediation($rule, $detail),
            'status' => 'open',
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
     * Build remediation steps
     */
    private function buildRemediation(array $rule, ScanDetail $detail): ?string
    {
        // For MVP, use a generic remediation or rule-specific
        $remediations = [
            'tops-iam-001' => 'Enable MFA for the IAM user to enhance security.',
            'tops-iam-002' => 'Remove access keys and use IAM Identity Center for Single Sign-on.',
            'tops-iam-003' => 'Remove inline policies and use IAM Groups instead.',
            'tops-iam-004' => 'Remove attached policies and use IAM Groups instead.',
            'tops-s3-001' => 'Enable Public Access Block on the bucket to prevent accidental public access.',
            'tops-s3-002' => 'Enable server-side encryption (SSE) on the bucket.',
            'tops-s3-003' => 'Enable versioning on the bucket to protect against accidental deletion.',
            'tops-s3-005' => 'Enable S3 server access logging and direct logs to a dedicated log bucket.',
            'tops-ec2-002' => 'Consider using a NAT Gateway or removing the public IP if not needed.',
            'tops-ec2-006' => 'Set the instance metadata options to require IMDSv2 (HttpTokens=required).',
            'tops-ec2-007' => 'Enable VPC Flow Logs for the VPC and deliver them to CloudWatch Logs or S3.',
            'tops-rds-007' => 'Enable deletion protection on the RDS instance.',
            'tops-rds-008' => 'Enable Enhanced Monitoring on the RDS instance for OS-level metrics.',
            'tops-rds-009' => 'Enable automatic minor version upgrades on the RDS instance.',
            'tops-iam-011' => 'Enable MFA on the AWS root account immediately and store the device securely.',
            'tops-iam-012' => 'Delete the root account access keys and use IAM roles or IAM Identity Center instead.',
            'tops-iam-013' => 'Configure an IAM password policy with a minimum length of at least 14 characters and complexity requirements.',
            'tops-cloudtrail-001' => 'Start logging on the CloudTrail trail so account activity is captured.',
            'tops-cloudtrail-002' => 'Enable log file validation on the trail to detect tampering.',
            'tops-cloudtrail-003' => 'Recreate or update the trail as a multi-region trail.',
            'tops-cloudtrail-004' => 'Configure the trail to deliver events to a CloudWatch Logs log group.',
            'tops-cloudtrail-005' => 'Configure the trail to encrypt log files with a KMS customer managed key.',
            'tops-lambda-001' => 'Change the function URL AuthType to AWS_IAM, or remove the function URL if not required.',
            'tops-lambda-002' => 'Migrate the function to a currently supported runtime version.',
            'tops-lambda-003' => 'Attach a VPC configuration to the function if it accesses private resources.',
            'tops-lambda-004' => 'Move secrets out of environment variables into AWS Secrets Manager or SSM Parameter Store.',
            'tops-kms-001' => 'Cancel the scheduled key deletion if the key is still needed for decryption.',
            'tops-kms-002' => 'Enable automatic key rotation on the customer managed KMS key.',
            'tops-kms-003' => 'Re-enable the KMS key if it is still required, or schedule it for deletion if not.',
        ];
        
        $ruleId = $rule['rule'] ?? null;
        return $remediations[$ruleId] ?? 'Review and remediate the security issue according to AWS best practices.';
    }
}
