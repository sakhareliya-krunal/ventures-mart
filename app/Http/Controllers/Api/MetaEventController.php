<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetaEventRequest;
use App\Services\MetaConversionsService;
use Illuminate\Http\JsonResponse;

class MetaEventController extends Controller
{
    public function store(MetaEventRequest $request, MetaConversionsService $meta): JsonResponse
    {
        $eventName = (string) $request->validated('event_name');
        $eventId = (string) $request->validated('event_id');
        $customData = $meta->customDataForBrowserEvent(
            $eventName,
            $request->input('custom_data') ?? [],
            $request,
        );

        $meta->queue($eventName, $eventId, $customData, $request, $request->user());

        return response()->json(['ok' => true]);
    }
}
