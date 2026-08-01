<div class="card" style="margin-top: 5px;">
  <div class="section-title">Courier Details</div>
  <table class="w-full">
    <tr>
      @if ($courier['partner'])
        <td style="width: 25%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Partner</div>
          <strong>{{ $courier['partner'] }}</strong>
        </td>
      @endif
      @if ($courier['awb_number'])
        <td style="width: 25%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">AWB</div>
          <strong>{{ $courier['awb_number'] }}</strong>
        </td>
      @endif
      @if ($courier['tracking_number'])
        <td style="width: 25%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Tracking</div>
          <strong>{{ $courier['tracking_number'] }}</strong>
        </td>
      @endif
      @if ($courier['dispatched_at'])
        <td style="width: 25%; vertical-align: top;">
          <div class="muted" style="font-size: 8px;">Dispatched</div>
          <strong>{{ \Illuminate\Support\Carbon::parse($courier['dispatched_at'])->timezone(config('app.timezone'))->format('d M Y') }}</strong>
        </td>
      @endif
    </tr>
    @if ($courier['expected_delivery_at'])
      <tr>
        <td colspan="4" style="padding-top: 2px;">
          <span class="muted" style="font-size: 8px;">Expected delivery:</span>
          <strong>{{ \Illuminate\Support\Carbon::parse($courier['expected_delivery_at'])->timezone(config('app.timezone'))->format('d M Y') }}</strong>
        </td>
      </tr>
    @endif
  </table>
</div>
