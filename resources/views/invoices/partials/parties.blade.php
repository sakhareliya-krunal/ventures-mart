<table class="w-full" style="margin-bottom: 4px;">
  <tr>
    <td style="width: 49.5%; padding-right: 3px; vertical-align: top;">
      <div class="card">
        <div class="section-title">Bill To</div>
        <strong>{{ $order->full_name }}</strong><br>
        @if ($order->phone)<span class="muted">{{ $order->phone }}</span><br>@endif
        @if ($order->email)<span class="muted">{{ $order->email }}</span><br>@endif
        {{ $order->address }}<br>
        {{ collect([$order->city, $order->district, $order->state])->filter()->implode(', ') }}
        {{ $order->postal_code }}
      </div>
    </td>
    <td style="width: 1%;"></td>
    <td style="width: 49.5%; padding-left: 3px; vertical-align: top;">
      <div class="card">
        <div class="section-title">Ship To</div>
        <strong>{{ $order->full_name }}</strong><br>
        @if ($order->phone)<span class="muted">{{ $order->phone }}</span><br>@endif
        @if ($order->email)<span class="muted">{{ $order->email }}</span><br>@endif
        {{ $order->address }}<br>
        {{ collect([$order->city, $order->district, $order->state])->filter()->implode(', ') }}
        {{ $order->postal_code }}
      </div>
    </td>
  </tr>
</table>

@if ($has_gstin)
  <div class="muted" style="font-size: 8px; margin-bottom: 3px;">
    Place of supply: {{ $order->state }}
    · Seller state: {{ $order->seller_state }}
    · Reverse charge: No
  </div>
@endif
