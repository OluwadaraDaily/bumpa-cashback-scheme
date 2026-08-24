<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            $request->user()
                ->unreadNotifications()
                ->latest()
                ->limit(20)
                ->get(),
        );
    }

    public function markAsRead(Request $request, string $notification): Response
    {
        $storedNotification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $storedNotification->markAsRead();

        return response()->noContent();
    }
}
