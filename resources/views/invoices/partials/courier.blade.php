<div class="card" style="margin-top: 5px;">
  <div class="section-title">Courier Details</div>
  <table class="w-full">
    <tr>
      @if ($courier['partner'])
        <td style="width: 33.33%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Partner</div>
          <strong>{{ $courier['partner'] }}</strong>
        </td>
      @endif
      @if ($courier['tracking_id'])
        <td style="width: 33.33%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Tracking ID (AWB)</div>
          <strong style="word-break: break-all;">{{ $courier['tracking_id'] }}</strong>
        </td>
      @endif
      @if ($courier['shipment_status'])
        <td style="width: 33.33%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Shipment status</div>
          <strong>{{ $courier['shipment_status'] }}</strong>
        </td>
      @endif
    </tr>
    <tr>
      @if ($courier['dispatched_at'])
        <td style="padding-top: 3px; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Dispatched</div>
          <strong>{{ \Illuminate\Support\Carbon::parse($courier['dispatched_at'])->timezone(config('app.timezone'))->format('d M Y') }}</strong>
        </td>
      @endif
      @if ($courier['expected_delivery_at'])
        <td style="padding-top: 3px; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Expected delivery</div>
          <strong>{{ \Illuminate\Support\Carbon::parse($courier['expected_delivery_at'])->timezone(config('app.timezone'))->format('d M Y') }}</strong>
        </td>
      @endif
      @if ($courier['tracking_url'])
        <td style="padding-top: 3px; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Track online</div>
          <a href="{{ $courier['tracking_url'] }}" style="color: #0b2e8a; font-weight: bold;">Open tracking</a>
        </td>
      @endif
    </tr>
  </table>
</div>
