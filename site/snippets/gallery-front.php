/**
 * Client galleries, part 3: what clients see.
 *   [rd_gallery]  on /pferde/deine-bilder/ and /hochzeit/eure-bilder/
 *     ?e=<slug>   gallery (after the access code was entered)
 *     ?t=<token>  purchased downloads (link from the order email)
 *   Code forms (.rd-code[data-rd-open]) on "Dein Event" pages post to /rd/v1/gallery/open.
 * Public REST: open, photos, download (free galleries), favourites. Sales live in gallery-shop.
 */
add_action( 'rest_api_init', function () {
	if ( ! class_exists( 'RD_Gal' ) ) {
		return;
	}
	$pub = '__return_true';
	$ev  = function ( $r ) {
		$e = RD_Gal::event_by_slug( (string) $r['slug'] );
		if ( ! $e || ! RD_Gal::has_access( $e ) ) {
			return new WP_Error( 'rd_locked', 'Bitte zuerst den Zugangscode eingeben.', array( 'status' => 403 ) );
		}
		return $e;
	};

	register_rest_route( 'rd/v1', '/gallery/open', array( 'methods' => 'POST', 'permission_callback' => $pub, 'callback' => function ( $r ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$rl  = 'rd_gal_rl_' . md5( $ip );
		$cnt = (int) get_transient( $rl );
		if ( $cnt >= 12 ) {
			return new WP_Error( 'rd_slow', 'Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.', array( 'status' => 429 ) );
		}
		$code = (string) $r->get_param( 'code' );
		$e    = strlen( RD_Gal::norm_code( $code ) ) >= 4 ? RD_Gal::event_by_code( $code ) : null;
		if ( ! $e || ! RD_Gal::is_open( $e ) ) {
			set_transient( $rl, $cnt + 1, 15 * MINUTE_IN_SECONDS );
			$msg = $e && 'published' === $e->status ? 'Diese Galerie ist abgelaufen. Schreib mir, ich schalte sie gern wieder frei.'
				: 'Diesen Code kenne ich nicht. Bitte prüfe die Schreibweise.';
			return new WP_Error( 'rd_code', $msg, array( 'status' => 404 ) );
		}
		RD_Gal::grant( $e );
		return array( 'url' => RD_Gal::gallery_url( $e ) );
	} ) );

	register_rest_route( 'rd/v1', '/gallery/(?P<slug>[a-z0-9-]+)/photos', array( 'methods' => 'GET', 'permission_callback' => $pub, 'callback' => function ( $r ) use ( $ev ) {
		$e = $ev( $r );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$sec = $r->get_param( 'section' );
		$out = array();
		foreach ( RD_Gal::photos( $e->id, null === $sec ? null : (int) $sec ) as $p ) {
			$out[] = RD_Gal::photo_json( $e, $p );
		}
		return $out;
	} ) );

	// free galleries: originals for anyone with the code. ?photo=ID | ?part=N[&section=ID]
	register_rest_route( 'rd/v1', '/gallery/(?P<slug>[a-z0-9-]+)/download', array( 'methods' => 'GET', 'permission_callback' => $pub, 'callback' => function ( $r ) use ( $ev ) {
		$e = $ev( $r );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		if ( 'free' !== $e->mode && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rd_paid', 'Diese Bilder können gekauft werden.', array( 'status' => 403 ) );
		}
		if ( $r->get_param( 'photo' ) ) {
			$p = RD_Gal::photo( (int) $r->get_param( 'photo' ) );
			if ( ! $p || $p->event_id !== $e->id ) {
				return new WP_Error( 'rd_404', 'Bild nicht gefunden', array( 'status' => 404 ) );
			}
			RD_Gal::send_file( $e, $p );
		}
		$sec   = $r->get_param( 'section' );
		$parts = RD_Gal::parts( RD_Gal::photos( $e->id, null === $sec ? null : (int) $sec ) );
		$n     = max( 1, (int) $r->get_param( 'part' ) );
		if ( empty( $parts[ $n - 1 ] ) ) {
			return new WP_Error( 'rd_404', 'Teil nicht gefunden', array( 'status' => 404 ) );
		}
		$items = array();
		foreach ( $parts[ $n - 1 ] as $p ) {
			$items[] = array( RD_Gal::orig_path( $e, $p ), $p->filename );
		}
		RD_Gal::send_zip( $items, $e->slug . ( count( $parts ) > 1 ? "-teil-$n" : '' ) . '.zip' );
	} ) );

	register_rest_route( 'rd/v1', '/gallery/(?P<slug>[a-z0-9-]+)/favs', array( 'methods' => 'POST', 'permission_callback' => $pub, 'callback' => function ( $r ) use ( $ev ) {
		global $wpdb;
		$e = $ev( $r );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$email = sanitize_email( (string) $r->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'rd_mail', 'Bitte eine gültige E-Mail-Adresse eingeben.', array( 'status' => 400 ) );
		}
		$ids = array_slice( array_values( array_unique( array_map( 'intval', (array) $r->get_param( 'ids' ) ) ) ), 0, 5000 );
		$wpdb->replace( RD_Gal::t( 'favs' ), array( 'event_id' => $e->id, 'email' => strtolower( $email ), 'ids' => wp_json_encode( $ids ), 'updated_at' => current_time( 'mysql', true ) ) );
		return array( 'saved' => count( $ids ) );
	} ) );
} );

/* ---------------- page output ---------------- */
add_shortcode( 'rd_gallery', function () {
	if ( ! class_exists( 'RD_Gal' ) ) {
		return '';
	}
	nocache_headers();
	$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
	if ( $token ) {
		return apply_filters( 'rd_gal_downloads_html', '<p class="rd-note">Dieser Download-Link ist ungültig.</p>', $token );
	}
	$slug = isset( $_GET['e'] ) ? sanitize_title( wp_unslash( $_GET['e'] ) ) : '';
	$e    = $slug ? RD_Gal::event_by_slug( $slug ) : null;
	if ( ! $e || ! RD_Gal::has_access( $e ) ) {
		return '<div class="rd-gal-gate">' . rd_gal_code_form( $e ? 'Bitte gib den Zugangscode für diese Galerie ein.' : '' ) . '</div>';
	}
	global $wpdb;
	$counts = $wpdb->get_results( $wpdb->prepare( 'SELECT section_id, COUNT(*) n, SUM(bytes) b FROM ' . RD_Gal::t( 'photos' ) . ' WHERE event_id=%s GROUP BY section_id', $e->id ), OBJECT_K );
	$secs   = array();
	foreach ( RD_Gal::sections( $e->id ) as $s ) {
		if ( isset( $counts[ $s->id ] ) ) {
			$secs[] = array( 'id' => (int) $s->id, 'name' => $s->name, 'n' => (int) $counts[ $s->id ]->n, 'parts' => count( RD_Gal::parts( RD_Gal::photos( $e->id, $s->id ) ) ) );
		}
	}
	if ( isset( $counts[0] ) ) {
		$secs[] = array( 'id' => 0, 'name' => 'Weitere Bilder', 'n' => (int) $counts[0]->n, 'parts' => count( RD_Gal::parts( RD_Gal::photos( $e->id, 0 ) ) ) );
	}
	$total = array_sum( wp_list_pluck( $secs, 'n' ) );
	$cfg   = array(
		'api'      => esc_url_raw( rest_url( 'rd/v1/gallery/' . $e->slug ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'slug'     => $e->slug,
		'mode'     => $e->mode,
		'sections' => $secs,
		'parts'    => count( RD_Gal::parts( RD_Gal::photos( $e->id ) ) ),
		'prices'   => RD_Gal::prices(),
		'shop'     => 'sale' === $e->mode && function_exists( 'WC' ),
		'checkout' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
		'cartUrl'  => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
	);
	$date = $e->ev_date ? date_i18n( 'j. F Y', strtotime( $e->ev_date ) ) : '';
	ob_start();
	?>
	<div class="rd-gal" data-cfg="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
		<header class="rd-gal-head">
			<p class="rd-eyebrow"><?php echo esc_html( trim( $date . ( 'sale' === $e->mode ? ' · Bilder zum Kaufen' : ' · Eure Galerie' ), ' ·' ) ); ?></p>
			<h1><?php echo esc_html( $e->name ); ?></h1>
			<p class="rd-gal-meta"><?php echo esc_html( number_format_i18n( $total ) . ' Bilder' . ( count( $secs ) > 1 ? ' · ' . count( $secs ) . ' Kapitel' : '' ) ); ?></p>
			<?php if ( 'sale' === $e->mode ) : ?>
				<p class="rd-gal-hint">Die Vorschau ist mit Wasserzeichen. Nach dem Kauf bekommst du deine Bilder in voller Auflösung ohne Wasserzeichen per Download-Link.</p>
			<?php else : ?>
				<div class="rd-gal-actions"><button type="button" class="rd-gal-btn" data-act="all">Alle Bilder herunterladen</button></div>
			<?php endif; ?>
		</header>
		<div class="rd-gal-bar">
			<input type="search" class="rd-gal-search" placeholder="<?php echo esc_attr( 'sale' === $e->mode ? 'Name, Pferd, Prüfung oder #Bildnummer' : 'Kapitel oder #Bildnummer suchen' ); ?>" aria-label="Suchen">
			<button type="button" class="rd-gal-favtoggle" aria-pressed="false">♥ <span>0</span></button>
		</div>
		<nav class="rd-gal-chips" aria-label="Kapitel"></nav>
		<div class="rd-gal-sections"></div>
		<div class="rd-gal-cart" hidden></div>
	</div>
	<?php
	return ob_get_clean();
} );

if ( ! function_exists( 'rd_gal_code_form' ) ) {
	function rd_gal_code_form( $hint = '' ) {
		$api = esc_url( rest_url( 'rd/v1/gallery/open' ) );
		return '<form class="rd-code" data-rd-open="' . $api . '" method="post" action="#"><label for="rd-code-in">Dein Code</label>'
			. '<div class="rd-code-row"><input id="rd-code-in" name="qr" type="text" required minlength="4" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="z. B. TOBI-7K3PX">'
			. '<button type="submit">Bilder öffnen</button></div><p class="rd-code-note" aria-live="polite">' . esc_html( $hint ) . '</p></form>';
	}
}

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	(function(){
	  document.querySelectorAll('.rd-code[data-rd-open]').forEach(function(f){
	    var note=f.querySelector('.rd-code-note'), btn=f.querySelector('button');
	    f.addEventListener('submit',function(ev){ ev.preventDefault(); btn.disabled=true; note.textContent='Einen Moment …';
	      fetch(f.dataset.rdOpen,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({code:f.qr.value})})
	        .then(function(r){return r.json().then(function(j){return [r.ok,j];});})
	        .then(function(a){ if(a[0]&&a[1].url){ note.textContent='Galerie wird geöffnet …'; location.href=a[1].url; } else { note.textContent=(a[1]&&a[1].message)||'Das hat nicht geklappt.'; btn.disabled=false; } })
	        .catch(function(){ note.textContent='Keine Verbindung. Bitte nochmal versuchen.'; btn.disabled=false; });
	    });
	  });
	})();
	</script>
	<?php
	if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'rd_gallery' ) ) {
		return;
	}
	?>
	<div class="rd-lb" hidden role="dialog" aria-modal="true" aria-label="Bild">
		<button class="rd-lb-x" type="button" aria-label="Schließen">×</button>
		<button class="rd-lb-prev" type="button" aria-label="Vorheriges Bild">‹</button>
		<figure><img alt=""><figcaption></figcaption></figure>
		<button class="rd-lb-next" type="button" aria-label="Nächstes Bild">›</button>
		<div class="rd-lb-actions"></div>
	</div>
	<script>
	(function(){
	  var root=document.querySelector('.rd-gal'); if(!root) return;
	  var cfg=JSON.parse(root.dataset.cfg), eur=function(v){return (+v).toLocaleString('de-DE',{style:'currency',currency:'EUR'});};
	  var secWrap=root.querySelector('.rd-gal-sections'), chips=root.querySelector('.rd-gal-chips'), search=root.querySelector('.rd-gal-search');
	  var favBtn=root.querySelector('.rd-gal-favtoggle'), cartBar=root.querySelector('.rd-gal-cart');
	  var favKey='rd_fav_'+cfg.slug, favs={}; try{ (JSON.parse(localStorage.getItem(favKey))||[]).forEach(function(i){favs[i]=1;}); }catch(e){}
	  var photos={}, order=[], loaded={}, onlyFav=false, cart={};
	  var api=function(path,opt){ opt=opt||{}; opt.credentials='same-origin'; opt.headers=Object.assign({'X-WP-Nonce':cfg.nonce},opt.headers||{});
	    return fetch(cfg.api+path,opt).then(function(r){return r.json().then(function(j){ if(!r.ok) throw j; return j;});}); };
	  var saveFavs=function(){ try{ localStorage.setItem(favKey, JSON.stringify(Object.keys(favs).map(Number))); }catch(e){} favBtn.querySelector('span').textContent=Object.keys(favs).length; };
	  saveFavs();

	  // sections + chips
	  cfg.sections.forEach(function(s){
	    var sec=document.createElement('section'); sec.className='rd-gal-sec'; sec.dataset.id=s.id; sec.dataset.name=s.name.toLowerCase(); sec.id='kapitel-'+s.id;
	    var head='<div class="rd-gal-sechead"><h2>'+esc(s.name)+'</h2><span class="rd-gal-count">'+s.n+' Bilder</span>';
	    if(cfg.mode==='sale' && cfg.shop) head+='<button type="button" class="rd-gal-btn rd-gal-buysec" data-sec="'+s.id+'">Alle '+s.n+' Bilder · '+eur(cfg.prices.section)+'</button>';
	    else if(cfg.mode==='free') head+='<button type="button" class="rd-gal-btn rd-gal-ghost" data-act="sec" data-sec="'+s.id+'">Kapitel herunterladen</button>';
	    sec.innerHTML=head+'</div><div class="rd-gal-grid" aria-busy="true"></div>';
	    secWrap.appendChild(sec);
	    var c=document.createElement('a'); c.href='#kapitel-'+s.id; c.textContent=s.name; c.dataset.id=s.id; chips.appendChild(c);
	  });
	  if(cfg.sections.length<2) chips.hidden=true;
	  function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

	  var io=new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ load(e.target.dataset.id); io.unobserve(e.target);} }); },{rootMargin:'600px 0px'});
	  secWrap.querySelectorAll('.rd-gal-sec').forEach(function(s){ io.observe(s); });

	  function load(id){
	    if(loaded[id]) return loaded[id];
	    var sec=secWrap.querySelector('.rd-gal-sec[data-id="'+id+'"]'), grid=sec.querySelector('.rd-gal-grid');
	    return loaded[id]=api('/photos?section='+id).then(function(list){
	      grid.innerHTML=''; grid.removeAttribute('aria-busy');
	      list.forEach(function(p){ photos[p.id]=p; grid.appendChild(tile(p)); });
	      rebuildOrder(); applyFilter();
	    }).catch(function(){ grid.innerHTML='<p class="rd-note">Bilder konnten nicht geladen werden. Bitte Seite neu laden.</p>'; });
	  }
	  function rebuildOrder(){ order=[].map.call(secWrap.querySelectorAll('.rd-gal-tile'),function(t){return +t.dataset.id;}); }
	  function tile(p){
	    var r=p.w&&p.h?p.w/p.h:1.5, t=document.createElement('div'); t.className='rd-gal-tile'; t.dataset.id=p.id;
	    t.style.flex=r+' 1 '+(r*190)+'px';
	    t.innerHTML='<button type="button" class="rd-gal-open" aria-label="Bild '+(p.n||'')+' öffnen"><img loading="lazy" decoding="async" src="'+p.t+'" alt="" style="aspect-ratio:'+r+'"></button>'+
	      (p.n!=null?'<span class="rd-gal-no">#'+p.n+'</span>':'')+
	      '<button type="button" class="rd-gal-fav'+(favs[p.id]?' on':'')+'" aria-label="Favorit" aria-pressed="'+!!favs[p.id]+'">♥</button>'+
	      (cfg.mode==='sale'&&cfg.shop?'<button type="button" class="rd-gal-add'+(inCart('photo',p.id)?' on':'')+'" aria-label="In den Warenkorb">'+(inCart('photo',p.id)?'✓':'+')+'</button>':'');
	    return t;
	  }

	  // interactions
	  secWrap.addEventListener('click',function(ev){
	    var t=ev.target.closest('.rd-gal-tile'), b=ev.target.closest('button'); if(!b) return;
	    if(b.classList.contains('rd-gal-open')) return openLb(+t.dataset.id);
	    if(b.classList.contains('rd-gal-fav')) return toggleFav(+t.dataset.id);
	    if(b.classList.contains('rd-gal-add')) return toggleCart('photo',+t.dataset.id);
	    if(b.classList.contains('rd-gal-buysec')) return toggleCart('section',+b.dataset.sec);
	    if(b.dataset.act==='sec') return downloadParts(+b.dataset.sec);
	  });
	  root.querySelector('[data-act="all"]') && root.querySelector('[data-act="all"]').addEventListener('click',function(){ downloadParts(null); });
	  function downloadParts(sec){
	    var n=sec===null?cfg.parts:(cfg.sections.find(function(s){return s.id===sec;})||{}).parts||1;
	    var base=cfg.api+'/download?'+(sec!==null?'section='+sec+'&':'')+'part=';
	    if(n<=1){ location.href=base+'1'; return; }
	    var html='<p>Die Bilder sind in '+n+' ZIP-Dateien aufgeteilt (je max. ca. 1,8 GB):</p><div class="rd-gal-parts">';
	    for(var i=1;i<=n;i++) html+='<a class="rd-gal-btn" href="'+base+i+'">Teil '+i+' herunterladen</a>';
	    modal(html+'</div>');
	  }
	  function toggleFav(id){ if(favs[id]) delete favs[id]; else favs[id]=1; saveFavs();
	    secWrap.querySelectorAll('.rd-gal-tile[data-id="'+id+'"] .rd-gal-fav').forEach(function(b){ b.classList.toggle('on',!!favs[id]); b.setAttribute('aria-pressed',!!favs[id]); });
	    if(lbId===id) lbRender(); if(onlyFav) applyFilter(); }
	  favBtn.addEventListener('click',function(){ onlyFav=!onlyFav; favBtn.setAttribute('aria-pressed',onlyFav);
	    if(onlyFav) cfg.sections.forEach(function(s){ load(s.id); });
	    applyFilter();
	    if(onlyFav && Object.keys(favs).length) showFavSave(); });
	  function showFavSave(){
	    if(document.querySelector('.rd-gal-favsave')) return;
	    var d=document.createElement('form'); d.className='rd-gal-favsave';
	    d.innerHTML='<span>Favoriten an mich schicken (z. B. für Album oder Abzüge):</span><input type="email" required placeholder="Deine E-Mail"><button class="rd-gal-btn">Senden</button>';
	    d.addEventListener('submit',function(ev){ ev.preventDefault(); var em=d.querySelector('input').value;
	      api('/favs',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:em,ids:Object.keys(favs).map(Number)})})
	        .then(function(j){ d.innerHTML='<span>✓ '+j.saved+' Favoriten gespeichert. Danke!</span>'; }).catch(function(e){ alert(e.message||'Fehler'); }); });
	    secWrap.parentNode.insertBefore(d,secWrap);
	  }
	  search.addEventListener('input',applyFilter);
	  function applyFilter(){
	    var q=search.value.trim().toLowerCase(), num=/^#?\d+$/.test(q)?parseInt(q.replace('#',''),10):null;
	    secWrap.querySelectorAll('.rd-gal-sec').forEach(function(sec){
	      var tiles=sec.querySelectorAll('.rd-gal-tile'), any=false, nameHit=!q||num!==null||sec.dataset.name.indexOf(q)>-1;
	      tiles.forEach(function(t){ var p=photos[t.dataset.id]; var ok=(!onlyFav||favs[p.id])&&(num===null||p.n===num); t.hidden=!ok; if(ok) any=true; });
	      sec.hidden=!(nameHit && (any || (!tiles.length && !onlyFav && num===null)));
	    });
	    chips.querySelectorAll('a').forEach(function(c){ var s=secWrap.querySelector('#kapitel-'+c.dataset.id); c.hidden=s.hidden; });
	    if(num!==null) cfg.sections.forEach(function(s){ load(s.id); });
	  }

	  // lightbox
	  var lb=document.querySelector('.rd-lb'), lbImg=lb.querySelector('img'), lbCap=lb.querySelector('figcaption'), lbAct=lb.querySelector('.rd-lb-actions'), lbId=null, lastFocus=null;
	  function visibleOrder(){ return order.filter(function(id){ var t=secWrap.querySelector('.rd-gal-tile[data-id="'+id+'"]'); return t&&!t.hidden&&!t.closest('.rd-gal-sec').hidden; }); }
	  function openLb(id){ lastFocus=document.activeElement; lbId=id; lb.hidden=false; document.documentElement.classList.add('rd-lb-open'); lbRender(); lb.querySelector('.rd-lb-x').focus(); }
	  function closeLb(){ lb.hidden=true; lbId=null; document.documentElement.classList.remove('rd-lb-open'); if(lastFocus) lastFocus.focus(); }
	  function step(d){ var v=visibleOrder(), i=v.indexOf(lbId); if(i<0) return; lbId=v[(i+d+v.length)%v.length]; lbRender(); }
	  function lbRender(){
	    var p=photos[lbId], s=cfg.sections.find(function(x){return x.id===p.s;});
	    lbImg.src=p.p; lbImg.alt='Bild '+(p.n||'');
	    lbCap.textContent=(p.n!=null?'#'+p.n+' · ':'')+(s?s.name:'');
	    var h='<button type="button" class="rd-gal-btn rd-gal-ghost" data-lb="fav">'+(favs[p.id]?'♥ Favorit':'♡ Favorit')+'</button>';
	    if(cfg.mode==='free') h+='<a class="rd-gal-btn" href="'+cfg.api+'/download?photo='+p.id+'">Herunterladen</a>';
	    else if(cfg.shop) h+='<button type="button" class="rd-gal-btn" data-lb="cart">'+(inCart('photo',p.id)?'✓ Im Warenkorb':'In den Warenkorb · '+eur(cfg.prices.photo))+'</button>';
	    lbAct.innerHTML=h;
	    var v=visibleOrder(), i=v.indexOf(lbId); [v[i+1],v[i-1]].forEach(function(n){ if(photos[n]){ var im=new Image(); im.src=photos[n].p; } });
	  }
	  lb.addEventListener('click',function(ev){
	    if(ev.target===lb||ev.target.closest('.rd-lb-x')) return closeLb();
	    if(ev.target.closest('.rd-lb-prev')) return step(-1);
	    if(ev.target.closest('.rd-lb-next')) return step(1);
	    var b=ev.target.closest('[data-lb]'); if(!b) return;
	    if(b.dataset.lb==='fav') toggleFav(lbId); if(b.dataset.lb==='cart') toggleCart('photo',lbId);
	  });
	  document.addEventListener('keydown',function(ev){ if(lb.hidden) return; if(ev.key==='Escape') closeLb(); if(ev.key==='ArrowRight') step(1); if(ev.key==='ArrowLeft') step(-1); });
	  var tx=null; lb.addEventListener('touchstart',function(e){ tx=e.touches[0].clientX; },{passive:true});
	  lb.addEventListener('touchend',function(e){ if(tx===null) return; var dx=e.changedTouches[0].clientX-tx; if(Math.abs(dx)>50) step(dx<0?1:-1); tx=null; });
	  if(cfg.mode==='sale') lbImg.addEventListener('contextmenu',function(e){ e.preventDefault(); });
	  secWrap.addEventListener('contextmenu',function(e){ if(cfg.mode==='sale'&&e.target.tagName==='IMG') e.preventDefault(); });

	  // small modal
	  function modal(html){ var m=document.createElement('div'); m.className='rd-gal-modal'; m.innerHTML='<div class="rd-gal-modal-box"><button type="button" class="rd-lb-x" aria-label="Schließen">×</button>'+html+'</div>';
	    m.addEventListener('click',function(ev){ if(ev.target===m||ev.target.closest('.rd-lb-x')) m.remove(); }); document.body.appendChild(m); }

	  // cart (sale galleries; server side lives in gallery-shop)
	  function key(kind,ref){ return kind+':'+ref; }
	  function inCart(kind,ref){ return !!cart[key(kind,ref)]; }
	  function setCart(j){ cart={}; (j.items||[]).forEach(function(i){ cart[key(i.kind,i.ref)]=1; }); renderCart(j);
	    secWrap.querySelectorAll('.rd-gal-add').forEach(function(b){ var on=inCart('photo',+b.closest('.rd-gal-tile').dataset.id); b.classList.toggle('on',on); b.textContent=on?'✓':'+'; });
	    secWrap.querySelectorAll('.rd-gal-buysec').forEach(function(b){ var s=cfg.sections.find(function(x){return x.id===+b.dataset.sec;}); var on=inCart('section',s.id);
	      b.classList.toggle('on',on); b.textContent=on?'✓ Kapitel im Warenkorb':'Alle '+s.n+' Bilder · '+eur(cfg.prices.section); });
	    if(lbId!==null) lbRender(); }
	  function renderCart(j){ var n=(j.items||[]).length; cartBar.hidden=!n; if(!n) return;
	    cartBar.innerHTML='<span><strong>'+j.label+'</strong> · '+eur(j.total)+'</span><a class="rd-gal-btn rd-gal-light" href="'+cfg.cartUrl+'">Warenkorb</a><a class="rd-gal-btn" href="'+cfg.checkout+'">Zur Kasse →</a>'; }
	  function toggleCart(kind,ref){ var rm=inCart(kind,ref);
	    api('/cart',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({kind:kind,ref:ref,remove:rm})}).then(setCart).catch(function(e){ alert(e.message||'Fehler'); }); }
	  if(cfg.mode==='sale'&&cfg.shop) api('/cart').then(setCart).catch(function(){});
	})();
	</script>
	<?php
}, 60 );
