<div class="card" style="margin-bottom: 5px;">
  <table class="w-full">
    <tr>
      <td style="width: 25%; vertical-align: top;">
        <div class="muted" style="font-size: 8px;">Invoice No.</div>
        <strong>{{ $order->invoice_number }}</strong>
      </td>
      <td style="width: 25%; vertical-align: top;">
        <div class="muted" style="font-size: 8px;">Invoice Date</div>
        <strong>{{ \Illuminate\Support\Carbon::parse($issued_at)->timezone(config('app.timezone'))->format('d M Y') }}</strong>
      </td>
      <td style="width: 25%; vertical-align: top;">
        <div class="muted" style="font-size: 8px;">Order ID</div>
        <strong>{{ $order->number }}</strong>
      </td>
      <td style="width: 25%; vertical-align: top; text-align: right;">
        <div class="muted" style="font-size: 8px;">Payment</div>
        <div><strong>{{ $payment_method_label }}</strong></div>
        <div style="margin-top: 2px; text-align: right;">
          <table class="badge" style="background: {{ $payment_badge['bg'] }}; color: {{ $payment_badge['fg'] }}; margin-left: auto;">
            <tr>
              <td style="vertical-align: middle; text-align: center; padding: 4px 8px; line-height: 1; color: {{ $payment_badge['fg'] }};">
                {{ $payment_badge['label'] }}
              </td>
            </tr>
          </table>
        </div>
      </td>
    </tr>
  </table>
</div>
