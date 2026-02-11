<?php

namespace App\Services;

class ScanTypesService
{
    /**
     * The available scan types with their display names.
     *
     * @var array
     */
    private static array $scanTypes = [
        ['value' => 'ec2', 'label' => 'EC2', 'region' => true],
        ['value' => 'iam', 'label' => 'IAM', 'region' => false],
        ['value' => 's3', 'label' => 'S3', 'region' => false],  // S3 bucket list is global; can run from any region
        ['value' => 'rds', 'label' => 'RDS', 'region' => true],
    ];

    /**
     * Get all available scan types
     *
     * @return array Array of scan type codes
     */
    public static function getAll(): array
    {
        return array_map(fn($type) => $type['value'], self::$scanTypes);
    }

    /**
     * Get scan types with their display names
     *
     * @return array Array of scan types with 'value', 'label', and 'region' keys
     */
    public static function getAllWithLabels(): array
    {
        return self::$scanTypes;
    }

    /**
     * Check if a scan type is valid
     *
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::getAll());
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
        return array_map(
            fn($type) => $type['value'],
            array_filter(self::$scanTypes, fn($type) => $type['region'])
        );
    }

    /**
     * Get non-region-based scan types
     *
     * @return array Array of scan type codes
     */
    public static function getNonRegionBased(): array
    {
        return array_map(
            fn($type) => $type['value'],
            array_filter(self::$scanTypes, fn($type) => !$type['region'])
        );
    }

    /**
     * Check if a scan type is region-based
     *
     * @param string $type
     * @return bool
     */
    public static function isRegionBased(string $type): bool
    {
        return in_array($type, self::getRegionBased());
    }
}
