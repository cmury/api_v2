<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UpdateSettingsRequest;
use App\Http\Resources\UserLogResource;
use App\Http\Resources\UserPreferenceResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserLog;
use App\Models\UserPreference;
use App\Support\DeleteUserAccount;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger,
        private readonly DeleteUserAccount $deleteUserAccount,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'authenticated_user',
            'data' => new UserResource($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());

        $this->activityLogger->log($user, UserActivityLogger::PROFILE_UPDATED);

        return response()->json([
            'message' => 'profile_updated',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function showSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'message' => 'user_settings',
            'data' => new UserPreferenceResource($this->preferencesFor($user)),
        ]);
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $preferences = $this->preferencesFor($user);

        $preferences->fill($request->validated());
        $preferences->save();

        $this->activityLogger->log($user, UserActivityLogger::SETTINGS_UPDATED);

        return response()->json([
            'message' => 'settings_updated',
            'data' => new UserPreferenceResource($preferences->fresh()),
        ]);
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Your password was incorrect.'],
            ]);
        }

        ($this->deleteUserAccount)($user);

        return response()->json([
            'message' => 'account_deleted',
        ]);
    }

    public function log(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = UserLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter = $request->string('filter')->toString()) {
            $query->where('action', 'like', '%'.$filter.'%');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return UserLogResource::collection(
            $query->paginate($perPage)
        );
    }

    private function preferencesFor(User $user): UserPreference
    {
        return UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'map_type' => 'ROADMAP',
                'date_range' => 12,
                'new_application_email_frequency' => config('imby.default_email_frequency', 'weekly'),
                'locale' => 'AU',
            ]
        );
    }
}
