<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserPasskeyResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserPasskey;
use App\Support\PasskeyCeremonyStore;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredential;

class PasskeyController extends Controller
{
    public function __construct(
        private readonly PasskeyCeremonyStore $ceremonyStore,
        private readonly UserActivityLogger $activityLogger,
    ) {}

    public function loginOptions(GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate();

        return response()->json([
            'message' => 'passkey_login_options',
            'data' => [
                'options' => $this->ceremonyStore->putLoginOptions($options),
            ],
        ]);
    }

    public function login(
        Request $request,
        VerifyPasskey $verify,
    ): JsonResponse {
        $credentialPayload = $this->validatedCredential($request);
        $options = $this->ceremonyStore->pullLoginOptions($credentialPayload);
        $credential = $this->publicKeyCredential($credentialPayload);

        try {
            $passkey = $verify($credential, $options);
        } catch (InvalidPasskeyException $e) {
            throw $e;
        }

        if (! Passkeys::allowsLogin($request, $passkey)) {
            throw ValidationException::withMessages([
                'credential' => ['Unable to sign in with this passkey.'],
            ]);
        }

        /** @var User $user */
        $user = $passkey->user;
        $token = $user->createToken('api')->plainTextToken;

        $this->activityLogger->log($user, UserActivityLogger::PASSKEY_LOGIN, [
            'passkey_id' => $passkey->id,
        ], $passkey);

        return response()->json([
            'message' => 'token_generated',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $passkeys = $user->passkeys()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => 'passkeys',
            'data' => UserPasskeyResource::collection($passkeys),
        ]);
    }

    public function registerOptions(
        Request $request,
        GenerateRegistrationOptions $generate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $options = $generate($user);

        return response()->json([
            'message' => 'passkey_register_options',
            'data' => [
                'options' => $this->ceremonyStore->putRegistrationOptions($options),
            ],
        ]);
    }

    public function store(
        Request $request,
        StorePasskey $storePasskey,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ]);

        $credentialPayload = $validated['credential'];
        $options = $this->ceremonyStore->pullRegistrationOptions($credentialPayload);
        $credential = $this->publicKeyCredential($credentialPayload);

        $passkey = $storePasskey(
            $user,
            $validated['name'],
            $credential,
            $options,
        );

        $this->activityLogger->log($user, UserActivityLogger::PASSKEY_REGISTERED, [
            'passkey_id' => $passkey->id,
            'name' => $passkey->name,
        ], $passkey);

        return response()->json([
            'message' => 'passkey_registered',
            'data' => [
                'passkey' => new UserPasskeyResource($passkey),
            ],
        ], 201);
    }

    public function destroy(
        Request $request,
        UserPasskey $passkey,
        DeletePasskey $deletePasskey,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless((int) $passkey->user_id === (int) $user->getKey(), 404);

        $deletePasskey($user, $passkey);

        $this->activityLogger->log($user, UserActivityLogger::PASSKEY_DELETED, [
            'passkey_id' => $passkey->id,
        ]);

        return response()->json([
            'message' => 'passkey_deleted',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCredential(Request $request): array
    {
        return $request->validate([
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ])['credential'];
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function publicKeyCredential(array $credential): PublicKeyCredential
    {
        try {
            return WebAuthn::fromJson(
                json_encode($credential, JSON_THROW_ON_ERROR),
                PublicKeyCredential::class,
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'credential' => ['Invalid credential format.'],
            ]);
        }
    }
}
