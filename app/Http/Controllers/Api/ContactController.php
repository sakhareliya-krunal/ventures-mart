<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\ContactFormSubmitted;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    public function store(ContactRequest $request)
    {
        $message = ContactMessage::query()->create($request->validated());

        try {
            Mail::to(config('mail.contact_to'))->send(new ContactFormSubmitted($message));
        } catch (Throwable $exception) {
            Log::error('Contact form mail failed', [
                'id' => $message->id,
                'email' => $message->email,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to send your message. Please try again or reach us on WhatsApp.',
            ], 500);
        }

        Log::info('Contact message received', [
            'id' => $message->id,
            'email' => $message->email,
        ]);

        return response()->json([
            'message' => 'Thanks! Your message has been received.',
        ], 201);
    }
}
