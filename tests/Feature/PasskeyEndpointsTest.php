<?php

namespace Tests\Feature;

use Tests\TestCase;

class PasskeyEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passkeys.relying_party_id' => 'localhost',
            'passkeys.allowed_origins' => ['http://localhost', 'http://localhost:5174'],
            'passkeys.user_handle_secret' => 'testing-passkey-secret',
        ]);
    }

    public function test_login_options_are_public(): void
    {
        $response = $this->getJson('/api/auth/passkeys/login/options');

        $response->assertOk()
            ->assertJsonPath('message', 'passkey_login_options')
            ->assertJsonStructure([
                'data' => [
                    'options' => [
                        'challenge',
                        'rpId',
                        'timeout',
                    ],
                ],
            ]);
    }

    public function test_login_requires_credential(): void
    {
        $this->postJson('/api/auth/passkeys/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['credential']);
    }

    public function test_passkey_management_requires_auth(): void
    {
        $this->getJson('/api/auth/passkeys')->assertUnauthorized();
        $this->getJson('/api/auth/passkeys/register/options')->assertUnauthorized();
        $this->postJson('/api/auth/passkeys/register', [])->assertUnauthorized();
    }
}
