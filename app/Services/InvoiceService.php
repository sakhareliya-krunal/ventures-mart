<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvoiceService
{
    public function __construct(private readonly OrderShipmentDetails $shipments)
    {
    }

    public function isInvoiceable(Order $order): bool
    {
        if ($order->status === 'Cancelled') {
            return false;
        }

        if ($order->payment_status === 'paid') {
            return true;
        }

        if ($order->payment_method === 'cod') {
            return in_array($order->status, ['Processing', 'Packed', 'Shipped', 'Delivered'], true);
        }

        return false;
    }

    public function ensureIssued(Order $order): Order
    {
        if (! $this->isInvoiceable($order)) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice is not available for this order yet.',
            ]);
        }

        if ($order->invoice_number) {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->invoice_number) {
                return $locked;
            }

            $issuedAt = now();
            $fy = $this->financialYearLabel($issuedAt);
            $prefix = (string) config('invoice.prefix', 'VM');
            $seriesPrefix = "{$prefix}/{$fy}/";

            $latest = Order::query()
                ->where('invoice_number', 'like', $seriesPrefix.'%')
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->value('invoice_number');

            $next = 1;
            if (is_string($latest) && preg_match('/\/(\d+)$/', $latest, $matches)) {
                $next = ((int) $matches[1]) + 1;
            }

            $locked->forceFill([
                'invoice_number' => $seriesPrefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                'invoice_issued_at' => $issuedAt,
            ])->save();

            return $locked->fresh('items');
        });
    }

    public function streamPdf(Order $order): Response
    {
        $order = $this->ensureIssued($order->loadMissing(['items', 'shiprocketShipment']));
        $payload = $this->buildViewData($order);

        $pdf = Pdf::loadView('invoices.tax-invoice', $payload)
            ->setPaper('a4');

        $filename = 'Invoice-'.str_replace('/', '-', $order->invoice_number).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Order $order): array
    {
        $order->loadMissing(['items', 'shiprocketShipment']);

        $subtotal = (float) $order->subtotal;
        $cgst = (float) $order->cgst;
        $sgst = (float) $order->sgst;
        $igst = (float) $order->igst;
        $useIgst = $igst > 0;

        $gstin = trim((string) config('invoice.gstin', ''));
        $hasGstin = $gstin !== '';

        $lines = [];
        $itemCount = $order->items->count();
        $allocatedCgst = 0.0;
        $allocatedSgst = 0.0;
        $allocatedIgst = 0.0;

        foreach ($order->items->values() as $index => $item) {
            $share = $subtotal > 0 ? ((float) $item->line_total / $subtotal) : 0;
            $isLast = $index === $itemCount - 1;

            if ($useIgst) {
                $lineIgst = $isLast ? round($igst - $allocatedIgst, 2) : round($igst * $share, 2);
                $allocatedIgst += $lineIgst;
                $lineCgst = 0.0;
                $lineSgst = 0.0;
            } else {
                $lineCgst = $isLast ? round($cgst - $allocatedCgst, 2) : round($cgst * $share, 2);
                $lineSgst = $isLast ? round($sgst - $allocatedSgst, 2) : round($sgst * $share, 2);
                $allocatedCgst += $lineCgst;
                $allocatedSgst += $lineSgst;
                $lineIgst = 0.0;
            }

            $lineTax = round($lineCgst + $lineSgst + $lineIgst, 2);

            $lines[] = [
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'hsn' => $item->hsn ?: config('invoice.default_hsn'),
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => 0.0,
                'taxable' => (float) $item->line_total,
                'cgst' => $lineCgst,
                'sgst' => $lineSgst,
                'igst' => $lineIgst,
                'tax' => $lineTax,
                'total' => round((float) $item->line_total + $lineTax, 2),
                'image_data_uri' => $this->productImageDataUri($item),
            ];
        }

        $paymentStatus = strtolower((string) $order->payment_status);
        $paymentBadge = match ($paymentStatus) {
            'paid' => ['label' => 'Paid', 'bg' => '#DCFCE7', 'fg' => '#166534'],
            'failed' => ['label' => 'Failed', 'bg' => '#FEE2E2', 'fg' => '#991B1B'],
            default => ['label' => 'Pending', 'bg' => '#FFEDD5', 'fg' => '#9A3412'],
        };

        $courier = $this->shipments->forCustomer($order);
        $hasCourier = $courier['has_details'];

        $orderUrl = str_replace(
            '{number}',
            (string) $order->number,
            (string) config('invoice.order_url_template', 'https://venturesmart.in/orders/{number}')
        );

        return [
            'order' => $order,
            'seller' => [
                'legal_name' => config('invoice.legal_name'),
                'trade_name' => config('invoice.trade_name'),
                'gstin' => $gstin,
                'address_line1' => config('invoice.address_line1'),
                'address_line2' => config('invoice.address_line2'),
                'city' => config('invoice.city'),
                'state' => config('invoice.state'),
                'state_code' => config('invoice.state_code'),
                'postal_code' => config('invoice.postal_code'),
                'country' => config('invoice.country', 'India'),
                'phone' => config('invoice.phone'),
                'email' => config('invoice.email'),
                'website' => config('invoice.website'),
            ],
            'lines' => $lines,
            'use_igst' => $useIgst,
            'has_gstin' => $hasGstin,
            'document_title' => $hasGstin ? 'TAX INVOICE' : 'INVOICE',
            'tax_rate_percent' => round(((float) config('gst.rate', 0.05)) * 100, 2),
            'logo_data_uri' => $this->fileDataUri(public_path(ltrim((string) config('invoice.logo'), '/'))),
            'issued_at' => $order->invoice_issued_at ?? now(),
            'payment_badge' => $paymentBadge,
            'payment_method_label' => $order->payment_method === 'cod' ? 'Cash on Delivery' : 'Online (Razorpay)',
            'courier' => $courier,
            'has_courier' => $hasCourier,
            'order_url' => $orderUrl,
            'qr_data_uri' => $this->qrDataUri($orderUrl),
            'discount_total' => 0.0,
        ];
    }

    public function financialYearLabel(?Carbon $date = null): string
    {
        $date ??= now();
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        if ($month < 4) {
            $start = $year - 1;
            $end = $year;
        } else {
            $start = $year;
            $end = $year + 1;
        }

        return sprintf('%02d-%02d', $start % 100, $end % 100);
    }

    private function productImageDataUri(OrderItem $item): ?string
    {
        $relative = ltrim((string) ($item->product_image ?? ''), '/');
        if ($relative === '') {
            return null;
        }

        return $this->fileDataUri(public_path($relative));
    }

    private function fileDataUri(string $absolutePath): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function qrDataUri(string $url): ?string
    {
        try {
            $result = (new Builder(
                writer: new PngWriter(),
                writerOptions: [],
                validateResult: false,
                data: $url,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 140,
                margin: 4,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            ))->build();

            return 'data:'.$result->getMimeType().';base64,'.base64_encode($result->getString());
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }

            return null;
        }
    }
}
