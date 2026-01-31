<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class RecommendationsLoader
{
    /**
     * Load recommendations from tips.json (or named file).
     */
    public function load(string $name = 'tips'): array
    {
        $path = base_path("rules/recommendations/{$name}.json");

        if (!File::exists($path)) {
            return ['recommendations' => []];
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['recommendations' => []];
        }

        return $data;
    }

    /**
     * Get recommendation that contains the given rule ID (finding_type).
     */
    public function getByRuleId(string $ruleId, string $name = 'tips'): ?array
    {
        $data = $this->load($name);
        $recommendations = $data['recommendations'] ?? [];

        foreach ($recommendations as $rec) {
            $rules = $rec['rules'] ?? [];
            if (in_array($ruleId, $rules, true)) {
                return $rec;
            }
        }

        return null;
    }

    /**
     * Get all recommendations for grouping (rule ID => recommendation).
     */
    public function getMapByRuleId(string $name = 'tips'): array
    {
        $data = $this->load($name);
        $recommendations = $data['recommendations'] ?? [];
        $map = [];

        foreach ($recommendations as $rec) {
            foreach ($rec['rules'] ?? [] as $ruleId) {
                $map[$ruleId] = $rec;
            }
        }

        return $map;
    }
}
