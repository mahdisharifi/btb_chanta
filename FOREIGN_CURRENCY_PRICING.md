# Foreign currency pricing

The supplier price form supports Toman (the existing flow), USD, CNY and AED. Foreign currency is accepted only for the cash price of the configured reference tier. The supplier/product reference tier saved in `supplier_tier_percentages` is used; products without a saved reference keep the current default index `1`. The stored `product_prices.price` remains the canonical integer Toman price, so existing filters, matrices, check prices, catalogs and API consumers continue to read the same unit.

## Conversion and refresh behavior

- A supplier enters the foreign unit amount. The application converts it using a fresh, error-free quote stored locally and the supplier's existing price rounding rules.
- With automatic updates disabled, the applied quote is saved with the foreign amount. Saving unrelated product settings keeps that saved quote and Toman price stable. Changing the currency or amount requires a fresh local quote; if the provider is unavailable, the last valid Toman price stays in place.
- With automatic updates enabled, only the reference cash price and existing automatically calculated tiers are updated. Default and percentage-based check rows are recalculated from their cash tier. Manual check values and custom warranty add-ons remain unchanged; percentage and fixed warranty values continue to be calculated by the existing product pricing logic.
- The product's Toman price rows remain the source for page renders. A failed quote fetch leaves the last valid quote and Toman price intact. Due updates retry after five minutes when the saved quote is missing or stale.
- Quote collection runs every five minutes; due product prices run from the scheduler each minute. The current shared-hosting queue worker consumes these jobs.
- Keep `APP_TIMEZONE=UTC` and the MySQL session timezone at UTC (`DB_TIMEZONE=+00:00`). Exchange quote timestamps and freshness comparisons use UTC.

## Rate provider

The current provider calls the direct HTTPS JSON feed at `https://nerkh.jahankhahan.shop/data/live.json`. It reads the `rates.dollar`, `rates.yuan` and `rates.aed` values, which are Toman per unit. It does not request GitHub-hosted JSON. [Nabz's public feed](https://itsyebekhe.github.io/nabz/) currently points to GitHub-hosted JSON, so it is not configured under the repository's no-GitHub requirement. Metals and other currencies are outside this feature.

After deployment, run the migration and seed the first local quote snapshot:

```sh
php artisan migrate --force
php artisan prices:refresh-exchange-rates
```

Keep the existing cPanel `schedule:run` cron entry active so future quotes and due product updates continue. Product pages do not call the rate provider.

Before enabling this in production, confirm the server has the `jobs` and `failed_jobs` tables, `QUEUE_CONNECTION=database`, the cPanel cron runs `php artisan schedule:run` every minute, and `php artisan schedule:list` shows the exchange-rate and due-price schedules. The app's scheduled `queue:work --stop-when-empty` is intended for shared hosting; verify it actually consumes a queued job and that `storage/logs/queue-worker.log` stays writable. These are host checks and cannot be completed from this repository checkout.

## WebSocket delivery

Price changes are queued after the database transaction commits on a private product-and-role channel. Channel authorization checks the user's role, wholesale-price permission, and that the product is active and published. Each role receives only the price types allowed by its settings. When supplier information is hidden for a role, the event and snapshot contain aggregate product prices only; supplier identifiers and per-supplier values are omitted. An uncached database snapshot synchronizes a page on initial connection or reconnect; updates then arrive through WebSockets without polling. The browser subscribes only while a corresponding price element is in or near the viewport, then leaves the channel when it scrolls away.

The app defaults to `BROADCAST_CONNECTION=log`; real WebSocket delivery requires credentials for a managed Pusher-compatible broadcaster in the deployment environment:

```dotenv
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=...
VITE_PUSHER_APP_KEY=${PUSHER_APP_KEY}
VITE_PUSHER_APP_CLUSTER=${PUSHER_APP_CLUSTER}
```

For a custom Pusher-compatible endpoint, also set `PUSHER_HOST`, `PUSHER_PORT` and `PUSHER_SCHEME`, with matching `VITE_PUSHER_*` values. Rebuild the Vite assets after changing `VITE_*` settings. No broadcaster credentials are committed. Until a broadcaster is configured, database updates still work, but open browser pages will not receive WebSocket messages.

The Pusher-compatible service must provide its app ID, key, secret, cluster (or host/port/scheme), and a WebSocket endpoint reachable by site visitors. Put secrets in the company's deployment environment, never in Git. After setting the variables, clear/rebuild Laravel's config cache, build the Vite assets with the public `VITE_*` values, then verify private-channel authorization and delivery using two authenticated browser sessions. The repository does not contain company service credentials or hosting access, so these production steps remain pending until those are available.

The product detail response uses private browser caching with a one-minute freshness window so a page opened after an event does not retain a long-lived old price snapshot.
