<?php

namespace App\Ai\Tools;

use App\Models\Authority;
use App\Support\Insights\ToolJson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/authorities/{id}.
 */
class GetAuthority implements Tool
{
    use ReadsToolArgs;

    public function name(): string
    {
        return 'get_authority';
    }

    public function description(): Stringable|string
    {
        return 'Fetch one authority by id, including contact details and application count. '
            .'Resolve the id with search_authorities first when the user names a council.';
    }

    public function handle(Request $request): Stringable|string
    {
        $id = $this->argInt($request, 'authority_id') ?? 0;
        if ($id < 1) {
            return ToolJson::encode(['error' => 'authority_id is required.']);
        }

        $authority = Authority::query()->withCount('applications')->find($id);
        if ($authority === null) {
            return ToolJson::encode(['error' => "Authority {$id} not found."]);
        }

        return ToolJson::encode([
            'id' => $authority->id,
            'name' => $authority->name,
            'region' => $authority->region,
            'state' => $authority->state,
            'phone' => $authority->phone,
            'email' => $authority->email,
            'url' => $authority->url,
            'postal_address' => $authority->postal_address,
            'postal_suburb' => $authority->postal_suburb,
            'postal_code' => $authority->postal_code,
            'tracking' => (bool) $authority->tracking,
            'tracking_system' => $authority->tracking_system,
            'tracking_url' => $authority->tracking_url,
            'amalgamated' => $authority->amalgamated,
            'start_date' => $authority->start_date,
            'end_date' => $authority->end_date,
            'applications_count' => $authority->applications_count,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'authority_id' => $schema->integer()->required()->description('Authority primary key.'),
        ];
    }
}
