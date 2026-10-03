/**
 * Shop emails are sent from buchung@ruidodat.com (the web server may send for this domain;
 * Gmail drops mail that claims to be from @gmail.com but comes from another server).
 * Customer replies go to Gmail via Reply-To. Admin emails keep WooCommerce's Reply-To (the customer).
 * Deployed via the Code Snippets plugin (site/build.py --step snippets).
 */
add_filter( 'woocommerce_email_headers', function ( $headers, $email_id, $object, $email = null ) {
	if ( $email && method_exists( $email, 'is_customer_email' ) && $email->is_customer_email() ) {
		$headers = preg_replace( '/^Reply-to:.*$/mi', '', (string) $headers );
		$headers .= "Reply-To: Rui Dodat Fotografie <ruidodat@gmail.com>\r\n";
	}
	return $headers;
}, 20, 4 );
