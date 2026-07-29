<?php

namespace App\Services;

/**
 * The services a scan can collect data for.
 *
 * This used to hold a hardcoded array; the definitions now live in each service's
 * rules/tasks/<service>/tasks.json and are read through ServiceRegistry. The public
 * surface is unchanged so callers (ScansController, ProcessAuditScanJob, Scan,
 * RulesEngine) did not have to move with it.
 */
class ScanTypesService
{
    /**
     * Get all available scan types
     *
     * @return array Array of scan type codes
     */
    public static function getAll(): array
    {
        return ServiceRegistry::names();
    }

    /**
     * Get scan types with their display names
     *
     * @return array Array of scan types with 'value', 'label', and 'region' keys
     */
    public static function getAllWithLabels(): array
    {
        return array_values(array_map(
            fn (array $definition) => [
                'value' => $definition['service'],
                'label' => $definition['label'],
                'region' => $definition['regional'],
            ],
            ServiceRegistry::all()
        ));
    }

    /**
     * Check if a scan type is valid
     *
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return ServiceRegistry::has($type);
    }

    /**
     * Get validation rule string for Laravel validation
     *
     * @return string
     */
    public static function getValidationRule(): string
    {
        return 'in:' . implode(',', self::getAll());
    }

    /**
     * Get region-based scan types
     *
     * @return array Array of scan type codes
     */
    public static function getRegionBased(): array
    {
        return array_keys(array_filter(
            ServiceRegistry::all(),
            fn (array $definition) => $definition['regional']
        ));
    }

    /**
     * Get non-region-based scan types
     *
     * @return array Array of scan type codes
     */
    public static function getNonRegionBased(): array
    {
        return array_keys(array_filter(
            ServiceRegistry::all(),
            fn (array $definition) => !$definition['regional']
        ));
    }

    /**
     * Check if a scan type is region-based
     *
     * @param string $type
     * @return bool
     */
    public static function isRegionBased(string $type): bool
    {
        return (bool) (ServiceRegistry::get($type)['regional'] ?? false);
    }
}
