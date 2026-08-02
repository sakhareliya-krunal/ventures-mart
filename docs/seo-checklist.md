# SEO deploy checklist (venturesmart.in)

## After deploy

1. Confirm `APP_NAME="Ventures Mart"` in production `.env`.
2. Open `/robots.txt` — must list `Sitemap: https://venturesmart.in/sitemap.xml` and disallow `/admin`, `/cart`, `/checkout`, `/profile`, `/orders`, `/wishlist`, `/login`, `/register`.
3. Open `/sitemap.xml` — must return **200** and list home, shop, categories, products, blog, plus privacy/terms/shopping-confidence.
4. View-source homepage — title, description, and Organization/`og:site_name` should say **Ventures Mart** (not “Venture Smart”).
5. View-source `/cart` or `/login` — `robots` meta should be `noindex,follow`.
6. If brand still looks wrong, clear SEO settings cache (`seo.settings`) or resave Admin → SEO settings.

## Google Search Console

1. Add/verify the URL-prefix or domain property for `https://venturesmart.in/`.
2. Set `GOOGLE_SITE_VERIFICATION=` in production `.env` (meta tag is rendered from SEO settings).
3. Submit sitemap: `https://venturesmart.in/sitemap.xml`.
4. Request indexing for `/`, top category URLs, and bestselling product URLs.
5. Optional: set `GA_MEASUREMENT_ID` / `GTM_CONTAINER_ID` for analytics.

## Admin focus-keyword checklist

Fill unique **title**, **meta description**, **focus keyword**, and 1–2 FAQs where useful:

| Page | Focus keyword direction |
|------|-------------------------|
| Home (`/admin/seo/pages/home`) | `toys and lunch boxes India` / `Ventures Mart toys lunch boxes` |
| `/category/lunch-box` | `steel lunch box for kids` |
| `/category/toys` | `kids toys online India` |
| Each product | `{product name}` + `steel lunch box` or toy type |

## PageSpeed follow-up

Re-run PageSpeed Insights on https://venturesmart.in/ after deploy. Expect Performance uplift from lighter favicon/logo/poster/hero video; SEO should stay 90+.
