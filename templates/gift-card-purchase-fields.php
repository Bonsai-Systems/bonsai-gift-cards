<?php
/**
 * Gift card purchase fields, shown on the product page before Add to Cart.
 *
 * @var array  $presets
 * @var float  $min
 * @var float  $max
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="bgcp-purchase-fields">

	<?php if ( ! empty( $presets ) ) : ?>
		<p class="form-row form-row-wide">
			<label for="bgcp_amount"><?php esc_html_e( 'How much would you like to put on the gift card?', 'bgcp' ); ?> <span class="required">*</span></label>
			<select name="bgcp_amount" id="bgcp_amount" class="bgcp-amount-select" required>
				<option value=""><?php esc_html_e( 'Select an amount', 'bgcp' ); ?></option>
				<?php foreach ( $presets as $amount ) : ?>
					<option value="<?php echo esc_attr( $amount ); ?>">
						<?php echo wp_kses_post( wc_price( $amount ) ); ?>
					</option>
				<?php endforeach; ?>
				<option value="custom"><?php esc_html_e( 'Choose my own amount', 'bgcp' ); ?></option>
			</select>
			<span class="description"><?php esc_html_e( 'This is the balance the gift card will start with.', 'bgcp' ); ?></span>
		</p>
		<p class="form-row form-row-wide bgcp-custom-amount-row" style="display:none;">
			<label for="bgcp_amount_custom"><?php esc_html_e( 'Enter your own amount (£)', 'bgcp' ); ?></label>
			<input type="number" id="bgcp_amount_custom" step="0.01" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" placeholder="<?php echo esc_attr( $min . ' - ' . $max ); ?>" />
			<span class="description">
				<?php
				printf(
					/* translators: 1: minimum amount, 2: maximum amount */
					esc_html__( 'Must be between %1$s and %2$s.', 'bgcp' ),
					wp_kses_post( wc_price( $min ) ),
					wp_kses_post( wc_price( $max ) )
				);
				?>
			</span>
		</p>
	<?php else : ?>
		<p class="form-row form-row-wide">
			<label for="bgcp_amount"><?php esc_html_e( 'How much would you like to put on the gift card? (£)', 'bgcp' ); ?> <span class="required">*</span></label>
			<input type="number" name="bgcp_amount" id="bgcp_amount" step="0.01" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" required />
			<span class="description">
				<?php
				printf(
					/* translators: 1: minimum amount, 2: maximum amount */
					esc_html__( 'This is the balance the gift card will start with. Must be between %1$s and %2$s.', 'bgcp' ),
					wp_kses_post( wc_price( $min ) ),
					wp_kses_post( wc_price( $max ) )
				);
				?>
			</span>
		</p>
	<?php endif; ?>

	<p class="form-row form-row-first">
		<label for="bgcp_recipient_name"><?php esc_html_e( 'Who is this gift card for? (their name)', 'bgcp' ); ?></label>
		<input type="text" name="bgcp_recipient_name" id="bgcp_recipient_name" />
		<span class="description"><?php esc_html_e( 'We\'ll use this to greet them in the gift card email, e.g. "Hi Jane,"', 'bgcp' ); ?></span>
	</p>

	<p class="form-row form-row-last">
		<label for="bgcp_recipient_email"><?php esc_html_e( 'Their email address', 'bgcp' ); ?></label>
		<input type="email" name="bgcp_recipient_email" id="bgcp_recipient_email" />
		<span class="description"><?php esc_html_e( "We'll email the gift card straight to this address so they can use it themselves. Buying it for yourself, or want to send it on separately? Just leave this blank and we'll email it to you instead.", 'bgcp' ); ?></span>
	</p>

	<p class="form-row form-row-wide">
		<label for="bgcp_delivery_date"><?php esc_html_e( 'When should we send it?', 'bgcp' ); ?></label>
		<input type="date" name="bgcp_delivery_date" id="bgcp_delivery_date" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
		<span class="description"><?php esc_html_e( "Pick a future date if you'd like the gift card email to arrive on a specific day, like a birthday. Leave this blank and we'll send it out straight away once your order is complete.", 'bgcp' ); ?></span>
	</p>

	<p class="form-row form-row-wide">
		<label for="bgcp_message"><?php esc_html_e( 'Add a personal message (optional)', 'bgcp' ); ?></label>
		<textarea name="bgcp_message" id="bgcp_message" rows="3" maxlength="500"></textarea>
		<span class="description"><?php esc_html_e( "This message will be included in the gift card email, underneath the recipient's name.", 'bgcp' ); ?></span>
	</p>

</div>
