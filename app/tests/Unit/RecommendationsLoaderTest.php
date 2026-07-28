<?php

namespace Tests\Unit;

use App\Services\RecommendationsLoader;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecommendationsLoaderTest extends TestCase
{
    private RecommendationsLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loader = new RecommendationsLoader();
    }

    /**
     * A missing file yields an empty recommendation set rather than an error.
     */
    public function test_load_returns_an_empty_set_when_the_file_is_missing(): void
    {
        File::shouldReceive('exists')->once()->andReturn(false);

        $this->assertEquals(['recommendations' => []], $this->loader->load('does-not-exist'));
    }

    /**
     * Malformed JSON is treated as no recommendations, not a crash.
     */
    public function test_load_returns_an_empty_set_for_invalid_json(): void
    {
        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn('{not valid json');

        $this->assertEquals(['recommendations' => []], $this->loader->load());
    }

    /**
     * A valid file is decoded and returned as-is.
     */
    public function test_load_decodes_the_recommendations_file(): void
    {
        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn(json_encode([
            'recommendations' => [
                ['name' => 'tops-rec-001', 'rules' => ['tops-s3-001']],
            ],
        ]));

        $data = $this->loader->load();

        $this->assertCount(1, $data['recommendations']);
        $this->assertEquals('tops-rec-001', $data['recommendations'][0]['name']);
    }

    /**
     * getByRuleId finds the recommendation whose rules list contains the id.
     */
    public function test_get_by_rule_id_finds_the_matching_recommendation(): void
    {
        $this->fakeTips();

        $recommendation = $this->loader->getByRuleId('tops-ec2-002');

        $this->assertNotNull($recommendation);
        $this->assertEquals('tops-rec-002', $recommendation['name']);
    }

    /**
     * An unknown rule id yields null.
     */
    public function test_get_by_rule_id_returns_null_for_an_unknown_rule(): void
    {
        $this->fakeTips();

        $this->assertNull($this->loader->getByRuleId('tops-does-not-exist'));
    }

    /**
     * getMapByRuleId flattens every rule id onto its recommendation.
     */
    public function test_get_map_by_rule_id_indexes_every_rule(): void
    {
        $this->fakeTips();

        $map = $this->loader->getMapByRuleId();

        $this->assertEqualsCanonicalizing(
            ['tops-s3-001', 'tops-s3-002', 'tops-ec2-002'],
            array_keys($map)
        );
        $this->assertEquals('tops-rec-001', $map['tops-s3-001']['name']);
        $this->assertEquals('tops-rec-001', $map['tops-s3-002']['name']);
        $this->assertEquals('tops-rec-002', $map['tops-ec2-002']['name']);
    }

    /**
     * Recommendations with no rules list are skipped without error.
     */
    public function test_get_map_by_rule_id_skips_recommendations_without_rules(): void
    {
        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn(json_encode([
            'recommendations' => [
                ['name' => 'tops-rec-orphan'],
            ],
        ]));

        $this->assertEquals([], $this->loader->getMapByRuleId());
    }

    /**
     * The recommendations file that actually ships with the app parses and maps
     * onto real rule ids.
     */
    public function test_the_shipped_tips_file_loads(): void
    {
        $map = $this->loader->getMapByRuleId();

        $this->assertNotEmpty($map);
        $this->assertArrayHasKey('tops-s3-001', $map);
    }

    private function fakeTips(): void
    {
        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn(json_encode([
            'recommendations' => [
                ['name' => 'tops-rec-001', 'rules' => ['tops-s3-001', 'tops-s3-002']],
                ['name' => 'tops-rec-002', 'rules' => ['tops-ec2-002']],
            ],
        ]));
    }
}
