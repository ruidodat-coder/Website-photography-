/**
 * Client galleries, part 5: wp-admin → "Galerien" (owner only).
 * Events (publish, code, send to client, expiry, previews, delete), favourites, prices, PhotoSorter API key, orders.
 */
add_action( 'admin_menu', function () {
	if ( ! class_exists( 'RD_Gal' ) ) {
		return;
	}
	add_menu_page( 'Galerien', 'Galerien', 'manage_options', 'rd-galerien', function () {
		global $wpdb;
		$events = $wpdb->get_results( 'SELECT * FROM ' . RD_Gal::t( 'events' ) . ' ORDER BY COALESCE(ev_date, created_at) DESC' );
		$counts = $wpdb->get_results( 'SELECT event_id, COUNT(*) n, SUM(bytes) b FROM ' . RD_Gal::t( 'photos' ) . ' GROUP BY event_id', OBJECT_K );
		$favs   = $wpdb->get_results( 'SELECT event_id, email, ids, updated_at FROM ' . RD_Gal::t( 'favs' ) . ' ORDER BY updated_at DESC' );
		$prices = RD_Gal::prices();
		$post   = admin_url( 'admin-post.php' );
		$act    = function ( $do, $id, $label, $cls = 'button', $confirm = '' ) use ( $post ) {
			return '<form method="post" action="' . esc_url( $post ) . '" style="display:inline"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>'
				. wp_nonce_field( 'rd_gal_' . $do, '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rd_gal"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">'
				. '<input type="hidden" name="id" value="' . esc_attr( $id ) . '"><button class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form> ';
		};
		echo '<div class="wrap"><h1>Galerien</h1>';
		if ( ! empty( $_GET['msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( wp_unslash( $_GET['msg'] ) ) . '</p></div>';
		}
		echo '<p>Events werden in <strong>PhotoSorter</strong> angelegt; fertige Lightroom-Exporte landen automatisch hier. <em>Kostenlos</em> = bezahlte Kunden laden alles ohne Wasserzeichen. <em>Verkauf</em> = Vorschau mit Wasserzeichen, Kauf pro Bild/Kapitel/Event.</p>';

		echo '<table class="widefat striped" style="margin-top:1rem"><thead><tr><th>Event</th><th>Datum</th><th>Modus</th><th>Status</th><th>Bilder</th><th>Code</th><th>Läuft ab</th><th>Aktionen</th></tr></thead><tbody>';
		if ( ! $events ) {
			echo '<tr><td colspan="8">Noch keine Events. Lege eines in PhotoSorter an und speichere es.</td></tr>';
		}
		foreach ( $events as $e ) {
			$n    = isset( $counts[ $e->id ] ) ? (int) $counts[ $e->id ]->n : 0;
			$gb   = isset( $counts[ $e->id ] ) ? size_format( (int) $counts[ $e->id ]->b, 1 ) : '0 B';
			$secs = array();
			foreach ( RD_Gal::sections( $e->id ) as $s ) {
				$secs[] = esc_html( $s->name ) . ' (' . (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RD_Gal::t( 'photos' ) . ' WHERE section_id=%d', $s->id ) ) . ')';
			}
			$open = RD_Gal::is_open( $e );
			echo '<tr><td><strong><a href="' . esc_url( RD_Gal::gallery_url( $e ) ) . '" target="_blank">' . esc_html( $e->name ) . '</a></strong><br><small>' . esc_html( ucfirst( $e->world ) ) . ' · ' . esc_html( $e->client_email ?: 'keine Kunden-E-Mail' ) . '</small>'
				. ( $secs ? '<br><small style="opacity:.7">' . implode( ' · ', $secs ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( $e->ev_date ? date_i18n( 'j.n.Y', strtotime( $e->ev_date ) ) : '–' ) . '</td>'
				. '<td>' . ( 'sale' === $e->mode ? 'Verkauf' : 'Kostenlos' ) . '</td>'
				. '<td>' . ( 'published' === $e->status ? ( $open ? '<span style="color:#1a7f37">● online</span>' : '<span style="color:#b32d2e">● abgelaufen</span>' ) : 'Entwurf' ) . '</td>'
				. '<td>' . $n . '<br><small>' . esc_html( $gb ) . '</small></td>'
				. '<td><code>' . esc_html( RD_Gal::dec( $e->code_enc ) ) . '</code></td>'
				. '<td>' . esc_html( $e->expires_at ? date_i18n( 'j.n.Y', strtotime( $e->expires_at . ' UTC' ) ) : '–' ) . '</td><td>';
			echo 'published' === $e->status ? $act( 'unpublish', $e->id, 'Offline nehmen' ) : $act( 'publish', $e->id, 'Veröffentlichen', 'button button-primary' );
			echo $act( 'send', $e->id, 'An Kunde senden', 'button', $e->client_email ? 'Link + Code an ' . $e->client_email . ' senden?' : 'Keine Kunden-E-Mail hinterlegt (in PhotoSorter eintragen).' );
			echo $act( 'extend', $e->id, '+12 Monate' );
			echo $act( 'newcode', $e->id, 'Neuer Code', 'button', 'Neuen Code erzeugen? Der alte funktioniert dann nicht mehr.' );
			if ( get_option( 'rd_gal_rebuild_' . $e->id ) ) {
				echo '<button class="button rd-rebuild" data-id="' . esc_attr( $e->id ) . '">Vorschaubilder neu erzeugen</button> ';
			}
			echo $act( 'delete', $e->id, 'Löschen', 'button button-link-delete', 'Event mit ALLEN Bildern endgültig löschen?' );
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		if ( $favs ) {
			echo '<h2 style="margin-top:2rem">Favoriten von Kunden</h2><table class="widefat striped"><thead><tr><th>Event</th><th>E-Mail</th><th>Bilder (Nummern)</th><th>Gespeichert</th></tr></thead><tbody>';
			foreach ( $favs as $f ) {
				$e    = RD_Gal::event( $f->event_id );
				$nums = array();
				foreach ( (array) json_decode( $f->ids, true ) as $pid ) {
					$p = RD_Gal::photo( (int) $pid );
					if ( $p ) {
						$nums[] = null !== $p->photo_number ? '#' . $p->photo_number : $p->filename;
					}
				}
				echo '<tr><td>' . esc_html( $e ? $e->name : $f->event_id ) . '</td><td>' . esc_html( $f->email ) . '</td><td><small>' . esc_html( implode( ', ', $nums ) ) . '</small></td><td>' . esc_html( get_date_from_gmt( $f->updated_at, 'j.n.Y H:i' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		// orders with gallery items
		if ( function_exists( 'wc_get_orders' ) ) {
			$pid    = (int) get_option( 'rd_gal_product' );
			$orders = $pid ? wc_get_orders( array( 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order' ) ) : array();
			$rows   = '';
			$sum    = 0;
			foreach ( $orders as $o ) {
				$lines = array();
				foreach ( $o->get_items() as $it ) {
					if ( $it->get_meta( '_rd_gal' ) ) {
						$lines[] = esc_html( $it->get_name() );
					}
				}
				if ( ! $lines ) {
					continue;
				}
				if ( $o->has_status( array( 'processing', 'completed' ) ) ) {
					$sum += (float) $o->get_total();
				}
				$rows .= '<tr><td><a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a></td><td>' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j.n.Y H:i' ) : '' ) . '</td>'
					. '<td>' . esc_html( $o->get_formatted_billing_full_name() ) . '<br><small>' . esc_html( $o->get_billing_email() ) . '</small></td><td><small>' . implode( '<br>', $lines ) . '</small></td>'
					. '<td>' . wp_kses_post( $o->get_formatted_order_total() ) . '</td><td>' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '</td><td>'
					. ( $o->get_meta( '_rd_gal_token' ) ? $act( 'resend', $o->get_id(), 'Link erneut senden' ) : '<small>Link folgt nach Zahlungseingang</small>' ) . '</td></tr>';
			}
			echo '<h2 style="margin-top:2rem">Bildverkäufe</h2><p>Bezahlt (letzte 50 Bestellungen): <strong>' . wp_kses_post( wc_price( $sum ) ) . '</strong></p>';
			echo '<table class="widefat striped"><thead><tr><th>Bestellung</th><th>Datum</th><th>Kunde</th><th>Inhalt</th><th>Summe</th><th>Status</th><th></th></tr></thead><tbody>' . ( $rows ?: '<tr><td colspan="7">Noch keine Bildverkäufe.</td></tr>' ) . '</tbody></table>';
		}

		// prices + settings
		echo '<h2 style="margin-top:2rem">Preise für Verkaufs-Galerien</h2><form method="post" action="' . esc_url( $post ) . '">' . wp_nonce_field( 'rd_gal_prices', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="rd_gal"><input type="hidden" name="do" value="prices"><table class="form-table">';
		foreach ( array( 'photo' => 'Einzelbild', 'bundle3' => '3 Bilder', 'bundle6' => '6 Bilder', 'section' => 'Ganzes Kapitel (z. B. alle Bilder eines Ritts)', 'event' => 'Ganzes Event' ) as $k => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><input name="p[' . esc_attr( $k ) . ']" type="number" step="0.01" min="0" value="' . esc_attr( number_format( (float) $prices[ $k ], 2, '.', '' ) ) . '"> €</td></tr>';
		}
		echo '<tr><th>Galerien laufen ab nach</th><td><input name="expiry" type="number" min="1" max="60" value="' . (int) get_option( 'rd_gal_expiry_months', 12 ) . '"> Monaten</td></tr>';
		echo '</table><button class="button button-primary">Speichern</button></form>';

		$key = RD_Gal::api_key();
		echo '<h2 style="margin-top:2rem">PhotoSorter-Verbindung</h2><p>In <code>config.json</code> von PhotoSorter eintragen:</p>'
			. '<pre style="background:#fff;padding:1rem;border:1px solid #ddd;max-width:720px;overflow:auto">"website_url": "' . esc_html( home_url( '/' ) ) . '",' . "\n" . '"api_key": "<span id="rd-key" style="filter:blur(4px)">' . esc_html( $key ) . '</span>"</pre>'
			. '<button class="button" onclick="document.getElementById(\'rd-key\').style.filter=\'none\'">Schlüssel anzeigen</button> '
			. $act( 'newkey', '', 'Neuen Schlüssel erzeugen', 'button', 'Neuen API-Schlüssel erzeugen? PhotoSorter braucht dann den neuen Schlüssel.' );
		$priv = RD_Gal::priv_dir();
		echo '<p><small>Originale liegen geschützt außerhalb des Webverzeichnisses: <code>' . esc_html( $priv ) . '</code></small></p>';
		echo '</div>';
		?>
		<script>
		document.querySelectorAll('.rd-rebuild').forEach(function(b){ b.addEventListener('click',function(){
		  var id=b.dataset.id, run=function(off){ b.disabled=true;
		    fetch('<?php echo esc_url_raw( rest_url( 'rd/v1/admin/events/' ) ); ?>'+id+'/rebuild?offset='+off,{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':'<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'}})
		      .then(function(r){return r.json();}).then(function(j){ b.textContent='Vorschaubilder: '+j.done+' / '+j.total; if(j.done<j.total) run(j.done); else b.textContent='✓ Vorschaubilder fertig'; })
		      .catch(function(){ b.disabled=false; b.textContent='Fehler, nochmal versuchen'; }); };
		  run(0); }); });
		</script>
		<?php
	}, 'dashicons-format-gallery', 26 );
} );

add_action( 'admin_post_rd_gal', function () {
	global $wpdb;
	$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
	if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'RD_Gal' ) ) {
		wp_die( 'Keine Berechtigung' );
	}
	check_admin_referer( 'rd_gal_' . $do );
	$id  = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$e   = $id ? RD_Gal::event( $id ) : null;
	$msg = 'Gespeichert.';
	$t   = RD_Gal::t( 'events' );
	switch ( $do ) {
		case 'publish':
			$vals = array( 'status' => 'published' );
			if ( ! $e->expires_at ) {
				$vals['expires_at'] = gmdate( 'Y-m-d H:i:s', strtotime( '+' . (int) get_option( 'rd_gal_expiry_months', 12 ) . ' months' ) );
			}
			$wpdb->update( $t, $vals, array( 'id' => $e->id ) );
			$msg = 'Galerie ist online.';
			break;
		case 'unpublish':
			$wpdb->update( $t, array( 'status' => 'draft' ), array( 'id' => $e->id ) );
			$msg = 'Galerie ist offline.';
			break;
		case 'extend':
			$base = $e->expires_at && strtotime( $e->expires_at . ' UTC' ) > time() ? strtotime( $e->expires_at . ' UTC' ) : time();
			$wpdb->update( $t, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+12 months', $base ) ) ), array( 'id' => $e->id ) );
			$msg = 'Ablaufdatum verlängert.';
			break;
		case 'newcode':
			$msg = 'Neuer Code: ' . RD_Gal::set_code( $e );
			break;
		case 'send':
			if ( ! $e->client_email ) {
				$msg = 'Keine Kunden-E-Mail hinterlegt.';
				break;
			}
			$code = RD_Gal::dec( $e->code_enc );
			$url  = RD_Gal::gallery_url( $e );
			$html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#222;max-width:560px"><p>Hallo,</p>'
				. '<p>eure Bilder von <strong>' . esc_html( $e->name ) . '</strong> sind online!</p>'
				. '<p style="margin:26px 0"><a href="' . esc_url( $url ) . '" style="background:#B4877A;color:#fff;padding:14px 26px;border-radius:999px;text-decoration:none;font-weight:bold">Zur Galerie</a></p>'
				. '<p>Euer Zugangscode: <strong style="font-size:18px;letter-spacing:1px">' . esc_html( $code ) . '</strong></p>'
				. ( 'free' === $e->mode ? '<p>Ihr könnt alle Bilder einzeln oder als ZIP in voller Auflösung herunterladen. Mit dem Herz markiert ihr eure Favoriten.</p>' : '' )
				. '<p>Die Galerie ist bis ' . esc_html( $e->expires_at ? date_i18n( 'j. F Y', strtotime( $e->expires_at . ' UTC' ) ) : 'auf Weiteres' ) . ' online. Bitte ladet eure Bilder bis dahin herunter.</p>'
				. '<p>Viel Freude beim Anschauen!<br>Rui Dodat Fotografie</p></div>';
			$ok   = wp_mail( $e->client_email, 'Eure Bilder sind online · ' . $e->name, $html, array( 'Content-Type: text/html; charset=UTF-8', 'From: Rui Dodat Fotografie <buchung@ruidodat.com>', 'Reply-To: ruidodat@gmail.com' ) );
			$msg  = $ok ? 'E-Mail an ' . $e->client_email . ' gesendet.' : 'E-Mail konnte nicht gesendet werden.';
			if ( 'published' !== $e->status ) {
				$msg .= ' Achtung: Die Galerie ist noch nicht veröffentlicht.';
			}
			break;
		case 'delete':
			RD_Gal::delete_event( $e );
			$msg = 'Event und alle Bilder gelöscht.';
			break;
		case 'prices':
			$p = array();
			foreach ( array( 'photo', 'bundle3', 'bundle6', 'section', 'event' ) as $k ) {
				$p[ $k ] = round( max( 0, (float) ( $_POST['p'][ $k ] ?? 0 ) ), 2 );
			}
			update_option( 'rd_gal_prices', $p );
			update_option( 'rd_gal_expiry_months', max( 1, min( 60, (int) ( $_POST['expiry'] ?? 12 ) ) ) );
			$msg = 'Preise gespeichert.';
			break;
		case 'newkey':
			RD_Gal::api_key( true );
			$msg = 'Neuer API-Schlüssel erzeugt. Bitte in PhotoSorter eintragen.';
			break;
		case 'resend':
			if ( function_exists( 'rd_gal_deliver' ) ) {
				rd_gal_deliver( (int) $id, true );
			}
			$msg = 'Download-Link erneut gesendet.';
			break;
	}
	wp_safe_redirect( add_query_arg( 'msg', rawurlencode( $msg ), admin_url( 'admin.php?page=rd-galerien' ) ) );
	exit;
} );
