<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\UserActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();

                $this->activityLogger->log($user, UserActivityLogger::PASSWORD_RESET);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'password_reset',
        ]);
    }
}
