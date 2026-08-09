<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your order is ready to track</title>
</head>
<body style="margin:0;background:#f5f5f5;color:#242424;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Tracking ID {{ $shipment['tracking_id'] }} is now available for order {{ $order->number }}.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e8e8e8;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:28px 24px 22px;background:#fff7f7;">
                            <img src="{{ $logoUrl }}" width="170" alt="Ventures Mart" style="display:block;max-width:170px;height:auto;border:0;">
                            <p style="margin:22px 0 6px;color:#d71920;font-size:13px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;">Ready to track</p>
                            <h1 style="margin:0;color:#1f1f1f;font-size:28px;line-height:1.25;">Your order is on its way</h1>
                            <p style="margin:12px 0 0;color:#606060;font-size:15px;line-height:1.6;">
                                Hi {{ $order->full_name }}, your shipment details are now available.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fafafa;border:1px solid #ececec;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px;border-bottom:1px solid #ececec;">
                                        <p style="margin:0 0 5px;color:#777;font-size:12px;text-transform:uppercase;">Order number</p>
                                        <p style="margin:0;color:#222;font-size:16px;font-weight:700;">{{ $order->number }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px;border-bottom:1px solid #ececec;">
                                        <p style="margin:0 0 5px;color:#777;font-size:12px;text-transform:uppercase;">Courier</p>
                                        <p style="margin:0;color:#222;font-size:16px;font-weight:700;">{{ $shipment['partner'] ?: 'Shiprocket courier' }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 5px;color:#777;font-size:12px;text-transform:uppercase;">Tracking ID / AWB</p>
                                        <p style="margin:0;color:#d71920;font-size:19px;font-weight:800;letter-spacing:0.02em;">{{ $shipment['tracking_id'] }}</p>
                                    </td>
                                </tr>
                            </table>

                            @if ($order->expected_delivery_at)
                                <p style="margin:18px 0 0;color:#555;font-size:14px;line-height:1.6;text-align:center;">
                                    Expected delivery:
                                    <strong style="color:#222;">{{ $order->expected_delivery_at->timezone('Asia/Kolkata')->format('j M Y') }}</strong>
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding-top:24px;">
                                        <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 26px;background:#d71920;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;border-radius:999px;">Track your order</a>
                                    </td>
                                </tr>
                            </table>

                            @if ($shipment['tracking_url'])
                                <p style="margin:16px 0 0;text-align:center;color:#777;font-size:12px;">
                                    You can also
                                    <a href="{{ $shipment['tracking_url'] }}" style="color:#d71920;">open courier tracking</a>.
                                </p>
                            @endif

                            <p style="margin:24px 0 0;color:#777;font-size:12px;line-height:1.6;text-align:center;">
                                Tracking may take a short time to show the first courier scan after AWB assignment.
                            </p>
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
