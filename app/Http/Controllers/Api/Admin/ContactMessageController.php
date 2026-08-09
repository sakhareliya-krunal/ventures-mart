<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $messages = ContactMessage::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $messages->getCollection()->map(fn (ContactMessage $message) => $this->data($message)),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
                'unread_count' => ContactMessage::query()->unread()->count(),
            ],
        ]);
    }

    public function show(ContactMessage $contactMessage)
    {
        return response()->json([
            'data' => $this->data($contactMessage),
        ]);
    }

    public function read(Request $request, ContactMessage $contactMessage)
    {
        if (! $contactMessage->read_at) {
            $contactMessage->forceFill([
                'read_at' => now(),
                'read_by_user_id' => $request->user()->id,
            ])->save();
        }

        return response()->json([
            'data' => $this->data($contactMessage->fresh()),
            'unread_count' => ContactMessage::query()->unread()->count(),
        ]);
    }

    public function readAll(Request $request)
    {
        ContactMessage::query()
            ->unread()
            ->update([
                'read_at' => now(),
                'read_by_user_id' => $request->user()->id,
                'updated_at' => now(),
            ]);

        return response()->json(['unread_count' => 0]);
    }

    public function destroy(ContactMessage $contactMessage)
    {
        $contactMessage->delete();

        return response()->json([
            'message' => 'Message deleted.',
            'unread_count' => ContactMessage::query()->unread()->count(),
        ]);
    }

    private function data(ContactMessage $message): array
    {
        return [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'message' => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'is_read' => (bool) $message->read_at,
        ];
    }
}
