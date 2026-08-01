<table class="w-full">
  <tr>
    <td style="width: 54%; vertical-align: top;"></td>
    <td style="width: 46%; vertical-align: top;">
      <div class="card">
        <table class="summary w-full">
          <tr>
            <td>Subtotal</td>
            <td>₹{{ number_format((float) $order->subtotal, 2) }}</td>
          </tr>
          <tr>
            <td>Discount</td>
            <td>₹{{ number_format((float) ($discount_total ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td>Delivery</td>
            <td>{{ (float) $order->shipping > 0 ? '₹'.number_format((float) $order->shipping, 2) : 'Free' }}</td>
          </tr>
          @if ((float) $order->cod_fee > 0)
            <tr>
              <td>COD charge</td>
              <td>₹{{ number_format((float) $order->cod_fee, 2) }}</td>
            </tr>
          @endif
          @if ($use_igst)
            <tr>
              <td>IGST ({{ number_format($tax_rate_percent, 1) }}%)</td>
              <td>₹{{ number_format((float) $order->igst, 2) }}</td>
            </tr>
          @else
            <tr>
              <td>CGST ({{ number_format($tax_rate_percent / 2, 2) }}%)</td>
              <td>₹{{ number_format((float) $order->cgst, 2) }}</td>
            </tr>
            <tr>
              <td>SGST ({{ number_format($tax_rate_percent / 2, 2) }}%)</td>
              <td>₹{{ number_format((float) $order->sgst, 2) }}</td>
            </tr>
          @endif
        </table>
        <table class="w-full grand-bar">
          <tr>
            <td style="vertical-align: middle;">Grand Total</td>
            <td class="text-right" style="vertical-align: middle;">₹{{ number_format((float) $order->total, 2) }}</td>
          </tr>
        </table>
      </div>
    </td>
  </tr>
</table>
