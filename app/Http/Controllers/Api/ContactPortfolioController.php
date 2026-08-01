<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListContactPortfolioRequest;
use App\Http\Requests\Warehouse\StoreContactPortfolioRequest;
use App\Http\Resources\ApplicationContactResource;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\Contact;
use App\Models\User;
use App\Support\Contacts\UserContactProfile;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Scaffold: contact / advertiser portfolio of applications.
 *
 * Public read: published only.
 * Owner: may list pending/rejected and add/remove portfolio items.
 */
class ContactPortfolioController extends Controller
{
    public function __construct(
        private readonly UserContactProfile $profiles = new UserContactProfile,
        private readonly UserActivityLogger $activityLogger = new UserActivityLogger,
    ) {}

    public function index(ListContactPortfolioRequest $request, Contact $contact): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        /** @var User|null $user */
        $user = $request->user();
        $isOwner = $user !== null && $this->profiles->owns($user, $contact);

        $query = ApplicationContact::query()
            ->with(['application.authority', 'contact'])
            ->where('contact_id', $contact->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if (! $isOwner && $status !== 'published') {
                abort(403, 'Only the contact owner can list non-published portfolio items.');
            }
            $query->where('status', $status);
        } elseif (! $isOwner) {
            $query->where('status', 'published');
        }

        if ($request->filled('role')) {
            $query->where('role', (string) $request->input('role'));
        }

        return ApplicationContactResource::collection($query->paginate($perPage));
    }

    public function store(StoreContactPortfolioRequest $request, Contact $contact): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeOwner($user, $contact);

        $data = $request->validated();
        $applicationId = (int) $data['application_id'];

        if (! Application::query()->whereKey($applicationId)->exists()) {
            return response()->json(['message' => 'application_not_found'], 422);
        }

        $existing = ApplicationContact::query()
            ->where('application_id', $applicationId)
            ->where('contact_id', $contact->id)
            ->where('role', $data['role'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'message' => 'portfolio_item_exists',
                'data' => new ApplicationContactResource($existing->load(['application.authority', 'contact'])),
            ], 200);
        }

        $link = ApplicationContact::query()->create([
            'application_id' => $applicationId,
            'contact_id' => $contact->id,
            'role' => $data['role'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'source' => 'portfolio',
            'status' => 'pending',
            'contributed_by_user_id' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->activityLogger->log(
            $user,
            UserActivityLogger::PORTFOLIO_ITEM_ADDED,
            [
                'contact_id' => $contact->id,
                'application_id' => $applicationId,
                'role' => $data['role'],
            ],
            $link,
        );

        return response()->json([
            'message' => 'portfolio_item_created',
            'data' => new ApplicationContactResource($link->load(['application.authority', 'contact'])),
        ], 201);
    }

    public function destroy(Request $request, Contact $contact, ApplicationContact $applicationContact): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeOwner($user, $contact);

        if ((int) $applicationContact->contact_id !== (int) $contact->id) {
            abort(404);
        }

        $this->activityLogger->log(
            $user,
            UserActivityLogger::PORTFOLIO_ITEM_REMOVED,
            [
                'contact_id' => $contact->id,
                'application_id' => $applicationContact->application_id,
                'application_contact_id' => $applicationContact->id,
            ],
            $applicationContact,
        );

        $applicationContact->delete();

        return response()->json(['message' => 'portfolio_item_removed']);
    }

    private function authorizeOwner(User $user, Contact $contact): void
    {
        if (! $this->profiles->owns($user, $contact)) {
            abort(403, 'Only the contact owner can manage this portfolio.');
        }
    }
}
