<?php

namespace App\Console\Commands;

use App\Services\Scanners\GenericAwsScanner;
use App\Services\ServiceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Checks every tasks.json and ruleset JSON without touching AWS.
 *
 * Both file types fail quietly when they are wrong: a tasks.json naming an operation
 * that does not exist only breaks mid-scan, and a rule naming a service or method
 * nothing collects simply never matches anything — it produces no findings and no error,
 * which looks exactly like a compliant account. Run this in CI.
 */
class ValidateScanRules extends Command
{
    protected $signature = 'scan:validate-rules';

    protected $description = 'Validate scan task definitions and finding rulesets';

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var array<string, string[]> Service => the profiles whose scans collect it. */
    private array $profiles = [];

    public function handle(): int
    {
        ServiceRegistry::flush();

        $collected = $this->validateTasks();
        $knownRuleIds = $this->validateRulesets($collected);
        $this->validateRecommendations($knownRuleIds);

        foreach ($this->warnings as $warning) {
            $this->warn('WARN  ' . $warning);
        }

        foreach ($this->errors as $error) {
            $this->error('ERROR ' . $error);
        }

        if ($this->errors) {
            $this->newLine();
            $this->error(sprintf('%d problem(s) found.', count($this->errors)));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK: %d services, %d collected methods, %d rules across %d rulesets, %d recommendations.',
            count(ServiceRegistry::all()),
            count($collected),
            $this->ruleCount,
            $this->rulesetCount,
            $this->recommendationCount
        ));

        return self::SUCCESS;
    }

    private int $ruleCount = 0;

    private int $rulesetCount = 0;

    private int $recommendationCount = 0;

    /**
     * Validate every tasks.json, returning the set of "service:method" pairs a scan
     * actually collects — which is what rules must target to ever fire.
     *
     * @return array<string, true>
     */
    private function validateTasks(): array
    {
        $collected = [];

        foreach (File::glob(base_path('rules/tasks/*/tasks.json')) as $path) {
            $directory = basename(dirname($path));
            $decoded = json_decode(File::get($path), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[] = "{$directory}/tasks.json is not valid JSON: " . json_last_error_msg();
                continue;
            }

            $config = $decoded['config'] ?? [];
            $service = $config['service'] ?? null;

            if ($service !== $directory) {
                $this->errors[] = "{$directory}/tasks.json declares config.service '{$service}'; it must match the directory name.";
                continue;
            }

            if (!ServiceRegistry::has($service)) {
                $this->errors[] = "{$service} was not picked up by the registry; check its config block.";
                continue;
            }

            $this->profiles[$service] = (array) ($config['profiles'] ?? []);
            $clientKey = ServiceRegistry::get($service)['client'];
            $methods = $this->methodsIn($decoded);

            if (empty($methods)) {
                $this->errors[] = "{$service} declares no tasks.";
                continue;
            }

            $start = $config['start'] ?? null;
            if (!$start) {
                $this->errors[] = "{$service} declares no config.start.";
            } elseif (!isset($methods[$start])) {
                $this->errors[] = "{$service} names '{$start}' as its start task, but no such task is defined.";
            }

            foreach (array_keys($methods) as $method) {
                if (!GenericAwsScanner::supportsOperation($clientKey, $method)) {
                    $this->errors[] = "{$service}: the AWS SDK has no '{$method}' operation on '{$clientKey}'.";
                    continue;
                }

                $collected["{$service}:{$method}"] = true;
            }

            foreach ($this->tasksIn($decoded) as $taskName => $taskConfig) {
                $this->validateTaskItems($service, $taskName, $taskConfig);
            }
        }

        return $collected;
    }

    /**
     * Every method a service will call: the tasks themselves plus their per-item actions.
     *
     * @return array<string, true>
     */
    private function methodsIn(array $decoded): array
    {
        $methods = [];

        foreach ($this->tasksIn($decoded) as $taskName => $taskConfig) {
            $methods[$taskName] = true;

            foreach ($taskConfig['actions'] ?? [] as $action) {
                if (is_string($action)) {
                    $methods[$action] = true;
                } elseif (is_array($action) && ($name = array_key_first($action)) !== null) {
                    $methods[$name] = true;
                }
            }
        }

        return $methods;
    }

    /**
     * @return array<string, array>
     */
    private function tasksIn(array $decoded): array
    {
        $tasks = [];

        foreach ($decoded['tasks'] ?? [] as $group) {
            foreach ($group as $name => $config) {
                if (!empty($config)) {
                    $tasks[$name] = $config;
                }
            }
        }

        return $tasks;
    }

    /**
     * A task with actions but no way to reach its items collects the list response and
     * nothing else — the failure is invisible without this check.
     */
    private function validateTaskItems(string $service, string $taskName, array $taskConfig): void
    {
        $hasPath = !empty($taskConfig['itemsPath']) || !empty($taskConfig['items']);

        if (!empty($taskConfig['actions']) && !$hasPath) {
            $this->errors[] = "{$service}:{$taskName} declares actions but no 'items' or 'itemsPath' to find items in.";
        }

        // Object items need a declared key to be identified; scalar items are their own
        // id, and there is no way to tell which a task returns without calling AWS, so
        // this is a warning rather than an error.
        if ($hasPath && empty($taskConfig['key']) && !empty($taskConfig['actions'])) {
            $this->warnings[] = "{$service}:{$taskName} declares no 'key'; findings will have no resource id unless its items are bare strings.";
        }
    }

    /**
     * @param array<string, true> $collected
     * @return array<string, string> Rule id => the ruleset file that defines it.
     */
    private function validateRulesets(array $collected): array
    {
        $seenIds = [];
        $fullRuleFiles = $this->fullRules();

        foreach (File::glob(base_path('rules/rulesets/*.json')) as $path) {
            $name = basename($path, '.json');
            $this->rulesetCount++;

            $decoded = json_decode(File::get($path), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[] = "{$name}.json is not valid JSON: " . json_last_error_msg();
                continue;
            }

            $this->validateControlCatalogue($name, $decoded['controls'] ?? []);
            $seenRefs = [];

            foreach ($decoded['rules'] ?? [] as $index => $rule) {
                $this->ruleCount++;

                if (is_array($rule) && array_key_exists('ref', $rule)) {
                    $this->validateReference($name, $index, $rule, $decoded['controls'] ?? [], $fullRuleFiles, $seenRefs);

                    continue;
                }

                $id = is_array($rule) ? ($rule['rule'] ?? null) : null;
                $where = $id ? "{$name}.json:{$id}" : "{$name}.json rule #{$index}";

                if (!$id) {
                    $this->errors[] = "{$where} has no 'rule' id.";
                    continue;
                }

                if (isset($seenIds[$id])) {
                    $this->errors[] = "{$where} reuses the rule id already defined in {$seenIds[$id]}. Ids identify findings, so they must be unique across rulesets.";
                }
                $seenIds[$id] = "{$name}.json";

                foreach (['service', 'method', 'condition'] as $required) {
                    if (empty($rule[$required])) {
                        $this->errors[] = "{$where} has no '{$required}'.";
                    }
                }

                if (empty($rule['service']) || empty($rule['method'])) {
                    continue;
                }

                // The quiet failure this command exists for: a rule targeting data no
                // tasks.json collects never matches, and reads as a passing check.
                if (!isset($collected["{$rule['service']}:{$rule['method']}"])) {
                    $this->errors[] = "{$where} targets {$rule['service']}:{$rule['method']}, which no tasks.json collects. It can never produce a finding.";
                }

                if (!empty($rule['condition'])) {
                    $this->validateCondition($where, $rule['condition']);
                }

                // An error, not a warning, since 2026-07-30 (roadmap N-4). A finding
                // with no remediation is homework rather than something the operator
                // can act on, and 39 rules had drifted into that state — including
                // every CIS rule — precisely because this only warned.
                if (empty(trim($rule['remediation'] ?? ''))) {
                    $this->errors[] = "{$where} has no 'remediation'. Every rule must tell the operator how to fix what it finds.";
                }
            }
        }

        return $seenIds;
    }

    /**
     * Rule id => the full rule (not a reference) with the ruleset files holding it.
     *
     * @return array<string, array{files: string[], service: mixed}>
     */
    private function fullRules(): array
    {
        $files = [];

        foreach (File::glob(base_path('rules/rulesets/*.json')) as $path) {
            $decoded = json_decode(File::get($path), true);

            foreach ($decoded['rules'] ?? [] as $rule) {
                if (is_array($rule) && isset($rule['rule']) && is_string($rule['rule'])) {
                    $files[$rule['rule']]['files'][] = basename($path, '.json');
                    $files[$rule['rule']]['service'] ??= $rule['service'] ?? null;
                }
            }
        }

        return $files;
    }

    /**
     * A reference must name a full rule in another ruleset, carry nothing of its own
     * beyond its controls, and list only controls its file's catalogue defines.
     *
     * @param array<string, array{files: string[], service: mixed}> $fullRules
     * @param string[] $seenRefs Refs already seen in this file.
     */
    private function validateReference(string $name, int|string $index, array $ref, array $catalogue, array $fullRules, array &$seenRefs): void
    {
        $id = $ref['ref'];
        $where = "{$name}.json ref " . (is_string($id) ? "'{$id}'" : "#{$index}");

        if (!is_string($id)) {
            $this->errors[] = "{$where} names a rule id that no other ruleset defines as a full rule.";
        } else {
            if (isset($seenRefs[$id])) {
                $this->errors[] = "{$where} appears more than once in this ruleset.";
            }
            $seenRefs[$id] = true;

            if (array_diff($fullRules[$id]['files'] ?? [], [$name]) === []) {
                $this->errors[] = "{$where} names a rule id that no other ruleset defines as a full rule.";
            } else {
                // A scan only collects a service for the profiles listed in its tasks.json;
                // otherwise the referenced rule never has data and silently never fires.
                $service = $fullRules[$id]['service'];
                if (is_string($service) && isset($this->profiles[$service]) && !in_array($name, $this->profiles[$service], true)) {
                    $this->errors[] = "{$where} targets service '{$service}', whose tasks.json config.profiles does not list '{$name}'. A {$name} scan never collects it, so the rule can never fire.";
                }
            }
        }

        foreach (['rule', 'condition', 'service', 'method'] as $own) {
            if (array_key_exists($own, $ref)) {
                $this->errors[] = "{$where} carries its own '{$own}'. A reference may only add controls; the check lives in the ruleset it points at.";
            }
        }

        if (!is_array($ref['controls'] ?? null) || $ref['controls'] === []) {
            $this->errors[] = "{$where} has no controls; a reference must list at least one control CID.";
        }

        foreach (is_array($ref['controls'] ?? null) ? $ref['controls'] : [] as $cid) {
            if (!isset($catalogue[(string) $cid])) {
                $this->errors[] = "{$where} lists control CID {$cid}, which is not in this file's controls catalogue.";
            }
        }
    }

    /**
     * Every catalogue entry needs a paragraph shaped N.N.N.C.NN and a level the manual uses.
     */
    private function validateControlCatalogue(string $name, array $catalogue): void
    {
        foreach ($catalogue as $cid => $control) {
            $paragraph = $control['paragraph'] ?? null;
            $level = $control['level'] ?? null;

            if (!is_string($paragraph) || !preg_match('/^\d+\.\d+\.\d+\.C\.\d{2}$/', $paragraph)) {
                $this->errors[] = "{$name}.json control CID {$cid} has a bad paragraph; expected N.N.N.C.NN.";
            }

            if (!in_array($level, ['MUST', 'MUST NOT', 'SHOULD', 'SHOULD NOT'], true)) {
                $this->errors[] = "{$name}.json control CID {$cid} has a bad level; expected MUST, MUST NOT, SHOULD or SHOULD NOT.";
            }
        }
    }

    /**
     * Validate rules/recommendations/tips.json, the richer guidance layer.
     *
     * These fail the same way rules do — silently. A recommendation pointing at a
     * rule id that does not exist is never shown to anyone, which is
     * indistinguishable from guidance nobody needed. tips.json referenced
     * tops-route53-001 for months, and nothing noticed because nothing checked.
     *
     * @param array<string, string> $knownRuleIds
     */
    private function validateRecommendations(array $knownRuleIds): void
    {
        $path = base_path('rules/recommendations/tips.json');

        if (!File::exists($path)) {
            $this->warnings[] = 'rules/recommendations/tips.json is missing; findings will fall back to the one-line remediation.';

            return;
        }

        $decoded = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'tips.json is not valid JSON: ' . json_last_error_msg();

            return;
        }

        $seenNames = [];

        foreach ($decoded['recommendations'] ?? [] as $index => $rec) {
            $name = $rec['name'] ?? null;
            $where = $name ? "tips.json:{$name}" : "tips.json recommendation #{$index}";
            $this->recommendationCount++;

            if (!$name) {
                $this->errors[] = "{$where} has no 'name'.";
                continue;
            }

            if (isset($seenNames[$name])) {
                $this->errors[] = "{$where} reuses a recommendation name.";
            }
            $seenNames[$name] = true;

            foreach (['recommendation', 'description'] as $required) {
                if (empty(trim((string) ($rec[$required] ?? '')))) {
                    $this->errors[] = "{$where} has no '{$required}'.";
                }
            }

            $rules = $rec['rules'] ?? [];

            if ($rules === []) {
                $this->errors[] = "{$where} references no rules, so it can never be shown.";
                continue;
            }

            foreach ($rules as $ruleId) {
                if (!isset($knownRuleIds[$ruleId])) {
                    $this->errors[] = "{$where} references rule '{$ruleId}', which no ruleset defines. The guidance can never be shown.";
                }
            }
        }
    }

    /**
     * Check the condition parses as a PHP expression, without running it.
     */
    private function validateCondition(string $where, string $condition): void
    {
        $code = '<?php return ' . $condition . ';';

        // A parse error here surfaces as a caught ParseError rather than a fatal.
        try {
            $tokens = @token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $this->errors[] = "{$where} has a condition that is not valid PHP: " . $e->getMessage();

            return;
        }

        if (empty($tokens)) {
            $this->errors[] = "{$where} has an empty condition.";
        }
    }
}
