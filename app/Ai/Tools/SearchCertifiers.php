<?php

namespace App\Ai\Tools;

use App\Models\Certifier;
use App\Support\Insights\ToolJson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/certifiers — Fair Trading building certifier register.
 */
class SearchCertifiers implements Tool
{
    use ReadsToolArgs;

    public function name(): string
    {
        return 'search_certifiers';
    }

    public function description(): Stringable|string
    {
        return 'Search NSW building certifiers from the Fair Trading BDC register '
            .'(registration number, name, organisation, suburb). '
            .'Filter by enrichment status (enriched stubs vs pending) or registration status.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');
        if ($search === '') {
            $search = $this->argString($request, 'name');
        }

        $query = Certifier::query()->withCount('applicationCertifiers as applications_count');

        if ($this->hasArg($request, 'state')) {
            $query->where('state', strtoupper($this->argString($request, 'state')));
        }

        if ($this->hasArg($request, 'enrichment_status')) {
            $query->where('enrichment_status', $this->argString($request, 'enrichment_status'));
        } elseif ($this->arg($request, 'enriched') !== null && $this->arg($request, 'enriched') !== '') {
            $raw = $this->arg($request, 'enriched');
            $enriched = is_bool($raw)
                ? $raw
                : filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($enriched === true) {
                $query->where('enrichment_status', Certifier::ENRICHMENT_ENRICHED);
            } elseif ($enriched === false) {
                $query->where('enrichment_status', '!=', Certifier::ENRICHMENT_ENRICHED);
            }
        }

        if ($this->hasArg($request, 'status')) {
            $query->where('status', $this->argString($request, 'status'));
        }

        if ($this->hasArg($request, 'registration_number')) {
            $number = strtoupper(preg_replace('/\s+/', '', $this->argString($request, 'registration_number')) ?? '');
            $query->where('registration_number', $number);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like, $search): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('organisation', 'ilike', $like)
                    ->orWhere('registration_number', 'ilike', '%'.strtoupper(preg_replace('/\s+/', '', $search) ?? '').'%')
                    ->orWhere('suburb', 'ilike', $like);
            });
        }

        $rows = $query->orderBy('name')->limit($perPage)->get();

        return ToolJson::encode([
            'count' => $rows->count(),
            'certifiers' => $rows->map(fn (Certifier $c) => [
                'id' => $c->id,
                'state' => $c->state,
                'registration_number' => $c->registration_number,
                'registration_type' => $c->registration_type,
                'name' => $c->name,
                'organisation' => $c->organisation,
                'status' => $c->status,
                'suburb' => $c->suburb,
                'postcode' => $c->postcode,
                'enrichment_status' => $c->enrichment_status,
                'applications_count' => $c->applications_count ?? null,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Name / organisation / registration number / suburb fragment.'),
            'name' => $schema->string()->description('Alias of search.'),
            'state' => $schema->string()->description('Jurisdiction code, e.g. NSW.'),
            'registration_number' => $schema->string()->description('Exact BDC / RBC number.'),
            'status' => $schema->string()->description('Register status, e.g. registered | suspended | expired | cancelled.'),
            'enrichment_status' => $schema->string()->description('pending | enriched | not_found | failed.'),
            'enriched' => $schema->string()->description('true = enriched only; false = not yet enriched (pending/failed/not_found).'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
