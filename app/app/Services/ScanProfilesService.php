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
 *
 * Membership is declared the other way round from how it reads here: each service's
 * tasks.json lists the profiles it belongs to, and ServiceRegistry inverts that. A new
 * service therefore joins a profile without this file changing. What stays here is what
 * genuinely belongs to the profile rather than the service — its label, description, and
 * the ruleset(s) it evaluates.
 */
class ScanProfilesService
{
    /**
     * @var array<string, array{label: string, description: string, rulesets: string[]}>
     */
    private static array $profiles = [
        'basic' => [
            'label' => 'Basic',
            'description' => 'Core security checks across all supported services',
            'rulesets' => ['basic'],
        ],
        'cis' => [
            'label' => 'CIS',
            'description' => 'CIS AWS Foundations Benchmark',
            'rulesets' => ['cis'],
        ],
        'pci' => [
            'label' => 'PCI',
            'description' => 'PCI DSS compliance checks',
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
            $ruleCounts = self::ruleCountsByService($value);

            // A service can belong to a profile (via its tasks.json) while the profile's
            // ruleset has no rules for it — scanning it would collect data that nothing
            // evaluates. Those services are not offered for selection.
            $services = array_values(array_filter(
                ServiceRegistry::namesForProfile($value),
                fn (string $service) => ($ruleCounts[$service] ?? 0) > 0
            ));

            $result[] = [
                'value' => $value,
                'label' => self::$profiles[$value]['label'],
                'description' => self::$profiles[$value]['description'],
                'services' => $services,
                // Per-service rule counts let the modal say what a selection will actually
                // run before the user commits to the time and the API calls.
                'serviceRuleCounts' => array_intersect_key($ruleCounts, array_flip($services)),
                'ruleCount' => array_sum(array_intersect_key($ruleCounts, array_flip($services))),
            ];
        }

        return $result;
    }

    /**
     * Services a profile can meaningfully scan — those its rulesets actually have rules for.
     *
     * @return string[]
     */
    private static function selectableServicesFor(string $profileValue): array
    {
        if (!isset(self::$profiles[$profileValue])) {
            return [];
        }

        $ruleCounts = self::ruleCountsByService($profileValue);

        return array_values(array_filter(
            ServiceRegistry::namesForProfile($profileValue),
            fn (string $service) => ($ruleCounts[$service] ?? 0) > 0
        ));
    }

    /**
     * Union of the services the given profiles can meaningfully scan.
     *
     * @param string[] $profileValues
     * @return string[]
     */
    public static function selectableServicesForAll(array $profileValues): array
    {
        $services = [];
        foreach ($profileValues as $value) {
            foreach (self::selectableServicesFor($value) as $service) {
                $services[] = $service;
            }
        }

        return array_values(array_unique($services));
    }

    /**
     * How many rules a profile's rulesets hold, per service.
     *
     * @return array<string, int>
     */
    private static function ruleCountsByService(string $profileValue): array
    {
        $counts = [];
        foreach (self::$profiles[$profileValue]['rulesets'] ?? [] as $ruleset) {
            foreach (self::rulesOf($ruleset) as $rule) {
                $service = $rule['service'] ?? null;
                if ($service === null) {
                    continue;
                }
                $counts[$service] = ($counts[$service] ?? 0) + 1;
            }
        }

        return $counts;
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
        $services = [];
        foreach ($profileValues as $value) {
            foreach (ServiceRegistry::namesForProfile($value) as $service) {
                $services[] = $service;
            }
        }

        return array_values(array_unique($services));
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
        return self::rulesOf($ruleset) !== [];
    }

    /**
     * A ruleset's rules, or an empty array if the file is missing or malformed.
     *
     * Deliberately not memoized. The files are small, the callers are a single
     * profile-listing endpoint and a validation rule, and a static cache here would
     * serve stale rules to any test that writes a ruleset file — which is exactly why
     * ServiceRegistry has to expose flush().
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rulesOf(string $ruleset): array
    {
        $path = base_path("rules/rulesets/{$ruleset}.json");
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return $decoded['rules'] ?? [];
    }
}
