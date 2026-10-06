/**
 * Client galleries, part 1: data, storage, image pipeline, access, ZIP streaming.
 * Used by gallery-api (uploader), gallery-front (clients), gallery-shop (sales) and gallery-admin.
 *
 * Storage: originals live OUTSIDE the web root (<home>/rd-galleries/<event>/), so they can only be
 * reached through the access-checked download endpoints. Previews/thumbnails (EXIF/GPS stripped,
 * watermarked for sale galleries) live in uploads/rd-gallery/<random event key>/.
 * The class is guarded so re-saving the snippet never redeclares it.
 */
if ( ! class_exists( 'RD_Gal' ) ) {
	final class RD_Gal {
		const DB_VER    = '1';
		const PREVIEW   = 2048;
		const THUMB     = 640;
		const ZIP_LIMIT = 1800000000; // bytes per ZIP part (keeps every part well below 4 GB)

		static function t( $name ) {
			global $wpdb;
			return $wpdb->prefix . 'rd_gal_' . $name;
		}

		static function install() {
			if ( get_option( 'rd_gal_db' ) === self::DB_VER ) {
				return;
			}
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$c = $wpdb->get_charset_collate();
			dbDelta( "CREATE TABLE " . self::t( 'events' ) . " (
  id varchar(32) NOT NULL,
  slug varchar(190) NOT NULL,
  name varchar(190) NOT NULL,
  ev_date date NULL,
  world varchar(12) NOT NULL DEFAULT 'hochzeit',
  mode varchar(12) NOT NULL DEFAULT 'free',
  client_email varchar(190) NOT NULL DEFAULT '',
  code_hmac char(64) NOT NULL DEFAULT '',
  code_enc text NULL,
  status varchar(12) NOT NULL DEFAULT 'draft',
  pub_key char(16) NOT NULL,
  expires_at datetime NULL,
  cover_photo bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY code_hmac (code_hmac)
) $c;" );
			dbDelta( "CREATE TABLE " . self::t( 'sections' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_id varchar(32) NOT NULL,
  name varchar(190) NOT NULL,
  position int(11) NOT NULL DEFAULT 0,
  start_no int(11) NULL,
  end_no int(11) NULL,
  PRIMARY KEY  (id),
  KEY event_id (event_id)
) $c;" );
			dbDelta( "CREATE TABLE " . self::t( 'photos' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_id varchar(32) NOT NULL,
  section_id bigint(20) unsigned NOT NULL DEFAULT 0,
  photo_number int(11) NULL,
  filename varchar(190) NOT NULL,
  sha char(64) NOT NULL,
  pkey char(16) NOT NULL,
  width int(11) NOT NULL DEFAULT 0,
  height int(11) NOT NULL DEFAULT 0,
  bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  uploaded_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY event_file (event_id,filename),
  KEY event_section (event_id,section_id)
) $c;" );
			dbDelta( "CREATE TABLE " . self::t( 'favs' ) . " (
  event_id varchar(32) NOT NULL,
  email varchar(190) NOT NULL,
  ids longtext NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (event_id,email)
) $c;" );
			update_option( 'rd_gal_db', self::DB_VER );
		}

		/* ---------------- storage ---------------- */
		static function priv_dir( $event_id = '' ) {
			$base = dirname( untrailingslashit( ABSPATH ) ) . '/rd-galleries';
			if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
				// fallback inside uploads, locked down by .htaccess
				$u    = wp_upload_dir();
				$base = $u['basedir'] . '/rd-private';
				wp_mkdir_p( $base );
				if ( ! file_exists( $base . '/.htaccess' ) ) {
					file_put_contents( $base . '/.htaccess', "Require all denied\nDeny from all\n" );
				}
			}
			$dir = $event_id ? $base . '/' . self::safe( $event_id ) : $base;
			if ( $event_id && ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			return $dir;
		}

		static function pub( $event ) {
			$u   = wp_upload_dir();
			$dir = $u['basedir'] . '/rd-gallery/' . $event->pub_key;
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				$root = $u['basedir'] . '/rd-gallery';
				if ( ! file_exists( $root . '/.htaccess' ) ) {
					file_put_contents( $root . '/.htaccess', "Options -Indexes\n" );
					file_put_contents( $root . '/index.html', '' );
				}
				file_put_contents( $dir . '/index.html', '' );
			}
			return array( $dir, $u['baseurl'] . '/rd-gallery/' . $event->pub_key );
		}

		static function safe( $s ) {
			$s = preg_replace( '/[^A-Za-z0-9._ -]+/', '-', (string) $s );
			return trim( $s, ' .-' ) ?: 'x';
		}

		static function orig_path( $event, $photo ) {
			return self::priv_dir( $event->id ) . '/' . self::safe( $photo->filename );
		}

		/* ---------------- keys, codes, crypto ---------------- */
		static function api_key( $regen = false ) {
			$k = get_option( 'rd_gal_api_key' );
			if ( ! $k || $regen ) {
				$k = 'rdg_' . wp_generate_password( 40, false, false );
				update_option( 'rd_gal_api_key', $k, false );
			}
			return $k;
		}

		static function key_ok( $given ) {
			$k = get_option( 'rd_gal_api_key' );
			return $k && is_string( $given ) && hash_equals( $k, $given );
		}

		static function norm_code( $code ) {
			return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
		}

		static function code_hmac( $code ) {
			return hash_hmac( 'sha256', self::norm_code( $code ), wp_salt( 'auth' ) );
		}

		static function new_code( $name ) {
			$word = strtoupper( remove_accents( (string) $name ) );
			$word = preg_replace( '/[^A-Z ]/', ' ', $word );
			$parts = array_values( array_filter( explode( ' ', $word ), function ( $w ) {
				return strlen( $w ) >= 3 && ! in_array( $w, array( 'HOCHZEIT', 'WEDDING', 'UND', 'AND', 'TURNIER', 'SHOOTING' ), true );
			} ) );
			$prefix = $parts ? substr( $parts[0], 0, 8 ) : 'EVENT';
			$abc    = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
			$rnd    = '';
			for ( $i = 0; $i < 5; $i++ ) {
				$rnd .= $abc[ random_int( 0, strlen( $abc ) - 1 ) ];
			}
			return $prefix . '-' . $rnd;
		}

		static function enc( $plain ) {
			$key = hash( 'sha256', wp_salt( 'secure_auth' ), true );
			$iv  = random_bytes( 16 );
			return base64_encode( $iv . openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv ) );
		}

		static function dec( $blob ) {
			$raw = base64_decode( (string) $blob, true );
			if ( ! $raw || strlen( $raw ) < 17 ) {
				return '';
			}
			$key = hash( 'sha256', wp_salt( 'secure_auth' ), true );
			return (string) openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		}

		/** Sets a fresh access code; returns it in plain text (shown to the owner, emailed to the client). */
		static function set_code( $event ) {
			global $wpdb;
			$code = self::new_code( $event->name );
			$wpdb->update( self::t( 'events' ), array( 'code_hmac' => self::code_hmac( $code ), 'code_enc' => self::enc( $code ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $event->id ) );
			return $code;
		}

		/* ---------------- lookups ---------------- */
		static function event( $id ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'events' ) . ' WHERE id=%s', $id ) );
		}

		static function event_by_slug( $slug ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'events' ) . ' WHERE slug=%s', $slug ) );
		}

		static function event_by_code( $code ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'events' ) . ' WHERE code_hmac=%s', self::code_hmac( $code ) ) );
		}

		static function sections( $event_id ) {
			global $wpdb;
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'sections' ) . ' WHERE event_id=%s ORDER BY position, start_no, id', $event_id ) );
		}

		static function photos( $event_id, $section_id = null ) {
			global $wpdb;
			$sql = 'SELECT * FROM ' . self::t( 'photos' ) . ' WHERE event_id=%s';
			$arg = array( $event_id );
			if ( null !== $section_id ) {
				$sql  .= ' AND section_id=%d';
				$arg[] = (int) $section_id;
			}
			return $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY photo_number IS NULL, photo_number, filename', $arg ) );
		}

		static function photo( $id ) {
			global $wpdb;
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'photos' ) . ' WHERE id=%d', $id ) );
		}

		static function is_open( $event ) {
			if ( ! $event || 'published' !== $event->status ) {
				return false;
			}
			return ! $event->expires_at || strtotime( $event->expires_at . ' UTC' ) > time();
		}

		static function world_page( $world ) {
			return home_url( 'hochzeit' === $world ? '/hochzeit/eure-bilder/' : '/pferde/deine-bilder/' );
		}

		static function gallery_url( $event ) {
			return add_query_arg( 'e', $event->slug, self::world_page( $event->world ) );
		}

		/* ---------------- client access (cookie per event) ---------------- */
		static function cookie_name( $event ) {
			return 'rd_gal_' . substr( md5( $event->id ), 0, 12 );
		}

		static function grant( $event ) {
			$exp = time() + 30 * DAY_IN_SECONDS;
			$val = $exp . '|' . hash_hmac( 'sha256', $event->id . '|' . $exp . '|' . $event->code_hmac, wp_salt( 'logged_in' ) );
			setcookie( self::cookie_name( $event ), $val, array( 'expires' => $exp, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			$_COOKIE[ self::cookie_name( $event ) ] = $val;
		}

		static function has_access( $event ) {
			if ( ! $event ) {
				return false;
			}
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
			if ( ! self::is_open( $event ) ) {
				return false;
			}
			$val = isset( $_COOKIE[ self::cookie_name( $event ) ] ) ? (string) wp_unslash( $_COOKIE[ self::cookie_name( $event ) ] ) : '';
			$p   = explode( '|', $val );
			if ( 2 !== count( $p ) || (int) $p[0] < time() ) {
				return false;
			}
			return hash_equals( hash_hmac( 'sha256', $event->id . '|' . (int) $p[0] . '|' . $event->code_hmac, wp_salt( 'logged_in' ) ), $p[1] );
		}

		/* ---------------- prices ---------------- */
		static function prices() {
			$d = array( 'photo' => 12.90, 'bundle3' => 29.90, 'bundle6' => 49.90, 'section' => 59.00, 'event' => 199.00 );
			return wp_parse_args( (array) get_option( 'rd_gal_prices', array() ), $d );
		}

		/** Best price for n single photos: packs of 6, then 3, then singles. */
		static function singles_total( $n ) {
			$p = self::prices();
			$t = 0.0;
			$t += intdiv( $n, 6 ) * $p['bundle6'];
			$n %= 6;
			$t += intdiv( $n, 3 ) * $p['bundle3'];
			$n %= 3;
			$t += $n * $p['photo'];
			return round( $t, 2 );
		}

		/* ---------------- image pipeline ---------------- */
		static function process( $event, $photo, $src ) {
			list( $dir ) = self::pub( $event );
			$wm          = ( 'sale' === $event->mode );
			$im          = new Imagick();
			$im->setOption( 'jpeg:size', ( self::PREVIEW * 2 ) . 'x' . ( self::PREVIEW * 2 ) ); // decode downscaled: low memory
			$im->readImage( $src );
			self::orient( $im );
			$profiles = $im->getImageProfiles( 'icc', true );
			$im->stripImage(); // drops EXIF incl. GPS
			if ( ! empty( $profiles['icc'] ) ) {
				$im->profileImage( 'icc', $profiles['icc'] );
			}
			$im->setImageCompression( Imagick::COMPRESSION_JPEG );
			if ( $im->getImageWidth() > self::PREVIEW || $im->getImageHeight() > self::PREVIEW ) {
				$im->resizeImage( self::PREVIEW, self::PREVIEW, Imagick::FILTER_LANCZOS, 1, true );
			}
			if ( $wm ) {
				self::watermark( $im );
			}
			$im->setImageCompressionQuality( 82 );
			$im->setInterlaceScheme( Imagick::INTERLACE_PLANE );
			$im->writeImage( $dir . '/' . $photo->pkey . '-p.jpg' );
			$im->thumbnailImage( self::THUMB, self::THUMB, true );
			$im->setImageCompressionQuality( 78 );
			$im->writeImage( $dir . '/' . $photo->pkey . '-t.jpg' );
			$im->clear();
		}

		static function orient( Imagick $im ) {
			switch ( $im->getImageOrientation() ) {
				case Imagick::ORIENTATION_BOTTOMRIGHT: $im->rotateImage( '#000', 180 ); break;
				case Imagick::ORIENTATION_RIGHTTOP: $im->rotateImage( '#000', 90 ); break;
				case Imagick::ORIENTATION_LEFTBOTTOM: $im->rotateImage( '#000', -90 ); break;
			}
			$im->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
		}

		/** Tiled logo watermark (white with a soft dark edge, so it shows on light and dark photos). */
		static function watermark( Imagick $im ) {
			$logo_path = get_attached_file( (int) get_option( 'rd_gal_logo_id', 441 ) );
			$w         = $im->getImageWidth();
			$h         = $im->getImageHeight();
			if ( ! $logo_path || ! file_exists( $logo_path ) ) {
				return;
			}
			$logo = new Imagick( $logo_path );
			$logo->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
			$tw = (int) round( max( $w, $h ) * 0.30 );
			$logo->resizeImage( $tw, 0, Imagick::FILTER_LANCZOS, 1 );
			$logo->rotateImage( new ImagickPixel( 'transparent' ), -18 );
			$white = clone $logo;
			$white->negateImage( false, Imagick::CHANNEL_RED | Imagick::CHANNEL_GREEN | Imagick::CHANNEL_BLUE );
			$white->evaluateImage( Imagick::EVALUATE_MULTIPLY, 0.42, Imagick::CHANNEL_ALPHA );
			$dark = clone $logo;
			$dark->evaluateImage( Imagick::EVALUATE_MULTIPLY, 0.18, Imagick::CHANNEL_ALPHA );
			$lw = $white->getImageWidth();
			$lh = $white->getImageHeight();
			$sx = (int) ( $lw * 1.25 );
			$sy = (int) ( $lh * 1.6 );
			for ( $y = -$lh / 2, $row = 0; $y < $h; $y += $sy, $row++ ) {
				for ( $x = ( $row % 2 ? -$lw / 2 : 0 ) - $lw / 4; $x < $w; $x += $sx ) {
					$im->compositeImage( $dark, Imagick::COMPOSITE_OVER, (int) $x + 2, (int) $y + 2 );
					$im->compositeImage( $white, Imagick::COMPOSITE_OVER, (int) $x, (int) $y );
				}
			}
			$logo->clear();
			$white->clear();
			$dark->clear();
		}

		/* ---------------- events: sync + delete ---------------- */
		static function validate_sections( $sections ) {
			$problems = array();
			$clean    = array();
			foreach ( $sections as $s ) {
				$name = isset( $s['name'] ) ? trim( (string) $s['name'] ) : '';
				if ( '' === $name ) {
					$problems[] = 'Section without name';
					continue;
				}
				if ( ! is_numeric( $s['start'] ?? null ) || ! is_numeric( $s['end'] ?? null ) ) {
					$problems[] = "'$name' has an invalid range";
					continue;
				}
				$a = (int) $s['start'];
				$b = (int) $s['end'];
				if ( $a > $b ) {
					$problems[] = "'$name' starts after it ends ($a > $b)";
				}
				$clean[] = array( $a, $b, $name );
			}
			usort( $clean, function ( $x, $y ) { return $x[0] <=> $y[0]; } );
			for ( $i = 1; $i < count( $clean ); $i++ ) {
				if ( $clean[ $i ][0] <= $clean[ $i - 1 ][1] ) {
					$problems[] = "'{$clean[$i-1][2]}' and '{$clean[$i][2]}' overlap";
				}
			}
			return $problems;
		}

		static function section_for( $event_id, $name, $number ) {
			$name = trim( preg_replace( '/^\d+\s+/', '', (string) $name ) ); // "03 Church ceremony" -> "Church ceremony"
			$secs = self::sections( $event_id );
			foreach ( $secs as $s ) {
				if ( '' !== $name && 0 === strcasecmp( $s->name, $name ) ) {
					return (int) $s->id;
				}
			}
			if ( null !== $number ) {
				foreach ( $secs as $s ) {
					if ( null !== $s->start_no && $number >= (int) $s->start_no && $number <= (int) $s->end_no ) {
						return (int) $s->id;
					}
				}
			}
			return 0; // "Weitere Bilder"
		}

		static function delete_event( $event ) {
			global $wpdb;
			$priv = self::priv_dir( $event->id );
			list( $pub ) = self::pub( $event );
			foreach ( array( $priv, $pub ) as $dir ) {
				foreach ( (array) glob( $dir . '/{,.}*', GLOB_BRACE ) as $f ) {
					if ( is_file( $f ) ) {
						unlink( $f );
					}
				}
				@rmdir( $dir );
			}
			foreach ( array( 'photos', 'sections', 'favs' ) as $t ) {
				$wpdb->delete( self::t( $t ), array( 'event_id' => $event->id ) );
			}
			$wpdb->delete( self::t( 'events' ), array( 'id' => $event->id ) );
		}

		static function delete_photo( $event, $photo ) {
			global $wpdb;
			list( $pub ) = self::pub( $event );
			@unlink( self::orig_path( $event, $photo ) );
			@unlink( $pub . '/' . $photo->pkey . '-p.jpg' );
			@unlink( $pub . '/' . $photo->pkey . '-t.jpg' );
			$wpdb->delete( self::t( 'photos' ), array( 'id' => $photo->id ) );
		}

		/* ---------------- client-facing photo data ---------------- */
		static function photo_json( $event, $p ) {
			list( , $url ) = self::pub( $event );
			return array( 'id' => (int) $p->id, 'n' => null === $p->photo_number ? null : (int) $p->photo_number, 'f' => $p->filename,
				't' => $url . '/' . $p->pkey . '-t.jpg?v=' . substr( $p->sha, 0, 8 ), 'p' => $url . '/' . $p->pkey . '-p.jpg?v=' . substr( $p->sha, 0, 8 ),
				'w' => (int) $p->width, 'h' => (int) $p->height, 's' => (int) $p->section_id );
		}

		/** Splits photos into ZIP parts of at most ZIP_LIMIT bytes. */
		static function parts( $photos ) {
			$parts = array();
			$cur   = array();
			$size  = 0;
			foreach ( $photos as $p ) {
				if ( $cur && $size + $p->bytes > self::ZIP_LIMIT ) {
					$parts[] = $cur;
					$cur     = array();
					$size    = 0;
				}
				$cur[] = $p;
				$size += $p->bytes;
			}
			if ( $cur ) {
				$parts[] = $cur;
			}
			return $parts;
		}

		/* ---------------- delivery ---------------- */
		static function send_file( $event, $photo ) {
			$path = self::orig_path( $event, $photo );
			if ( ! is_file( $path ) ) {
				status_header( 404 );
				exit;
			}
			self::no_buffers();
			header( 'Content-Type: image/jpeg' );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'Content-Disposition: attachment; filename="' . rawurlencode( $photo->filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $photo->filename ) );
			header( 'Cache-Control: private, no-store' );
			readfile( $path );
			exit;
		}

		static function no_buffers() {
			@set_time_limit( 0 );
			@ini_set( 'zlib.output_compression', 'Off' );
			while ( ob_get_level() ) {
				ob_end_clean();
			}
		}

		/** Streams a ZIP (store, no compression) without temp files. CRC is computed on the fly (data descriptors). */
		static function send_zip( $items, $zipname ) {
			self::no_buffers();
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . rawurlencode( $zipname ) . '"' );
			header( 'Cache-Control: private, no-store' );
			$offset = 0;
			$central = '';
			$count = 0;
			$used  = array();
			$dt    = getdate();
			$dtime = ( $dt['hours'] << 11 ) | ( $dt['minutes'] << 5 ) | intdiv( $dt['seconds'], 2 );
			$ddate = ( ( $dt['year'] - 1980 ) << 9 ) | ( $dt['mon'] << 5 ) | $dt['mday'];
			foreach ( $items as $it ) {
				list( $path, $name ) = $it;
				if ( ! is_file( $path ) ) {
					continue;
				}
				$base = $name;
				for ( $i = 2; isset( $used[ $name ] ); $i++ ) {
					$name = preg_replace( '/(\.[^.]+)?$/', "-$i$1", $base, 1 );
				}
				$used[ $name ] = 1;
				$local = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0808, 0, $dtime, $ddate, 0, 0, 0, strlen( $name ), 0 ) . $name;
				echo $local;
				$ctx  = hash_init( 'crc32b' );
				$size = 0;
				$fh   = fopen( $path, 'rb' );
				while ( ! feof( $fh ) ) {
					$buf = fread( $fh, 1048576 );
					hash_update( $ctx, $buf );
					$size += strlen( $buf );
					echo $buf;
					flush();
				}
				fclose( $fh );
				$crc = unpack( 'N', hash_final( $ctx, true ) )[1];
				echo pack( 'VVVV', 0x08074b50, $crc, $size, $size );
				$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0808, 0, $dtime, $ddate, $crc, $size, $size, strlen( $name ), 0, 0, 0, 0, 0, $offset ) . $name;
				$offset  += strlen( $local ) + $size + 16;
				$count++;
			}
			echo $central;
			echo pack( 'VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen( $central ), $offset, 0 );
			exit;
		}
	}
}

add_action( 'init', function () {
	if ( class_exists( 'RD_Gal' ) ) {
		RD_Gal::install();
	}
} );

// Let the PhotoSorter uploader through the site-wide password while the site is private (only with a valid API key).
add_filter( 'password_protected_is_active', function ( $active ) {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( $active && class_exists( 'RD_Gal' ) && ( false !== strpos( $uri, '/rd/v1/admin/' ) || false !== strpos( $uri, 'rd%2Fv1%2Fadmin' ) ) ) {
		$key = isset( $_SERVER['HTTP_X_RD_KEY'] ) ? (string) $_SERVER['HTTP_X_RD_KEY'] : '';
		if ( RD_Gal::key_ok( $key ) ) {
			return false;
		}
	}
	return $active;
} );
