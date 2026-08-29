<?php

namespace App\Services\Admin;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardRevenueSeries
{
    private const STORE_TIMEZONE = 'Asia/Kolkata';

    public const RANGES = ['day', 'week', 'month', 'year'];

    /**
     * @return array{
     *   revenue_range: string,
     *   revenue_period_label: string,
     *   revenue_period_total: float,
     *   revenue_period_orders: int,
     *   revenue_series: list<array{key: string, label: string, total: float, orders?: list<array{id: int, number: string, created_at: string, created_at_display: string, total: float}>}>,
     *   revenue_last_7_days: list<array{date: string, label: string, total: float}>
     * }
     */
    public function build(?string $range = null): array
    {
        $range = $this->normalizeRange($range);
        $series = match ($range) {
            'day' => $this->daySeries(),
            'month' => $this->monthSeries(),
            'year' => $this->yearSeries(),
            default => $this->weekSeries(),
        };

        $weekSeries = $range === 'week' ? $series : $this->weekSeries();
        $periodTotal = array_sum(array_column($series, 'total'));
        $window = $this->windowFor($range);

        return [
            'revenue_range' => $range,
            'revenue_period_label' => $this->labelFor($range),
            'revenue_period_total' => round((float) $periodTotal, 2),
            'revenue_period_orders' => $this->orderCount($window['start']),
            'revenue_series' => $series,
            'revenue_last_7_days' => array_map(static fn (array $point) => [
                'date' => $point['key'],
                'label' => $point['label'],
                'total' => $point['total'],
            ], $weekSeries),
        ];
    }

    public function normalizeRange(?string $range): string
    {
        $range = strtolower(trim((string) $range));
        if ($range === '') {
            return 'week';
        }

        if (! in_array($range, self::RANGES, true)) {
            throw ValidationException::withMessages([
                'range' => ['Range must be one of: day, week, month, year.'],
            ]);
        }

        return $range;
    }

    /**
     * @return list<array{key: string, label: string, total: float, orders: list<array{id: int, number: string, created_at: string, created_at_display: string, total: float}>}>
     */
    private function daySeries(): array
    {
        $start = Carbon::now(self::STORE_TIMEZONE)->startOfDay();
        $queryStart = $start->copy()->setTimezone(config('app.timezone', 'UTC'));
        $queryEnd = $start->copy()->endOfDay()->setTimezone(config('app.timezone', 'UTC'));

        $ordersByHour = Order::query()
            ->where('status', '!=', 'Cancelled')
            ->whereBetween('created_at', [$queryStart, $queryEnd])
            ->orderBy('created_at')
            ->get(['id', 'number', 'created_at', 'total'])
            ->groupBy(fn (Order $order) => $order->created_at->copy()->timezone(self::STORE_TIMEZONE)->format('H'));
        $series = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $orders = $ordersByHour->get($key, collect());
            $series[] = [
                'key' => $key,
                'label' => $start->copy()->addHours($hour)->format('g A'),
                'total' => round((float) $orders->sum('total'), 2),
                'orders' => $orders->map(fn (Order $order) => [
                    'id' => (int) $order->id,
                    'number' => (string) $order->number,
                    'created_at' => $order->created_at->toIso8601String(),
                    'created_at_display' => $order->created_at->copy()->timezone(self::STORE_TIMEZONE)->format('g:i:s A'),
                    'total' => (float) $order->total,
                ])->values()->all(),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, total: float}>
     */
    private function weekSeries(): array
    {
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $totals = $this->groupedTotals('day', $start);
        $series = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $series[] = [
                'key' => $key,
                'label' => $day->format('D'),
                'total' => (float) ($totals[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, total: float}>
     */
    private function monthSeries(): array
    {
        $start = Carbon::now()->startOfMonth();
        $days = (int) $start->daysInMonth;
        $totals = $this->groupedTotals('day', $start);
        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $series[] = [
                'key' => $key,
                'label' => $day->format('j'),
                'total' => (float) ($totals[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, total: float}>
     */
    private function yearSeries(): array
    {
        $start = Carbon::now()->startOfYear();
        $totals = $this->groupedTotals('month', $start);
        $series = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $series[] = [
                'key' => $key,
                'label' => $month->format('M'),
                'total' => (float) ($totals[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<string, float>
     */
    private function groupedTotals(string $bucket, Carbon $start): array
    {
        $driver = DB::connection()->getDriverName();
        $bucketSql = $this->bucketExpression($driver, $bucket);

        return Order::query()
            ->select(DB::raw("{$bucketSql} as bucket"), DB::raw('SUM(total) as total'))
            ->where('status', '!=', 'Cancelled')
            ->where('created_at', '>=', $start)
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    private function bucketExpression(string $driver, string $bucket): string
    {
        if ($driver === 'sqlite') {
            return match ($bucket) {
                'hour' => "strftime('%H', created_at)",
                'month' => "strftime('%Y-%m', created_at)",
                default => "strftime('%Y-%m-%d', created_at)",
            };
        }

        return match ($bucket) {
            'hour' => 'LPAD(HOUR(created_at), 2, \'0\')',
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            default => 'DATE(created_at)',
        };
    }

    /**
     * @return array{start: Carbon}
     */
    private function windowFor(string $range): array
    {
        return [
            'start' => match ($range) {
                'day' => Carbon::now(self::STORE_TIMEZONE)->startOfDay()->setTimezone(config('app.timezone', 'UTC')),
                'month' => Carbon::now()->startOfMonth(),
                'year' => Carbon::now()->startOfYear(),
                default => Carbon::now()->startOfWeek(Carbon::MONDAY),
            },
        ];
    }

    private function labelFor(string $range): string
    {
        return match ($range) {
            'day' => 'Today',
            'month' => Carbon::now()->format('F Y'),
            'year' => (string) Carbon::now()->year,
            default => 'This week',
        };
    }

    private function orderCount(Carbon $start): int
    {
        return (int) Order::query()
            ->where('status', '!=', 'Cancelled')
            ->where('created_at', '>=', $start)
            ->count();
    }
}
