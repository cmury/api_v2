<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Store WebAuthn ceremony options in cache (keyed by challenge) so SPA clients
 * can complete passkey login/register without a shared PHP session cookie.
 */
final class PasskeyCeremonyStore
{
    private const TTL_SECONDS = 300;

    public function putLoginOptions(PublicKeyCredentialRequestOptions $options): array
    {
        $browser = WebAuthn::toBrowserArray($options);
        $challenge = $this->challengeFromBrowserArray($browser);

        Cache::put($this->loginKey($challenge), WebAuthn::toJson($options), self::TTL_SECONDS);

        return $browser;
    }

    public function putRegistrationOptions(PublicKeyCredentialCreationOptions $options): array
    {
        $browser = WebAuthn::toBrowserArray($options);
        $challenge = $this->challengeFromBrowserArray($browser);

        Cache::put($this->registrationKey($challenge), WebAuthn::toJson($options), self::TTL_SECONDS);

        return $browser;
    }

    public function pullLoginOptions(array $credential): PublicKeyCredentialRequestOptions
    {
        $challenge = $this->challengeFromCredential($credential);
        $serialized = Cache::pull($this->loginKey($challenge));

        if (! is_string($serialized) || $serialized === '') {
            throw ValidationException::withMessages([
                'credential' => ['Passkey login expired. Please try again.'],
            ]);
        }

        return WebAuthn::fromJson($serialized, PublicKeyCredentialRequestOptions::class);
    }

    public function pullRegistrationOptions(array $credential): PublicKeyCredentialCreationOptions
    {
        $challenge = $this->challengeFromCredential($credential);
        $serialized = Cache::pull($this->registrationKey($challenge));

        if (! is_string($serialized) || $serialized === '') {
            throw ValidationException::withMessages([
                'credential' => ['Passkey registration expired. Please try again.'],
            ]);
        }

        return WebAuthn::fromJson($serialized, PublicKeyCredentialCreationOptions::class);
    }

    /**
     * @param  array<string, mixed>  $browser
     */
    private function challengeFromBrowserArray(array $browser): string
    {
        $challenge = $browser['challenge'] ?? null;

        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'credential' => ['Unable to start passkey ceremony.'],
            ]);
        }

        return $challenge;
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function challengeFromCredential(array $credential): string
    {
        $clientDataJson = data_get($credential, 'response.clientDataJSON');

        if (! is_string($clientDataJson) || $clientDataJson === '') {
            throw ValidationException::withMessages([
                'credential' => ['Invalid passkey credential.'],
            ]);
        }

        try {
            $decoded = Base64UrlSafe::decodeNoPadding($clientDataJson);
            /** @var array<string, mixed> $clientData */
            $clientData = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            try {
                $decoded = Base64UrlSafe::decode($clientDataJson);
                /** @var array<string, mixed> $clientData */
                $clientData = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'credential' => ['Invalid passkey credential.'],
                ]);
            }
        }

        $challenge = $clientData['challenge'] ?? null;

        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'credential' => ['Invalid passkey credential.'],
            ]);
        }

        return $challenge;
    }

    private function loginKey(string $challenge): string
    {
        return 'passkey:login:'.$challenge;
    }

    private function registrationKey(string $challenge): string
    {
        return 'passkey:register:'.$challenge;
    }
}
