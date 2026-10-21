# Supplier profile and URL changes

## Shared registration

- `/admin/suppliers/create` selects an existing user; it does not create a profile.
- The selected user is passed to `/register/supplier/complete?source=admin&user_id=...`.
- Only `super_admin` may use selection mode. Livewire locks the selected user and checks authorization again when saving.
- The shared form creates a pending, inactive supplier, just like self-registration. Activation/approval remains a separate administrative operation.
- Loading the form never creates an administrator-owned placeholder supplier. Existing administrator roles are preserved.
- Initial slugs are generated from the company name with a nonempty fallback and numeric suffixes for occupied current or historical slugs.

## Published URLs

- Profile owners cannot change published slugs. The dedicated admin edit form exposes the URL controls only to `super_admin`.
- Changes use `SupplierSlugService` and a database transaction to store the previous slug and update the current slug together.
- Historical URLs resolve directly to the current active supplier with HTTP 301, including after multiple renames. Historical slugs remain reserved.
- Soft-deleted or inactive suppliers do not receive historical-URL redirects. Hard deletion cascades redirect history; those URLs no longer redirect.
- The admin edit form lists historical URLs. Removing or retargeting history is intentionally not supported, to avoid breaking existing links.
- URL changes made before history was introduced cannot be reconstructed automatically; only known previous slugs can be recovered.

## Deployment and validation

1. Back up the database and deploy the new files during the normal maintenance window.
2. Run `php artisan migrate --force` before serving requests with the new slug-history validation/middleware. The migration creates a new table; it does not backfill unknown historical slugs or change existing supplier slugs.
   The create migration adopts an existing table without recreating it. Slug history columns use 191 characters to support legacy MySQL hosts with a 1000-byte index limit. The reconciliation migration adds missing columns, safely narrows compatible slugs, and adds a missing unique index. It stops for manual review if core columns are missing, slugs exceed 191 characters, or duplicate old slugs exist; it does not delete or fabricate history.
3. Clear application route/view caches using the project's deployment procedure.
4. Run `php artisan test tests/Feature/AdminSupplierCreationTest.php tests/Feature/SupplierSlugRedirectTest.php` against the isolated test database, never the production database.
5. Manually verify user selection, shared-form submission, permission denial, mobile/tablet layout, and 301 responses after a rename.

Live MySQL version/collation, `EXPLAIN`, actual hosting deployment and browser/device behavior must be verified in the target environment. Source review is not runtime verification.

## Recovering an existing-table migration error

1. Export `supplier_slug_redirects` and `migrations` before deploying the fix. Do not drop the table, run `migrate:fresh`/`refresh`, or edit the migration ledger manually to bypass schema checks.
2. Upload both `2026_10_20_120000_create_supplier_slug_redirects_table.php` and `2026_10_21_120000_reconcile_supplier_slug_redirects_table.php`.
3. Stop web and queue writers, run `php artisan migrate --force`, then `php artisan migrate:status`. Resume writers after both succeed. This supports an existing table with an unrecorded create migration and an older table whose create migration was already recorded.
4. If preflight reports missing core columns or duplicate old slugs, inspect `SHOW CREATE TABLE supplier_slug_redirects` and resolve the inconsistency with a backup before retrying. No rows are automatically deleted or reassigned.
5. Both migrations intentionally retain the table and audit columns on rollback. Roll back application code first; any deliberate removal of historical URLs requires a separately reviewed data-retention operation.
