# Shiprocket fulfillment

Confirmed COD orders and verified Razorpay orders can be sent to Shiprocket
automatically. The queued workflow creates the custom order, selects a
serviceable courier, assigns an AWB, and schedules pickup.

## Enable the integration

1. Rotate any API-user password that has been exposed.
2. Add the new API-user credentials to the deployment environment:

   ```dotenv
   SHIPROCKET_ENABLED=false
   ORDER_FULFILLMENT_METHOD=shiprocket
   SHIPROCKET_EMAIL=
   SHIPROCKET_PASSWORD=
   SHIPROCKET_PICKUP_LOCATION=
   SHIPROCKET_WEBHOOK_TOKEN=
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

7. In Shiprocket, open **Settings > API > Webhooks** and configure:

   - URL: `https://your-domain.example/api/fulfillment/provider-update`
   - Method: `POST`
   - Security token: the same strong random value used for
     `SHIPROCKET_WEBHOOK_TOKEN`

   Shiprocket sends the token in the `x-api-key` header. Webhooks are the
   primary tracking update path; the scheduled sync remains a reconciliation
   fallback.

## Fulfillment ownership

Every order stores an explicit `fulfillment_method`:

- `shiprocket` orders are assigned, tracked, and dated automatically.
- `manual` orders expose editable courier and delivery-date fields to admins.

Checkout requests cannot choose this value. `ORDER_FULFILLMENT_METHOD` is a
server-side default, and Shiprocket automatically falls back to manual when
the integration is disabled.

An admin can switch a Shiprocket order to manual fulfillment only before AWB
assignment, pickup scheduling, or courier handoff. If a remote order already
exists, Shiprocket cancellation must succeed before the local method changes.
All attempts and fulfillment transitions are retained in the order's audit
history.

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
- `php artisan shiprocket:sync` queues fallback tracking refreshes for stale,
  active Shiprocket shipments.
- The scheduler runs that command every 30 minutes.
- Cancelling an admin order queues cancellation in Shiprocket.
- Customer cancellations use the same service when the order is still pre-ship
  with no AWB/pickup. Prepaid Razorpay cancels set `payment_status` to
  `refund_pending` until an admin marks the order refunded.
- Shiprocket-managed courier fields are read-only in admin to prevent local and
  remote values from diverging.
- AWB assignment queues one branded customer tracking email. Webhook and
  scheduled-sync updates recover the notification if the AWB arrived outside
  the initial fulfillment request.
- Keep `php artisan queue:work` running continuously in production; otherwise
  Shiprocket cancel jobs, cancellation emails, confirmation mail, and tracking
  emails wait until a worker processes the queue.

### Customer emails (website vs Shiprocket)

- **Website order confirmation** (`OrderConfirmation`) is queued after checkout
  (COD immediately; Razorpay after payment) and runs **after the HTTP response**
  so it does not depend on `queue:work` for delivery. It still requires a real
  mail transport (`MAIL_MAILER=smtp` or similar). `MAIL_MAILER=log` only writes
  to `storage/logs` and never reaches the customer inbox.
- **Website tracking mail** (`ShipmentTracking`) still uses the queue after AWB
  assignment — keep a queue worker running for that path.
- **Shiprocket’s own buyer emails** are separate. Creating a remote order always
  sends `billing_email` (required by their API). To stop Shiprocket-branded mail
  and rely on Ventures Mart templates only, disable notifications in the
  Shiprocket dashboard: **Settings → Notifications → Buyer Communication**,
  then save. There is no API flag in this app to turn those off per order.

The integration is resumable: a retry after an AWB or pickup failure reuses the
stored Shiprocket order and shipment identifiers rather than creating a
duplicate order.

### Pickup and tracking reconciliation

- Pickup scheduling is idempotent. If Shiprocket responds that pickup was
  already generated/scheduled, local `pickup_status`, `pickup_scheduled_at`,
  `stage`, and `sync_status` are marked completed instead of leaving the
  shipment stuck as `failed` with `awb_assigned`.
- Webhook and Sync Tracking updates reconcile the same pickup/sync fields when
  provider status text indicates pickup (for example `Pickup Generated`). This
  heals older stuck rows without manual database edits.
- Before creating a remote order, fulfillment looks up an existing Shiprocket
  order by channel `order_id` (`orders.number`) so retries do not create
  duplicates when local IDs were never saved.
- Tracking activities from polling/webhooks are stored in
  `shiprocket_tracking_events` and shown on the admin order detail page.
