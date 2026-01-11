<?php

namespace App\Services\RulesEngine;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Services\Scanners\IamScanner;
use App\Services\Scanners\S3Scanner;
use App\Services\Scanners\Ec2Scanner;
use App\Services\Scanners\RdsScanner;
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
            if ($service === 'ec2' && $region) {
                $result = $scanner->executeApiCall($taskName, $credentials, $params, $region);
            } else {
                $result = $scanner->executeApiCall($taskName, $credentials, $params);
            }
            
            // Store the main task result
            $this->storeScanDetail($scan, $service, null, null, $taskName, $result, null, $region);
            
            // Get items from the result (e.g., Users, Roles, Buckets, Reservations)
            $itemsKey = $this->getItemsKey($taskConfig);
            $items = $result[$itemsKey] ?? [];
            
            // Handle nested structures (e.g., EC2 Reservations -> Instances)
            $items = $this->extractItems($items, $taskConfig, $service);
            
            // Execute actions for each item
            $actions = $taskConfig['actions'] ?? [];
            $idKey = $taskConfig['id'] ?? null;
            $keyField = $taskConfig['key'] ?? null;
            
            foreach ($items as $item) {
                $resourceId = $this->getResourceId($item, $keyField, $idKey);
                $resourceType = $idKey ?? 'resource';
                
                // Store the item itself
                $this->storeScanDetail($scan, $service, $resourceType, $resourceId, $taskName, $item, null, $region);
                
                // Execute actions for this item
                foreach ($actions as $actionName) {
                    try {
                        $actionParams = $this->buildActionParams($item, $taskConfig, $actionName);
                        
                        if ($service === 'ec2' && $region) {
                            $actionResult = $scanner->executeApiCall($actionName, $credentials, $actionParams, $region);
                        } else {
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
     * Get scanner instance for a service
     */
    private function getScanner(string $service, Scan $scan): object
    {
        $awsAccount = $scan->awsAccount;
        $roleArn = $awsAccount->iam_role_arn;
        $externalId = $awsAccount->external_id;
        $region = $scan->region ?? 'us-east-1';
        
        return match ($service) {
            'iam' => new IamScanner($roleArn, $externalId, $region),
            's3' => new S3Scanner($roleArn, $externalId, $region),
            'ec2' => new Ec2Scanner($roleArn, $externalId, $region),
            'rds' => new RdsScanner($roleArn, $externalId, $region),
            default => throw new \Exception("Unknown service: {$service}"),
        };
    }

    /**
     * Get items key from task config (e.g., "Users", "Roles", "Buckets")
     */
    private function getItemsKey(array $taskConfig): ?string
    {
        if (isset($taskConfig['items'])) {
            $items = array_keys($taskConfig['items']);
            return $items[0] ?? null;
        }
        return null;
    }

    /**
     * Get resource ID from item
     */
    private function getResourceId(array $item, ?string $keyField, ?string $idKey): ?string
    {
        if ($keyField && isset($item[$keyField])) {
            return $item[$keyField];
        }
        
        // Try common ID fields
        $commonFields = ['UserName', 'RoleName', 'BucketName', 'InstanceId', 'DBInstanceIdentifier'];
        foreach ($commonFields as $field) {
            if (isset($item[$field])) {
                return $item[$field];
            }
        }
        
        return null;
    }

    /**
     * Build parameters for action API call
     */
    private function buildActionParams(array $item, array $taskConfig, string $actionName): array
    {
        // Check for default params function in config
        $defaults = $taskConfig['defaults']['actions']['params'] ?? null;
        
        if ($defaults) {
            // For MVP, use simple mapping
            // In production, you might want to evaluate the function
            // For now, use common patterns
            if (isset($item['UserName'])) {
                return ['UserName' => $item['UserName']];
            }
            if (isset($item['RoleName'])) {
                return ['RoleName' => $item['RoleName']];
            }
            if (isset($item['BucketName'])) {
                return ['Bucket' => $item['BucketName']];
            }
            if (isset($item['Name'])) {
                // Try to determine the parameter name based on action
                if (strpos($actionName, 'User') !== false) {
                    return ['UserName' => $item['Name']];
                }
                if (strpos($actionName, 'Role') !== false) {
                    return ['RoleName' => $item['Name']];
                }
                if (strpos($actionName, 'Bucket') !== false || strpos($actionName, 'S3') !== false) {
                    return ['Bucket' => $item['Name']];
                }
            }
        }
        
        // Default: return item as-is (may need adjustment per service)
        return $item;
    }

    /**
     * Extract items from nested structures (e.g., EC2 Reservations -> Instances)
     */
    private function extractItems(array $items, array $taskConfig, string $service): array
    {
        // For EC2, handle Reservations -> Instances structure
        if ($service === 'ec2' && isset($taskConfig['items']['Reservations'])) {
            $extracted = [];
            foreach ($items as $reservation) {
                if (isset($reservation['Instances']) && is_array($reservation['Instances'])) {
                    foreach ($reservation['Instances'] as $instance) {
                        $extracted[] = $instance;
                    }
                }
            }
            return $extracted;
        }
        
        // For other services, return items as-is
        return $items;
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
