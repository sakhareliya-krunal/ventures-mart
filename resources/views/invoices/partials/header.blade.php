<table class="w-full" style="margin-bottom: 2px;">
  <tr>
    <td style="width: 42%; vertical-align: middle;">
      @if ($logo_data_uri)
        <img
          src="{{ $logo_data_uri }}"
          alt="{{ $seller['trade_name'] }}"
          width="194"
          height="37"
          style="width: 194px; height: 37px; display: block; border: 0;"
        >
      @else
        <div style="color:#052B68; font-size: 15px; font-weight: bold;">{{ $seller['trade_name'] }}</div>
      @endif
    </td>
    <td style="width: 58%; text-align: right; vertical-align: top; font-size: 9px; line-height: 1.35;">
      @if ($seller['legal_name'])
        <div style="color:#052B68; font-size: 11px; font-weight: bold;">{{ $seller['legal_name'] }}</div>
      @endif
      @if ($seller['address_line1'])
        <div>{{ $seller['address_line1'] }}</div>
      @endif
      @if ($seller['address_line2'])
        <div>{{ $seller['address_line2'] }}</div>
      @endif
      <div>
        {{ trim(collect([$seller['city'], $seller['state']])->filter()->implode(', ')) }}
        @if ($seller['postal_code']) – {{ $seller['postal_code'] }}@endif
        @if (!empty($seller['country'])), {{ $seller['country'] }}@endif
      </div>
      <div>
        @if ($seller['phone']){{ $seller['phone'] }}@endif
        @if ($seller['phone'] && $seller['email']) · @endif
        @if ($seller['email']){{ $seller['email'] }}@endif
      </div>
      @if (!empty($seller['website']))
        <div>{{ $seller['website'] }}</div>
      @endif
      @if ($has_gstin)
        <div style="margin-top: 2px;">
          <strong>GSTIN:</strong> {{ $seller['gstin'] }}
          @if ($seller['state'])
            · {{ $seller['state'] }}
          @endif
          @if ($seller['state_code'])
            ({{ $seller['state_code'] }})
          @endif
        </div>
      @endif
    </td>
  </tr>
</table>
