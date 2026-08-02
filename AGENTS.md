# Repository Guidelines

## Project Structure & Module Organization

This repository is a Laravel + Vue 3 SPA storefront. Laravel API code lives in `app/`, with controllers under `app/Http/Controllers/Api`, request validation in `app/Http/Requests`, resources in `app/Http/Resources`, services in `app/Services`, and Eloquent models in `app/Models`. Vue source is in `resources/js`, organized by `components`, `pages`, `layouts`, `router`, `stores`, `services`, and `utils`. CSS is split under `resources/js/styles`. Blade shells, mail, invoices, and error views live in `resources/views`. Routes are in `routes/api.php`, `routes/web.php`, and `routes/console.php`. Database migrations, factories, and seeders are in `database/`. Tests are split between `tests/Feature` and `tests/Unit`. Public images and built Vite output are under `public/`.

## Build, Test, and Development Commands

- `composer install` and `npm install`: install PHP and Node dependencies.
- `cp .env.example .env && php artisan key:generate`: create local configuration.
- `php artisan migrate --seed`: prepare the local database and seed demo data.
- `npm run dev`: run Laravel on `127.0.0.1:8000` with Vite.
- `composer run dev`: run server, queue listener, logs, and Vite together.
- `npm run build`: compile production frontend assets.
- `composer test` or `php artisan test`: run PHPUnit tests.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, final newline, spaces, and 4-space indentation except YAML at 2 spaces. PHP follows Laravel conventions: StudlyCase classes, singular models, plural database tables, and action-oriented controller methods. Vue files use PascalCase component/page names, Pinia stores use focused domain names such as `cart.js`, and shared helpers belong in `resources/js/utils`. Prefer service classes for business logic that would otherwise grow controllers.

## Testing Guidelines

Use PHPUnit through Laravel's test runner. Put HTTP, auth, checkout, admin, and integration behavior in `tests/Feature`; keep isolated logic in `tests/Unit`. Name tests after the behavior or endpoint under test, for example `CartApiTest.php` or `AdminUserCreateTest.php`. Add or update tests when changing API responses, payment/order flows, invoice logic, auth, or admin behavior.

## Commit & Pull Request Guidelines

Recent commits use short imperative summaries such as `Add invoice system..` and `Solve UI issues.` Keep new commit messages concise and specific, for example `Fix cart total calculation`. Pull requests should describe the user-facing change, list test commands run, link related issues, and include screenshots or short clips for visual storefront/admin changes. Note migrations, seed data changes, environment variables, and payment/webhook impacts explicitly.

## Security & Configuration Tips

Do not commit `.env`, credentials, Razorpay secrets, database dumps, or generated local logs. Keep `.env.example` updated when adding required configuration. Use `config/` files and Laravel env helpers for runtime settings instead of hard-coded secrets.
