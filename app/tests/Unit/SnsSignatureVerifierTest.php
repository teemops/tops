<?php

namespace Tests\Unit;

use App\Services\SnsSignatureVerifier;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SnsSignatureVerifierTest extends TestCase
{
    private SnsSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new SnsSignatureVerifier();
    }

    /**
     * Build a request carrying the SNS message-type header and an optional body.
     */
    private function snsRequest(?string $messageType, array $body = []): Request
    {
        $request = Request::create('/api/sns/callback', 'POST', $body);

        if ($messageType !== null) {
            $request->headers->set('x-amz-sns-message-type', $messageType);
        }

        return $request;
    }

    /**
     * A request with no SNS message-type header is not from SNS.
     */
    public function test_rejects_a_request_without_the_message_type_header(): void
    {
        $this->assertFalse($this->verifier->verify($this->snsRequest(null)));
    }

    /**
     * Subscription confirmations are accepted so the topic can be wired up.
     */
    public function test_accepts_a_subscription_confirmation(): void
    {
        $this->assertTrue($this->verifier->verify($this->snsRequest('SubscriptionConfirmation')));
    }

    /**
     * A notification with no Message payload is rejected.
     */
    public function test_rejects_a_notification_without_a_message(): void
    {
        $this->assertFalse($this->verifier->verify($this->snsRequest('Notification')));
    }

    /**
     * A Message that is not valid JSON is rejected.
     */
    public function test_rejects_a_notification_with_unparsable_json(): void
    {
        $request = $this->snsRequest('Notification', ['Message' => 'not-json-at-all']);

        $this->assertFalse($this->verifier->verify($request));
    }

    /**
     * Every Tops field must be present for the message to be trusted.
     */
    #[DataProvider('missingFieldProvider')]
    public function test_rejects_a_notification_missing_a_required_field(string $missingField): void
    {
        $message = [
            'TopsRoleArn' => 'arn:aws:iam::123456789012:role/Tops',
            'TopsExternalId' => 'external-id',
            'TopsUniqueId' => 'unique-id',
        ];
        unset($message[$missingField]);

        $request = $this->snsRequest('Notification', ['Message' => json_encode($message)]);

        $this->assertFalse($this->verifier->verify($request));
    }

    public static function missingFieldProvider(): array
    {
        return [
            'missing role arn' => ['TopsRoleArn'],
            'missing external id' => ['TopsExternalId'],
            'missing unique id' => ['TopsUniqueId'],
        ];
    }

    /**
     * A well-formed notification carrying all Tops fields is accepted.
     */
    public function test_accepts_a_well_formed_notification(): void
    {
        $request = $this->snsRequest('Notification', [
            'Message' => json_encode([
                'TopsRoleArn' => 'arn:aws:iam::123456789012:role/Tops',
                'TopsExternalId' => 'external-id',
                'TopsUniqueId' => 'unique-id',
            ]),
        ]);

        $this->assertTrue($this->verifier->verify($request));
    }

    /**
     * The AWS-SDK entry point currently delegates to the basic verification.
     */
    public function test_verify_with_aws_sdk_matches_the_basic_verification(): void
    {
        $valid = $this->snsRequest('Notification', [
            'Message' => json_encode([
                'TopsRoleArn' => 'arn:aws:iam::123456789012:role/Tops',
                'TopsExternalId' => 'external-id',
                'TopsUniqueId' => 'unique-id',
            ]),
        ]);

        $this->assertTrue($this->verifier->verifyWithAwsSdk($valid));
        $this->assertFalse($this->verifier->verifyWithAwsSdk($this->snsRequest(null)));
    }
}
