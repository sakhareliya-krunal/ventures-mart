# SEO deploy checklist (venturesmart.in)

## After deploy

1. Confirm `APP_NAME="Ventures Mart"` in production `.env`.
2. Prefer `APP_LOCALE=en-IN` and `SEO_DEFAULT_LOCALE=en-IN` (HTML `lang` uses SEO locale).
3. Open `/robots.txt` — must list `Sitemap: https://venturesmart.in/sitemap.xml` and disallow `/admin`, `/cart`, `/checkout`, `/profile`, `/orders`, `/wishlist`, `/login`, `/register`.
4. Open `/sitemap.xml` — must return **200** XML (`<?xml` + `<urlset>`), not an HTML error page. Resubmit in Search Console after any sitemap fix.
5. View-source homepage — title should be the Premium Stainless Steel Lunch Boxes default (or your Admin override), description present, Organization/`og:site_name` = **Ventures Mart**.
6. View-source `/cart` or `/login` — `robots` meta should be `noindex,follow`.
7. If home title still says “Toys & lunch boxes”, open **Admin → SEO settings → Page = Home**, clear or replace SEO title/description, save (DB overrides empty fallbacks).
8. Confirm `GET /api/seo?path=/` returns JSON with the expected `title` / `description` / `json_ld`.

## Google Search Console (ranking, not Lighthouse)

1. Property verified for `https://venturesmart.in/`.
2. `GOOGLE_SITE_VERIFICATION=FbCDbEAU2TcJuIMEUlki4N3dx2dxqVLd_ob9RWqV-_s` in production `.env` (token only — not the full `<meta>` tag). The app also defaults to this token if the env/admin value is empty. After deploy, run `php artisan optimize:clear` (and clear `seo.settings` cache if verification was previously blank in Admin).
3. View-source homepage should include `<meta name="google-site-verification" content="FbCDbEAU2TcJuIMEUlki4N3dx2dxqVLd_ob9RWqV-_s">`.
4. Submit sitemap: `https://venturesmart.in/sitemap.xml` — status Success.
5. URL Inspection → Request indexing for `/`, `/category/lunch-box`, `/category/toys`, and top product URLs.
6. Optional: `GA_MEASUREMENT_ID` / `GTM_CONTAINER_ID`.

**Note:** PageSpeed SEO 100 is a crawl checklist. Ranking on competitive queries also needs indexing, unique content, reviews, and time.

## PageSpeed SEO toward 100

After deploy + `npm run build`, re-test https://venturesmart.in/ (mobile). Expect:

- Document title + meta description
- `html lang="en-IN"`
- Image alts on storefront media
- Named header cart/wishlist/account controls

If SEO is still below 100, expand the failed audit name in PSI and fix that specific item.

## Admin SEO (Settings → SEO)

### Site-wide
- Brand, logo, default OG image, robots disallow list, sitemap toggle, GA/GTM, Search Console verification.
- Redirects: create / edit / enable-disable / delete.

### Static page SEO selector
Edit SEO for: `home`, `shop`, `about`, `contact`, `blog`, `shipping`, `returns`, `payments`, `privacy-policy`, `terms`, `shopping-confidence-shipping-replacement`.

### Focus-keyword direction

| Page | Focus keyword direction |
|------|-------------------------|
| Home | `toys and lunch boxes India` / `stainless steel lunch box India` |
| `/category/lunch-box` | `steel lunch box for kids` |
| `/category/toys` | `kids toys online India` |
| Each product | `{product name}` + `steel lunch box` or toy type |
| Blog posts | Topic + India / kids / lunch box as relevant |

Also add 1–2 FAQs and collect real product reviews (helps rich results).

## Rich results / schema

Product pages emit Product + Offer (INR, stock, free shipping India, 7-day return policy), optional AggregateRating, BreadcrumbList, FAQ. Validate with [Rich Results Test](https://search.google.com/test/rich-results) on a product URL after deploy.
