<?php

namespace App\Console\Commands;

use App\Enums\InventoryReservationState;
use App\Models\InventoryAuditFlag;
use App\Models\InventoryOutboxMessage;
use App\Models\InventoryReservation;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InventoryRolloutCheckCommand extends Command
{
    protected $signature = 'inventory:rollout-check {--json : Emit machine-readable output}';

    protected $description = 'Verify inventory schema, projections, exceptions, and operational queues before rollout';

    public function handle(InventoryService $inventory): int
    {
        $checks = [];
        $requiredTables = [
            'inventory_balances',
            'inventory_ledger_entries',
            'inventory_reservations',
            'inventory_returns',
            'inventory_outbox_messages',
            'payment_webhook_events',
        ];

        foreach ($requiredTables as $table) {
            $checks["table:{$table}"] = Schema::hasTable($table) ? 0 : 1;
        }

        $checks['products_without_balance'] = Product::query()
            ->whereDoesntHave('inventoryBalance')
            ->count();
        $checks['unresolved_audit_flags'] = InventoryAuditFlag::query()
            ->whereNull('resolved_at')
            ->count();
        $checks['expired_active_reservations'] = InventoryReservation::query()
            ->where('state', InventoryReservationState::Reserved->value)
            ->where('expires_at', '<=', now())
            ->count();
        $checks['failed_webhook_events'] = PaymentWebhookEvent::query()
            ->where('status', 'failed')
            ->count();
        $checks['failed_outbox_messages'] = InventoryOutboxMessage::query()
            ->whereNull('processed_at')
            ->where('attempts', '>', 0)
            ->count();

        $variance = 0;
        Product::query()->orderBy('id')->each(function (Product $product) use ($inventory, &$variance): void {
            $variance += count($inventory->reconcile($product)['issues']);
        });
        $checks['reconciliation_variances'] = $variance;

        $blockers = collect($checks)->filter(fn (int $count) => $count > 0);
        $payload = [
            'ready' => $blockers->isEmpty(),
            'database_driver' => $this->laravel['db']->connection()->getDriverName(),
            'products' => Product::query()->count(),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check => $count) {
                $this->line(sprintf('[%s] %s: %d', $count === 0 ? 'PASS' : 'FAIL', $check, $count));
            }
            $this->newLine();
            $blockers->isEmpty()
                ? $this->info('Inventory rollout checks passed.')
                : $this->error('Inventory rollout is blocked until failed checks are resolved.');
        }

        return $blockers->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
