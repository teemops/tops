<?php

namespace Tests\Unit;

use App\Services\SnsSignatureVerifier;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Roadmap N-6.
 *
 * These sign payloads with a locally generated key pair and hand the validator a
 * cert-fetching callable returning the matching certificate, so real signature
 * verification runs — the crypto is not mocked. A test that stubbed `validate()`
 * would pass just as happily against the stub this replaces.
 *
 * The previous version of this file tested that stub, including a case asserting
 * every SubscriptionConfirmation is accepted without checking its signature.
 */
class SnsSignatureVerifierTest extends TestCase
{
    private const TOPIC = 'arn:aws:sns:us-west-2:123456789012:teemops-sns';
    private const CERT_URL = 'https://sns.us-west-2.amazonaws.com/SimpleNotificationService-abc123.pem';

    private string $privateKey;
    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.aws.sns_arn', self::TOPIC);

        [$this->privateKey, $this->certificate] = $this->generateKeyPair();
    }

    /**
     * A self-signed certificate and its private key, standing in for the one AWS
     * publishes at SigningCertURL.
     */
    private function generateKeyPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new(['commonName' => 'sns.us-west-2.amazonaws.com'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem);

        return [$keyPem, $certPem];
    }

    /**
     * The canonical string SNS signs: selected fields in a fixed order, each as
     * "key\nvalue\n". Mirrors MessageValidator::getStringToSign.
     */
    private function stringToSign(array $payload): string
    {
        $signable = $payload['Type'] === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

        $parts = '';
        foreach ($signable as $key) {
            if (isset($payload[$key])) {
                $parts .= "{$key}\n{$payload[$key]}\n";
            }
        }

        return $parts;
    }

    private function payload(array $overrides = [], string $type = 'Notification'): array
    {
        $payload = array_merge([
            'Type' => $type,
            'MessageId' => 'f0e4c2f7-1234-4a3b-9c2d-abcdef123456',
            'TopicArn' => self::TOPIC,
            'Timestamp' => '2026-07-30T12:00:00.000Z',
            'SignatureVersion' => '1',
            'SigningCertURL' => self::CERT_URL,
            'Message' => json_encode([
                'TopsRoleArn' => 'arn:aws:iam::123456789012:role/TeemOps',
                'TopsExternalId' => 'a1b2c3d4-0000-4000-8000-000000000000',
                'TopsUniqueId' => 'org-unique-id',
            ]),
        ], $overrides);

        if ($type !== 'Notification') {
            $payload += [
                'Token' => 'confirmation-token',
                'SubscribeURL' => 'https://sns.us-west-2.amazonaws.com/?Action=ConfirmSubscription',
            ];
        }

        return $payload;
    }

    /**
     * Signs the payload unless a Signature was supplied explicitly.
     */
    private function signed(array $payload): array
    {
        if (isset($payload['Signature'])) {
            return $payload;
        }

        openssl_sign($this->stringToSign($payload), $signature, $this->privateKey, OPENSSL_ALGO_SHA1);
        $payload['Signature'] = base64_encode($signature);

        return $payload;
    }

    /**
     * SNS sends text/plain, so that is the default here — it is the case that
     * previously left Laravel's input bag empty.
     */
    private function request(array $payload, string $contentType = 'text/plain'): Request
    {
        return Request::create(
            '/api/aws-accounts/sns-callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => $contentType],
            json_encode($payload)
        );
    }

    private function verifier(?callable $certClient = null): SnsSignatureVerifier
    {
        return new SnsSignatureVerifier(new MessageValidator(
            $certClient ?? fn () => $this->certificate
        ));
    }

    public function test_accepts_a_correctly_signed_notification_from_our_topic(): void
    {
        $this->assertTrue(
            $this->verifier()->verify($this->request($this->signed($this->payload())))
        );
    }

    public function test_accepts_a_correctly_signed_subscription_confirmation(): void
    {
        $payload = $this->signed($this->payload([], 'SubscriptionConfirmation'));

        $this->assertTrue($this->verifier()->verify($this->request($payload)));
    }

    /**
     * The stub auto-accepted every SubscriptionConfirmation without looking at the
     * signature, which is how a stranger's topic got itself subscribed.
     */
    public function test_rejects_a_subscription_confirmation_with_a_bad_signature(): void
    {
        $payload = $this->payload(
            ['Signature' => base64_encode('not-a-signature')],
            'SubscriptionConfirmation'
        );

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    public function test_rejects_a_notification_with_an_invalid_signature(): void
    {
        $payload = $this->payload(['Signature' => base64_encode('not-a-signature')]);

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    /**
     * Signed correctly over the original body, then a field changed afterwards.
     */
    public function test_rejects_a_notification_whose_body_was_tampered_with(): void
    {
        $payload = $this->signed($this->payload());
        $payload['Message'] = json_encode([
            'TopsRoleArn' => 'arn:aws:iam::999999999999:role/AttackerRole',
            'TopsExternalId' => 'a1b2c3d4-0000-4000-8000-000000000000',
            'TopsUniqueId' => 'org-unique-id',
        ]);

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    /**
     * A valid AWS signature proves AWS sent it, not that we asked for it — anyone
     * can create a topic and have AWS sign messages for it. This is the check the
     * signature alone does not give you.
     */
    public function test_rejects_a_validly_signed_message_from_someone_elses_topic(): void
    {
        $payload = $this->signed($this->payload([
            'TopicArn' => 'arn:aws:sns:us-west-2:999999999999:attacker-topic',
        ]));

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    /**
     * Fail closed: an unconfigured deployment has no legitimate SNS traffic, so
     * "we cannot tell whose topic this is" must not resolve to "allow".
     */
    public function test_rejects_everything_when_no_topic_is_configured(): void
    {
        Config::set('services.aws.sns_arn', null);

        $this->assertFalse(
            $this->verifier()->verify($this->request($this->signed($this->payload())))
        );
    }

    /**
     * The classic bypass: point SigningCertURL at storage you control that still
     * lives on an amazonaws.com host.
     */
    public function test_rejects_a_signing_cert_url_that_is_not_an_sns_endpoint(): void
    {
        $payload = $this->signed($this->payload([
            'SigningCertURL' => 'https://attacker-bucket.s3.amazonaws.com/cert.pem',
        ]));

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    public function test_rejects_a_signing_cert_url_served_over_http(): void
    {
        $payload = $this->signed($this->payload([
            'SigningCertURL' => 'http://sns.us-west-2.amazonaws.com/cert.pem',
        ]));

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    public function test_rejects_a_message_signed_by_a_different_key(): void
    {
        [$otherKey] = $this->generateKeyPair();

        $payload = $this->payload();
        openssl_sign($this->stringToSign($payload), $signature, $otherKey, OPENSSL_ALGO_SHA1);
        $payload['Signature'] = base64_encode($signature);

        $this->assertFalse($this->verifier()->verify($this->request($payload)));
    }

    public function test_rejects_a_message_when_the_certificate_cannot_be_fetched(): void
    {
        $payload = $this->signed($this->payload());

        $this->assertFalse(
            $this->verifier(fn () => false)->verify($this->request($payload))
        );
    }

    public function test_rejects_an_envelope_missing_required_keys(): void
    {
        $this->assertFalse($this->verifier()->verify($this->request([
            'Type' => 'Notification',
            'Message' => '{}',
        ])));
    }

    public function test_rejects_an_empty_body(): void
    {
        $this->assertFalse($this->verifier()->verify($this->request([])));
    }

    public function test_rejects_a_body_that_is_not_json(): void
    {
        $request = Request::create(
            '/api/aws-accounts/sns-callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            'this is not json'
        );

        $this->assertFalse($this->verifier()->verify($request));
    }

    /**
     * Clients that do set a JSON content type must still work — decoding the raw
     * body is about tolerating SNS's text/plain, not requiring it.
     */
    public function test_accepts_a_valid_message_sent_as_application_json(): void
    {
        $payload = $this->signed($this->payload());

        $this->assertTrue(
            $this->verifier()->verify($this->request($payload, 'application/json'))
        );
    }
}
