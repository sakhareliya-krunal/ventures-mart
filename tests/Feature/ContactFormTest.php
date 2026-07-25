<?php

namespace Tests\Feature;

use App\Mail\ContactFormSubmitted;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_stores_message_and_sends_mail(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Test User',
            'email' => 'customer@example.com',
            'message' => 'Please confirm contact mail works.',
        ];

        $response = $this->postJson('/api/contact', $payload);

        $response->assertCreated()->assertJson([
            'message' => 'Thanks! Your message has been received.',
        ]);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Test User',
            'email' => 'customer@example.com',
        ]);

        Mail::assertSent(ContactFormSubmitted::class, function (ContactFormSubmitted $mail) use ($payload) {
            return $mail->contactMessage->email === $payload['email']
                && $mail->hasTo(config('mail.contact_to'));
        });
    }

    public function test_contact_form_requires_fields(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', [])->assertUnprocessable();

        Mail::assertNothingSent();
        $this->assertSame(0, ContactMessage::query()->count());
    }
}
