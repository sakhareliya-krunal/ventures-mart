<table class="items">
  <thead>
    <tr>
      <th style="width: 4%;" class="text-center">#</th>
      <th style="width: 7%;" class="text-center">Image</th>
      <th>Product</th>
      <th style="width: 9%;">SKU</th>
      <th style="width: 7%;">HSN</th>
      <th style="width: 5%;" class="text-right">Qty</th>
      <th style="width: 10%;" class="text-right">Unit Price</th>
      <th style="width: 9%;" class="text-right">Discount</th>
      <th style="width: 9%;" class="text-right">Tax</th>
      <th style="width: 10%;" class="text-right">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($lines as $index => $line)
      <tr>
        <td class="text-center">{{ $index + 1 }}</td>
        <td class="text-center">
          @if (!empty($line['image_data_uri']))
            <img class="thumb" src="{{ $line['image_data_uri'] }}" width="32" height="32" alt="">
          @else
            <div class="thumb-placeholder">N/A</div>
          @endif
        </td>
        <td>
          <div class="product-name">{{ $line['name'] }}</div>
        </td>
        <td class="muted">{{ $line['sku'] ?: '—' }}</td>
        <td>{{ $line['hsn'] }}</td>
        <td class="text-right">{{ $line['quantity'] }}</td>
        <td class="text-right nowrap">₹{{ number_format($line['unit_price'], 2) }}</td>
        <td class="text-right nowrap">₹{{ number_format($line['discount'] ?? 0, 2) }}</td>
        <td class="text-right nowrap">₹{{ number_format($line['tax'], 2) }}</td>
        <td class="text-right nowrap"><strong>₹{{ number_format($line['total'], 2) }}</strong></td>
      </tr>
    @endforeach
  </tbody>
</table>
