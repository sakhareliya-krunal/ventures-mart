<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order confirmed</title>
</head>
<body style="margin:0;background:#f5f5f5;color:#242424;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Your Ventures Mart order {{ $order->number }} is confirmed. Your invoice is attached.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e8e8e8;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:28px 24px 20px;background:#fff7f7;">
                            <img src="{{ $logoUrl }}" width="170" alt="Ventures Mart" style="display:block;max-width:170px;height:auto;border:0;">
                            <p style="margin:22px 0 6px;color:#d71920;font-size:13px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;">Order confirmed</p>
                            <h1 style="margin:0;color:#1f1f1f;font-size:28px;line-height:1.25;">Thank you, {{ $order->full_name }}!</h1>
                            <p style="margin:12px 0 0;color:#606060;font-size:15px;line-height:1.6;">
                                We have received your order. Your customer invoice is attached to this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:22px;background:#fafafa;border:1px solid #ececec;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 5px;color:#777;font-size:12px;text-transform:uppercase;">Order number</p>
                                        <p style="margin:0;color:#222;font-size:16px;font-weight:700;">{{ $order->number }}</p>
                                    </td>
                                    <td align="right" style="padding:16px;">
                                        <p style="margin:0 0 5px;color:#777;font-size:12px;text-transform:uppercase;">Payment</p>
                                        <p style="margin:0;color:#222;font-size:15px;font-weight:700;">
                                            {{ $order->payment_method === 'cod' ? 'Cash on Delivery' : 'Paid online' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:0 0 14px;color:#222;font-size:18px;">Your items</h2>
                            @foreach ($items as $item)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #eeeeee;">
                                    <tr>
                                        <td width="82" valign="top" style="padding:16px 14px 16px 0;">
                                            <img src="{{ $item['image_url'] }}" width="68" height="68" alt="{{ $item['name'] }}" style="display:block;width:68px;height:68px;object-fit:cover;border:1px solid #e8e8e8;border-radius:10px;background:#f4f4f4;">
                                        </td>
                                        <td valign="middle" style="padding:16px 8px 16px 0;">
                                            <p style="margin:0 0 5px;color:#222;font-size:15px;font-weight:700;line-height:1.4;">{{ $item['name'] }}</p>
                                            <p style="margin:0;color:#777;font-size:13px;">SKU: {{ $item['sku'] }} &nbsp;·&nbsp; Qty: {{ $item['quantity'] }}</p>
                                        </td>
                                        <td align="right" valign="middle" style="padding:16px 0;color:#222;font-size:15px;font-weight:700;white-space:nowrap;">
                                            ₹{{ number_format($item['line_total'], 2) }}
                                        </td>
                                    </tr>
                                </table>
                            @endforeach

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:8px;border-top:2px solid #eeeeee;">
                                <tr>
                                    <td style="padding-top:14px;color:#666;font-size:14px;">Subtotal</td>
                                    <td align="right" style="padding-top:14px;color:#222;font-size:14px;">₹{{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top:8px;color:#666;font-size:14px;">Shipping</td>
                                    <td align="right" style="padding-top:8px;color:#222;font-size:14px;">₹{{ number_format($order->shipping, 2) }}</td>
                                </tr>
                                @if ((float) $order->cod_fee > 0)
                                    <tr>
                                        <td style="padding-top:8px;color:#666;font-size:14px;">Cash on delivery fee</td>
                                        <td align="right" style="padding-top:8px;color:#222;font-size:14px;">₹{{ number_format($order->cod_fee, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding-top:8px;color:#666;font-size:14px;">Tax</td>
                                    <td align="right" style="padding-top:8px;color:#222;font-size:14px;">₹{{ number_format($order->tax, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top:12px;color:#222;font-size:17px;font-weight:700;">Total</td>
                                    <td align="right" style="padding-top:12px;color:#d71920;font-size:18px;font-weight:700;">₹{{ number_format($order->total, 2) }}</td>
                                </tr>
                            </table>

                            <div style="margin-top:24px;padding:18px;background:#fafafa;border-radius:12px;">
                                <p style="margin:0 0 7px;color:#222;font-size:14px;font-weight:700;">Delivery address</p>
                                <p style="margin:0;color:#666;font-size:14px;line-height:1.6;">
                                    {{ $order->full_name }}<br>
                                    {{ $order->address }}<br>
                                    {{ collect([$order->city, $order->district, $order->state, $order->postal_code])->filter()->unique()->join(', ') }}
                                </p>
                            </div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding-top:26px;">
                                        <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 24px;background:#d71920;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;border-radius:999px;">View your order</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 24px;background:#222;color:#cccccc;font-size:12px;line-height:1.6;">
                            Need help? Contact Ventures Mart at
                            <a href="mailto:{{ config('invoice.email') }}" style="color:#ffffff;">{{ config('invoice.email') }}</a>
                            or <a href="tel:{{ preg_replace('/[^+\d]/', '', (string) config('invoice.phone')) }}" style="color:#ffffff;">{{ config('invoice.phone') }}</a>.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
