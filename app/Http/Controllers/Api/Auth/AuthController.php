<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'surname' => $request->string('surname')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'company' => $request->input('company'),
            'mobile' => $request->input('mobile'),
            'is_verified' => true,
        ]);

        UserPreference::query()->create([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'registered',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Your email address or password is incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'token_generated',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'token_invalidated',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'authenticated_user',
            'data' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'profile_updated',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password was incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'password_updated',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
