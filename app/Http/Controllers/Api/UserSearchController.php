<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserSearchRequest;
use App\Http\Requests\User\UpdateUserSearchRequest;
use App\Http\Resources\UserSearchResource;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\UserSearch;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserSearchController extends Controller
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $searches = UserSearch::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        return UserSearchResource::collection($searches);
    }

    public function store(StoreUserSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $search = UserSearch::query()->create([
            ...$request->validated(),
            'user_id' => $user->id,
            'filter' => $request->input('filter', []),
        ]);

        $this->activityLogger->log(
            $user,
            UserActivityLogger::SEARCH_CREATED,
            null,
            $search,
        );

        return response()->json([
            'message' => 'search_created',
            'data' => new UserSearchResource($search),
        ], 201);
    }

    public function show(Request $request, UserSearch $search): UserSearchResource
    {
        $this->authorizeSearch($request, $search);

        return new UserSearchResource($search);
    }

    public function update(UpdateUserSearchRequest $request, UserSearch $search): JsonResponse
    {
        $this->authorizeSearch($request, $search);

        $search->update([
            ...$request->validated(),
            'filter' => $request->input('filter', $search->filter ?? []),
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->activityLogger->log(
            $user,
            UserActivityLogger::SEARCH_UPDATED,
            null,
            $search,
        );

        return response()->json([
            'message' => 'search_updated',
            'data' => new UserSearchResource($search->fresh()),
        ]);
    }

    public function destroy(Request $request, UserSearch $search): JsonResponse
    {
        $this->authorizeSearch($request, $search);

        /** @var User $user */
        $user = $request->user();

        UserPreference::query()
            ->where('user_id', $user->id)
            ->where('default_search_id', $search->id)
            ->update(['default_search_id' => null]);

        $this->activityLogger->log(
            $user,
            UserActivityLogger::SEARCH_DELETED,
            ['search_id' => $search->id, 'name' => $search->name],
            $search,
        );

        $search->delete();

        return response()->json([
            'message' => 'search_deleted',
        ]);
    }

    private function authorizeSearch(Request $request, UserSearch $search): void
    {
        if ((int) $search->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
