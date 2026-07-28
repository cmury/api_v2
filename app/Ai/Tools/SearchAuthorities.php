<?php

namespace App\Ai\Tools;

use App\Models\Authority;
use App\Support\Insights\ToolJson;
use App\Support\Warehouse\AuthoritySearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/authorities — search councils / LGAs.
 */
class SearchAuthorities implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly AuthoritySearch $authoritySearch = new AuthoritySearch,
    ) {}

    public function name(): string
    {
        return 'search_authorities';
    }

    public function description(): Stringable|string
    {
        return 'Search planning authorities (councils / LGAs). Use for council lists, phone/email/website/postal details, '
            .'region, state, tracking portals, and application counts. Prefer this over SQL for authority questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');
        if ($search === '') {
            $search = $this->argString($request, 'name');
        }

        $query = $this->authoritySearch->query(
            $search !== '' ? $search : null,
            $this->hasArg($request, 'state') ? $this->argString($request, 'state') : null,
            $request->boolean('include_amalgamated'),
        );

        $order = $this->argString($request, 'order', 'name') ?: 'name';
        $rows = $this->authoritySearch->ordered($query, $order)
            ->limit($perPage)
            ->get([
                'id', 'name', 'region', 'state', 'phone', 'email', 'url',
                'postal_address', 'postal_suburb', 'postal_code',
                'tracking', 'tracking_system', 'tracking_url', 'amalgamated',
            ]);

        return ToolJson::encode([
            'count' => $rows->count(),
            'authorities' => $rows->map(fn (Authority $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'region' => $a->region,
                'state' => $a->state,
                'phone' => $a->phone,
                'email' => $a->email,
                'url' => $a->url,
                'postal_address' => $a->postal_address,
                'postal_suburb' => $a->postal_suburb,
                'postal_code' => $a->postal_code,
                'tracking' => (bool) $a->tracking,
                'tracking_system' => $a->tracking_system,
                'tracking_url' => $a->tracking_url,
                'amalgamated' => $a->amalgamated,
                'applications_count' => $a->applications_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Council name fragment, e.g. Dungog or Randwick.'),
            'name' => $schema->string()->description('Alias of search.'),
            'state' => $schema->string()->description('State code: NSW, ACT, VIC, …'),
            'include_amalgamated' => $schema->boolean()->description('Include former councils. Default false.'),
            'order' => $schema->string()->description('name, state, region, applications_count; prefix - for desc.'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
