<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order confirmed</title>
</head>
<body style="margin:0;background:#f5f7fb;color:#1c2c4c;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Your Ventures Mart order {{ $order->number }} is confirmed. Your invoice is attached.
    </div>

    @php
        $logoSrc = $logoPath ? $message->embed($logoPath) : $logoUrl;
    @endphp

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dbe6f8;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:30px 24px 26px;background:#0b2e8a;border-bottom:5px solid #ffc107;">
                            <img src="{{ $logoSrc }}" width="180" alt="Ventures Mart" style="display:block;width:190px;max-width:190px;height:auto;border:0;margin:0 auto 18px;">
                            <p style="margin:0 0 8px;color:#ffc107;font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;">Order confirmed</p>
                            <h1 style="margin:0;color:#ffffff;font-size:28px;line-height:1.25;font-weight:700;">Thank you, {{ $order->full_name }}!</h1>
                            <p style="margin:12px auto 0;max-width:460px;color:#e8eef8;font-size:15px;line-height:1.6;">
                                We have received your order. Your customer invoice is attached to this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:22px;background:#f9fbff;border:1px solid #dbe6f8;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 5px;color:#6b7a99;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;">Order number</p>
                                        <p style="margin:0;color:#071f63;font-size:17px;font-weight:800;">{{ $order->number }}</p>
                                    </td>
                                    <td align="right" style="padding:16px;">
                                        <p style="margin:0 0 5px;color:#6b7a99;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;">Payment</p>
                                        <p style="margin:0;color:#1c2c4c;font-size:15px;font-weight:700;">
                                            {{ $order->payment_method === 'cod' ? 'Cash on Delivery' : 'Paid online' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:0 0 14px;color:#071f63;font-size:18px;font-weight:800;">Your items</h2>
                            @foreach ($items as $item)
                                @php
                                    $itemImageSrc = $item['image_path'] ? $message->embed($item['image_path']) : $item['image_url'];
                                @endphp
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #e5e7eb;">
                                    <tr>
                                        <td width="96" valign="top" style="padding:16px 16px 16px 0;">
                                            <img src="{{ $itemImageSrc }}" width="82" height="82" alt="{{ $item['name'] }}" style="display:block;width:82px;height:82px;object-fit:cover;border:1px solid #dbe6f8;border-radius:10px;background:#edf3ff;">
                                        </td>
                                        <td valign="middle" style="padding:16px 8px 16px 0;">
                                            <p style="margin:0 0 6px;color:#1c2c4c;font-size:15px;font-weight:800;line-height:1.4;">{{ $item['name'] }}</p>
                                            <p style="margin:0;color:#6b7a99;font-size:13px;line-height:1.5;">SKU: {{ $item['sku'] }} &nbsp;&middot;&nbsp; Qty: {{ $item['quantity'] }}</p>
                                        </td>
                                        <td align="right" valign="middle" style="padding:16px 0;color:#071f63;font-size:15px;font-weight:800;white-space:nowrap;">
                                            Rs. {{ number_format($item['line_total'], 2) }}
                                        </td>
                                    </tr>
                                </table>
                            @endforeach

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:10px;border-top:2px solid #0b2e8a;">
                                <tr>
                                    <td style="padding-top:14px;color:#6b7a99;font-size:14px;">Subtotal</td>
                                    <td align="right" style="padding-top:14px;color:#1c2c4c;font-size:14px;">Rs. {{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top:8px;color:#6b7a99;font-size:14px;">Shipping</td>
                                    <td align="right" style="padding-top:8px;color:#1c2c4c;font-size:14px;">Rs. {{ number_format($order->shipping, 2) }}</td>
                                </tr>
                                @if ((float) $order->cod_fee > 0)
                                    <tr>
                                        <td style="padding-top:8px;color:#6b7a99;font-size:14px;">Cash on delivery fee</td>
                                        <td align="right" style="padding-top:8px;color:#1c2c4c;font-size:14px;">Rs. {{ number_format($order->cod_fee, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding-top:8px;color:#6b7a99;font-size:14px;">Tax</td>
                                    <td align="right" style="padding-top:8px;color:#1c2c4c;font-size:14px;">Rs. {{ number_format($order->tax, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top:13px;color:#071f63;font-size:17px;font-weight:800;">Total</td>
                                    <td align="right" style="padding-top:13px;color:#e61e4d;font-size:19px;font-weight:800;">Rs. {{ number_format($order->total, 2) }}</td>
                                </tr>
                            </table>

                            <div style="margin-top:24px;padding:18px;background:#edf3ff;border:1px solid #dbe6f8;border-radius:12px;">
                                <p style="margin:0 0 7px;color:#071f63;font-size:14px;font-weight:800;">Delivery address</p>
                                <p style="margin:0;color:#1c2c4c;font-size:14px;line-height:1.6;">
                                    {{ $order->full_name }}<br>
                                    {{ $order->address }}<br>
                                    {{ collect([$order->city, $order->district, $order->state, $order->postal_code])->filter()->unique()->join(', ') }}
                                </p>
                            </div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding-top:26px;">
                                        <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 24px;background:#e61e4d;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;border-radius:999px;">View your order</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 24px;background:#071f63;color:#e8eef8;font-size:12px;line-height:1.6;">
                            Need help? Contact Ventures Mart at
                            <a href="mailto:{{ config('invoice.email') }}" style="color:#ffc107;text-decoration:none;">{{ config('invoice.email') }}</a>
                            or <a href="tel:{{ preg_replace('/[^+\d]/', '', (string) config('invoice.phone')) }}" style="color:#ffc107;text-decoration:none;">{{ config('invoice.phone') }}</a>.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
