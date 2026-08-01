<?php

namespace App\Support\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves / creates the professional contact profile linked to a product user.
 *
 * Prefers users.contact_id when the column exists (agents_v2 migration);
 * falls back to contacts.payload.owner_user_id, then email match.
 */
final class UserContactProfile
{
    public function for(User $user): ?Contact
    {
        if ($this->usersHaveContactId()) {
            $id = $user->contact_id ?? null;
            if (is_numeric($id) && (int) $id > 0) {
                $contact = Contact::query()->find((int) $id);
                if ($contact !== null) {
                    return $contact;
                }
            }
        }

        $owned = Contact::query()
            ->where('payload->owner_user_id', $user->id)
            ->orderBy('id')
            ->first();
        if ($owned !== null) {
            $this->attach($user, $owned);

            return $owned;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email !== '') {
            $byEmail = Contact::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->orderBy('id')
                ->first();
            if ($byEmail !== null) {
                $this->attach($user, $byEmail);

                return $byEmail;
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     type?: string,
     *     name?: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     mobile?: string|null,
     *     website?: string|null,
     *     abn?: string|null,
     *     notes?: string|null
     * }  $attributes
     */
    public function upsert(User $user, array $attributes = []): Contact
    {
        $contact = $this->for($user);

        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                (string) $user->name,
                (string) $user->surname,
            ]))) ?: (string) $user->email;
        }

        $email = isset($attributes['email']) && is_string($attributes['email']) && $attributes['email'] !== ''
            ? strtolower(trim($attributes['email']))
            : strtolower(trim((string) $user->email));

        if ($contact === null) {
            $contact = Contact::query()->create([
                'type' => $attributes['type'] ?? 'person',
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $attributes['phone'] ?? null,
                'mobile' => $attributes['mobile'] ?? $user->mobile,
                'website' => $attributes['website'] ?? null,
                'abn' => $attributes['abn'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'payload' => ['owner_user_id' => $user->id],
            ]);
        } else {
            $payload = is_array($contact->payload) ? $contact->payload : [];
            $payload['owner_user_id'] = $user->id;

            $contact->fill(array_filter([
                'type' => $attributes['type'] ?? null,
                'name' => isset($attributes['name']) ? $name : null,
                'email' => array_key_exists('email', $attributes) ? ($email !== '' ? $email : null) : null,
                'phone' => $attributes['phone'] ?? null,
                'mobile' => $attributes['mobile'] ?? null,
                'website' => $attributes['website'] ?? null,
                'abn' => $attributes['abn'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ], fn ($v) => $v !== null));

            $contact->payload = $payload;
            $contact->save();
        }

        $this->attach($user, $contact);

        return $contact->fresh() ?? $contact;
    }

    public function owns(User $user, Contact $contact): bool
    {
        if ($this->usersHaveContactId() && (int) ($user->contact_id ?? 0) === (int) $contact->id) {
            return true;
        }

        $payload = is_array($contact->payload) ? $contact->payload : [];

        return (int) ($payload['owner_user_id'] ?? 0) === (int) $user->id;
    }

    private function attach(User $user, Contact $contact): void
    {
        $payload = is_array($contact->payload) ? $contact->payload : [];
        if ((int) ($payload['owner_user_id'] ?? 0) !== (int) $user->id) {
            $payload['owner_user_id'] = $user->id;
            $contact->payload = $payload;
            $contact->save();
        }

        if ($this->usersHaveContactId() && (int) ($user->contact_id ?? 0) !== (int) $contact->id) {
            $user->forceFill(['contact_id' => $contact->id])->save();
        }
    }

    private function usersHaveContactId(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }

        try {
            $has = Schema::connection($this->connection())->hasColumn('users', 'contact_id');
        } catch (\Throwable) {
            $has = false;
        }

        return $has;
    }

    private function connection(): string
    {
        return (string) config('database.data_connection', 'data');
    }
}
