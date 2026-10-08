<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Polled by the header bell: the latest notifications plus the unread count.
     */
    public function index(): JsonResponse
    {
        $items = AppNotification::latest('id')->limit(15)->get()->map(fn (AppNotification $n) => [
            'id'      => $n->id,
            'type'    => $n->type,
            'title'   => $n->title,
            'message' => $n->message,
            'url'     => $n->url,
            'read'    => $n->read_at !== null,
            'time'    => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread' => AppNotification::whereNull('read_at')->count(),
            'items'  => $items,
        ]);
    }

    /**
     * Mark one notification (id) or every unread one (no id) as read.
     */
    public function read(Request $request): JsonResponse
    {
        AppNotification::whereNull('read_at')
            ->when($request->filled('id'), fn ($q) => $q->where('id', (int) $request->input('id')))
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
