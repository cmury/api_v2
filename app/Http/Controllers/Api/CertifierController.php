<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListCertifiersRequest;
use App\Http\Resources\ApplicationCertifierResource;
use App\Http\Resources\CertifierResource;
use App\Models\ApplicationCertifier;
use App\Models\Certifier;
use App\Support\Warehouse\ListOrdering;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CertifierController extends Controller
{
    private const ORDER_COLUMNS = [
        'name',
        'registration_number',
        'suburb',
        'status',
        'enrichment_status',
        'registered_at',
        'enriched_at',
        'created_at',
    ];

    /**
     * Search NSW Fair Trading (and future jurisdiction) building certifiers.
     *
     * Example: `GET /certifiers?search=Smith&enriched=1&state=NSW`
     * Example: `GET /certifiers?enrichment_status=pending`
     */
    public function index(ListCertifiersRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = Certifier::query()->withCount('applicationCertifiers as applications_count');

        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->input('state')));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('registration_type')) {
            $query->where('registration_type', (string) $request->input('registration_type'));
        }

        if ($request->filled('registration_number')) {
            $number = strtoupper(preg_replace('/\s+/', '', (string) $request->input('registration_number')) ?? '');
            $query->where('registration_number', $number);
        }

        if ($request->filled('suburb')) {
            $query->where('suburb', 'ilike', (string) $request->input('suburb'));
        }

        if ($request->filled('postcode')) {
            $query->where('postcode', (string) $request->input('postcode'));
        }

        // enrichment_status takes precedence over the enriched boolean shortcut.
        if ($request->filled('enrichment_status')) {
            $query->where('enrichment_status', (string) $request->input('enrichment_status'));
        } elseif ($request->exists('enriched')) {
            if ($request->boolean('enriched')) {
                $query->where('enrichment_status', Certifier::ENRICHMENT_ENRICHED);
            } else {
                $query->where('enrichment_status', '!=', Certifier::ENRICHMENT_ENRICHED);
            }
        }

        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like, $search): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('organisation', 'ilike', $like)
                    ->orWhere('registration_number', 'ilike', '%'.strtoupper(preg_replace('/\s+/', '', $search) ?? '').'%')
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('suburb', 'ilike', $like);
            });
        }

        [$column, $direction] = ListOrdering::parse($request->input('order', 'name'), 'name');
        $column = ListOrdering::column($column, self::ORDER_COLUMNS, 'name');
        $query->orderBy($column, $direction)->orderBy('id');

        return CertifierResource::collection($query->paginate($perPage));
    }

    public function show(Certifier $certifier): CertifierResource
    {
        $certifier->loadCount('applicationCertifiers as applications_count');

        return new CertifierResource($certifier);
    }

    /**
     * Applications linked to a certifier.
     */
    public function applications(Certifier $certifier): AnonymousResourceCollection
    {
        $rows = ApplicationCertifier::query()
            ->with(['application.authority'])
            ->where('certifier_id', $certifier->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get();

        return ApplicationCertifierResource::collection($rows);
    }
}
