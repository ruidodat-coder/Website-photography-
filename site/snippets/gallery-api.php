/**
 * Client galleries, part 2: uploader API for the local PhotoSorter app.
 * Auth: header "X-RD-Key: <API key from wp-admin → Galerien>". Never exposed to browsers.
 *   POST|PUT /rd/v1/admin/events/{id}            create/update event + sections
 *   GET      /rd/v1/admin/events/{id}            status, per-section counts, filename→hash map
 *   POST     /rd/v1/admin/events/{id}/photos     multipart upload (file, section_name, photo_number)
 *   POST     /rd/v1/admin/events/{id}/photos/delete {filename}   (DELETE …/photos/{filename} also works)
 *   POST     /rd/v1/admin/events/{id}/publish    {published: true|false}
 */
add_action( 'rest_api_init', function () {
	if ( ! class_exists( 'RD_Gal' ) ) {
		return;
	}
	$auth = function ( WP_REST_Request $r ) {
		return RD_Gal::key_ok( $r->get_header( 'x_rd_key' ) ) || current_user_can( 'manage_options' )
			? true : new WP_Error( 'rd_auth', 'Invalid API key', array( 'status' => 401 ) );
	};
	$need = function ( $id ) {
		$e = RD_Gal::event( $id );
		return $e ?: new WP_Error( 'rd_404', 'Event not found. Save it in PhotoSorter first.', array( 'status' => 404 ) );
	};
	$summary = function ( $e ) {
		global $wpdb;
		$counts = $wpdb->get_results( $wpdb->prepare( 'SELECT section_id, COUNT(*) n FROM ' . RD_Gal::t( 'photos' ) . ' WHERE event_id=%s GROUP BY section_id', $e->id ), OBJECT_K );
		$secs   = array();
		foreach ( RD_Gal::sections( $e->id ) as $s ) {
			$secs[] = array( 'name' => $s->name, 'uploaded' => isset( $counts[ $s->id ] ) ? (int) $counts[ $s->id ]->n : 0 );
		}
		if ( isset( $counts[0] ) ) {
			$secs[] = array( 'name' => 'Weitere Bilder', 'uploaded' => (int) $counts[0]->n );
		}
		$files = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT filename, sha FROM ' . RD_Gal::t( 'photos' ) . ' WHERE event_id=%s', $e->id ) ) as $p ) {
			$files[ $p->filename ] = $p->sha;
		}
		return array( 'id' => $e->id, 'slug' => $e->slug, 'status' => $e->status, 'mode' => $e->mode, 'world' => $e->world,
			'code' => RD_Gal::dec( $e->code_enc ), 'url' => RD_Gal::gallery_url( $e ), 'expires_at' => $e->expires_at,
			'sections' => $secs, 'files' => (object) $files );
	};
	$id_rx = '(?P<id>[A-Za-z0-9_-]{1,32})';

	// API key for PhotoSorter's config.json (logged-in administrators only)
	register_rest_route( 'rd/v1', '/admin/key', array( 'methods' => 'GET', 'callback' => function () {
		return array( 'api_key' => RD_Gal::api_key(), 'website_url' => home_url( '/' ) );
	}, 'permission_callback' => function () {
		return current_user_can( 'manage_options' );
	} ) );

	register_rest_route( 'rd/v1', "/admin/events/$id_rx", array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => function ( $r ) use ( $need, $summary ) {
			$e = $need( $r['id'] );
			return is_wp_error( $e ) ? $e : $summary( $e );
		} ),
		array( 'methods' => 'POST, PUT', 'permission_callback' => $auth, 'callback' => function ( $r ) use ( $summary ) {
			global $wpdb;
			$d        = $r->get_json_params() ?: array();
			$name     = sanitize_text_field( $d['name'] ?? '' );
			$sections = is_array( $d['sections'] ?? null ) ? $d['sections'] : array();
			if ( '' === $name ) {
				return new WP_Error( 'rd_bad', 'Event name missing', array( 'status' => 400 ) );
			}
			$problems = RD_Gal::validate_sections( $sections );
			if ( $problems ) {
				return new WP_Error( 'rd_ranges', implode( '; ', $problems ), array( 'status' => 400, 'problems' => $problems ) );
			}
			$date  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] ?? '' ) ? $d['date'] : null;
			$world = in_array( $d['world'] ?? '', array( 'pferde', 'hochzeit' ), true ) ? $d['world'] : 'hochzeit';
			$mode  = in_array( $d['mode'] ?? '', array( 'free', 'sale' ), true ) ? $d['mode'] : 'free';
			$now   = current_time( 'mysql', true );
			$e     = RD_Gal::event( $r['id'] );
			$row   = array( 'name' => $name, 'ev_date' => $date, 'world' => $world, 'client_email' => sanitize_email( $d['client'] ?? '' ), 'updated_at' => $now );
			if ( $e ) {
				$wpdb->update( RD_Gal::t( 'events' ), $row, array( 'id' => $e->id ) );
				if ( $e->mode !== $mode ) { // watermark changes: previews must be rebuilt
					$wpdb->update( RD_Gal::t( 'events' ), array( 'mode' => $mode ), array( 'id' => $e->id ) );
					update_option( 'rd_gal_rebuild_' . $e->id, 1, false );
				}
			} else {
				$slug = sanitize_title( trim( ( $date ?: '' ) . ' ' . $name ) );
				$base = $slug;
				for ( $i = 2; RD_Gal::event_by_slug( $slug ); $i++ ) {
					$slug = $base . '-' . $i;
				}
				$wpdb->insert( RD_Gal::t( 'events' ), $row + array( 'id' => $r['id'], 'slug' => $slug, 'mode' => $mode, 'status' => 'draft',
					'pub_key' => strtolower( wp_generate_password( 16, false, false ) ), 'created_at' => $now ) );
				RD_Gal::set_code( RD_Gal::event( $r['id'] ) );
			}
			// sections: match by name so photos keep their section; removed sections fall back to "Weitere Bilder"
			$old  = RD_Gal::sections( $r['id'] );
			$keep = array();
			foreach ( array_values( $sections ) as $pos => $s ) {
				$nm  = sanitize_text_field( $s['name'] );
				$hit = null;
				foreach ( $old as $o ) {
					if ( 0 === strcasecmp( $o->name, $nm ) ) {
						$hit = $o;
					}
				}
				$vals = array( 'name' => $nm, 'position' => $pos, 'start_no' => (int) $s['start'], 'end_no' => (int) $s['end'] );
				if ( $hit ) {
					$wpdb->update( RD_Gal::t( 'sections' ), $vals, array( 'id' => $hit->id ) );
					$keep[] = (int) $hit->id;
				} else {
					$wpdb->insert( RD_Gal::t( 'sections' ), $vals + array( 'event_id' => $r['id'] ) );
					$keep[] = (int) $wpdb->insert_id;
				}
			}
			foreach ( $old as $o ) {
				if ( ! in_array( (int) $o->id, $keep, true ) ) {
					$wpdb->delete( RD_Gal::t( 'sections' ), array( 'id' => $o->id ) );
					$wpdb->update( RD_Gal::t( 'photos' ), array( 'section_id' => 0 ), array( 'event_id' => $r['id'], 'section_id' => $o->id ) );
				}
			}
			return $summary( RD_Gal::event( $r['id'] ) );
		} ),
	) );

	register_rest_route( 'rd/v1', "/admin/events/$id_rx/photos", array(
		'methods' => 'POST', 'permission_callback' => $auth, 'callback' => function ( $r ) use ( $need ) {
			global $wpdb;
			$e = $need( $r['id'] );
			if ( is_wp_error( $e ) ) {
				return $e;
			}
			$f = $r->get_file_params()['file'] ?? null;
			if ( ! $f || UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
				return new WP_Error( 'rd_file', 'No file received', array( 'status' => 400 ) );
			}
			$fn = RD_Gal::safe( basename( $r->get_param( 'filename' ) ?: $f['name'] ) );
			if ( ! preg_match( '/\.jpe?g$/i', $fn ) || ! @getimagesize( $f['tmp_name'] ) ) {
				return new WP_Error( 'rd_type', 'Only JPG exports are accepted', array( 'status' => 415 ) );
			}
			$sha = hash_file( 'sha256', $f['tmp_name'] );
			$num = $r->get_param( 'photo_number' );
			$num = is_numeric( $num ) ? (int) $num : ( preg_match_all( '/\d+/', pathinfo( $fn, PATHINFO_FILENAME ), $m ) ? (int) end( $m[0] ) : null );
			$sid = RD_Gal::section_for( $e->id, (string) $r->get_param( 'section_name' ), $num );
			$cur = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RD_Gal::t( 'photos' ) . ' WHERE event_id=%s AND filename=%s', $e->id, $fn ) );
			if ( $cur && $cur->sha === $sha ) {
				if ( (int) $cur->section_id !== $sid ) {
					$wpdb->update( RD_Gal::t( 'photos' ), array( 'section_id' => $sid ), array( 'id' => $cur->id ) );
				}
				return array( 'status' => 'unchanged', 'id' => (int) $cur->id );
			}
			$dims = getimagesize( $f['tmp_name'] );
			$vals = array( 'section_id' => $sid, 'photo_number' => $num, 'sha' => $sha, 'width' => (int) $dims[0], 'height' => (int) $dims[1],
				'bytes' => (int) filesize( $f['tmp_name'] ), 'uploaded_at' => current_time( 'mysql', true ) );
			if ( $cur ) {
				$wpdb->update( RD_Gal::t( 'photos' ), $vals, array( 'id' => $cur->id ) );
				$pid = (int) $cur->id;
			} else {
				$wpdb->insert( RD_Gal::t( 'photos' ), $vals + array( 'event_id' => $e->id, 'filename' => $fn, 'pkey' => strtolower( wp_generate_password( 16, false, false ) ) ) );
				$pid = (int) $wpdb->insert_id;
			}
			$photo = RD_Gal::photo( $pid );
			$dest  = RD_Gal::orig_path( $e, $photo );
			if ( ! move_uploaded_file( $f['tmp_name'], $dest ) ) {
				return new WP_Error( 'rd_store', 'Could not store the file', array( 'status' => 500 ) );
			}
			@chmod( $dest, 0640 );
			try {
				RD_Gal::process( $e, $photo, $dest );
			} catch ( Exception $ex ) {
				return new WP_Error( 'rd_img', 'Preview failed: ' . $ex->getMessage(), array( 'status' => 500 ) );
			}
			if ( ! $e->cover_photo ) {
				$wpdb->update( RD_Gal::t( 'events' ), array( 'cover_photo' => $pid ), array( 'id' => $e->id ) );
			}
			return array( 'status' => $cur ? 'replaced' : 'created', 'id' => $pid, 'section_id' => $sid );
		},
	) );

	$del = function ( $r ) use ( $need ) {
		global $wpdb;
		$e = $need( $r['id'] );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$fn = RD_Gal::safe( basename( rawurldecode( (string) ( $r['filename'] ?? $r->get_param( 'filename' ) ) ) ) );
		$p  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RD_Gal::t( 'photos' ) . ' WHERE event_id=%s AND filename=%s', $e->id, $fn ) );
		if ( ! $p ) {
			return array( 'status' => 'absent' );
		}
		RD_Gal::delete_photo( $e, $p );
		if ( (int) $e->cover_photo === (int) $p->id ) {
			$wpdb->update( RD_Gal::t( 'events' ), array( 'cover_photo' => null ), array( 'id' => $e->id ) );
		}
		return array( 'status' => 'deleted' );
	};
	register_rest_route( 'rd/v1', "/admin/events/$id_rx/photos/delete", array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => $del ) );
	register_rest_route( 'rd/v1', "/admin/events/$id_rx/photos/(?P<filename>[^/]+)", array( 'methods' => 'DELETE', 'permission_callback' => $auth, 'callback' => $del ) );

	register_rest_route( 'rd/v1', "/admin/events/$id_rx/publish", array(
		'methods' => 'POST', 'permission_callback' => $auth, 'callback' => function ( $r ) use ( $need, $summary ) {
			global $wpdb;
			$e = $need( $r['id'] );
			if ( is_wp_error( $e ) ) {
				return $e;
			}
			$on   = rest_sanitize_boolean( $r->get_param( 'published' ) );
			$vals = array( 'status' => $on ? 'published' : 'draft', 'updated_at' => current_time( 'mysql', true ) );
			if ( $on && ! $e->expires_at ) {
				$vals['expires_at'] = gmdate( 'Y-m-d H:i:s', strtotime( '+' . (int) get_option( 'rd_gal_expiry_months', 12 ) . ' months' ) );
			}
			$wpdb->update( RD_Gal::t( 'events' ), $vals, array( 'id' => $e->id ) );
			return $summary( RD_Gal::event( $e->id ) );
		},
	) );

	// previews are rebuilt in batches after a free/sale switch (called by wp-admin or PhotoSorter)
	register_rest_route( 'rd/v1', "/admin/events/$id_rx/rebuild", array(
		'methods' => 'POST', 'permission_callback' => $auth, 'callback' => function ( $r ) use ( $need ) {
			$e = $need( $r['id'] );
			if ( is_wp_error( $e ) ) {
				return $e;
			}
			$all   = RD_Gal::photos( $e->id );
			$from  = max( 0, (int) $r->get_param( 'offset' ) );
			$batch = array_slice( $all, $from, 15 );
			foreach ( $batch as $p ) {
				$src = RD_Gal::orig_path( $e, $p );
				if ( is_file( $src ) ) {
					RD_Gal::process( $e, $p, $src );
				}
			}
			$next = $from + count( $batch );
			if ( $next >= count( $all ) ) {
				delete_option( 'rd_gal_rebuild_' . $e->id );
			}
			return array( 'done' => $next, 'total' => count( $all ) );
		},
	) );
} );
