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

    /**
     * Attach a user to a fresh organization in the given role.
     *
     * @return array{0: User, 1: Organization}
     */
    private function memberOf(string $role): array
    {
        $user = User::factory()->create();

        if ($role === 'owner') {
            return [$user, Organization::factory()->create(['user_id' => $user->id])];
        }

        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return [$user, $organization];
    }

    // ---------------------------------------------------------------- listing

    public function test_listing_accounts_requires_authentication(): void
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/organizations/{$organization->org_id}/aws-accounts")
            ->assertStatus(401);
    }

    public function test_an_owner_can_list_their_organizations_accounts(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'name' => 'Production',
        ]);
        AwsAccount::factory()->create(); // another organization's

        $response = $this->actingAs($user)
            ->getJson("/api/organizations/{$organization->org_id}/aws-accounts");

        $response->assertOk();
        $this->assertCount(1, $response->json('accounts'));
        $this->assertEquals($account->id, $response->json('accounts.0.id'));
        $this->assertEquals('Production', $response->json('accounts.0.name'));
    }

    /**
     * Viewers are deliberately not shown AWS account details.
     */
    public function test_a_viewer_cannot_list_accounts(): void
    {
        [$viewer, $organization] = $this->memberOf('viewer');

        $this->actingAs($viewer)
            ->getJson("/api/organizations/{$organization->org_id}/aws-accounts")
            ->assertStatus(403);
    }

    public function test_an_auditor_can_list_accounts(): void
    {
        [$auditor, $organization] = $this->memberOf('auditor');

        $this->actingAs($auditor)
            ->getJson("/api/organizations/{$organization->org_id}/aws-accounts")
            ->assertOk();
    }

    // ------------------------------------------------------------------ show

    public function test_an_account_can_be_shown(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->getJson("/api/aws-accounts/{$account->id}")
            ->assertOk()
            ->assertJson(['id' => $account->id, 'name' => $account->name]);
    }

    public function test_an_account_from_another_organization_is_not_found(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $foreign = AwsAccount::factory()->create();

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->getJson("/api/aws-accounts/{$foreign->id}")
            ->assertStatus(404);
    }

    // ----------------------------------------------------------------- store

    public function test_an_owner_can_add_an_account_manually(): void
    {
        [$user, $organization] = $this->memberOf('owner');

        $response = $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts", [
                'name' => 'Production',
                'aws_account_id' => '123456789012',
                'iam_role_arn' => 'arn:aws:iam::123456789012:role/TeemOps',
            ]);

        $response->assertStatus(201)->assertJson([
            'name' => 'Production',
            'awsAccountId' => '123456789012',
            'status' => 'completed',
        ]);

        $account = AwsAccount::findOrFail($response->json('id'));
        $this->assertEquals($organization->org_id, $account->unique_id);
        $this->assertEquals('arn:aws:iam::123456789012:role/TeemOps', $account->iam_role_arn);
    }

    public function test_adding_an_account_validates_the_arn(): void
    {
        [$user, $organization] = $this->memberOf('owner');

        $this->actingAs($user)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts", [
                'name' => 'Production',
                'aws_account_id' => '123456789012',
                'iam_role_arn' => 'not-an-arn',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('iam_role_arn');
    }

    public function test_an_auditor_cannot_add_an_account(): void
    {
        [$auditor, $organization] = $this->memberOf('auditor');

        $this->actingAs($auditor)
            ->postJson("/api/organizations/{$organization->org_id}/aws-accounts", [
                'name' => 'Production',
                'aws_account_id' => '123456789012',
                'iam_role_arn' => 'arn:aws:iam::123456789012:role/TeemOps',
            ])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------- update

    public function test_an_owner_can_rename_an_account(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->putJson("/api/aws-accounts/{$account->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJson(['name' => 'Renamed']);

        $this->assertEquals('Renamed', $account->fresh()->name);
    }

    public function test_renaming_validates_the_name(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->putJson("/api/aws-accounts/{$account->id}", ['name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_an_auditor_cannot_rename_an_account(): void
    {
        [$auditor, $organization] = $this->memberOf('auditor');
        $account = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($auditor)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->putJson("/api/aws-accounts/{$account->id}", ['name' => 'Renamed'])
            ->assertStatus(403);
    }

    // --------------------------------------------------------------- destroy

    public function test_an_owner_can_delete_an_account(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->deleteJson("/api/aws-accounts/{$account->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('aws_accounts', ['id' => $account->id]);
    }

    /**
     * The UI asks for the AWS account id as a confirmation step; a mismatch must
     * not delete anything.
     */
    public function test_deleting_with_a_mismatched_confirmation_id_is_rejected(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => '123456789012',
        ]);

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->deleteJson("/api/aws-accounts/{$account->id}", ['aws_account_id' => '999999999999'])
            ->assertStatus(422)
            ->assertJson(['error' => 'AWS account ID does not match.']);

        $this->assertDatabaseHas('aws_accounts', ['id' => $account->id, 'deleted_at' => null]);
    }

    public function test_deleting_with_a_matching_confirmation_id_succeeds(): void
    {
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'aws_account_id' => '123456789012',
        ]);

        $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->deleteJson("/api/aws-accounts/{$account->id}", ['aws_account_id' => '123456789012'])
            ->assertOk();

        $this->assertSoftDeleted('aws_accounts', ['id' => $account->id]);
    }

    public function test_an_auditor_cannot_delete_an_account(): void
    {
        [$auditor, $organization] = $this->memberOf('auditor');
        $account = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($auditor)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->deleteJson("/api/aws-accounts/{$account->id}")
            ->assertStatus(403);
    }

    // ------------------------------------------------------ cloudformation url

    public function test_it_returns_the_cloudformation_console_url(): void
    {
        $this->configureMessaging('eu-west-1');
        [$user, $organization] = $this->memberOf('owner');
        $account = AwsAccount::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($user)
            ->withHeader('X-Organization-Id', $organization->org_id)
            ->getJson("/api/aws-accounts/{$account->id}/cloudformation-url");

        $response->assertOk()->assertJson(['stackName' => 'tops-vendor-audit']);
        $this->assertStringContainsString('region=eu-west-1', $response->json('cloudFormationUrl'));
    }

    // ---------------------------------------------------------- sns callback

    /**
     * Build a CloudFormation-style SNS notification for the given account.
     */
    private function snsPayload(AwsAccount $account, array $overrides = []): array
    {
        return array_merge([
            'TopsRoleArn' => 'arn:aws:iam::123456789012:role/TeemOps',
            'TopsExternalId' => $account->external_id,
            'TopsUniqueId' => $account->unique_id,
        ], $overrides);
    }

    private function postSnsCallback(array $message): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('x-amz-sns-message-type', 'Notification')
            ->postJson('/api/aws-accounts/sns-callback', ['Message' => json_encode($message)]);
    }

    public function test_the_sns_callback_activates_a_pending_account(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
            'name' => 'Pending AWS Account',
            'unique_id' => $organization->org_id,
        ]);

        $this->postSnsCallback($this->snsPayload($account))
            ->assertOk()
            ->assertJson(['success' => true, 'account_id' => $account->id]);

        $account->refresh();
        $this->assertEquals('completed', $account->status);
        $this->assertEquals('123456789012', $account->aws_account_id);
        $this->assertEquals('AWS Account 123456789012', $account->name);
        $this->assertEquals('arn:aws:iam::123456789012:role/TeemOps', $account->iam_role_arn);
    }

    /**
     * A name the user already chose is not clobbered by the callback.
     */
    public function test_the_sns_callback_keeps_a_user_chosen_name(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
            'name' => 'Production',
            'unique_id' => $organization->org_id,
        ]);

        $this->postSnsCallback($this->snsPayload($account))->assertOk();

        $this->assertEquals('Production', $account->fresh()->name);
    }

    public function test_the_sns_callback_rejects_a_request_without_the_sns_header(): void
    {
        $account = AwsAccount::factory()->pending()->create();

        $this->postJson('/api/aws-accounts/sns-callback', [
            'Message' => json_encode($this->snsPayload($account)),
        ])->assertStatus(401);

        $this->assertEquals('pending', $account->fresh()->status);
    }

    public function test_the_sns_callback_rejects_a_message_missing_required_fields(): void
    {
        $account = AwsAccount::factory()->pending()->create();

        $this->postSnsCallback($this->snsPayload($account, ['TopsRoleArn' => null]))
            ->assertStatus(401);
    }

    public function test_the_sns_callback_404s_for_an_unknown_account(): void
    {
        $this->postSnsCallback([
            'TopsRoleArn' => 'arn:aws:iam::123456789012:role/TeemOps',
            'TopsExternalId' => 'no-such-external-id',
            'TopsUniqueId' => 'no-such-unique-id',
        ])->assertStatus(404);
    }

    /**
     * The external id must match too — the unique id alone is shared across every
     * account in an organization.
     */
    public function test_the_sns_callback_requires_a_matching_external_id(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
        ]);

        $this->postSnsCallback($this->snsPayload($account, ['TopsExternalId' => 'wrong-external-id']))
            ->assertStatus(404);

        $this->assertEquals('pending', $account->fresh()->status);
    }

    public function test_the_sns_callback_accepts_a_subscription_confirmation(): void
    {
        $this->withHeader('x-amz-sns-message-type', 'SubscriptionConfirmation')
            ->postJson('/api/aws-accounts/sns-callback', [
                'Type' => 'SubscriptionConfirmation',
                'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription',
            ])
            ->assertOk()
            ->assertJson(['status' => 'subscription_confirmed']);
    }
}
