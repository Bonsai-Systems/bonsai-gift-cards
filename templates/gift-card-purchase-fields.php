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
			<label for="bgcp_amount"><?php esc_html_e( 'Choose an amount', 'bgcp' ); ?> <span class="required">*</span></label>
			<select name="bgcp_amount" id="bgcp_amount" class="bgcp-amount-select" required>
				<option value=""><?php esc_html_e( 'Select an amount', 'bgcp' ); ?></option>
				<?php foreach ( $presets as $amount ) : ?>
					<option value="<?php echo esc_attr( $amount ); ?>">
						<?php echo wp_kses_post( wc_price( $amount ) ); ?>
					</option>
				<?php endforeach; ?>
				<option value="custom"><?php esc_html_e( 'Other amount', 'bgcp' ); ?></option>
			</select>
		</p>
		<p class="form-row form-row-wide bgcp-custom-amount-row" style="display:none;">
			<label for="bgcp_amount_custom"><?php esc_html_e( 'Enter amount (£)', 'bgcp' ); ?></label>
			<input type="number" id="bgcp_amount_custom" step="0.01" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" placeholder="<?php echo esc_attr( $min . ' - ' . $max ); ?>" />
		</p>
	<?php else : ?>
		<p class="form-row form-row-wide">
			<label for="bgcp_amount"><?php esc_html_e( 'Amount (£)', 'bgcp' ); ?> <span class="required">*</span></label>
			<input type="number" name="bgcp_amount" id="bgcp_amount" step="0.01" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" required />
		</p>
	<?php endif; ?>

	<p class="form-row form-row-first">
		<label for="bgcp_recipient_name"><?php esc_html_e( "Recipient's name", 'bgcp' ); ?></label>
		<input type="text" name="bgcp_recipient_name" id="bgcp_recipient_name" />
	</p>

	<p class="form-row form-row-last">
		<label for="bgcp_recipient_email"><?php esc_html_e( "Recipient's email", 'bgcp' ); ?></label>
		<input type="email" name="bgcp_recipient_email" id="bgcp_recipient_email" />
		<span class="description"><?php esc_html_e( 'Leave blank to send it to yourself instead.', 'bgcp' ); ?></span>
	</p>

	<p class="form-row form-row-wide">
		<label for="bgcp_delivery_date"><?php esc_html_e( 'Delivery date', 'bgcp' ); ?></label>
		<input type="date" name="bgcp_delivery_date" id="bgcp_delivery_date" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
		<span class="description"><?php esc_html_e( 'Leave blank to send immediately once the order is complete.', 'bgcp' ); ?></span>
	</p>

	<p class="form-row form-row-wide">
		<label for="bgcp_message"><?php esc_html_e( 'Gift message', 'bgcp' ); ?></label>
		<textarea name="bgcp_message" id="bgcp_message" rows="3" maxlength="500"></textarea>
	</p>

</div>
