<?php

namespace App\Services;

use App\Services\Scanners\GenericAwsScanner;
use Illuminate\Support\Facades\Log;

/**
 * Which AWS services actually hold resources in a given region, via the Resource Groups
 * Tagging API.
 *
 * Fanning a scan out across every service in every enabled region is most of the cost of
 * a large scan, and most of it is wasted: a typical account runs workloads in two or
 * three regions. One getResources call per region tells us where to bother.
 *
 * tag:GetResources needs no customer setup and no template change — the role the
 * onboarding CloudFormation creates already carries ReadOnlyAccess and
 * ResourceGroupsandTagEditorReadOnlyAccess — so this works against every account already
 * onboarded.
 *
 * IMPORTANT — why absence is not proof of absence:
 * GetResources reports "tagged or previously tagged" resources only. A resource that has
 * never carried a tag is not listed, so a service missing from the index may simply have
 * untagged resources. Skipping it would report that region as clean when it was never
 * looked at, which for a compliance product is the worst way to be wrong. Pruning is
 * therefore opt-in (config scan.prune_regions_with_tagging, default off) and only ever
 * removes work when this index answered with something, never when it came back empty or
 * failed.
 */
class RegionResourceIndex
{
    /**
     * ARN service prefixes present in a region, or null when the region could not be
     * indexed and no pruning decision may be made from it.
     *
     * @return string[]|null
     */
    public function servicePrefixesIn(string $roleArn, string $externalId, array $credentials, string $region): ?array
    {
        try {
            $scanner = new GenericAwsScanner($roleArn, $externalId, $region, 'resourcegroupstaggingapi', 'Tagging');

            // GenericAwsScanner walks every page, so this is the whole region.
            $result = $scanner->executeApiCall('getResources', $credentials, [], $region);
        } catch (\Throwable $e) {
            Log::warning('Could not index region resources; will not prune this region', [
                'region' => $region,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $mappings = $result['ResourceTagMappingList'] ?? [];

        // An empty answer is the signature of an account that does not tag, which is
        // indistinguishable from a genuinely empty region. Refuse to decide.
        if (empty($mappings)) {
            Log::info('Region reported no tagged resources; will not prune this region', [
                'region' => $region,
            ]);

            return null;
        }

        $prefixes = [];

        foreach ($mappings as $mapping) {
            $prefix = $this->servicePrefixOf($mapping['ResourceARN'] ?? '');

            if ($prefix !== null) {
                $prefixes[$prefix] = true;
            }
        }

        return array_keys($prefixes);
    }

    /**
     * The service portion of an ARN: arn:partition:service:region:account:resource.
     */
    private function servicePrefixOf(string $arn): ?string
    {
        $parts = explode(':', $arn);

        return ($parts[0] ?? '') === 'arn' && !empty($parts[2]) ? $parts[2] : null;
    }
}
