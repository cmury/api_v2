<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListLegislationRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\LegislationResource;
use App\Models\Application;
use App\Models\Legislation;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ApplicationQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegislationController extends Controller
{
    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    public function index(ListLegislationRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = Legislation::query();

        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }

        if ($request->filled('instrument_type')) {
            $query->where('instrument_type', (string) $request->input('instrument_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('short_title', 'ilike', $like)
                    ->orWhere('display_name', 'ilike', $like)
                    ->orWhere('abbrev', 'ilike', $like)
                    ->orWhere('description', 'ilike', $like);
            });
        }

        [$column, $direction] = $this->parseOrder($request->input('order', 'name'));
        $allowed = ['name', 'jurisdiction', 'year', 'instrument_type', 'status', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            $column = 'name';
        }

        $query->orderBy($column, $direction);

        return LegislationResource::collection($query->paginate($perPage));
    }

    public function show(Legislation $legislation): LegislationResource
    {
        return new LegislationResource($legislation);
    }

    public function applications(Request $request, Legislation $legislation): AnonymousResourceCollection
    {
        $perPage = min(
            max((int) $request->input('per_page', config('imby.list_per_page', 25)), 1),
            (int) config('imby.list_max_per_page', 100),
        );

        $filter = ApplicationFilter::fromArray([
            ...$request->all(),
            'legislation_ids' => [$legislation->id],
        ]);

        $query = Application::query()->with(['authority']);
        $this->applicationQuery->applyToApplications($query, $filter);
        $query->orderByDesc('submitted');

        return ApplicationResource::collection($query->paginate($perPage));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseOrder(mixed $order): array
    {
        $order = (string) ($order ?: 'name');
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }
}
