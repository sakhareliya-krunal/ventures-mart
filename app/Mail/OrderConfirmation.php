<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        private readonly string $invoiceContents,
        public readonly string $invoiceFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order confirmed - '.$this->order->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-confirmation',
            with: [
                'order' => $this->order,
                'items' => $this->order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'sku' => $item->product_sku,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                    'image_url' => $this->publicUrl($item->product_image, 'images/ventures-mart-logo.png'),
                    'image_path' => $this->publicPath($item->product_image),
                ])->all(),
                'logoUrl' => $this->publicUrl(config('mail.logo'), 'images/ventures-mart-logo-light.png'),
                'logoPath' => $this->publicPath(config('mail.logo')),
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
        return [
            Attachment::fromData(
                fn () => $this->invoiceContents,
                $this->invoiceFilename,
            )->withMime('application/pdf'),
        ];
    }

    private function publicUrl(?string $path, string $fallback): string
    {
        $path = trim((string) $path);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = $path !== '' ? $path : $fallback;

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function publicPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        $absolutePath = public_path(ltrim($path, '/'));

        return is_file($absolutePath) && is_readable($absolutePath) ? $absolutePath : null;
    }
}
