<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListApplicationsRequest;
use App\Http\Requests\Warehouse\StoreApplicationContactRequest;
use App\Http\Resources\ApplicationCertifierResource;
use App\Http\Resources\ApplicationContactResource;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\LegislationResource;
use App\Models\Application;
use App\Models\ApplicationCertifier;
use App\Models\ApplicationContact;
use App\Models\Contact;
use App\Models\User;
use App\Support\UserActivityLogger;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ApplicationQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
        private readonly UserActivityLogger $activityLogger = new UserActivityLogger,
    ) {}

    public function index(ListApplicationsRequest $request)
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $filter = ApplicationFilter::fromArray($request->validated());

        $query = Application::query()->with(['authority']);
        $this->applicationQuery->applyToApplications($query, $filter);

        [$column, $direction] = $this->parseOrder($request->input('order', '-submitted'));
        $allowed = ['submitted', 'estimated_cost', 'created_at', 'authority_no', 'portal_no'];
        if (! in_array($column, $allowed, true)) {
            $column = 'submitted';
        }

        $query->orderBy($column, $direction);

        return ApplicationResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        $application->load([
            'authority',
            'locations',
            'legislations',
            'applicationTypes.applicationClass',
            'developmentTypes.developmentClass',
            'decisionTypes.decisionClass',
            'applicationContacts' => fn ($q) => $q->where('status', 'published')->orderByDesc('is_primary')->orderBy('role'),
            'applicationContacts.contact',
            'applicationCertifiers' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'applicationCertifiers.certifier',
        ]);

        foreach ($application->locations as $location) {
            $coords = DB::connection($location->getConnectionName())
                ->table('locations')
                ->where('id', $location->id)
                ->whereNotNull('geom')
                ->selectRaw('ST_Y(geom::geometry) AS lat')
                ->selectRaw('ST_X(geom::geometry) AS lng')
                ->first();

            if ($coords !== null) {
                $location->setAttribute('lat', (float) $coords->lat);
                $location->setAttribute('lng', (float) $coords->lng);
            }
        }

        /** @var User|null $user */
        $user = $request->user();
        if ($user instanceof User) {
            $this->activityLogger->logOnce(
                $user,
                UserActivityLogger::APPLICATION_VIEWED,
                ['application_id' => $application->id],
                $application,
            );
        }

        return new ApplicationResource($application);
    }

    public function legislations(Application $application): AnonymousResourceCollection
    {
        return LegislationResource::collection(
            $application->legislations()->orderBy('name')->get(),
        );
    }

    /**
     * Contacts linked to an application (published by default).
     *
     * Query: `?include_pending=1` also returns the caller's pending contributions.
     */
    public function contacts(Request $request, Application $application): AnonymousResourceCollection
    {
        $query = ApplicationContact::query()
            ->with('contact')
            ->where('application_id', $application->id)
            ->orderByDesc('is_primary')
            ->orderBy('role');

        if ($request->boolean('include_pending') && $request->user()) {
            $userId = (int) $request->user()->id;
            $query->where(function ($q) use ($userId): void {
                $q->where('status', 'published')
                    ->orWhere(function ($pending) use ($userId): void {
                        $pending->where('status', 'pending')
                            ->where('contributed_by_user_id', $userId);
                    });
            });
        } else {
            $query->where('status', 'published');
        }

        if ($request->filled('role')) {
            $query->where('role', (string) $request->input('role'));
        }

        return ApplicationContactResource::collection($query->get());
    }

    /**
     * Certifiers linked to an application (Fair Trading register links).
     */
    public function certifiers(Application $application): AnonymousResourceCollection
    {
        $rows = ApplicationCertifier::query()
            ->with('certifier')
            ->where('application_id', $application->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return ApplicationCertifierResource::collection($rows);
    }

    /**
     * Contribute a contact on an application (stored as pending until moderated).
     */
    public function storeContact(StoreApplicationContactRequest $request, Application $application): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! empty($data['contact_id'])) {
            $contact = Contact::query()->find((int) $data['contact_id']);
            if ($contact === null) {
                return response()->json(['message' => 'contact_not_found'], 422);
            }
        } else {
            $contact = $this->findOrCreateContact($data);
        }

        $existing = ApplicationContact::query()
            ->where('application_id', $application->id)
            ->where('contact_id', $contact->id)
            ->where('role', $data['role'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'message' => 'application_contact_exists',
                'data' => new ApplicationContactResource($existing->load('contact')),
            ], 200);
        }

        $link = ApplicationContact::query()->create([
            'application_id' => $application->id,
            'contact_id' => $contact->id,
            'role' => $data['role'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'source' => 'user',
            'status' => 'pending',
            'contributed_by_user_id' => $user->id,
            'email_override' => $data['email_override'] ?? null,
            'phone_override' => $data['phone_override'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->activityLogger->log(
            $user,
            UserActivityLogger::CONTACT_CONTRIBUTED,
            [
                'application_id' => $application->id,
                'contact_id' => $contact->id,
                'role' => $data['role'],
            ],
            $link,
        );

        return response()->json([
            'message' => 'application_contact_created',
            'data' => new ApplicationContactResource($link->load('contact')),
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateContact(array $data): Contact
    {
        $email = isset($data['email']) && is_string($data['email']) && $data['email'] !== ''
            ? strtolower(trim($data['email']))
            : null;

        if ($email !== null) {
            $existing = Contact::query()->whereRaw('lower(email) = ?', [$email])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return Contact::query()->create([
            'type' => $data['type'] ?? 'organisation',
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'website' => $data['website'] ?? null,
            'abn' => $data['abn'] ?? null,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseOrder(mixed $order): array
    {
        $order = (string) ($order ?: '-submitted');
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }
}
