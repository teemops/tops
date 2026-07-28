<?php

namespace Tests\Unit;

use App\Http\Requests\InitAwsAccountRequest;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\StoreAwsAccountRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\TransferOwnershipRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises the rules() of each FormRequest directly. Authorization is handled by
 * middleware/controllers for all of these, so it is asserted rather than mocked.
 */
class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run a request's rules against the given payload.
     */
    private function validate(string $requestClass, array $payload): \Illuminate\Contracts\Validation\Validator
    {
        /** @var FormRequest $request */
        $request = new $requestClass();

        return Validator::make($payload, $request->rules());
    }

    private function assertPasses(string $requestClass, array $payload): void
    {
        $validator = $this->validate($requestClass, $payload);

        $this->assertTrue(
            $validator->passes(),
            $requestClass.' should accept payload, got: '.json_encode($validator->errors()->toArray())
        );
    }

    private function assertFailsOn(string $requestClass, array $payload, string $field): void
    {
        $validator = $this->validate($requestClass, $payload);

        $this->assertTrue($validator->fails(), $requestClass.' should reject payload');
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * All of these requests defer authorization to middleware or the controller.
     */
    #[DataProvider('authorizingRequestProvider')]
    public function test_authorization_is_delegated(string $requestClass): void
    {
        $this->assertTrue((new $requestClass())->authorize());
    }

    public static function authorizingRequestProvider(): array
    {
        return [
            [InitAwsAccountRequest::class],
            [InviteMemberRequest::class],
            [StoreAwsAccountRequest::class],
            [StoreOrganizationRequest::class],
            [TransferOwnershipRequest::class],
            [UpdateMemberRoleRequest::class],
            [UpdateOrganizationRequest::class],
        ];
    }

    public function test_init_aws_account_has_nothing_to_validate(): void
    {
        $this->assertSame([], (new InitAwsAccountRequest())->rules());
    }

    public function test_store_aws_account_accepts_a_well_formed_payload(): void
    {
        $this->assertPasses(StoreAwsAccountRequest::class, [
            'name' => 'Production',
            'aws_account_id' => '123456789012',
            'iam_role_arn' => 'arn:aws:iam::123456789012:role/TeemOps',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidAwsAccountProvider')]
    public function test_store_aws_account_rejects_bad_input(array $overrides, string $field): void
    {
        $this->assertFailsOn(StoreAwsAccountRequest::class, array_merge([
            'name' => 'Production',
            'aws_account_id' => '123456789012',
            'iam_role_arn' => 'arn:aws:iam::123456789012:role/TeemOps',
        ], $overrides), $field);
    }

    public static function invalidAwsAccountProvider(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'name too short' => [['name' => 'P'], 'name'],
            'account id too short' => [['aws_account_id' => '12345'], 'aws_account_id'],
            'account id too long' => [['aws_account_id' => '1234567890123'], 'aws_account_id'],
            'account id not numeric' => [['aws_account_id' => '12345678901a'], 'aws_account_id'],
            'missing role arn' => [['iam_role_arn' => null], 'iam_role_arn'],
            'role arn wrong service' => [['iam_role_arn' => 'arn:aws:s3:::my-bucket'], 'iam_role_arn'],
            'role arn without role name' => [['iam_role_arn' => 'arn:aws:iam::123456789012:role/'], 'iam_role_arn'],
        ];
    }

    public function test_store_aws_account_explains_the_format_requirements(): void
    {
        $messages = (new StoreAwsAccountRequest())->messages();

        $this->assertStringContainsString('12 digits', $messages['aws_account_id.regex']);
        $this->assertStringContainsString('arn:aws:iam', $messages['iam_role_arn.regex']);
    }

    /**
     * Store and update share the same name rules.
     *
     * @param  class-string  $requestClass
     */
    #[DataProvider('organizationRequestProvider')]
    public function test_organization_name_rules(string $requestClass): void
    {
        $this->assertPasses($requestClass, ['name' => 'Acme Corp']);
        $this->assertFailsOn($requestClass, ['name' => ''], 'name');
        $this->assertFailsOn($requestClass, ['name' => 'A'], 'name');
        $this->assertFailsOn($requestClass, ['name' => str_repeat('a', 256)], 'name');
    }

    public static function organizationRequestProvider(): array
    {
        return [
            'store' => [StoreOrganizationRequest::class],
            'update' => [UpdateOrganizationRequest::class],
        ];
    }

    public function test_invite_member_requires_a_valid_email(): void
    {
        $this->assertPasses(InviteMemberRequest::class, ['email' => 'new@example.com']);
        $this->assertFailsOn(InviteMemberRequest::class, [], 'email');
        $this->assertFailsOn(InviteMemberRequest::class, ['email' => 'not-an-email'], 'email');
        $this->assertFailsOn(
            InviteMemberRequest::class,
            ['email' => str_repeat('a', 250).'@example.com'],
            'email'
        );
    }

    #[DataProvider('validRoleProvider')]
    public function test_update_member_role_accepts_the_known_roles(string $role): void
    {
        $this->assertPasses(UpdateMemberRoleRequest::class, ['role' => $role]);
    }

    public static function validRoleProvider(): array
    {
        return [['owner'], ['administrator'], ['auditor'], ['viewer']];
    }

    public function test_update_member_role_rejects_an_unknown_role(): void
    {
        $this->assertFailsOn(UpdateMemberRoleRequest::class, ['role' => 'superuser'], 'role');
        $this->assertFailsOn(UpdateMemberRoleRequest::class, [], 'role');
    }

    public function test_transfer_ownership_requires_an_existing_user(): void
    {
        $user = User::factory()->create();

        $this->assertPasses(TransferOwnershipRequest::class, ['user_id' => $user->id]);
        $this->assertFailsOn(TransferOwnershipRequest::class, ['user_id' => $user->id + 9999], 'user_id');
        $this->assertFailsOn(TransferOwnershipRequest::class, ['user_id' => 'not-an-id'], 'user_id');
        $this->assertFailsOn(TransferOwnershipRequest::class, [], 'user_id');
    }
}
