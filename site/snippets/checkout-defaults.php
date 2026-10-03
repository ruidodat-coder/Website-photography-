/**
 * Checkout defaults for a German shop: preselect Germany for guests and
 * hide the (unused) Bundesland field for German addresses.
 * Deployed via the Code Snippets plugin (site/build.py --step snippets).
 */
add_filter( 'woocommerce_customer_default_location_array', function ( $location ) {
	return array( 'country' => 'DE', 'state' => '' );
} );

add_filter( 'default_checkout_billing_country', function () {
	return 'DE';
} );

add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
	$locale['DE']['state'] = array( 'required' => false, 'hidden' => true );
	return $locale;
} );
