<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpsertUserContactProfileRequest;
use App\Http\Requests\Warehouse\ClaimApplicationRequest;
use App\Http\Resources\ApplicationContactResource;
use App\Http\Resources\ContactResource;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\User;
use App\Support\Contacts\UserContactProfile;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Scaffold: registered user claims on applications + linked contact profile.
 *
 * - GET/PUT /user/contact-profile
 * - GET /user/claims
 * - POST/DELETE /applications/{application}/claim
 */
class ApplicationClaimController extends Controller
{
    public function __construct(
        private readonly UserContactProfile $profiles = new UserContactProfile,
        private readonly UserActivityLogger $activityLogger = new UserActivityLogger,
    ) {}

    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $contact = $this->profiles->for($user);

        return response()->json([
            'data' => $contact !== null ? new ContactResource($contact) : null,
        ]);
    }

    public function upsertProfile(UpsertUserContactProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $contact = $this->profiles->upsert($user, $request->validated());

        return response()->json([
            'message' => 'contact_profile_upserted',
            'data' => new ContactResource($contact),
        ]);
    }

    /**
     * Applications this user has claimed (any status).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = ApplicationContact::query()
            ->with(['application.authority', 'contact'])
            ->where('contributed_by_user_id', $user->id)
            ->where('source', 'claim')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('role')) {
            $query->where('role', (string) $request->input('role'));
        }

        return ApplicationContactResource::collection($query->get());
    }

    public function store(ClaimApplicationRequest $request, Application $application): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $contact = $this->profiles->upsert($user, array_filter([
            'type' => $data['type'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'website' => $data['website'] ?? null,
            'abn' => $data['abn'] ?? null,
        ], fn ($v) => $v !== null));

        $existing = ApplicationContact::query()
            ->where('application_id', $application->id)
            ->where('contact_id', $contact->id)
            ->where('role', $data['role'])
            ->first();

        if ($existing !== null) {
            if ($existing->status === 'rejected') {
                $existing->update([
                    'status' => 'pending',
                    'source' => 'claim',
                    'contributed_by_user_id' => $user->id,
                    'is_primary' => (bool) ($data['is_primary'] ?? $existing->is_primary),
                    'notes' => $data['notes'] ?? $existing->notes,
                ]);
                $existing->load(['contact', 'application.authority']);

                $this->activityLogger->log(
                    $user,
                    UserActivityLogger::APPLICATION_CLAIMED,
                    ['application_id' => $application->id, 'role' => $data['role'], 'reactivated' => true],
                    $existing,
                );

                return response()->json([
                    'message' => 'application_claimed',
                    'data' => new ApplicationContactResource($existing),
                ], 200);
            }

            return response()->json([
                'message' => 'application_already_claimed',
                'data' => new ApplicationContactResource($existing->load(['contact', 'application.authority'])),
            ], 200);
        }

        $link = ApplicationContact::query()->create([
            'application_id' => $application->id,
            'contact_id' => $contact->id,
            'role' => $data['role'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'source' => 'claim',
            'status' => 'pending',
            'contributed_by_user_id' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->activityLogger->log(
            $user,
            UserActivityLogger::APPLICATION_CLAIMED,
            ['application_id' => $application->id, 'role' => $data['role']],
            $link,
        );

        return response()->json([
            'message' => 'application_claimed',
            'data' => new ApplicationContactResource($link->load(['contact', 'application.authority'])),
        ], 201);
    }

    public function destroy(Request $request, Application $application): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = $request->filled('role') ? (string) $request->input('role') : null;

        $query = ApplicationContact::query()
            ->where('application_id', $application->id)
            ->where('contributed_by_user_id', $user->id)
            ->where('source', 'claim');

        if ($role !== null) {
            $query->where('role', $role);
        }

        $links = $query->get();
        if ($links->isEmpty()) {
            return response()->json(['message' => 'claim_not_found'], 404);
        }

        foreach ($links as $link) {
            $this->activityLogger->log(
                $user,
                UserActivityLogger::APPLICATION_UNCLAIMED,
                [
                    'application_id' => $application->id,
                    'role' => $link->role,
                    'application_contact_id' => $link->id,
                ],
                $link,
            );
            $link->delete();
        }

        return response()->json([
            'message' => 'application_unclaimed',
            'removed' => $links->count(),
        ]);
    }
}
