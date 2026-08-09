<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancellation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order cancelled - '.$this->order->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-cancellation',
            with: [
                'order' => $this->order,
                'logoUrl' => $this->publicUrl((string) config('invoice.logo')),
                'orderUrl' => str_replace(
                    '{number}',
                    (string) $this->order->number,
                    (string) config('invoice.order_url_template'),
                ),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function publicUrl(string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
