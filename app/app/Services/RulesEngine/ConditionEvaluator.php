<?php

namespace App\Services\RulesEngine;

/**
 * Evaluates PHP condition expressions from rules
 * 
 * Supports standard PHP expressions with $data variable containing the raw_data
 * Examples:
 * - count($data['MFADevices']) == 0
 * - $data === false
 * - isset($data['PublicIpAddress'])
 * - count($data['AccessKeyMetadata']) > 0
 */
class ConditionEvaluator
{
    /**
     * Evaluate a PHP condition expression against data
     * 
     * @param string $condition PHP condition expression (e.g., "count(\$data['MFADevices']) == 0")
     * @param array $data Data to evaluate against (the raw_data from scan_details)
     * @return bool Result of condition evaluation
     */
    public function evaluate(string $condition, array $data): bool
    {
        // Handle error cases - if data contains error marker, treat as false
        if (isset($data['__error__']) && $data['__error__'] === true) {
            $data = false;
        }
        
        try {
            // Use a closure to safely evaluate the PHP expression
            // The $data variable will be available in the closure scope
            $result = (function ($condition, $data) {
                // Extract $data into local scope for the expression
                extract(['data' => $data], EXTR_SKIP);
                
                // Evaluate the condition as PHP code
                // This is safe because we control the rules files
                return eval("return {$condition};");
            })($condition, $data);
            
            return (bool) $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Condition evaluation failed', [
                'condition' => $condition,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
