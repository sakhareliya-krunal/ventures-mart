# SEO deploy checklist (venturesmart.in)

## After deploy

1. Confirm `APP_NAME="Ventures Mart"` in production `.env`.
2. Open `/robots.txt` — must list `Sitemap: https://venturesmart.in/sitemap.xml` and disallow `/admin`, `/cart`, `/checkout`, `/profile`, `/orders`, `/wishlist`, `/login`, `/register`.
3. Open `/sitemap.xml` — must return **200** XML (`<?xml` + `<urlset>`), not an HTML error page. Resubmit in Search Console after any sitemap fix.
4. View-source homepage — title, description, and Organization/`og:site_name` should say **Ventures Mart** (not “Venture Smart”).
5. View-source `/cart` or `/login` — `robots` meta should be `noindex,follow`.
6. If brand still looks wrong, clear SEO settings cache (`seo.settings`) or resave Admin → SEO settings.
7. Confirm `GET /api/seo?path=/product/your-slug` returns JSON with `json_ld` (SPA schema sync).

## Google Search Console

1. Add/verify the URL-prefix or domain property for `https://venturesmart.in/`.
2. Set `GOOGLE_SITE_VERIFICATION=` in production `.env` (token only — not the full `<meta>` tag).
3. Submit sitemap: `https://venturesmart.in/sitemap.xml`.
4. Request indexing for `/`, top category URLs, and bestselling product URLs.
5. Optional: set `GA_MEASUREMENT_ID` / `GTM_CONTAINER_ID` for analytics.

## Admin SEO (Settings → SEO)

### Site-wide
- Brand, logo, default OG image, robots disallow list, sitemap toggle, GA/GTM, Search Console verification.
- Redirects: create / edit / enable-disable / delete (301 when slugs change are also auto-created).

### Static page SEO selector
Edit SEO for: `home`, `shop`, `about`, `contact`, `blog`, `shipping`, `returns`, `payments`, `privacy-policy`, `terms`, `shopping-confidence-shipping-replacement`.

### Score checklist (pass/fail)
Admin SEO tab shows score **/100** plus checks for: title length, description length, focus keyword, keyword in copy, canonical, robots, image alt, OG image, FAQs, custom schema. Score uses stored fields **or** generated fallbacks from the product/category/post.

### Focus-keyword direction

| Page | Focus keyword direction |
|------|-------------------------|
| Home | `toys and lunch boxes India` / `Ventures Mart toys lunch boxes` |
| `/category/lunch-box` | `steel lunch box for kids` |
| `/category/toys` | `kids toys online India` |
| Each product | `{product name}` + `steel lunch box` or toy type |
| Blog posts | Topic + India / kids / lunch box as relevant |

## Rich results / schema

Product pages emit Product + Offer (INR, stock, free shipping India, 7-day return policy), optional AggregateRating, BreadcrumbList, FAQ. Blog posts include BlogPosting with author/publisher. Validate with [Rich Results Test](https://search.google.com/test/rich-results) on a product URL after deploy.

## PageSpeed follow-up

Re-run PageSpeed Insights on https://venturesmart.in/ after deploy. Expect help from lighter assets, `/build` immutable caching, lazy gallery images (except LCP), and WebP uploads.
