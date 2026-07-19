<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserLog */
class UserLogResource extends JsonResource
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'login' => 'Logged In',
        'logout' => 'Logged Out',
        'password_changed' => 'Updated Password',
        'password_reset' => 'Reset Password',
        'profile_updated' => 'Updated Profile',
        'settings_updated' => 'Updated Settings',
        'search_created' => 'Added Saved Search',
        'search_updated' => 'Updated Saved Search',
        'search_modified' => 'Updated Saved Search',
        'search_deleted' => 'Deleted Saved Search',
        'location' => 'Viewed Location',
        'application' => 'Viewed Application',
        'notification' => 'Notification Sent',
        'plan_changed' => 'Changed Plan',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => self::LABELS[$this->action] ?? $this->action,
            'payload' => $this->payload,
            'actionable_type' => $this->actionable_type,
            'actionable_id' => $this->actionable_id,
            'created_at' => $this->created_at,
        ];
    }
}
