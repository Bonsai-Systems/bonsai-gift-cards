# Gift card checkout flow — pre-launch sign-off

Client:
Tested by:
Date:
Stripe test mode: ☐

Required per Bonsai policy — any custom `$wpdb` table + checkout/payment logic needs
this signed off before client handover. Re-run and re-date after any change to
`class-bgcp-cart.php`, `class-bgcp-db.php`, `class-bgcp-order.php`, or `class-bgcp-rest.php`.

## Purchase → issue → email

- [ ] Add a gift-card product to cart with a preset amount → correct price on cart line
- [ ] Add a gift-card product with a custom amount (within min/max) → correct price
- [ ] Complete checkout with test card 4242 4242 4242 4242 → order placed
- [ ] Gift card row appears in **Products → Gift Card Codes** with correct amount,
      recipient, status "Active"
- [ ] Gift card email received at the recipient address (not just purchaser) with
      correct code, amount, and (if set) gift message
- [ ] Buy 2x the same gift-card product in one line (quantity 2) → two distinct codes
      minted, two emails sent
- [ ] Set a future delivery date on a gift-card purchase → email is *not* sent
      immediately; confirm `bgcp_send_scheduled_gift_card` is a scheduled WP-Cron event
      (WP Crontrol or similar) and fires on the delivery date

## Redemption at checkout

- [ ] Apply a valid code in the Blocks checkout sidebar → fee line appears, total drops
      by the correct amount
- [ ] Apply a second code on the same order → fee line updates to cover both, capped at
      the order total (no negative total)
- [ ] Remove an applied code → fee line updates/disappears, total recalculates
- [ ] Complete an order using a gift card to *partially* cover it (rest paid by Stripe)
      → order total, gift card balance, and Stripe charge amount all reconcile
- [ ] Complete an order where the gift card balance fully covers the order (no card
      payment needed) → order completes correctly
- [ ] Apply an invalid/expired/disabled code → clear error message, no fee applied
- [ ] Two browser sessions apply the *same* code simultaneously, both attempt checkout
      → only one succeeds in full; confirm the card's balance never goes negative
      (this is what `BGCP_DB::adjust_balance()`'s atomic UPDATE exists to prevent —
      re-verify after any change to that method)

## Failure paths

- [ ] Start checkout with a code applied, then abandon/fail the Stripe payment →
      nothing is debited from the gift card (check balance unchanged)
- [ ] Self-serve balance check (`[bgcp_balance_check]` shortcode) with a valid code →
      correct balance and status shown
- [ ] Self-serve balance check with an unknown code → friendly "not found" message,
      no PHP notice/warning in the log

## Refund / cancellation

- [ ] Cancel or refund an order that *issued* an untouched gift card → card status
      flips to "void" automatically
- [ ] Cancel or refund an order that issued a gift card *already partly spent*
      elsewhere → card is left alone (not auto-voided) — confirm this is a deliberate
      manual-review case, not a bug, and adjust balance manually via the admin list if
      appropriate for this specific order

## Admin

- [ ] **Products → Gift Card Codes**: search by code, recipient email — correct results
- [ ] Manually create a card (phone/in-person sale) with "send email now" checked →
      card created, email sent
- [ ] Manually create a card with "send email now" unchecked → card created, no email
- [ ] Disable an active card from the admin list → confirm dialog appears, card status
      becomes "disabled", and it can no longer be applied at checkout
- [ ] Re-enable a disabled card → works again at checkout
- [ ] **Products → Gift Card Settings**: upload an image, save, confirm it appears in
      the next gift card email sent
