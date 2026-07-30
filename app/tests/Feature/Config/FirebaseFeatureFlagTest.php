<?php

namespace Tests\Feature\Config;

use Tests\TestCase;

/**
 * The firebase_auth feature flag is computed in config/features.php from the
 * FIREBASE_USER_AUTH flag AND the presence of the server-side service-account
 * credentials. These tests exercise that expression directly by re-evaluating
 * the config file under different environment states, since the resolved config
 * is what gates the Firebase routes and the frontend signup path.
 */
class FirebaseFeatureFlagTest extends TestCase
{
    /** @var array<string, array{putenv: string|false, env: bool, server: bool}> */
    private array $originalEnv = [];

    private const KEYS = [
        'FIREBASE_USER_AUTH',
        'FIREBASE_PROJECT_ID',
        'FIREBASE_PRIVATE_KEY',
        'FIREBASE_CLIENT_EMAIL',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::KEYS as $key) {
            $this->originalEnv[$key] = [
                'putenv' => getenv($key),
                'env' => array_key_exists($key, $_ENV),
                'server' => array_key_exists($key, $_SERVER),
            ];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $state) {
            $this->clearKey($key);

            if ($state['putenv'] !== false) {
                putenv("$key={$state['putenv']}");
                $_ENV[$key] = $state['putenv'];
                $_SERVER[$key] = $state['putenv'];
            }
        }

        parent::tearDown();
    }

    private function clearKey(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    /**
     * Laravel's env() reads from $_ENV / $_SERVER (the default adapter chain),
     * not from putenv() alone, so set all three to be safe.
     */
    private function setEnv(array $values): void
    {
        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                $this->clearKey($key);

                continue;
            }

            putenv("$key={$values[$key]}");
            $_ENV[$key] = $values[$key];
            $_SERVER[$key] = $values[$key];
        }
    }

    private function resolveFlag(): bool
    {
        return (require base_path('config/features.php'))['firebase_auth'];
    }

    public function test_it_is_off_by_default_when_the_flag_is_unset(): void
    {
        $this->setEnv([]);

        $this->assertFalse($this->resolveFlag());
    }

    public function test_it_stays_off_when_the_flag_is_on_but_credentials_are_missing(): void
    {
        $this->setEnv(['FIREBASE_USER_AUTH' => 'true']);

        $this->assertFalse(
            $this->resolveFlag(),
            'Firebase must not enable when the service-account credentials are blank.'
        );
    }

    public function test_it_is_on_only_when_the_flag_and_credentials_are_all_present(): void
    {
        $this->setEnv([
            'FIREBASE_USER_AUTH' => 'true',
            'FIREBASE_PROJECT_ID' => 'demo-project',
            'FIREBASE_PRIVATE_KEY' => '-----BEGIN PRIVATE KEY-----abc-----END PRIVATE KEY-----',
            'FIREBASE_CLIENT_EMAIL' => 'sa@demo-project.iam.gserviceaccount.com',
        ]);

        $this->assertTrue($this->resolveFlag());
    }

    public function test_it_stays_off_when_credentials_are_present_but_the_flag_is_off(): void
    {
        $this->setEnv([
            'FIREBASE_USER_AUTH' => 'false',
            'FIREBASE_PROJECT_ID' => 'demo-project',
            'FIREBASE_PRIVATE_KEY' => '-----BEGIN PRIVATE KEY-----abc-----END PRIVATE KEY-----',
            'FIREBASE_CLIENT_EMAIL' => 'sa@demo-project.iam.gserviceaccount.com',
        ]);

        $this->assertFalse($this->resolveFlag());
    }
}
