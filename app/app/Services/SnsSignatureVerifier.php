<?php

namespace App\Services;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that an inbound SNS message really came from AWS, and from our topic.
 *
 * Roadmap N-6. This replaces a stub that checked three JSON field names and
 * returned true, on a route that is unauthenticated by design and that activates
 * an AWS account with a caller-supplied IAM role ARN.
 *
 * Two independent checks, and both are needed:
 *
 *  1. **Cryptographic signature**, via AWS's own aws/aws-php-sns-message-validator.
 *     Proves AWS signed the message. The signing certificate URL is constrained
 *     by the package to `sns.<region>.amazonaws.com`, which closes the classic
 *     bypass of pointing SigningCertURL at attacker-controlled storage that
 *     merely lives on an amazonaws.com host.
 *
 *  2. **Topic allowlist.** A valid signature only proves *AWS* sent it — anyone
 *     can create their own SNS topic and have AWS sign messages for it. Without
 *     comparing TopicArn against our own topic, step 1 alone would accept a
 *     validly-signed message from a stranger's topic. This is the check that is
 *     usually missed.
 */
class SnsSignatureVerifier
{
    private MessageValidator $validator;

    /**
     * @param MessageValidator|null $validator Injectable so tests can supply a
     *                                         cert-fetching callable instead of
     *                                         reaching out to AWS.
     */
    public function __construct(?MessageValidator $validator = null)
    {
        $this->validator = $validator ?? new MessageValidator();
    }

    public function verify(Request $request): bool
    {
        $payload = $this->payload($request);

        if ($payload === []) {
            return $this->reject('body was empty or not JSON');
        }

        try {
            $message = new Message($payload);
        } catch (\InvalidArgumentException $e) {
            // Missing or malformed required keys — Type, MessageId, TopicArn,
            // Timestamp, Signature, SigningCertURL, Message, and Token/SubscribeURL
            // on the confirmation types.
            return $this->reject('message was not a well-formed SNS envelope', [
                'reason' => $e->getMessage(),
            ]);
        }

        try {
            $this->validator->validate($message);
        } catch (\Throwable $e) {
            // Covers InvalidSnsMessageException — bad signature, unreachable or
            // untrusted certificate, disallowed SigningCertURL host.
            return $this->reject('signature verification failed', [
                'reason' => $e->getMessage(),
                'type' => $payload['Type'] ?? null,
                'topic_arn' => $payload['TopicArn'] ?? null,
            ]);
        }

        return $this->topicIsOurs($payload);
    }

    /**
     * A signature proves AWS sent the message, not that we asked for it.
     *
     * Fails closed when no topic is configured: an unconfigured deployment has no
     * legitimate SNS traffic to accept, so "we cannot tell whose topic this is"
     * must not resolve to "allow".
     */
    private function topicIsOurs(array $payload): bool
    {
        $expected = config('services.aws.sns_arn');

        if (empty($expected)) {
            return $this->reject(
                'no TOPS_SNS_ARN is configured, so the topic cannot be verified. '
                . 'Run ./install.sh, or ignore this if you do not use the SNS callback'
            );
        }

        if (! hash_equals((string) $expected, (string) ($payload['TopicArn'] ?? ''))) {
            return $this->reject('message came from a topic that is not ours', [
                'expected_topic_arn' => $expected,
                'received_topic_arn' => $payload['TopicArn'] ?? null,
            ]);
        }

        return true;
    }

    /**
     * SNS posts JSON with `Content-Type: text/plain; charset=UTF-8`, so Laravel
     * does not parse the body into the input bag and `$request->input()` comes
     * back empty. Decode the raw body, falling back to the parsed input for
     * clients that do send a JSON content type.
     */
    private function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }

        return $request->all();
    }

    private function reject(string $why, array $context = []): bool
    {
        Log::warning("Rejected SNS callback: {$why}", $context);

        return false;
    }
}
