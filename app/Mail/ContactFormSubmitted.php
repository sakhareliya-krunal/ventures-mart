<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address((string) config('mail.contact_to'))],
            replyTo: [
                new Address($this->contactMessage->email, $this->contactMessage->name),
            ],
            subject: 'New contact from '.$this->contactMessage->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-form-submitted',
            with: [
                'contactMessage' => $this->contactMessage,
            ],
        );
    }
}
