/**
 * Keep the visitor in "their" world: shared pages (Freie Termine, Gutscheine, Kontakt, Shop, Warenkorb,
 * Impressum …) show the Pferde or Hochzeit header and colours instead of the neutral one.
 * World = ?w=pferde|hochzeit (menu links carry it, cache-safe) → world page itself → cookie → referrer.
 */
if ( ! function_exists( 'rd_world_current' ) ) {
	function rd_world_current() {
		static $w = null;
		if ( null !== $w ) {
			return $w;
		}
		$ok = array( 'pferde', 'hochzeit' );
		$w  = '';
		$q  = isset( $_GET['w'] ) ? sanitize_key( wp_unslash( $_GET['w'] ) ) : '';
		if ( in_array( $q, $ok, true ) ) {
			$w = $q;
		} elseif ( is_singular( 'page' ) && in_array( get_page_template_slug(), array( 'rd-pferde', 'rd-hochzeit' ), true ) ) {
			$w = substr( get_page_template_slug(), 3 );
		} elseif ( isset( $_COOKIE['rd_world'] ) && in_array( $_COOKIE['rd_world'], $ok, true ) ) {
			$w = $_COOKIE['rd_world'];
		} else {
			$ref = wp_get_raw_referer();
			if ( $ref && preg_match( '#/(pferde|hochzeit)(/|$)#', (string) wp_parse_url( $ref, PHP_URL_PATH ), $m ) ) {
				$w = $m[1];
			}
		}
		return $w;
	}
}

add_action( 'template_redirect', function () {
	$w = rd_world_current();
	if ( $w && ( $_COOKIE['rd_world'] ?? '' ) !== $w && ! headers_sent() ) {
		setcookie( 'rd_world', $w, array( 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	}
} );

// neutral header → world header
add_filter( 'pre_render_block', function ( $pre, $block ) {
	if ( null !== $pre || 'core/template-part' !== ( $block['blockName'] ?? '' ) || 'header' !== ( $block['attrs']['slug'] ?? '' ) ) {
		return $pre;
	}
	$w = rd_world_current();
	if ( ! $w ) {
		return $pre;
	}
	$attrs = array_merge( $block['attrs'], array( 'slug' => 'header-' . $w ) );
	return render_block( array( 'blockName' => 'core/template-part', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}, 10, 2 );

// neutral page colours → world colours
add_filter( 'render_block', function ( $html, $block ) {
	if ( 'core/group' === ( $block['blockName'] ?? '' ) && false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'world-main' ) ) {
		$w = rd_world_current();
		if ( $w ) {
			$html = preg_replace( '/\bworld-main\b/', 'world-main world-' . $w, $html, 1 );
		}
	}
	return $html;
}, 10, 2 );

// WooCommerce pages (shop, product, cart) have no world wrapper: give the body the world colours
add_filter( 'body_class', function ( $classes ) {
	$w = rd_world_current();
	if ( $w ) {
		$classes[] = 'rd-in-' . $w;
	}
	return $classes;
} );
