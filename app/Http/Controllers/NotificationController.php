<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The header bell. mine(), not the global scope: notifications are personal,
 * so a super admin's bell shows their own jobs, not every user's.
 */
class NotificationController extends Controller
{
    /**
     * Polled by the header bell: the latest notifications plus the unread count.
     */
    public function index(): JsonResponse
    {
        $items = AppNotification::mine()->latest('id')->limit(15)->get()->map(fn (AppNotification $n) => [
            'id'      => $n->id,
            'type'    => $n->type,
            'title'   => $n->title,
            'message' => $n->message,
            'url'     => $n->url,
            'read'    => $n->read_at !== null,
            'time'    => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread' => AppNotification::mine()->whereNull('read_at')->count(),
            'items'  => $items,
        ]);
    }

    /**
     * Mark one notification (id) or every unread one (no id) as read.
     */
    public function read(Request $request): JsonResponse
    {
        AppNotification::mine()
            ->whereNull('read_at')
            ->when($request->filled('id'), fn ($q) => $q->where('id', (int) $request->input('id')))
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
