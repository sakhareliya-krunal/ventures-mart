<table class="w-full" style="margin-top: 6px;">
  <tr>
    <td style="width: 72%; vertical-align: middle;">
      <div class="footer-thanks">Thank you for shopping with {{ $seller['trade_name'] }}!</div>
      <div class="muted" style="font-size: 8.5px;">
        Need help? Write to
        @if ($seller['email'])
          <strong style="color:#1F2937;">{{ $seller['email'] }}</strong>
        @endif
        @if ($seller['phone'])
          or call <strong style="color:#1F2937;">{{ $seller['phone'] }}</strong>
        @endif
      </div>
      @if (!empty($seller['website']))
        <div class="muted" style="font-size: 8.5px;">{{ $seller['website'] }}</div>
      @endif
    </td>
    <td style="width: 28%; text-align: center; vertical-align: middle;">
      @if ($qr_data_uri)
        <img class="qr" src="{{ $qr_data_uri }}" width="56" height="56" alt="Order QR">
        <div class="muted" style="font-size: 7px;">Scan to view order</div>
      @endif
    </td>
  </tr>
</table>
