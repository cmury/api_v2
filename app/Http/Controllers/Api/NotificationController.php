<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Warehouse\SearchNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly SearchNotifications $searchNotifications = new SearchNotifications,
    ) {}

    /**
     * GeoJSON of applications first ingested into IMBY since the last
     * notification (or the user's frequency window), matching notify-enabled searches.
     */
    public function searches(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('preferences');

        return response()->json($this->searchNotifications->forUser($user));
    }
}
