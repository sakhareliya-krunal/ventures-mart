# VentureSmart production QA report

Audit date: 2026-09-13 (Asia/Calcutta)

## Executive summary

Core public, customer, admin, authorization, invoice, and responsive flows are operational. Release should still be held until the paid-order inventory discrepancy and mail authentication failure are resolved. Performance is materially below a reasonable production baseline: the home page transfers roughly 58 MiB and the sampled product page takes 17.9 seconds to reach mobile LCP.

This change converts unknown SPA URLs to real HTTP 404 responses, prevents duplicate guest-session and catalog requests, fixes stale route titles, and defers home video downloads until their section approaches the viewport.

## P0 — Paid-order inventory state is inconsistent

- Reproduce: open order `VM-QPYPC71X` in Admin, compare order/fulfillment state with each line's allocation, reservation, and shipped quantity, then run report-only inventory reconciliation.
- Expected: a paid and shipped order has consistent allocation and shipped ledger state; scheduled reconciliation succeeds.
- Actual: the order is Shipped but remains `unallocated`; both lines have no reservation and zero shipped quantity. Hourly reconciliation failed 135 times since September 11, including the audit day.
- Suspected area: `InventoryReconcileCommand`, `FulfillmentAuditService`, inventory reservation/release transitions, and provider synchronization.
- Regression: cover paid → allocated → shipped, assert reservation consumption and shipped quantities, and expect zero report-only variance. Alert on persistent scheduler failures.

## P1 — Home and product performance are unacceptable

- Reproduce: run Lighthouse mobile against `/` and a populated `/product/:slug` with a clean profile and default throttling.
- Expected: no eager below-fold media transfer, LCP near 2.5 seconds, and minimal blocking.
- Actual: home mobile score 32, 57.6 MiB transferred, 11.8 s LCP, and 3.96 s blocking; desktop score 39 and about 58 MiB. Four videos total 56.7 MB and the splash SVG is 1,926,294 bytes. The product scored 31 with 17.9 s LCP, 18.3 s interactive, and 3.67 s blocking; its primary image waited about 3.6 s to render after loading.
- Suspected area: `HomeVideoSection.vue`, `resources/views/app.blade.php`, splash assets, product gallery/reveal behavior, and image variants.
- Regression: assert videos have no `src` before intersection and `preload="none"`; add Lighthouse transfer, LCP, and TBT budgets. Verify the product LCP image is eager/high-priority and not held by reveal animation.

## P1 — Outbound email authentication fails

- Reproduce: during a controlled maintenance window, send one transaction through the production mailer and inspect the queue failure/log. Do not resend an existing order email until credentials are corrected.
- Expected: SMTP accepts authentication, the message reaches the designated inbox, and timestamps update only after successful delivery.
- Actual: unresolved Gmail `535` errors affect order and queued mail. Both existing orders lack a confirmation-email timestamp.
- Suspected area: production `MAIL_*` configuration, cached configuration, Gmail account policy, and queue-worker environment.
- Regression: add a designated-inbox deployment smoke test, queue monitoring, and a test that failed sends never record success.

## P1 — Unknown URLs return soft 404 responses

- Reproduce: request a unique nonexistent path and a missing product/category/post URL directly; inspect the HTTP status.
- Expected: HTTP 404 with the 404 page and `noindex,follow`.
- Actual before fix: HTTP 200 with the correct visual 404 and robots directive. Production reconfirmed this on 2026-09-13.
- Suspected area: the catch-all route and `SpaController` always returned status 200 even when `SeoService` classified a path as not found.
- Regression: `SpaHttpStatusTest` asserts known-route success plus generic and dynamic-content 404 responses.

## P1 — Public HTML bypasses CDN caching

- Reproduce: issue clean document requests to `/` and inspect response headers twice.
- Expected: cacheable anonymous HTML where safe, without creating a session on every document request.
- Actual: production returned `Cache-Control: no-cache, private`, XSRF/session cookies, and CDN MISS headers. Earlier TTFB was about 1.0–2.7 seconds.
- Suspected area: Laravel web session/CSRF middleware on the SPA shell and CDN rules.
- Regression: add anonymous-document and CDN smoke tests while preserving private caching for session-specific responses.

## P2 — Security headers and server hardening are incomplete

- Reproduce: inspect the production document response headers.
- Expected: HSTS, CSP or equivalent frame protection, MIME-sniffing protection, referrer policy, permissions policy, and no runtime version disclosure.
- Actual: these policies were absent and `x-powered-by: PHP/8.3.33` was exposed on 2026-09-13.
- Suspected area: Apache/PHP configuration and missing security-header middleware.
- Regression: assert response headers and run an external post-deploy check. Roll CSP out in report-only mode first because the shell contains inline script/style.

## P2 — Catalog and session requests are duplicated

- Reproduce: load shop/search/category from a clean guest profile and inspect `/api/products`; inspect `/api/user` during startup.
- Expected: one request per effective catalog query and one session lookup.
- Actual before fix: identical product requests and the guest `/api/user` lookup occurred twice, producing noisy expected 401 entries.
- Suspected area: `ShopPage` mount plus params watcher, and router startup plus `MainLayout` mount.
- Regression: `requestDeduplication.test.js` asserts reuse of in-flight catalog calls and one resolved guest-session lookup.

## P2 — Browser titles become stale

- Reproduce: navigate login → home, empty checkout → cart, and customer → admin redirect; change search query; navigate between admin screens.
- Expected: the final route owns the title, search includes its query, and admin screens are distinguishable.
- Actual before fix: the prior title persisted, search lost its query-specific title, and admin screens used `Admin | Ventures Mart`.
- Suspected area: `seoHeadFromServer` reused initial `window.__APP__.seo`; nested `ShopPage` metadata overrode search; admin layout ignored `route.meta.title`.
- Regression: `seoNavigationRegression.test.js` covers initial versus navigated metadata; retain an end-to-end redirect/title matrix.

## P2/P3 — Accessibility and product-content cleanup remain open

- Reproduce: run axe/Lighthouse and keyboard checks on home, product, footer, checkout, and invalid forms at desktop and 390 px; inspect the recently added toy in API/admin.
- Expected: WCAG AA contrast, practical 44 px targets, valid ARIA, matching accessible names, intrinsic image dimensions, first-invalid-field focus, concise content, alt text, and a computed SEO score.
- Actual: home/product accessibility scores were 89/90. Defects include contrast, small targets, prohibited ARIA on five payment marks, add-button name mismatches, unsized logos, and weak invalid focus. The toy has a 145-character title, 165-character description, run-on excerpt, 1.51:1 zero-rating link, and no configured alt/SEO score.
- Suspected area: product cards/gallery, footer, payment marks, shared logo/images, submit handlers, product record/editor validation, and rating styles.
- Regression: add axe and keyboard coverage plus editor limits/warnings and alt/SEO fixtures. Content changes require owner review.

## Verification performed

- `npm run test:ui`: 42 files and 129 tests passed.
- `php artisan test`: 203 tests and 1,104 assertions passed. The rerun initially exposed stale checkout fixtures missing required `district` values and an old fulfillment expectation; these now match current validation and the documented restore-capable manual switch.
- `npm run build`: production assets compiled successfully.
- Production public headers reconfirmed HTTP-to-HTTPS behavior, pre-fix soft-404 status 200, private/no-cache responses, cookies, CDN misses, missing hardening headers, and PHP disclosure.
- No live order was submitted. No settings, existing inventory, orders, customers, or published records were changed.

## Live checks not completed in this fresh context

The prior plan refers to supplied customer/admin credentials, but they were not present in this context or repository configuration. To avoid unowned production state, the address/cart/wishlist checkout exercise, `[QA]` contact and password-reset submissions, controlled email delivery, and disposable admin CRUD records were not repeated. Run these once the credentials and designated inbox are available, then verify cleanup by API and with a fresh browser profile.
