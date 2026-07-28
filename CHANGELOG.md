# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased] - 2026-07-28
### Changed
- Renamed from "Ley Arms Gift Cards" to **Bonsai Gift Card Plugin** — a generic,
  client-portable base rather than a Ley Arms-specific build. Prefix `lagc`/`LAGC` →
  `bgcp`/`BGCP` throughout (files, classes, functions, constants, hooks, nonces, DB
  table `wp_bgcp_gift_cards`, options, text domain, CSS classes, JS globals). Gift card
  code format changed from `LEY-XXXX-XXXX` to `GC-XXXX-XXXX`. All Ley Arms-specific
  copy (email subject/body, product field description) replaced with generic wording
  — customise per client at the point of deployment rather than in the base plugin.

### Fixed
- Plugin fataled on activation — `bonsai-gift-card-plugin.php` required files/classes
  (`BGCP_Settings`, `BGCP_Product_Type`, `BGCP_Order_Handler`, `BGCP_Shortcodes`) that
  didn't exist or didn't match the actual filenames/class names on disk.
- Reconciled two unfinished, mutually incompatible redemption implementations
  (AJAX/cart-page-shortcode vs. Blocks+REST) into a single canonical Blocks+REST flow,
  now supporting multiple gift cards applied to one order.
- Fixed undefined `BGCP_URL`/`BGCP_PATH` constants (PHP 8 fatal) across
  `class-bgcp-product.php`, `class-bgcp-shortcode.php`, `class-bgcp-blocks-integration.php`
  — standardised on `BGCP_PLUGIN_URL`/`BGCP_PLUGIN_DIR`.
- Fixed `BGCP_DB` method-name and data-key mismatches in `class-bgcp-order.php`
  (`create()`→`create_card()`, `table()`→`table_name()`, `get_by_id()`→`get_card()`,
  `initial_balance`→`initial_amount` key, `set_status()` called with an ID instead of
  a code).
- `BGCP_Blocks_Integration` was never registered with WooCommerce Blocks — added the
  `woocommerce_blocks_loaded`/`*_block_registration` hook wiring.
- Balance deduction (`BGCP_DB::adjust_balance()`) is now a single atomic `UPDATE`
  instead of read-then-write, closing a double-spend race condition on simultaneous
  redemptions of the same code.
- `$wpdb->insert()`/`update()` return values are now checked; insert failures return
  `WP_Error` instead of silently returning a code for a card that was never created.
- REST routes `/apply` and `/remove` now require a valid `wp_rest` nonce (previously
  `permission_callback => '__return_true'` on state-changing routes); `/balance` is
  rate-limited per IP.
- Removed inline `<script>`/`onclick` from `gift-card-purchase-fields.php` template and
  the admin gift-card list — moved to properly enqueued jQuery assets
  (`product-fields.js`, `admin.js`).
- Admin card disable/enable nonce is now bound to the specific card code, not reusable
  across cards.
- `balance-check.js` rewritten in jQuery (was vanilla `fetch`/`DOMContentLoaded`).

### Added
- `includes/class-bgcp-settings.php` — the "Gift Cards → Settings" admin page
  referenced by the README and by the email/admin classes, but never actually built.
- `tests/manual/checkout-flow.md` — dated sign-off checklist per Bonsai testing policy.
- `CLAUDE.md`, `.gitignore`.
