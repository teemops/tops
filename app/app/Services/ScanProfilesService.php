<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Scan profiles (a.k.a. "groups") bundle two things a scan needs:
 *   - which AWS services to collect data for (maps to Scan::scan_types)
 *   - which finding ruleset(s) to evaluate that data against (Scan::rulesets)
 *
 * The modal lets users pick profiles instead of individual services. A profile is
 * only "available" (selectable/shown) once at least one of its rulesets actually
 * has rules, so empty compliance rulesets (cis/pci) stay hidden until authored.
 */
class ScanProfilesService
{
    /**
     * @var array<string, array{label: string, description: string, services: string[], rulesets: string[]}>
     */
    private static array $profiles = [
        'basic' => [
            'label' => 'Basic',
            'description' => 'Core security checks across all supported services',
            'services' => ['s3', 'iam', 'ec2', 'rds', 'cloudtrail', 'lambda', 'kms'],
            'rulesets' => ['basic'],
        ],
        'cis' => [
            'label' => 'CIS',
            'description' => 'CIS AWS Foundations Benchmark',
            'services' => ['s3', 'iam', 'ec2', 'rds', 'cloudtrail', 'lambda', 'kms'],
            'rulesets' => ['cis'],
        ],
        'pci' => [
            'label' => 'PCI',
            'description' => 'PCI DSS compliance checks',
            'services' => ['s3', 'iam', 'ec2', 'rds', 'cloudtrail', 'lambda', 'kms'],
            'rulesets' => ['pci'],
        ],
    ];

    /**
     * A profile is available when at least one of its rulesets contains rules.
     */
    public static function isAvailable(string $value): bool
    {
        $profile = self::$profiles[$value] ?? null;
        if (!$profile) {
            return false;
        }

        foreach ($profile['rulesets'] as $ruleset) {
            if (self::rulesetHasRules($ruleset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Available profile values (used for validation).
     *
     * @return string[]
     */
    public static function getAvailable(): array
    {
        return array_values(array_filter(
            array_keys(self::$profiles),
            fn ($value) => self::isAvailable($value)
        ));
    }

    /**
     * Available profiles with display metadata (for the API / modal).
     *
     * @return array<int, array{value: string, label: string, description: string, services: string[]}>
     */
    public static function getAvailableWithLabels(): array
    {
        $result = [];
        foreach (self::getAvailable() as $value) {
            $result[] = [
                'value' => $value,
                'label' => self::$profiles[$value]['label'],
                'description' => self::$profiles[$value]['description'],
                'services' => self::$profiles[$value]['services'],
            ];
        }

        return $result;
    }

    /**
     * Display metadata keyed by ruleset rather than profile, for consumers that
     * work in ruleset terms (compliance scoring reads Scan::rulesets, not profiles).
     * Where several profiles share a ruleset the first one's labels win.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function rulesetLabels(): array
    {
        $labels = [];
        foreach (self::$profiles as $profile) {
            foreach ($profile['rulesets'] as $ruleset) {
                $labels[$ruleset] ??= [
                    'label' => $profile['label'],
                    'description' => $profile['description'],
                ];
            }
        }

        return $labels;
    }

    /**
     * Laravel validation rule restricting input to available profiles.
     */
    public static function getValidationRule(): string
    {
        return 'in:' . implode(',', self::getAvailable());
    }

    /**
     * Union of services collected by the given profiles (deduped).
     *
     * @param string[] $profileValues
     * @return string[]
     */
    public static function servicesFor(array $profileValues): array
    {
        return self::unionOf($profileValues, 'services');
    }

    /**
     * Union of rulesets evaluated by the given profiles (deduped).
     *
     * @param string[] $profileValues
     * @return string[]
     */
    public static function rulesetsFor(array $profileValues): array
    {
        return self::unionOf($profileValues, 'rulesets');
    }

    /**
     * @param string[] $profileValues
     * @return string[]
     */
    private static function unionOf(array $profileValues, string $key): array
    {
        $values = [];
        foreach ($profileValues as $value) {
            foreach (self::$profiles[$value][$key] ?? [] as $item) {
                $values[] = $item;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * Count-based availability check: does the ruleset JSON declare any rules?
     */
    private static function rulesetHasRules(string $ruleset): bool
    {
        $path = base_path("rules/rulesets/{$ruleset}.json");
        if (!File::exists($path)) {
            return false;
        }

        $decoded = json_decode(File::get($path), true);

        return !empty($decoded['rules'] ?? []);
    }
}
