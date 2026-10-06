/**
 * Client galleries, part 4: selling photos from "sale" galleries (tournaments, wedding guests) via WooCommerce.
 * One hidden virtual product carries every gallery purchase; each cart line says what it is
 * (photo / section / whole event) and gets its price here. Singles get the pack prices (3 / 6).
 * Paid orders (processing/completed) get a download token, an email with the link, and a download
 * page that always serves the newest export of each photo.
 */
if ( ! function_exists( 'rd_gal_product_id' ) ) {
	function rd_gal_product_id() {
		$id = (int) get_option( 'rd_gal_product' );
		if ( $id && 'product' === get_post_type( $id ) ) {
			$p = wc_get_product( $id );
			if ( $p && ( ! $p->is_downloadable() || ! $p->is_sold_individually() ) ) { // older versions of this product
				$p->set_downloadable( true );
				$p->set_sold_individually( true );
				$p->save();
			}
			return $id;
		}
		$p = new WC_Product_Simple();
		$p->set_name( 'Galerie-Bilder (Download)' );
		$p->set_status( 'publish' );
		$p->set_catalog_visibility( 'hidden' );
		$p->set_virtual( true );
		$p->set_downloadable( true ); // Germanized shows the digital-content waiver (§ 356 Abs. 5 BGB) for downloadable products
		$p->set_regular_price( '1' );
		$p->set_sold_individually( true );
		$id = $p->save();
		update_option( 'rd_gal_product', $id );
		return $id;
	}

	/** What a cart line / order line unlocks: array of photo rows. */
	function rd_gal_line_photos( $d ) {
		$e = RD_Gal::event( $d['event'] ?? '' );
		if ( ! $e ) {
			return array( null, array() );
		}
		if ( 'event' === $d['kind'] ) {
			return array( $e, RD_Gal::photos( $e->id ) );
		}
		if ( 'section' === $d['kind'] ) {
			return array( $e, RD_Gal::photos( $e->id, (int) $d['ref'] ) );
		}
		$p = RD_Gal::photo( (int) $d['ref'] );
		return array( $e, $p && $p->event_id === $e->id ? array( $p ) : array() );
	}

	function rd_gal_line_label( $d ) {
		$e = RD_Gal::event( $d['event'] ?? '' );
		$n = $e ? $e->name : 'Galerie';
		if ( 'event' === $d['kind'] ) {
			return "Alle Bilder · $n";
		}
		if ( 'section' === $d['kind'] ) {
			global $wpdb;
			$s = (int) $d['ref'] ? $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . RD_Gal::t( 'sections' ) . ' WHERE id=%d', $d['ref'] ) ) : 'Weitere Bilder';
			return 'Alle Bilder: ' . ( $s ?: 'Kapitel' ) . " · $n";
		}
		$p = RD_Gal::photo( (int) $d['ref'] );
		return 'Bild ' . ( $p && null !== $p->photo_number ? '#' . $p->photo_number : ( $p ? $p->filename : '' ) ) . " · $n";
	}

	function rd_gal_cart_summary( $event ) {
		$items = array();
		$total = 0.0;
		$n     = 0;
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
			foreach ( WC()->cart->get_cart() as $line ) {
				if ( empty( $line['rd_gal'] ) ) {
					continue;
				}
				$d = $line['rd_gal'];
				if ( $event && $d['event'] !== $event->id ) {
					continue;
				}
				$items[] = array( 'kind' => $d['kind'], 'ref' => (int) $d['ref'] );
				$total  += (float) $line['line_total'];
				$n++;
			}
		}
		$photos = count( array_filter( $items, function ( $i ) { return 'photo' === $i['kind']; } ) );
		$label  = $photos === $n ? ( 1 === $n ? '1 Bild' : "$n Bilder" ) : ( 1 === $n ? '1 Artikel' : "$n Artikel" );
		return array( 'items' => $items, 'total' => round( $total, 2 ), 'label' => $label );
	}
}

add_action( 'rest_api_init', function () {
	if ( ! class_exists( 'RD_Gal' ) || ! function_exists( 'WC' ) ) {
		return;
	}
	$boot = function () {
		if ( function_exists( 'wc_load_cart' ) && ( null === WC()->cart || ! did_action( 'woocommerce_cart_loaded_from_session' ) ) ) {
			wc_load_cart();
		}
	};
	$cookies = function () {
		if ( WC()->session && ! headers_sent() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		if ( WC()->cart && method_exists( WC()->cart, 'maybe_set_cart_cookies' ) && ! headers_sent() ) {
			WC()->cart->maybe_set_cart_cookies();
		}
	};
	register_rest_route( 'rd/v1', '/gallery/(?P<slug>[a-z0-9-]+)/cart', array(
		array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( $r ) use ( $boot ) {
			$e = RD_Gal::event_by_slug( (string) $r['slug'] );
			if ( ! $e || ! RD_Gal::has_access( $e ) ) {
				return new WP_Error( 'rd_locked', 'Kein Zugriff', array( 'status' => 403 ) );
			}
			$boot();
			return rd_gal_cart_summary( $e );
		} ),
		array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( $r ) use ( $boot, $cookies ) {
			$e = RD_Gal::event_by_slug( (string) $r['slug'] );
			if ( ! $e || ! RD_Gal::has_access( $e ) ) {
				return new WP_Error( 'rd_locked', 'Kein Zugriff', array( 'status' => 403 ) );
			}
			if ( 'sale' !== $e->mode ) {
				return new WP_Error( 'rd_free', 'Diese Galerie ist zum kostenlosen Download.', array( 'status' => 400 ) );
			}
			$kind = (string) $r->get_param( 'kind' );
			$ref  = (int) $r->get_param( 'ref' );
			if ( ! in_array( $kind, array( 'photo', 'section', 'event' ), true ) ) {
				return new WP_Error( 'rd_bad', 'Unbekannter Artikel', array( 'status' => 400 ) );
			}
			if ( 'photo' === $kind ) {
				$p = RD_Gal::photo( $ref );
				if ( ! $p || $p->event_id !== $e->id ) {
					return new WP_Error( 'rd_404', 'Bild nicht gefunden', array( 'status' => 404 ) );
				}
				$photo_section = (int) $p->section_id;
			}
			$boot();
			$cart = WC()->cart;
			// remove the line itself (remove=true) and anything the new line already covers
			foreach ( $cart->get_cart() as $k => $line ) {
				$d = $line['rd_gal'] ?? null;
				if ( ! $d || $d['event'] !== $e->id ) {
					continue;
				}
				$same    = $d['kind'] === $kind && (int) $d['ref'] === $ref;
				$covered = 'event' === $kind || ( 'section' === $kind && 'photo' === $d['kind'] && ( $pp = RD_Gal::photo( (int) $d['ref'] ) ) && (int) $pp->section_id === $ref );
				if ( $same || ( $covered && ! $r->get_param( 'remove' ) ) ) {
					$cart->remove_cart_item( $k );
				}
				if ( ! $r->get_param( 'remove' ) && 'photo' === $kind && ( 'event' === $d['kind'] || ( 'section' === $d['kind'] && (int) $d['ref'] === $photo_section ) ) ) {
					return new WP_Error( 'rd_dupe', 'Dieses Bild ist schon im Warenkorb enthalten (ganzes Kapitel bzw. alle Bilder).', array( 'status' => 409 ) );
				}
			}
			if ( ! $r->get_param( 'remove' ) ) {
				$cart->add_to_cart( rd_gal_product_id(), 1, 0, array(), array( 'rd_gal' => array( 'event' => $e->id, 'kind' => $kind, 'ref' => $ref ) ) );
			}
			$cookies();
			return rd_gal_cart_summary( $e );
		} ),
	) );

	// purchased downloads: ?photo=ID or ?event=ID&part=N
	register_rest_route( 'rd/v1', '/dl/(?P<token>[A-Za-z0-9]{20,64})', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( $r ) {
		$ent = rd_gal_entitlement( (string) $r['token'] );
		if ( is_wp_error( $ent ) ) {
			return $ent;
		}
		if ( $r->get_param( 'photo' ) ) {
			$pid = (int) $r->get_param( 'photo' );
			foreach ( $ent as $grp ) {
				foreach ( $grp['photos'] as $p ) {
					if ( (int) $p->id === $pid ) {
						RD_Gal::send_file( $grp['event'], RD_Gal::photo( $pid ) ); // newest version
					}
				}
			}
			return new WP_Error( 'rd_404', 'Bild nicht in dieser Bestellung', array( 'status' => 404 ) );
		}
		$eid = (string) $r->get_param( 'event' );
		if ( empty( $ent[ $eid ] ) ) {
			return new WP_Error( 'rd_404', 'Nicht gefunden', array( 'status' => 404 ) );
		}
		$parts = RD_Gal::parts( $ent[ $eid ]['photos'] );
		$n     = max( 1, (int) $r->get_param( 'part' ) );
		if ( empty( $parts[ $n - 1 ] ) ) {
			return new WP_Error( 'rd_404', 'Teil nicht gefunden', array( 'status' => 404 ) );
		}
		$items = array();
		foreach ( $parts[ $n - 1 ] as $p ) {
			$items[] = array( RD_Gal::orig_path( $ent[ $eid ]['event'], $p ), $p->filename );
		}
		RD_Gal::send_zip( $items, $ent[ $eid ]['event']->slug . ( count( $parts ) > 1 ? "-teil-$n" : '' ) . '.zip' );
	} ) );
} );

if ( ! function_exists( 'rd_gal_entitlement' ) ) {
	/** token → [event_id => ['event'=>row, 'photos'=>[rows]]] for a paid, unexpired order. */
	function rd_gal_entitlement( $token ) {
		$orders = wc_get_orders( array( 'limit' => 1, 'meta_key' => '_rd_gal_token', 'meta_value' => $token, 'status' => array( 'wc-processing', 'wc-completed' ) ) );
		if ( ! $orders ) {
			return new WP_Error( 'rd_token', 'Dieser Download-Link ist ungültig.', array( 'status' => 404 ) );
		}
		$o = $orders[0];
		if ( (int) $o->get_meta( '_rd_gal_exp' ) < time() ) {
			return new WP_Error( 'rd_token_exp', 'Dieser Download-Link ist abgelaufen. Schreib mir kurz, ich schicke dir einen neuen.', array( 'status' => 410 ) );
		}
		$out = array();
		foreach ( $o->get_items() as $item ) {
			$d = json_decode( (string) $item->get_meta( '_rd_gal' ), true );
			if ( ! $d ) {
				continue;
			}
			list( $e, $photos ) = rd_gal_line_photos( $d );
			if ( ! $e ) {
				continue;
			}
			if ( ! isset( $out[ $e->id ] ) ) {
				$out[ $e->id ] = array( 'event' => $e, 'photos' => array() );
			}
			foreach ( $photos as $p ) {
				$out[ $e->id ]['photos'][ $p->id ] = $p;
			}
		}
		foreach ( $out as &$g ) {
			$g['photos'] = array_values( $g['photos'] );
			usort( $g['photos'], function ( $a, $b ) { return array( (int) $a->photo_number, $a->filename ) <=> array( (int) $b->photo_number, $b->filename ); } );
		}
		return $out;
	}

	function rd_gal_deliver( $order_id, $force_mail = false ) {
		$o = wc_get_order( $order_id );
		if ( ! $o ) {
			return;
		}
		$has = false;
		foreach ( $o->get_items() as $item ) {
			if ( $item->get_meta( '_rd_gal' ) ) {
				$has = true;
			}
		}
		if ( ! $has ) {
			return;
		}
		$token = $o->get_meta( '_rd_gal_token' );
		$new   = ! $token;
		if ( $new ) {
			$token = wp_generate_password( 32, false, false );
			$o->update_meta_data( '_rd_gal_token', $token );
		}
		$o->update_meta_data( '_rd_gal_exp', time() + 60 * DAY_IN_SECONDS );
		$o->save();
		if ( ! $new && ! $force_mail ) {
			return;
		}
		$world = 'pferde';
		foreach ( $o->get_items() as $item ) {
			$d = json_decode( (string) $item->get_meta( '_rd_gal' ), true );
			$e = $d ? RD_Gal::event( $d['event'] ) : null;
			if ( $e ) {
				$world = $e->world;
			}
		}
		$url  = add_query_arg( 't', $token, RD_Gal::world_page( $world ) );
		$name = $o->get_billing_first_name();
		$html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#222;max-width:560px">'
			. '<p>Hallo ' . esc_html( $name ) . ',</p><p>vielen Dank für deine Bestellung! Deine Bilder stehen in voller Auflösung und ohne Wasserzeichen für dich bereit:</p>'
			. '<p style="margin:28px 0"><a href="' . esc_url( $url ) . '" style="background:#9B6B3A;color:#fff;padding:14px 26px;border-radius:999px;text-decoration:none;font-weight:bold">Bilder herunterladen</a></p>'
			. '<p>Der Link ist 60 Tage gültig. Bitte speichere deine Bilder in dieser Zeit.</p><p>Viel Freude damit!<br>Rui Dodat Fotografie</p>'
			. '<p style="font-size:12px;color:#777">Bestellung ' . esc_html( $o->get_order_number() ) . ' · Link funktioniert nicht? ' . esc_html( $url ) . '</p></div>';
		wp_mail( $o->get_billing_email(), 'Deine Bilder sind bereit · Rui Dodat Fotografie', $html, array( 'Content-Type: text/html; charset=UTF-8', 'From: Rui Dodat Fotografie <buchung@ruidodat.com>', 'Reply-To: ruidodat@gmail.com' ) );
		$o->add_order_note( 'Download-Link für Galerie-Bilder per E-Mail gesendet.' );
	}
}

/* ---------------- WooCommerce hooks ---------------- */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( ! class_exists( 'RD_Gal' ) || ( is_admin() && ! wp_doing_ajax() ) ) {
		return;
	}
	$p       = RD_Gal::prices();
	$singles = array();
	foreach ( $cart->get_cart() as $k => $line ) {
		if ( empty( $line['rd_gal'] ) ) {
			continue;
		}
		$d = $line['rd_gal'];
		$line['data']->set_name( rd_gal_line_label( $d ) ); // checkout block reads the product name
		if ( 'photo' === $d['kind'] ) {
			$singles[ $d['event'] ][] = $k;
		} else {
			$line['data']->set_price( 'event' === $d['kind'] ? $p['event'] : $p['section'] );
		}
		if ( $line['quantity'] > 1 ) {
			$cart->set_quantity( $k, 1, false );
		}
	}
	foreach ( $singles as $keys ) {
		$n     = count( $keys );
		$total = RD_Gal::singles_total( $n );
		$each  = floor( $total / $n * 100 ) / 100;
		foreach ( $keys as $i => $k ) {
			$price = $i === $n - 1 ? round( $total - $each * ( $n - 1 ), 2 ) : $each;
			$cart->cart_contents[ $k ]['data']->set_price( $price );
		}
	}
}, 20 );

add_filter( 'woocommerce_cart_item_name', function ( $name, $line ) {
	return empty( $line['rd_gal'] ) ? $name : esc_html( rd_gal_line_label( $line['rd_gal'] ) );
}, 10, 2 );

add_filter( 'woocommerce_cart_item_quantity', function ( $html, $key, $line ) {
	return empty( $line['rd_gal'] ) ? $html : '1';
}, 10, 3 );

add_filter( 'woocommerce_cart_item_thumbnail', function ( $img, $line ) {
	if ( empty( $line['rd_gal'] ) || ! class_exists( 'RD_Gal' ) ) {
		return $img;
	}
	$d = $line['rd_gal'];
	$e = RD_Gal::event( $d['event'] );
	$p = 'photo' === $d['kind'] ? RD_Gal::photo( (int) $d['ref'] ) : ( $e && $e->cover_photo ? RD_Gal::photo( (int) $e->cover_photo ) : null );
	if ( ! $e || ! $p ) {
		return $img;
	}
	$j = RD_Gal::photo_json( $e, $p );
	return '<img src="' . esc_url( $j['t'] ) . '" alt="" style="width:90px;height:auto;border-radius:6px">';
}, 10, 2 );

add_filter( 'woocommerce_store_api_cart_item_images', function ( $images, $line ) {
	if ( empty( $line['rd_gal'] ) || ! class_exists( 'RD_Gal' ) ) {
		return $images;
	}
	$d = $line['rd_gal'];
	$e = RD_Gal::event( $d['event'] );
	$p = 'photo' === $d['kind'] ? RD_Gal::photo( (int) $d['ref'] ) : ( $e && $e->cover_photo ? RD_Gal::photo( (int) $e->cover_photo ) : null );
	if ( ! $e || ! $p ) {
		return $images;
	}
	$j = RD_Gal::photo_json( $e, $p );
	return array( (object) array( 'id' => 0, 'src' => $j['t'], 'thumbnail' => $j['t'], 'srcset' => '', 'sizes' => '', 'name' => '', 'alt' => '' ) );
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $key, $values ) {
	if ( empty( $values['rd_gal'] ) ) {
		return;
	}
	$item->set_name( rd_gal_line_label( $values['rd_gal'] ) );
	$item->add_meta_data( '_rd_gal', wp_json_encode( $values['rd_gal'] ), true );
}, 10, 3 );

add_action( 'woocommerce_order_status_processing', 'rd_gal_deliver', 20 );
add_action( 'woocommerce_order_status_completed', 'rd_gal_deliver', 20 );

add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$o = wc_get_order( $order_id );
	if ( ! $o || ! class_exists( 'RD_Gal' ) ) {
		return;
	}
	$token = $o->get_meta( '_rd_gal_token' );
	if ( $token && $o->has_status( array( 'processing', 'completed' ) ) ) {
		echo '<p class="rd-note"><strong>Deine Bilder sind bereit.</strong> <a href="' . esc_url( add_query_arg( 't', $token, RD_Gal::world_page( 'pferde' ) ) ) . '">Jetzt herunterladen</a> (Link kommt auch per E-Mail).</p>';
	} elseif ( $o->get_meta( '_rd_gal_token' ) === '' && $o->has_status( 'on-hold' ) ) {
		echo '<p class="rd-note">Sobald deine Zahlung eingegangen ist, bekommst du den Download-Link für deine Bilder per E-Mail.</p>';
	}
}, 5 );

/* ---------------- download page (?t=token) ---------------- */
add_filter( 'rd_gal_downloads_html', function ( $html, $token ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return $html;
	}
	$ent = rd_gal_entitlement( $token );
	if ( is_wp_error( $ent ) ) {
		return '<div class="rd-gal-gate"><p class="rd-note">' . esc_html( $ent->get_error_message() ) . '</p></div>';
	}
	$base = rest_url( 'rd/v1/dl/' . $token );
	ob_start();
	echo '<div class="rd-gal rd-gal-dl"><header class="rd-gal-head"><p class="rd-eyebrow">Deine Bestellung</p><h1>Deine Bilder</h1>'
		. '<p class="rd-gal-meta">Volle Auflösung, ohne Wasserzeichen. Einzeln antippen oder alles als ZIP laden.</p></header>';
	foreach ( $ent as $eid => $g ) {
		$parts = RD_Gal::parts( $g['photos'] );
		echo '<section class="rd-gal-sec"><div class="rd-gal-sechead"><h2>' . esc_html( $g['event']->name ) . '</h2><span class="rd-gal-count">' . count( $g['photos'] ) . ' Bilder</span>';
		foreach ( $parts as $i => $part ) {
			echo '<a class="rd-gal-btn" href="' . esc_url( add_query_arg( array( 'event' => $eid, 'part' => $i + 1 ), $base ) ) . '">ZIP' . ( count( $parts ) > 1 ? ' Teil ' . ( $i + 1 ) : '' ) . ' herunterladen</a>';
		}
		echo '</div><div class="rd-gal-grid">';
		foreach ( $g['photos'] as $p ) {
			$j = RD_Gal::photo_json( $g['event'], $p );
			$r = $j['w'] && $j['h'] ? $j['w'] / $j['h'] : 1.5;
			echo '<div class="rd-gal-tile" style="flex:' . esc_attr( $r ) . ' 1 ' . esc_attr( $r * 190 ) . 'px"><a class="rd-gal-open" href="' . esc_url( add_query_arg( 'photo', $p->id, $base ) ) . '" aria-label="Bild herunterladen">'
				. '<img loading="lazy" src="' . esc_url( $j['t'] ) . '" alt="" style="aspect-ratio:' . esc_attr( $r ) . '"></a>'
				. ( null !== $p->photo_number ? '<span class="rd-gal-no">#' . (int) $p->photo_number . '</span>' : '' ) . '<span class="rd-gal-dlicon">↓</span></div>';
		}
		echo '</div></section>';
	}
	echo '</div>';
	return ob_get_clean();
}, 10, 2 );
