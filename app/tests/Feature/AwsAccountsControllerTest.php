<?php

namespace Tests\Feature;

use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AwsAccountsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Configure AWS messaging as if ./install.sh had been run.
     */
    private function configureMessaging(string $region = 'us-west-2'): void
    {
        Config::set('services.aws.parent_account_id', '123456789012');
        Config::set('services.aws.cloudformation_template_url', "https://test-123456789012-tops-deploy.s3.{$region}.amazonaws.com/templates/iam.role.child.account.cfn.yaml");
        Config::set('services.aws.deployment_region', $region);
    }

    public function test_init_returns_503_when_messaging_not_configured(): void
    {
        Config::set('services.aws.parent_account_id', null);
        Config::set('services.aws.cloudformation_template_url', null);
        Config::set('services.aws.deployment_region', null);

        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts/init");

        $response->assertStatus(503)
            ->assertJsonStructure(['error']);

        $this->assertDatabaseCount('aws_accounts', 0);
    }

    public function test_init_returns_cloudformation_url_with_pinned_region_when_configured(): void
    {
        $this->configureMessaging('us-west-2');

        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts/init");

        $response->assertStatus(200)
            ->assertJsonStructure(['accountId', 'uniqueId', 'externalId', 'cloudFormationUrl', 'deploymentRegion', 'status']);

        $account = AwsAccount::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('pending', $account->status);
        // UniqueId is derived from the organization's org_id.
        $this->assertSame($organization->org_id, $account->unique_id);

        $url = $response->json('cloudFormationUrl');
        $this->assertStringContainsString('region=us-west-2', $url);
        $this->assertStringContainsString('param_ParentDeploymentRegion=us-west-2', $url);
        $this->assertStringContainsString('param_ParentAWSAccountId=123456789012', $url);
        $this->assertStringContainsString('param_ExternalId=' . urlencode($account->external_id), $url);
        $this->assertStringContainsString('param_UniqueId=' . urlencode($account->unique_id), $url);
    }

    public function test_init_reuses_existing_pending_account_instead_of_creating_duplicates(): void
    {
        $this->configureMessaging();

        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $first = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts/init")
            ->assertStatus(200);

        $second = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts/init")
            ->assertStatus(200);

        // Same pending record is returned, and only one row exists.
        $this->assertSame($first->json('accountId'), $second->json('accountId'));
        $this->assertSame($first->json('externalId'), $second->json('externalId'));
        $this->assertSame(1, AwsAccount::where('organization_id', $organization->id)->count());
    }

    public function test_init_forbidden_for_member_without_add_permission(): void
    {
        $this->configureMessaging();

        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $owner->id]);

        $viewer = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);

        $response = $this->actingAs($viewer)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts/init");

        $response->assertStatus(403);
        $this->assertDatabaseCount('aws_accounts', 0);
    }
}
