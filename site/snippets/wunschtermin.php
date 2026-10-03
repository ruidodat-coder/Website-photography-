/**
 * Checkout field "Wunschtermine & Ort" (block checkout, WooCommerce 9.9+).
 * Required (and only shown) when the cart contains a shoot, wedding or couple shoot.
 * The product IDs are filled in by site/build.py --step snippets (RD_TERMIN_IDS).
 * Shown in order emails and in the admin order screen.
 * Written without named functions/constants so re-saving never redeclares anything.
 */
add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}
	$ids      = RD_TERMIN_IDS;
	$in_cart  = array( 'cart' => array( 'properties' => array( 'items' => array( 'contains' => array( 'enum' => $ids ) ) ) ) );
	woocommerce_register_additional_checkout_field( array(
		'id'         => 'ruidodat/wunschtermin',
		'label'      => 'Wunschtermine & Ort',
		'location'   => 'order',
		'type'       => 'text',
		'required'   => $in_cart,
		'hidden'     => array( 'not' => $in_cart ),
		'attributes' => array(
			'placeholder' => 'z. B. Sa 17.10. ab 16 Uhr oder So 18.10., Reitstall Lingenfeld',
			'maxLength'   => 200,
		),
	) );
} );

// Server-side backstop: never accept a shoot/wedding order without a date.
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order, $request ) {
	$ids    = RD_TERMIN_IDS;
	$needed = false;
	foreach ( $order->get_items() as $item ) {
		if ( in_array( (int) $item->get_product_id(), $ids, true ) ) {
			$needed = true;
			break;
		}
	}
	if ( ! $needed ) {
		return;
	}
	$fields = (array) $request->get_param( 'additional_fields' );
	$value  = isset( $fields['ruidodat/wunschtermin'] ) ? trim( (string) $fields['ruidodat/wunschtermin'] ) : '';
	if ( strlen( $value ) < 4 ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'rd_termin_required',
			'Bitte gib 2–3 Wunschtermine und den Ort an, damit ich deinen Termin bestätigen kann.',
			400
		);
	}
}, 10, 2 );
