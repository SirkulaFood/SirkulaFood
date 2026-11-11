<div align="center">
  <img src="public/images/SirkulaFood-logo-light.png" alt="SirkulaFood" width="210">
  <h1>Save food. Keep its value.</h1>
  <p><strong>Smart inventory management to reduce food waste.</strong></p>
  <p>SirkulaFood helps retail teams review food nearing its expiration date and coordinate discounts or donations for eligible surplus.</p>
  <p><a href="https://github.com/SirkulaFood/SirkulaFood">Source code</a></p>
</div>

---

## About

SirkulaFood is a Laravel and Vue web platform for managing products, inventory batches, and expiration dates. Teams can review at-risk products, consider markdowns, and coordinate eligible surplus with donation partners. Recommendations and follow-up actions are recorded so decisions can be reviewed.

## Account types

| Account type | Purpose | Registration |
|:--|:--|:--|
| **Foundation / charity** | Receives donated products. | Available on the registration page. |
| **MSME partner (UMKM)** | Receives information about discounted products. | Available on the registration page. |
| **Supermarket / store** | Manages inventory and offers products for donation or discount. | Available on the registration page. |
| **System admin** | Administers the platform and partner accounts. | Provisioned internally. |

Store owners can grant operational access to staff through manager or cashier accounts.

## Product workflow

| Stage | What SirkulaFood supports |
|:--|:--|
| **Monitor** | Review products, batches, quantities, prices, and remaining shelf life. |
| **Assess** | Use image review as an initial aid. It is not a food-safety certification. |
| **Recover value** | Review discount recommendations and approval workflows before applying changes. |
| **Redirect** | Reserve eligible products for donation partners and coordinate handoffs. |
| **Record** | Keep activity, decisions, and summaries for review. |

## Technology and integrations

| Technology | Role |
|:--|:--|
| **Laravel 12** | Backend, inventory, business rules, approvals, audit trail, and APIs. |
| **Vue 3 + Inertia** | Dashboard, partner portal, simulation, and user workflows. |
| **9Router** | OpenAI-compatible gateway for configured language and vision models. Configure credentials and model settings per environment. |
| **Telegram** | Webhook-based conversations and inventory follow-up. Requests that require approval remain subject to authorized staff review. |
| **n8n** | Workflow exports are included as inactive drafts. Configure endpoints and credentials in the deployment environment before importing or activating them. |
| **MySQL / SQLite** | Application storage, depending on local configuration. |

### AI decision flow

```text
Inventory data / product photo
              ↓
Laravel validates context and request
              ↓
9Router → configured AI model → structured result
              ↓
Staff review / approval through the application or Telegram
              ↓
Approved discount or donation → Laravel audit record
```

AI can provide context, but a photo alone cannot confirm freshness, contamination, smell, or food safety. Staff retain authority over eligibility and operational decisions.

## Run locally

### Prerequisites

- PHP 8.2 or newer and Composer
- Node.js and npm
- MySQL or SQLite
- PHP extensions required by Laravel 12

### Installation

```bash
git clone https://github.com/SirkulaFood/SirkulaFood.git
cd SirkulaFood
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set up the database connection in `.env`, then run migrations and build the frontend assets:

```bash
php artisan migrate
npm run build
```

For local development, run the required services in separate terminals:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

Or use the available Composer script:

```bash
composer run dev
```

### Optional integrations

- **9Router:** Set `NINEROUTER_API_KEY`, `NINEROUTER_BASE_URL`, `NINEROUTER_MODEL`, and `NINEROUTER_VISION_MODEL`. The example base URL is `http://127.0.0.1:20128/v1`; it must be reachable from the Laravel process.
- **Telegram:** Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_WEBHOOK_SECRET`. Register a public HTTPS webhook with `php artisan telegram:set-webhook --url=https://your-domain.example`.
- **n8n:** Set the webhook variables in `.env.example` as needed. The workflow exports in this repository are inactive until configured and enabled in your n8n environment.
- **Queue and scheduler:** Configure a queue worker and Laravel scheduler in the deployment environment for notifications and scheduled jobs.

## Project structure

```text
app/                   Laravel backend, controllers, models, services, and jobs
resources/js/           Vue pages and interface components
resources/css/          Application styles
routes/                 Web routes, API routes, and scheduled tasks
SirkulaFood-agent-*.json n8n workflow exports
```

## Impact

SirkulaFood aims to help reduce avoidable food waste and recover value from inventory. Any claims about waste or emissions reductions require a baseline and measured operational data.

---

<div align="center">
  <sub>Built with a circular mindset: review the stock, coordinate the next step, and keep people in control.</sub>
</div>