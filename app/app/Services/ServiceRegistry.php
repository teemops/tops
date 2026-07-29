<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * The registry of scannable services, discovered from rules/tasks/<service>/tasks.json.
 *
 * Each tasks.json owns everything the app needs to know about its service — the SDK
 * client to build, whether it is regional, which scan profiles include it — so adding a
 * service is a single new JSON file rather than an edit to half a dozen PHP arrays.
 *
 * ScanTypesService and ScanProfilesService are thin readers over this; nothing else
 * should need to know where the definitions live.
 */
class ServiceRegistry
{
    /**
     * Discovered service definitions keyed by service name, or null before first load.
     *
     * @var array<string, array{service: string, label: string, client: string, regional: bool, profiles: string[], scanner: ?string, arnService: string}>|null
     */
    private static ?array $services = null;

    /**
     * All service definitions, keyed by service name and ordered by it.
     *
     * @return array<string, array{service: string, label: string, client: string, regional: bool, profiles: string[], scanner: ?string, arnService: string}>
     */
    public static function all(): array
    {
        if (self::$services === null) {
            self::$services = self::discover();
        }

        return self::$services;
    }

    /**
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $service): bool
    {
        return isset(self::all()[$service]);
    }

    /**
     * @return array{service: string, label: string, client: string, regional: bool, profiles: string[], scanner: ?string, arnService: string}|null
     */
    public static function get(string $service): ?array
    {
        return self::all()[$service] ?? null;
    }

    /**
     * Services whose definition lists the given profile.
     *
     * @return string[]
     */
    public static function namesForProfile(string $profile): array
    {
        return array_keys(array_filter(
            self::all(),
            fn (array $definition) => in_array($profile, $definition['profiles'], true)
        ));
    }

    /**
     * Drop the cached definitions. Tests that write fixture tasks.json files need this;
     * nothing in the request path should.
     */
    public static function flush(): void
    {
        self::$services = null;
    }

    /**
     * Read every rules/tasks/<service>/tasks.json config block.
     *
     * A malformed or unreadable file is logged and skipped rather than thrown: one bad
     * contribution should not take down the scan-types endpoint for every other service.
     * `scan:validate-rules` is where a broken file is meant to fail loudly, in CI.
     *
     * @return array<string, array{service: string, label: string, client: string, regional: bool, profiles: string[], scanner: ?string, arnService: string}>
     */
    private static function discover(): array
    {
        $services = [];

        foreach (File::glob(base_path('rules/tasks/*/tasks.json')) as $path) {
            $directory = basename(dirname($path));

            $decoded = json_decode(File::get($path), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                Log::error('Skipping unreadable tasks.json', [
                    'path' => $path,
                    'error' => json_last_error_msg(),
                ]);
                continue;
            }

            $config = $decoded['config'] ?? null;

            if (!is_array($config)) {
                Log::error('Skipping tasks.json with no config block', ['path' => $path]);
                continue;
            }

            // The directory name is the service identity used everywhere else (scan_types,
            // scan_details.service, rule "service" fields). A config.service that disagrees
            // with it would silently collect data nothing can match a rule against.
            $service = $config['service'] ?? $directory;

            if ($service !== $directory) {
                Log::error('Skipping tasks.json whose config.service does not match its directory', [
                    'path' => $path,
                    'config_service' => $service,
                    'directory' => $directory,
                ]);
                continue;
            }

            $services[$service] = [
                'service' => $service,
                'label' => $config['label'] ?? strtoupper($service),
                // Defaults to the service name: true for every AWS service whose SDK
                // manifest key matches (s3, iam, ec2, ...), explicit where it does not
                // (elasticloadbalancingv2).
                'client' => $config['client'] ?? $service,
                // Most AWS services are regional, so that is the safer default: treating a
                // regional service as global would scan one region and silently report the
                // rest as clean.
                'regional' => (bool) ($config['regional'] ?? true),
                'profiles' => array_values((array) ($config['profiles'] ?? ['basic'])),
                // The service portion of this service's ARNs, used to match against the
                // Resource Groups Tagging API when pruning empty regions. Usually the
                // service name, but not always — ELBv2 resources are arn:aws:
                // elasticloadbalancing:...
                'arnService' => $config['arnService'] ?? $service,
                // Escape hatch for services needing behaviour the generic scanner cannot
                // express (S3's bucket-region lookup, IAM's NoSuchEntity translation).
                'scanner' => $config['scanner'] ?? null,
            ];
        }

        ksort($services);

        return $services;
    }
}
