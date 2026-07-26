<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListApplicationsRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ApplicationQuery;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
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

    public function show(Application $application): ApplicationResource
    {
        $application->load([
            'authority',
            'locations',
            'applicationTypes.applicationClass',
            'developmentTypes.developmentClass',
            'decisionTypes.decisionClass',
        ]);

        return new ApplicationResource($application);
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
