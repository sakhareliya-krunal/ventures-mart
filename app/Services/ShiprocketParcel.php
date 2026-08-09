<?php

namespace App\Services;

use App\Models\Order;

class ShiprocketParcel
{
    /**
     * @return array{weight: float, length: float, breadth: float, height: float}
     */
    public function forOrder(Order $order): array
    {
        $order->loadMissing('items');

        $weight = 0.0;
        $length = 0.0;
        $breadth = 0.0;
        $height = 0.0;

        foreach ($order->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $itemWeight = $this->measurement($item->weight_kg, 'fallback_weight_kg', 0.5);
            $itemLength = $this->measurement($item->length_cm, 'fallback_length_cm', 20);
            $itemBreadth = $this->measurement($item->breadth_cm, 'fallback_breadth_cm', 15);
            $itemHeight = $this->measurement($item->height_cm, 'fallback_height_cm', 10);

            $weight += $itemWeight * $quantity;
            $length = max($length, $itemLength);
            $breadth = max($breadth, $itemBreadth);
            $height += $itemHeight * $quantity;
        }

        return [
            'weight' => round(max(0.001, $weight), 3),
            'length' => round(max(0.51, $length), 2),
            'breadth' => round(max(0.51, $breadth), 2),
            'height' => round(max(0.51, $height), 2),
        ];
    }

    private function measurement(mixed $value, string $configKey, float $fallback): float
    {
        $number = (float) $value;

        return $number > 0
            ? $number
            : max(0.001, (float) config('services.shiprocket.'.$configKey, $fallback));
    }
}
