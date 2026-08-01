<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListContactsRequest;
use App\Http\Resources\ApplicationContactResource;
use App\Http\Resources\ContactResource;
use App\Models\ApplicationContact;
use App\Models\Contact;
use App\Support\Warehouse\ListOrdering;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    private const ORDER_COLUMNS = ['name', 'type', 'email', 'created_at'];

    /**
     * Search people / organisations linked to applications.
     *
     * Example: `GET /contacts?search=Hassell&type=organisation&role=architect`
     */
    public function index(ListContactsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = Contact::query()->withCount([
            'applicationContacts as applications_count' => function ($q) use ($request): void {
                $q->where('status', 'published');
                if ($request->filled('role')) {
                    $q->where('role', $request->input('role'));
                }
            },
        ]);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('email')) {
            $query->where('email', 'ilike', (string) $request->input('email'));
        }

        if ($request->filled('abn')) {
            $query->where('abn', (string) $request->input('abn'));
        }

        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('abn', 'ilike', $like);
            });
        }

        if ($request->filled('role')) {
            $role = (string) $request->input('role');
            $query->whereExists(function ($q) use ($role): void {
                $q->selectRaw('1')
                    ->from('application_contacts')
                    ->whereColumn('application_contacts.contact_id', 'contacts.id')
                    ->where('application_contacts.role', $role)
                    ->where('application_contacts.status', 'published');
            });
        }

        [$column, $direction] = ListOrdering::parse($request->input('order', 'name'), 'name');
        $column = ListOrdering::column($column, self::ORDER_COLUMNS, 'name');
        $query->orderBy($column, $direction)->orderBy('id');

        return ContactResource::collection($query->paginate($perPage));
    }

    public function show(Contact $contact): ContactResource
    {
        $contact->loadCount([
            'applicationContacts as applications_count' => fn ($q) => $q->where('status', 'published'),
        ]);

        return new ContactResource($contact);
    }

    /**
     * Published application links for a contact.
     */
    public function applications(Contact $contact): AnonymousResourceCollection
    {
        $rows = ApplicationContact::query()
            ->with(['application.authority'])
            ->where('contact_id', $contact->id)
            ->where('status', 'published')
            ->orderByDesc('is_primary')
            ->orderBy('role')
            ->get();

        return ApplicationContactResource::collection($rows);
    }
}
