# Shiprocket fulfillment

Confirmed COD orders and verified Razorpay orders can be sent to Shiprocket
automatically. The queued workflow creates the custom order, selects a
serviceable courier, assigns an AWB, and schedules pickup.

## Enable the integration

1. Rotate any API-user password that has been exposed.
2. Add the new API-user credentials to the deployment environment:

   ```dotenv
   SHIPROCKET_ENABLED=false
   SHIPROCKET_EMAIL=
   SHIPROCKET_PASSWORD=
   SHIPROCKET_PICKUP_LOCATION=
   ```

   Leave `SHIPROCKET_PICKUP_LOCATION` blank to use the account's single active
   primary pickup location.
3. Run the migrations:

   ```bash
   php artisan migrate
   ```

4. Validate credentials and pickup configuration:

   ```bash
   php artisan shiprocket:validate
   ```

5. Ensure both the queue worker and scheduler are continuously running:

   ```bash
   php artisan queue:work
   php artisan schedule:work
   ```

6. Set `SHIPROCKET_ENABLED=true` and restart long-running workers:

   ```bash
   php artisan queue:restart
   ```

## Package measurements

Products support per-unit weight, length, breadth, and height in the admin
product form. Missing values use these environment defaults:

```dotenv
SHIPROCKET_FALLBACK_WEIGHT_KG=0.5
SHIPROCKET_FALLBACK_LENGTH_CM=20
SHIPROCKET_FALLBACK_BREADTH_CM=15
SHIPROCKET_FALLBACK_HEIGHT_CM=10
```

Measurements are copied to order items at checkout. For multi-item orders,
weight is summed by quantity, length and breadth use the maximum item values,
and height is summed by quantity.

## Operations

- Failed or incomplete fulfillment can be retried from the admin order detail.
- Tracking can be synced manually from the same page.
- `php artisan shiprocket:sync` queues tracking refreshes for active shipments.
- The scheduler runs that command every 30 minutes.
- Cancelling an admin order queues cancellation in Shiprocket.
- Shiprocket-managed courier fields are read-only in admin to prevent local and
  remote values from diverging.

The integration is resumable: a retry after an AWB or pickup failure reuses the
stored Shiprocket order and shipment identifiers rather than creating a
duplicate order.
