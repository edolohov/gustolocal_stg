<?php
/**
 * Checkout Form Template
 * 
 * Simplified checkout form with delivery options
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>
	
	<!-- Delivery Options for Checkout -->
	<div class="delivery-options-checkout">
		<h3><?php esc_html_e( 'Способ получения заказа', 'woocommerce' ); ?></h3>
		<div class="delivery-radio-group">
			<label class="delivery-option">
				<input type="radio" name="delivery_type" value="delivery" <?php checked(WC()->session->get('delivery_type', 'delivery'), 'delivery'); ?>>
				<span class="delivery-label">
					<strong><?php esc_html_e( 'Доставка до двери в Валенсии', 'woocommerce' ); ?></strong>
					<span class="delivery-price"><?php echo wc_price(10.00); ?></span>
				</span>
			</label>
			<label class="delivery-option">
				<input type="radio" name="delivery_type" value="pickup" <?php checked(WC()->session->get('delivery_type', 'delivery'), 'pickup'); ?>>
				<span class="delivery-label">
					<strong><?php esc_html_e( 'Самовывоз (Amado Granell 40)', 'woocommerce' ); ?></strong>
					<span class="delivery-price"><?php esc_html_e( 'Бесплатно', 'woocommerce' ); ?></span>
				</span>
			</label>
		</div>
	</div>
	
	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
	
	<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
	
	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

	<div id="order_review" class="woocommerce-checkout-review-order">
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
	</div>

	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

