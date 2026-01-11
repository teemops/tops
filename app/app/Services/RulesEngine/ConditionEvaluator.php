<?php

namespace App\Services\RulesEngine;

/**
 * Evaluates JavaScript-like condition expressions from rules
 * 
 * Supports:
 * - Comparison operators: ==, !=, >, <, >=, <=
 * - Logical operators: &&, ||
 * - Property access: dot notation (r.MFADevices.length)
 * - Array access: bracket notation (r[0])
 * - Basic expressions
 */
class ConditionEvaluator
{
    /**
     * Evaluate a condition expression against data
     * 
     * @param string $condition Condition expression (e.g., "r.MFADevices.length == 0")
     * @param array $data Data to evaluate against (the raw_data from scan_details)
     * @return bool Result of condition evaluation
     */
    public function evaluate(string $condition, array $data): bool
    {
        // Handle error cases - if data contains error marker, treat as false
        if (isset($data['__error__']) && $data['__error__'] === true) {
            // For conditions checking r==false, this will match
            // For other conditions, they will fail (which is correct)
            $data = false;
        }
        
        // Create a safe evaluation context
        // For MVP, we'll use a simple PHP-based evaluator
        // In production, consider using a proper expression evaluator library
        
        try {
            // Replace property access (r.MFADevices.length) with array access
            $phpExpression = $this->convertToPhpExpression($condition, $data);
            
            // Evaluate the expression
            return $this->evaluatePhpExpression($phpExpression, $data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Condition evaluation failed', [
                'condition' => $condition,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Convert JavaScript-like expression to PHP expression
     */
    private function convertToPhpExpression(string $condition, array $data): string
    {
        // Replace 'r' with '$data'
        $expression = str_replace('r', '$data', $condition);
        
        // Replace dot notation with array access
        // r.MFADevices.length -> $data['MFADevices']['length']
        $expression = preg_replace_callback(
            '/\$data\.([a-zA-Z_][a-zA-Z0-9_]*)/',
            function ($matches) {
                return "\$data['{$matches[1]}']";
            },
            $expression
        );
        
        // Handle chained property access (r.Status.ServeSignature)
        $expression = preg_replace_callback(
            "/\$data\['([^']+)'\]\.([a-zA-Z_][a-zA-Z0-9_]*)/",
            function ($matches) {
                return "\$data['{$matches[1]}']['{$matches[2]}']";
            },
            $expression
        );
        
        // Handle array access (r[0])
        $expression = preg_replace(
            "/\$data\[(\d+)\]/",
            "\$data[$1]",
            $expression
        );
        
        // Replace JavaScript operators
        $expression = str_replace('===', '==', $expression);
        $expression = str_replace('!==', '!=', $expression);
        $expression = str_replace('&&', '&&', $expression);
        $expression = str_replace('||', '||', $expression);
        
        // Handle undefined checks
        $expression = preg_replace(
            "/\$data\['([^']+)'\]\s*!=\s*undefined/",
            "isset(\$data['$1'])",
            $expression
        );
        $expression = preg_replace(
            "/\$data\['([^']+)'\]\s*==\s*undefined/",
            "!isset(\$data['$1'])",
            $expression
        );
        
        return $expression;
    }

    /**
     * Safely evaluate PHP expression
     */
    private function evaluatePhpExpression(string $expression, array $data): bool
    {
        // Extract the actual expression part (remove $data variable assignments)
        // We'll use eval in a controlled way - in production, consider using a proper expression parser
        
        // Create a safe evaluation function
        $evaluate = function ($expr, $data) {
            // Extract variable names and create local scope
            extract(['data' => $data], EXTR_SKIP);
            
            // Replace $data with $data for evaluation
            $expr = str_replace('$data', '$data', $expr);
            
            // Use a whitelist of allowed operations
            // For safety, we'll parse and evaluate manually
            return $this->parseAndEvaluate($expr, $data);
        };
        
        return $evaluate($expression, $data);
    }

    /**
     * Parse and evaluate expression safely
     */
    private function parseAndEvaluate(string $expression, $data): bool
    {
        // Handle case where data is false (error case)
        if ($data === false) {
            // Check if expression is checking for false
            if (preg_match('/^(.+?)\s*==\s*false$/', $expression, $matches)) {
                $left = $this->getValue(trim($matches[1]), $data);
                return $left === false;
            }
            // For other conditions with false data, return false
            return false;
        }
        
        // Ensure data is an array
        if (!is_array($data)) {
            $data = [];
        }
        
        // For MVP, use a simple recursive descent parser
        // Handle common patterns:
        // - Comparisons: ==, !=, >, <, >=, <=
        // - Logical: &&, ||
        // - Property access
        
        // Handle boolean literals
        if ($expression === 'true' || $expression === 'false') {
            return $expression === 'true';
        }
        
        // Handle == comparisons
        if (preg_match('/^(.+?)\s*==\s*(.+?)$/', $expression, $matches)) {
            $left = $this->getValue(trim($matches[1]), $data);
            $right = $this->getValue(trim($matches[2]), $data);
            return $left == $right;
        }
        
        // Handle != comparisons
        if (preg_match('/^(.+?)\s*!=\s*(.+?)$/', $expression, $matches)) {
            $left = $this->getValue(trim($matches[1]), $data);
            $right = $this->getValue(trim($matches[2]), $data);
            return $left != $right;
        }
        
        // Handle > comparisons
        if (preg_match('/^(.+?)\s*>\s*(.+?)$/', $expression, $matches)) {
            $left = $this->getValue(trim($matches[1]), $data);
            $right = $this->getValue(trim($matches[2]), $data);
            return $left > $right;
        }
        
        // Handle < comparisons
        if (preg_match('/^(.+?)\s*<\s*(.+?)$/', $expression, $matches)) {
            $left = $this->getValue(trim($matches[1]), $data);
            $right = $this->getValue(trim($matches[2]), $data);
            return $left < $right;
        }
        
        // Handle >= comparisons
        if (preg_match('/^(.+?)\s*>=\s*(.+?)$/', $expression, $matches)) {
            $left = $this->getValue(trim($matches[1]), $data);
            $right = $this->getValue(trim($matches[2]), $data);
            return $left >= $right;
        }
        
        // Handle <= comparisons
        if (preg_match('/^(.+?)\s*<=\s*(.+?)$/', $expression, $matches)) {
            $left = $this->getValue(trim($matches[1]), $data);
            $right = $this->getValue(trim($matches[2]), $data);
            return $left <= $right;
        }
        
        // Handle && (logical AND)
        if (strpos($expression, '&&') !== false) {
            $parts = explode('&&', $expression);
            foreach ($parts as $part) {
                if (!$this->parseAndEvaluate(trim($part), $data)) {
                    return false;
                }
            }
            return true;
        }
        
        // Handle || (logical OR)
        if (strpos($expression, '||') !== false) {
            $parts = explode('||', $expression);
            foreach ($parts as $part) {
                if ($this->parseAndEvaluate(trim($part), $data)) {
                    return true;
                }
            }
            return false;
        }
        
        // Handle single value (property access or literal)
        $value = $this->getValue($expression, $data);
        return (bool) $value;
    }

    /**
     * Get value from expression (handles property access, arrays, literals)
     */
    private function getValue(string $expr, $data)
    {
        $expr = trim($expr);
        
        // Handle case where data is false
        if ($data === false) {
            // If expression is just checking the data itself
            if ($expr === '$data' || $expr === 'r') {
                return false;
            }
            return null;
        }
        
        // Ensure data is an array
        if (!is_array($data)) {
            return null;
        }
        
        // Handle string literals
        if (preg_match("/^['\"](.+?)['\"]$/", $expr, $matches)) {
            return $matches[1];
        }
        
        // Handle numeric literals
        if (is_numeric($expr)) {
            return $expr + 0; // Convert to int or float
        }
        
        // Handle boolean literals
        if ($expr === 'true') {
            return true;
        }
        if ($expr === 'false') {
            return false;
        }
        
        // Handle property access: $data['key'] or $data['key']['subkey']
        if (preg_match_all("/\$data\['([^']+)'\]/", $expr, $matches)) {
            $value = $data;
            foreach ($matches[1] as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return null;
                }
            }
            return $value;
        }
        
        // Handle array length: .length
        if (preg_match("/^(.+?)\.length$/", $expr, $matches)) {
            $array = $this->getValue($matches[1], $data);
            return is_array($array) ? count($array) : 0;
        }
        
        // Handle array index access: [0]
        if (preg_match("/^(.+?)\[(\d+)\]$/", $expr, $matches)) {
            $array = $this->getValue($matches[1], $data);
            $index = (int) $matches[2];
            return is_array($array) && isset($array[$index]) ? $array[$index] : null;
        }
        
        return null;
    }

    /**
     * Convert PHP array to JavaScript-like object (for reference, not used in evaluation)
     */
    private function convertToJsObject(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
