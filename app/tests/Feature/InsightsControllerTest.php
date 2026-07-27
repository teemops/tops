<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_aggregated_insights_for_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);
        $scan = Scan::factory()->completed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
        ]);

        ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'severity' => 'critical',
            'service' => 's3',
            'status' => 'open',
            'finding_type' => 's3-public-bucket',
        ]);

        ScanResult::factory()->create([
            'scan_id' => $scan->id,
            'severity' => 'high',
            'service' => 'iam',
            'status' => 'resolved',
            'resolved_at' => now(),
            'finding_type' => 'iam-privilege-escalation',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights?period=30d");

        $response->assertOk()
            ->assertJsonPath('summary.totalFindings', 2)
            ->assertJsonPath('summary.openFindings', 1)
            ->assertJsonPath('summary.criticalOpen', 1)
            ->assertJsonPath('summary.remediationRate', 50)
            ->assertJsonStructure([
                'period',
                'summary',
                'severityDistribution',
                'trend',
                'topServices',
                'keyInsights',
            ]);
    }

    public function test_returns_403_when_user_cannot_view_insights_for_organization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/insights");

        $response->assertStatus(403);
    }
}
