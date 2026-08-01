<?php

namespace App\Ai\Tools;

use App\Models\Contact;
use App\Support\Insights\ToolJson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/contacts — people / organisations on applications.
 */
class SearchContacts implements Tool
{
    use ReadsToolArgs;

    public function name(): string
    {
        return 'search_contacts';
    }

    public function description(): Stringable|string
    {
        return 'Search people or organisations linked to development applications '
            .'(architects, builders, applicants, certifiers, …). '
            .'Filter by name, email, type (person|organisation), or role.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');
        if ($search === '') {
            $search = $this->argString($request, 'name');
        }

        $query = Contact::query()->withCount([
            'applicationContacts as applications_count' => fn ($q) => $q->where('status', 'published'),
        ]);

        if ($this->hasArg($request, 'type')) {
            $query->where('type', $this->argString($request, 'type'));
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('abn', 'ilike', $like);
            });
        }

        if ($this->hasArg($request, 'role')) {
            $role = $this->argString($request, 'role');
            $query->whereExists(function ($q) use ($role): void {
                $q->selectRaw('1')
                    ->from('application_contacts')
                    ->whereColumn('application_contacts.contact_id', 'contacts.id')
                    ->where('application_contacts.role', $role)
                    ->where('application_contacts.status', 'published');
            });
        }

        $rows = $query->orderBy('name')->limit($perPage)->get();

        return ToolJson::encode([
            'count' => $rows->count(),
            'contacts' => $rows->map(fn (Contact $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'website' => $c->website,
                'abn' => $c->abn,
                'applications_count' => $c->applications_count ?? null,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Name / email / ABN fragment.'),
            'name' => $schema->string()->description('Alias of search.'),
            'type' => $schema->string()->description('person | organisation.'),
            'role' => $schema->string()->description('architect | planner | builder | applicant | developer | certifier | other.'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
