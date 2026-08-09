# Inventory rollout

Run inventory changes in these stages. Do not combine schema deployment and write cutover on a busy production database.

1. Put checkout and admin inventory writes into maintenance mode.
2. Back up the database and deploy migrations:
   `php artisan migrate --force`
3. Review the legacy backfill:
   `php artisan inventory:reconcile --check`
   `php artisan inventory:rollout-check`
4. Resolve every inventory audit flag in Admin → Inventory → Audit flags. Repairs must use audited adjustments or `inventory:reconcile --repair`.
5. Run verification:
   `php artisan test`
   `php vendor/bin/phpunit -c phpunit.mysql.xml`
   `npm run test:ui`
   `npm run build`
6. Restart the queue worker and scheduler. Confirm these commands are executing:
   `inventory:expire-reservations`, `inventory:dispatch-outbox`, `inventory:reconcile --check`.
7. Remove maintenance mode, place one COD and one Razorpay test order, and verify reservation, commitment, Shiprocket handoff, cancellation, and return records.
8. Monitor `InventoryHold` orders, unresolved audit flags, failed webhook events, and failed outbox messages before retiring the `products.stock` compatibility projection.

The MySQL race suite creates and refreshes only the dedicated `ventures_mart_inventory_test` database. Its safety guard refuses any database name that does not end in `_test`.
