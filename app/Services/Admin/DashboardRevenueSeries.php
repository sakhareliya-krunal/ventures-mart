<?php

namespace App\Services\Admin;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardRevenueSeries
{
    public const RANGES = ['day', 'week', 'month', 'year'];

    /**
     * @return array{
     *   revenue_range: string,
     *   revenue_period_label: string,
     *   revenue_period_total: float,
     *   revenue_period_orders: int,
     *   revenue_series: list<array{key: string, label: string, total: float}>,
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
     * @return list<array{key: string, label: string, total: float}>
     */
    private function daySeries(): array
    {
        $start = Carbon::today()->startOfDay();
        $totals = $this->groupedTotals('hour', $start);
        $series = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $series[] = [
                'key' => $key,
                'label' => (string) $hour,
                'total' => (float) ($totals[$key] ?? $totals[(string) $hour] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, total: float}>
     */
    private function weekSeries(): array
    {
        $start = Carbon::today()->subDays(6)->startOfDay();
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
        $start = Carbon::today()->subDays(29)->startOfDay();
        $totals = $this->groupedTotals('day', $start);
        $series = [];

        for ($i = 0; $i < 30; $i++) {
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
        $start = Carbon::today()->startOfMonth()->subMonths(11)->startOfDay();
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
                'day' => Carbon::today()->startOfDay(),
                'month' => Carbon::today()->subDays(29)->startOfDay(),
                'year' => Carbon::today()->startOfMonth()->subMonths(11)->startOfDay(),
                default => Carbon::today()->subDays(6)->startOfDay(),
            },
        ];
    }

    private function labelFor(string $range): string
    {
        return match ($range) {
            'day' => 'Today',
            'month' => 'Last 30 days',
            'year' => 'Last 12 months',
            default => 'Last 7 days',
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
