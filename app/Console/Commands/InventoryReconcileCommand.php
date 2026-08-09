<?php

namespace App\Console\Commands;

use App\Models\InventoryAuditFlag;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;

class InventoryReconcileCommand extends Command
{
    protected $signature = 'inventory:reconcile
                            {--check : Report mismatches without attempting repair}
                            {--repair : Create reconciliation ledger entries for detected issues}';

    protected $description = 'Compare inventory balances, reservations, and products.stock projections';

    public function handle(InventoryService $inventory): int
    {
        $repair = (bool) $this->option('repair');

        if ($repair && $this->option('check')) {
            $this->error('Use either --check or --repair, not both.');

            return self::FAILURE;
        }

        if (! $repair && ! $this->option('check')) {
            $this->info('Running report-only reconciliation (--check). Pass --repair to record correction entries.');
        }

        $issueCount = 0;
        $repairedCount = 0;
        $productsChecked = 0;

        Product::query()->orderBy('id')->chunkById(100, function ($products) use (
            $inventory,
            $repair,
            &$issueCount,
            &$repairedCount,
            &$productsChecked
        ): void {
            foreach ($products as $product) {
                $result = $inventory->reconcile($product, repair: $repair);
                $productsChecked++;

                foreach ($result['issues'] as $issue) {
                    $issueCount++;
                    $this->warn("[product:{$product->id}] {$issue['code']}: {$issue['message']}");
                }

                if ($result['repaired']) {
                    $repairedCount++;
                }
            }
        });

        $openFlags = InventoryAuditFlag::query()->whereNull('resolved_at')->count();

        $this->newLine();
        $this->info("Checked {$productsChecked} product(s).");
        $this->info("Found {$issueCount} issue(s).");

        if ($openFlags > 0) {
            $this->warn("{$openFlags} unresolved inventory audit flag(s) require manual review.");
        }

        if ($repair) {
            $this->info("Recorded reconciliation entries for {$repairedCount} product(s).");
        }

        return $repair || $issueCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
