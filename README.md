# Bonsai Gift Card Plugin

Custom WooCommerce gift card plugin. Built as a standalone plugin (not
theme-coupled) so it's portable to other Bonsai client sites later.

## Setup

1. Upload/activate as a normal plugin (needs WooCommerce active).
2. On activation it creates `wp_bgcp_gift_cards` — check it exists via
   phpMyAdmin/Adminer if anything looks off (WordPress dbDelta is fussy
   about exact SQL formatting).
3. **Gift Cards → Settings** — upload the client's gift card image.
4. Create/edit a Simple Product ("Gift Voucher"), tick **Gift card?** under
   Product Data → General, set preset amounts (e.g. `25,50,75,100`) and/or
   a min/max for a custom amount.
5. Add the product to the Gift Vouchers page.
6. Drop `[bgcp_balance_check]` on the Gift Vouchers page (or anywhere) so
   customers can self-serve balance lookups.

## How redemption works

- Customer enters a code in the "Have a gift card?" field injected into
  the Blocks checkout sidebar (more than one code can be applied at once).
- Applied codes live in the WC session as a provisional list — nothing is
  debited yet.
- The combined balance is applied as a single cart fee (negative), so
  it's deducted **after tax**, same as cash — matches Pimwick's behaviour.
- On `woocommerce_checkout_create_order` (while the order is being built,
  before payment), each applied code is re-validated against its live DB
  balance and debited atomically. Throwing an exception at this point is
  WooCommerce's documented way to abort order creation cleanly — so if a
  balance changed underneath the session (spent elsewhere, expired)
  nothing is created and nothing is left debited. Any codes already
  deducted for that same order are rolled back first.

## Things worth testing before going live

- Full purchase → email → redeem → balance-check loop, including a
  gift card used to *partially* cover an order (rest paid by Stripe).
- A cancelled/failed Stripe payment after a code was "applied" but before
  `payment_complete` — the session just clears, nothing should get debited.
- Order refund/cancellation on an order that *issued* a gift card that's
  since been partly spent — currently only untouched cards get voided
  automatically; a partially-spent card on a refunded order needs a
  manual look via the admin list (adjust balance to 0 if appropriate).
- Scheduled delivery dates rely on WP-Cron — fine on normal hosting, but
  if the site's cron is disabled/offloaded, wire `bgcp_send_scheduled_gift_card`
  into whatever real cron runner is in use.
- The checkout field re-render currently calls
  `wp.data.dispatch('wc/store/cart').invalidateResolutionForStore()` to
  refresh totals after apply/remove — worth confirming this fires cleanly
  against whatever WooCommerce Blocks version is running; a full-page
  reload fallback is already wired in if it doesn't.

## Not built (deliberately, out of scope for this client)

- Multiple gift card images / email designer — one client, one image.
- Redemption restrictions by product/category.
- PDF gift cards, QR codes.
- Multi-currency, WooCommerce Subscriptions compatibility.

Any of the above can be bolted on later without restructuring — the DB
schema and hooks are generic on purpose.
