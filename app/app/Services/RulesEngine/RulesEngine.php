<?php

namespace App\Services\RulesEngine;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Services\ScanTypesService;
use App\Services\Scanners\GenericAwsScanner;
use App\Services\ServiceRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class RulesEngine
{
    /**
     * Load tasks.json file for a service
     */
    public function loadTasks(string $service): array
    {
        $tasksPath = base_path("rules/tasks/{$service}/tasks.json");
        
        if (!File::exists($tasksPath)) {
            throw new \Exception("Tasks file not found: {$tasksPath}");
        }
        
        $tasksJson = File::get($tasksPath);
        $tasks = json_decode($tasksJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in tasks file: " . json_last_error_msg());
        }
        
        return $tasks;
    }

    /**
     * Execute scan for a service based on tasks.json
     */
    public function executeScan(Scan $scan, string $service, array $credentials, ?string $region = null): void
    {
        $tasks = $this->loadTasks($service);
        $scanner = $this->getScanner($service, $scan);
        
        // Get start task from config
        $startTask = $tasks['config']['start'] ?? null;
        
        if (!$startTask) {
            throw new \Exception("No start task defined in tasks.json for service: {$service}");
        }
        
        // Execute all tasks
        foreach ($tasks['tasks'] as $taskGroup) {
            foreach ($taskGroup as $taskName => $taskConfig) {
                if (empty($taskConfig)) {
                    // Empty task config means it's an action, skip for now
                    continue;
                }

                // tasks.json declares fallback action params under the file-level
                // "config.defaults" block, not per-task — merge it in here (a
                // per-task "defaults" key still wins) so buildActionParams() can
                // find it via $taskConfig['defaults'].
                if (!isset($taskConfig['defaults']) && isset($tasks['config']['defaults'])) {
                    $taskConfig['defaults'] = $tasks['config']['defaults'];
                }

                $this->executeTask($scan, $scanner, $service, $taskName, $taskConfig, $credentials, $region);
            }
        }
    }

    /**
     * Execute a single task
     */
    private function executeTask(
        Scan $scan,
        object $scanner,
        string $service,
        string $taskName,
        array $taskConfig,
        array $credentials,
        ?string $region = null
    ): void {
        try {
            // Execute the main task API call
            $params = [];
            if (ScanTypesService::isRegionBased($service) && $region) {
                $result = $scanner->executeApiCall($taskName, $credentials, $params, $region);
            } else {
                $result = $scanner->executeApiCall($taskName, $credentials, $params);
            }
            
            // Store the main task result
            $this->storeScanDetail($scan, $service, null, null, $taskName, $result, null, $region);

            // Get items from the result (e.g., Users, Roles, Buckets, Reservations.Instances)
            $items = $this->extractItems($result, $taskConfig);
            
            // Execute actions for each item
            $actions = $taskConfig['actions'] ?? [];
            $idKey = $taskConfig['id'] ?? null;
            $keyField = $taskConfig['key'] ?? null;
            
            foreach ($items as $item) {
                $resourceId = $this->getResourceId($item, $keyField);
                $resourceType = $idKey ?? 'resource';

                // Store the item itself
                $this->storeScanDetail($scan, $service, $resourceType, $resourceId, $taskName, $this->itemRawData($item), null, $region);
                
                // Execute actions for this item
                // Actions can be either:
                // 1. New format: array of objects like [{"getUser": {"params": {...}}}]
                // 2. Legacy format: array of strings like ["getUser", "listMFADevices"]
                foreach ($actions as $action) {
                    try {
                        // Extract action name and params based on format
                        if (is_array($action) && !isset($action[0])) {
                            // New format: object with action name as key
                            // e.g., {"getUser": {"params": {...}}}
                            $actionName = array_key_first($action);
                            $actionConfig = $action[$actionName] ?? [];
                            $actionParamsConfig = $actionConfig['params'] ?? null;
                        } elseif (is_string($action)) {
                            // Legacy format: simple string
                            $actionName = $action;
                            $actionParamsConfig = null;
                        } else {
                            Log::warning("Invalid action format", [
                                'action' => $action,
                                'action_type' => gettype($action),
                            ]);
                            continue;
                        }
                        
                        // Build params from action config or fallback to defaults
                        $actionParams = $this->buildActionParams($item, $taskConfig, $actionName, $actionParamsConfig);
                        
                        // Ensure params is always an array
                        if (!is_array($actionParams)) {
                            Log::error("buildActionParams did not return an array", [
                                'action' => $actionName,
                                'return_type' => gettype($actionParams),
                                'return_value' => $actionParams,
                            ]);
                            $actionParams = [];
                        }
                        
                        Log::debug("Executing action with params", [
                            'action' => $actionName,
                            'params' => $actionParams,
                            'params_count' => count($actionParams),
                            'item_keys' => is_array($item) ? array_keys($item) : null,
                            'resource_id' => $resourceId,
                        ]);
                        
                        if (ScanTypesService::isRegionBased($service) && $region) {
                            $actionResult = $scanner->executeApiCall($actionName, $credentials, $actionParams, $region);
                        } else {
                            // Global services (e.g. S3 handles region automatically for
                            // bucket-specific calls) run without an explicit region.
                            $actionResult = $scanner->executeApiCall($actionName, $credentials, $actionParams);
                        }
                        
                        // Store action result
                        $this->storeScanDetail($scan, $service, $resourceType, $resourceId, $actionName, $actionResult, $resourceId, $region);
                    } catch (\Exception $e) {
                        // Store error marker for failed API calls that rules check for
                        // Some rules check for missing configurations (e.g., S3 PublicAccessBlock)
                        // The condition "r==false" will match when API call fails
                        // We store a special marker that ConditionEvaluator recognizes as false
                        $errorData = ['__error__' => true, 'error_message' => $e->getMessage()];
                        
                        // Store the error so rules can evaluate it
                        // For example, getPublicAccessBlock throws exception if not configured
                        // Rules can check for this condition with "r==false"
                        $this->storeScanDetail($scan, $service, $resourceType, $resourceId, $actionName, $errorData, $resourceId, $region);
                        
                        Log::warning("Action execution failed", [
                            'scan_id' => $scan->id,
                            'service' => $service,
                            'task' => $taskName,
                            'action' => $actionName,
                            'resource_id' => $resourceId,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with next action
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Task execution failed", [
                'scan_id' => $scan->id,
                'service' => $service,
                'task' => $taskName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build the scanner for a service from its registry definition.
     *
     * Almost every service uses GenericAwsScanner; the handful with behaviour the API
     * model cannot express name their own class in tasks.json ("scanner").
     */
    private function getScanner(string $service, Scan $scan): object
    {
        $definition = ServiceRegistry::get($service);

        if (!$definition) {
            throw new \Exception("Unknown service: {$service}");
        }

        $awsAccount = $scan->awsAccount;
        $roleArn = $awsAccount->iam_role_arn;
        $externalId = $awsAccount->external_id;
        $region = $scan->region ?? 'us-east-1';

        $scannerClass = $definition['scanner'] ?? null;

        if ($scannerClass) {
            if (!class_exists($scannerClass) || !is_subclass_of($scannerClass, GenericAwsScanner::class)) {
                throw new \Exception("Service {$service} declares an unusable scanner: {$scannerClass}");
            }

            return new $scannerClass($roleArn, $externalId, $region);
        }

        return new GenericAwsScanner(
            $roleArn,
            $externalId,
            $region,
            $definition['client'],
            $definition['label']
        );
    }

    /**
     * Where a task's items live in its response, as a dot path.
     *
     * Taken from the task's "itemsPath" when it declares one, otherwise from the first
     * key of its "items" block — which is how every single-level task already reads
     * (Users, Roles, Buckets). Nested responses declare the full path explicitly, e.g.
     * "Reservations.Instances".
     */
    private function getItemsPath(array $taskConfig): ?string
    {
        if (!empty($taskConfig['itemsPath']) && is_string($taskConfig['itemsPath'])) {
            return $taskConfig['itemsPath'];
        }

        if (isset($taskConfig['items']) && is_array($taskConfig['items'])) {
            return array_key_first($taskConfig['items']);
        }

        return null;
    }

    /**
     * Identify a resource within its task's item list.
     *
     * Scalar items (SQS queue URLs, DynamoDB table names) are their own identifier.
     * Everything else uses the "key" the task declares. There is deliberately no
     * guessing from well-known field names any more: the old fallback list quietly
     * covered for tasks whose declared key was simply wrong, which meant a genuinely
     * mis-declared key in a new service produced findings against a null resource id
     * instead of an error anybody would notice.
     */
    private function getResourceId(mixed $item, ?string $keyField): ?string
    {
        if (!is_array($item)) {
            return $item === null ? null : (string) $item;
        }

        if ($keyField && isset($item[$keyField]) && is_scalar($item[$keyField])) {
            return (string) $item[$keyField];
        }

        Log::warning('Could not identify resource; check the task\'s "key" in tasks.json', [
            'declared_key' => $keyField,
            'item_keys' => is_array($item) ? array_keys($item) : null,
        ]);

        return null;
    }

    /**
     * Scan details store JSON objects, so a scalar item is wrapped to keep the column
     * shape uniform. Rules matching a task's per-item rows read $data['Value'].
     */
    private function itemRawData(mixed $item): array
    {
        return is_array($item) ? $item : ['Value' => $item];
    }

    /**
     * Build parameters for action API call
     * 
     * @param mixed $item The item from the list operation (an object, or a scalar for
     *                    list operations that return bare strings)
     * @param array $taskConfig The task configuration
     * @param string $actionName The action name (e.g., "getUser", "listMFADevices")
     * @param array|null $actionParamsConfig Params config from the action object (new format) or null
     * @return array Parameters array for the API call
     */
    private function buildActionParams(mixed $item, array $taskConfig, string $actionName, ?array $actionParamsConfig = null): array
    {
        // First, check if params are provided directly in the action config (new format)
        if ($actionParamsConfig !== null && is_array($actionParamsConfig)) {
            Log::debug('Using params from action config', [
                'action' => $actionName,
                'params_config' => $actionParamsConfig,
            ]);
            
            $params = $this->evaluateParams($actionParamsConfig, $item);
            
            Log::debug('Evaluated params from action config', [
                'action' => $actionName,
                'params' => $params,
            ]);
            
            return $params;
        }
        
        Log::debug('No params in action config, checking defaults', [
            'action' => $actionName,
        ]);
        
        // Fall back to default params in task config
        $defaults = $taskConfig['defaults']['actions']['params'] ?? null;
        
        if ($defaults && is_array($defaults)) {
            // New format: defaults is an array of params to evaluate
            Log::debug('Using default params from task config', [
                'action' => $actionName,
                'defaults' => $defaults,
            ]);
            
            $params = $this->evaluateParams($defaults, $item);
            
            Log::debug('Evaluated default params', [
                'action' => $actionName,
                'params' => $params,
            ]);
            
            return $params;
        }

        // No params anywhere. This used to guess from well-known field names
        // (UserName, RoleName, Bucket...), which only ever worked for the handful of
        // services those names came from and silently produced a wrong or empty call for
        // anything else. Actions now declare their params, per action or via the task's
        // defaults, and a missing declaration is reported rather than papered over.
        Log::warning('Action has no params declared, calling it with none', [
            'action' => $actionName,
            'item_keys' => is_array($item) ? array_keys($item) : null,
        ]);

        return [];
    }

    /**
     * Evaluate params configuration to build parameter array
     * 
     * Supports multiple formats:
     * 1. PHP expressions: { "RoleName": "$item['RoleName']" }
     * 2. Simple field access: { "UserName": "UserName" } (extracts from item)
     * 3. Direct values: { "MaxItems": 100 }
     * 
     * Examples:
     * - { "RoleName": "$item['RoleName']" } - PHP expression
     * - { "UserName": "$item['UserName']" } - PHP expression
     * - { "Bucket": "$item['Name']" } - PHP expression using Name field
     * - { "MaxItems": 100 } - Direct value
     * - { "AttributeNames": ["All"] } - Direct list value
     *
     * $item may be a scalar, for list operations returning bare strings; "$item" then
     * refers to the value itself.
     */
    private function evaluateParams(array $paramsConfig, mixed $item): array
    {
        $params = [];
        
        foreach ($paramsConfig as $paramName => $paramValue) {
            if (is_string($paramValue)) {
                // Check if it's a PHP expression (contains $item or PHP array syntax)
                if (strpos($paramValue, '$item') !== false || preg_match('/\$[a-zA-Z_]/', $paramValue)) {
                    // PHP expression - evaluate it
                    try {
                        $value = $this->evaluatePhpExpression($paramValue, $item);
                        
                        Log::debug("Evaluated PHP expression for param", [
                            'param' => $paramName,
                            'expression' => $paramValue,
                            'value' => $value,
                            'value_type' => gettype($value),
                        ]);
                        
                        if ($value === null) {
                            Log::warning("Param expression evaluated to null", [
                                'param' => $paramName,
                                'expression' => $paramValue,
                                'item_keys' => is_array($item) ? array_keys($item) : null,
                            ]);
                        } else {
                            // Ensure we store the actual value, not the expression string
                            $params[$paramName] = $value;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to evaluate param expression", [
                            'param' => $paramName,
                            'expression' => $paramValue,
                            'error' => $e->getMessage(),
                            'item_keys' => is_array($item) ? array_keys($item) : null,
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Try simple field access as fallback
                        $fallbackValue = $this->getFieldValue($paramValue, $item);
                        if ($fallbackValue !== null) {
                            $params[$paramName] = $fallbackValue;
                        }
                    }
                } else {
                    // Simple field name - extract from item
                    $value = $this->getFieldValue($paramValue, $item);
                    if ($value !== null) {
                        $params[$paramName] = $value;
                    } else {
                        Log::warning("Field not found in item", [
                            'param' => $paramName,
                            'field' => $paramValue,
                            'item_keys' => is_array($item) ? array_keys($item) : null,
                        ]);
                    }
                }
            } else {
                // Direct value (number, boolean, etc.)
                $params[$paramName] = $paramValue;
            }
        }
        
        // Ensure we always return an array (never null or string)
        if (!is_array($params)) {
            Log::error("evaluateParams did not return an array", [
                'return_type' => gettype($params),
                'return_value' => $params,
                'params_config' => $paramsConfig,
            ]);
            return [];
        }
        
        // Log final params for debugging
        Log::debug("Final evaluated params array", [
            'params' => $params,
            'params_count' => count($params),
            'is_array' => is_array($params),
        ]);
        
        if (empty($params)) {
            Log::warning("No params evaluated, returning empty array", [
                'params_config' => $paramsConfig,
                'item_keys' => is_array($item) ? array_keys($item) : null,
            ]);
        }
        
        return $params;
    }

    /**
     * Evaluate PHP expression for parameter value
     */
    private function evaluatePhpExpression(string $expression, mixed $item): mixed
    {
        // Evaluate PHP expression with $item available in scope
        // Example: "$item['UserName']" -> evaluates to the actual UserName value
        // Use string concatenation instead of interpolation to avoid quoting issues
        try {
            $result = (function ($expr, $item) {
                extract(['item' => $item], EXTR_SKIP);
                // The expression should already be valid PHP (e.g., "$item['UserName']")
                // Use concatenation to safely build the eval string
                // This ensures the expression is evaluated correctly without interpolation issues
                $evalResult = eval('return ' . $expr . ';');
                
                // Log for debugging
                \Illuminate\Support\Facades\Log::debug("PHP expression eval result", [
                    'expression' => $expr,
                    'result' => $evalResult,
                    'result_type' => gettype($evalResult),
                ]);
                
                return $evalResult;
            })($expression, $item);
            
            return $result;
        } catch (\Throwable $e) {
            Log::error("PHP expression evaluation failed", [
                'expression' => $expression,
                'error' => $e->getMessage(),
                'item_keys' => is_array($item) ? array_keys($item) : null,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get field value from item using dot notation or array access
     */
    private function getFieldValue(string $fieldPath, mixed $item): mixed
    {
        // Handle dot notation: "item.RoleName" -> item['RoleName']
        $fieldPath = str_replace('item.', '', $fieldPath);
        $fieldPath = trim($fieldPath);
        
        // Handle array access notation: "item['RoleName']" or "$item['RoleName']" -> extract field name
        if (preg_match("/\['([^']+)'\]/", $fieldPath, $matches)) {
            $fieldPath = $matches[1];
        }
        
        // Handle simple field name (e.g., "RoleName", "UserName")
        // Check if it exists in the item
        if (isset($item[$fieldPath])) {
            return $item[$fieldPath];
        }
        
        // Try common variations (e.g., "Name" might map to "UserName" or "RoleName" depending on context)
        return null;
    }

    /**
     * Pull a task's items out of its API response by walking the declared dot path,
     * flattening every list encountered along the way.
     *
     * This used to hardcode EC2's Reservations -> Instances shape, so any other nested
     * response needed PHP. The walk handles arbitrary depth, which is what lets services
     * like ELBv2 (LoadBalancers -> Listeners) be added as JSON alone. Items may be
     * objects or scalars; callers must not assume either.
     *
     * @return array<int, mixed>
     */
    private function extractItems(array $result, array $taskConfig): array
    {
        $path = $this->getItemsPath($taskConfig);

        if (!$path) {
            return [];
        }

        $current = [$result];

        foreach (explode('.', $path) as $segment) {
            $next = [];

            foreach ($current as $container) {
                if (!is_array($container) || !array_key_exists($segment, $container)) {
                    continue;
                }

                $value = $container[$segment];

                // A list at this level fans out into the next; a single object is
                // carried through so paths can descend into non-list keys too.
                if (is_array($value) && array_is_list($value)) {
                    foreach ($value as $entry) {
                        $next[] = $entry;
                    }
                    continue;
                }

                $next[] = $value;
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * Store scan detail in database
     */
    private function storeScanDetail(
        Scan $scan,
        string $service,
        ?string $resourceType,
        ?string $resourceId,
        string $apiMethod,
        array $rawData,
        ?string $parentResourceId,
        ?string $region
    ): void {
        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => $service,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'api_method' => $apiMethod,
            'raw_data' => $rawData,
            'parent_resource_id' => $parentResourceId,
            'region' => $region,
        ]);
    }
}
