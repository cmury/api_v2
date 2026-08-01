<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserFavouriteRequest;
use App\Http\Requests\User\UpdateUserFavouriteRequest;
use App\Http\Resources\UserFavouriteResource;
use App\Models\Application;
use App\Models\User;
use App\Models\UserFavourite;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserFavouriteController extends Controller
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $favourites = UserFavourite::query()
            ->with(['application.authority'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return UserFavouriteResource::collection($favourites);
    }

    public function store(StoreUserFavouriteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $applicationId = (int) $request->input('application_id');

        if (! Application::query()->whereKey($applicationId)->exists()) {
            return response()->json(['message' => 'application_not_found'], 422);
        }

        $favourite = UserFavourite::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'application_id' => $applicationId,
            ],
            [
                'notes' => $request->input('notes'),
            ],
        );

        $created = $favourite->wasRecentlyCreated;

        if (! $created && $request->exists('notes')) {
            $favourite->update(['notes' => $request->input('notes')]);
        }

        $favourite->load(['application.authority']);

        $this->activityLogger->log(
            $user,
            UserActivityLogger::FAVOURITE_CREATED,
            ['application_id' => $applicationId],
            $favourite,
        );

        return response()->json([
            'message' => $created ? 'favourite_created' : 'favourite_exists',
            'data' => new UserFavouriteResource($favourite),
        ], $created ? 201 : 200);
    }

    public function show(Request $request, UserFavourite $favourite): UserFavouriteResource
    {
        $this->authorizeFavourite($request, $favourite);
        $favourite->load(['application.authority']);

        return new UserFavouriteResource($favourite);
    }

    public function update(UpdateUserFavouriteRequest $request, UserFavourite $favourite): JsonResponse
    {
        $this->authorizeFavourite($request, $favourite);
        $favourite->update($request->validated());
        $favourite->load(['application.authority']);

        /** @var User $user */
        $user = $request->user();
        $this->activityLogger->log(
            $user,
            UserActivityLogger::FAVOURITE_UPDATED,
            ['application_id' => $favourite->application_id],
            $favourite,
        );

        return response()->json([
            'message' => 'favourite_updated',
            'data' => new UserFavouriteResource($favourite),
        ]);
    }

    public function destroy(Request $request, UserFavourite $favourite): JsonResponse
    {
        $this->authorizeFavourite($request, $favourite);

        /** @var User $user */
        $user = $request->user();
        $this->activityLogger->log(
            $user,
            UserActivityLogger::FAVOURITE_DELETED,
            ['application_id' => $favourite->application_id, 'favourite_id' => $favourite->id],
            $favourite,
        );

        $favourite->delete();

        return response()->json(['message' => 'favourite_deleted']);
    }

    private function authorizeFavourite(Request $request, UserFavourite $favourite): void
    {
        if ((int) $favourite->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
