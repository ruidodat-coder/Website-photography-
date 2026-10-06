/**
 * Wedding availability + price check: shortcode [rd_wedding_check] and REST route rd/v1/availability.
 * The REST route reads the public "busy only" Google calendar feed (RD_ICS_URL, filled in by build.py)
 * on the server and returns only "frei" / "teilweise" / "belegt" for one date - never event details.
 * Written without named functions/constants so re-saving the snippet never redeclares anything.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'rd/v1', '/availability', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'date' => array( 'required' => true ) ),
		'callback'            => function ( WP_REST_Request $req ) {
			$date = (string) $req->get_param( 'date' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return new WP_Error( 'rd_bad_date', 'Ungültiges Datum', array( 'status' => 400 ) );
			}
			$tz        = new DateTimeZone( 'Europe/Berlin' );
			$day_start = new DateTime( $date . ' 00:00:00', $tz );
			$day_end   = ( clone $day_start )->modify( '+1 day' );

			$ics = get_transient( 'rd_busy_ics' );
			if ( false === $ics ) {
				$res = wp_remote_get( 'RD_ICS_URL', array( 'timeout' => 10 ) );
				if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
					return array( 'date' => $date, 'status' => 'unbekannt' );
				}
				$ics = wp_remote_retrieve_body( $res );
				set_transient( 'rd_busy_ics', $ics, 15 * MINUTE_IN_SECONDS );
			}
			$ics    = preg_replace( "/\r?\n[ \t]/", '', $ics ); // unfold lines
			$status = 'frei';
			$parse  = function ( $line, $tz ) {
				// DTSTART;VALUE=DATE:20261023 | DTSTART:20261014T130000Z | DTSTART;TZID=Europe/Berlin:20261014T150000
				if ( ! preg_match( '/^DT(?:START|END)([^:]*):(\d{8})(T(\d{6})(Z?))?/', $line, $m ) ) {
					return array( null, false );
				}
				if ( empty( $m[3] ) ) {
					return array( new DateTime( $m[2] . ' 00:00:00', $tz ), true );
				}
				$src_tz = ! empty( $m[5] ) ? new DateTimeZone( 'UTC' ) : $tz;
				$dt     = DateTime::createFromFormat( 'Ymd His', $m[2] . ' ' . $m[4], $src_tz );
				$dt->setTimezone( $tz );
				return array( $dt, false );
			};
			if ( preg_match_all( '/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $events ) ) {
				foreach ( $events[1] as $ev ) {
					if ( ! preg_match( '/^DTSTART[^\n]*$/m', $ev, $s ) ) {
						continue;
					}
					list( $start, $all_day ) = $parse( trim( $s[0] ), $tz );
					$end = null;
					if ( preg_match( '/^DTEND[^\n]*$/m', $ev, $e ) ) {
						list( $end ) = $parse( trim( $e[0] ), $tz );
					}
					if ( ! $start ) {
						continue;
					}
					if ( ! $end ) {
						$end = ( clone $start )->modify( $all_day ? '+1 day' : '+1 hour' );
					}
					if ( $start < $day_end && $end > $day_start ) {
						if ( $all_day ) {
							$status = 'belegt';
							break;
						}
						$status = 'teilweise';
					}
				}
			}
			return array( 'date' => $date, 'status' => $status );
		},
	) );
} );

add_shortcode( 'rd_wedding_check', function () {
	$cfg = array(
		'api'      => esc_url_raw( rest_url( 'rd/v1/availability' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'contact'  => home_url( '/kontakt/' ),
		'calendar' => home_url( '/verfuegbarkeit/' ),
		'packages' => array(
			array( 'id' => 'jawort', 'name' => 'Ja-Wort', 'price' => 890, 'desc' => 'Standesamt, 2,5 Stunden, ca. 150 Bilder', 'incl' => array() ),
			array( 'id' => 'herz', 'name' => 'Herzstück', 'price' => 1890, 'desc' => '7 Stunden, ca. 450 Bilder, Sneak Peek, Gästegalerie', 'incl' => array() ),
			array( 'id' => 'immer', 'name' => 'Für Immer', 'price' => 2690, 'desc' => '10 Stunden, ca. 700 Bilder, Paarshooting inklusive', 'incl' => array( 'paar' ) ),
			array( 'id' => 'grenzenlos', 'name' => 'Grenzenlos', 'price' => 3790, 'desc' => '12 Stunden, 2 Fotografen, Album, 2 Elternalben, Paarshooting', 'incl' => array( 'paar', 'zweit', 'album' ) ),
		),
		'extras'   => array(
			array( 'id' => 'zweit', 'name' => 'Zweitfotograf/in', 'price' => 590 ),
			array( 'id' => 'album', 'name' => 'Hochzeitsalbum 30×30', 'price' => 590 ),
			array( 'id' => 'paar', 'name' => 'Paarshooting', 'price' => 190 ),
			array( 'id' => 'drohne', 'name' => 'Drohnenaufnahmen', 'price' => 249 ),
			array( 'id' => 'stunde', 'name' => '1 zusätzliche Stunde', 'price' => 190 ),
		),
		'zones'    => array(
			array( 'id' => 'z12', 'name' => 'bis 60 km ab Germersheim (z. B. Speyer, Landau, Karlsruhe, Mannheim, Heidelberg)', 'price' => 0 ),
			array( 'id' => 'z3', 'name' => '60–100 km (z. B. Kaiserslautern, Pirmasens, Baden-Baden, Heilbronn)', 'price' => 49 ),
			array( 'id' => 'z4', 'name' => 'über 100 km', 'price' => 49, 'note' => 'zzgl. 0,40 €/km ab km 100 (hin und zurück), ggf. Übernachtung' ),
		),
	);
	ob_start();
	?>
	<div class="rd-wcheck" data-cfg="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
		<div class="rd-wcheck-form">
			<label>Euer Hochzeitsdatum<input type="date" name="date" required></label>
			<label>Paket<select name="pkg"></select></label>
			<label>Location<select name="zone"></select></label>
			<fieldset><legend>Extras</legend><div class="rd-wcheck-extras"></div></fieldset>
		</div>
		<div class="rd-wcheck-result" aria-live="polite">
			<p class="rd-wcheck-status rd-wcheck-idle">Wählt euer Datum, dann seht ihr sofort, ob ich frei bin.</p>
			<div class="rd-wcheck-offer"></div>
		</div>
	</div>
	<style>
	.rd-wcheck{display:grid;grid-template-columns:1fr 1fr;gap:2rem;background:#fff;border:1px solid var(--rd-muted,#eee);border-radius:4px;padding:2rem;text-align:left;max-width:1100px;margin:0 auto}
	@media (max-width:781px){.rd-wcheck{grid-template-columns:1fr;padding:1.2rem;gap:1.2rem}}
	.rd-wcheck label{display:block;font-weight:600;font-size:.85rem;letter-spacing:.04em;margin-bottom:1rem}
	.rd-wcheck input,.rd-wcheck select{display:block;width:100%;box-sizing:border-box;min-width:0;max-width:100%;margin-top:.35rem;padding:.75rem .8rem;font:inherit;font-weight:400;border:1px solid #d8cfc8;border-radius:2px;background:#fffdfb}
	.rd-wcheck fieldset{border:0;padding:0;margin:0}.rd-wcheck legend{font-weight:600;font-size:.85rem;letter-spacing:.04em;margin-bottom:.4rem}
	.rd-wcheck-extras label{display:flex;gap:.5rem;align-items:center;font-weight:400;margin:.3rem 0;letter-spacing:0}
	.rd-wcheck-extras input{width:auto;margin:0}.rd-wcheck-extras .rd-incl{opacity:.55}
	.rd-wcheck-status{padding:.9rem 1rem;border-radius:2px;margin:0 0 1rem;font-weight:600}
	.rd-wcheck-idle{background:#f6f1ee}.rd-wcheck-frei{background:#e7f1e6;color:#2d5a2b}.rd-wcheck-teilweise{background:#fbf1de;color:#7a5410}.rd-wcheck-belegt{background:#f7e3e0;color:#8a2f25}.rd-wcheck-wait{background:#f6f1ee;color:#777}
	.rd-wcheck-offer table{width:100%;border-collapse:collapse;font-size:.95rem}.rd-wcheck-offer td{padding:.45rem 0;border-bottom:1px solid #f0e8e3}.rd-wcheck-offer td:last-child{text-align:right;white-space:nowrap}
	.rd-wcheck-offer .rd-total td{font-weight:700;font-size:1.15rem;border-bottom:2px solid var(--rd-accent,#b4877a)}
	.rd-wcheck-offer .rd-small{font-size:.8rem;opacity:.75;margin:.6rem 0}
	.rd-wcheck-actions{display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1rem}
	.rd-wcheck-actions a{display:inline-block;padding:.8em 1.4em;font-size:.8rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;border-radius:2px;background:var(--rd-accent,#b4877a);color:#fff}
	.rd-wcheck-actions a.rd-alt{background:transparent;color:inherit;border:1.5px solid currentColor}
	</style>
	<script>
	(function(){
	  document.querySelectorAll('.rd-wcheck').forEach(function(root){
	    var cfg=JSON.parse(root.dataset.cfg), f=function(n){return root.querySelector('[name="'+n+'"]');};
	    var eur=function(v){return v.toLocaleString('de-DE',{style:'currency',currency:'EUR',maximumFractionDigits:0});};
	    var date=f('date'), pkg=f('pkg'), zone=f('zone'), ex=root.querySelector('.rd-wcheck-extras');
	    var st=root.querySelector('.rd-wcheck-status'), offer=root.querySelector('.rd-wcheck-offer');
	    var today=new Date(); today.setDate(today.getDate()+1);
	    var max=new Date(); max.setMonth(max.getMonth()+24);
	    var iso=function(d){return d.toISOString().slice(0,10);}; date.min=iso(today); date.max=iso(max);
	    cfg.packages.forEach(function(p,i){pkg.add(new Option(p.name+' – '+eur(p.price),p.id,false,i===2));});
	    cfg.zones.forEach(function(z){zone.add(new Option(z.name+(z.price?' · '+eur(z.price):' · inklusive'),z.id));});
	    cfg.extras.forEach(function(x){var l=document.createElement('label');l.innerHTML='<input type="checkbox" value="'+x.id+'"> <span>'+x.name+' (+'+eur(x.price)+')</span>';ex.appendChild(l);});
	    var avail=null, seq=0;
	    function render(){
	      var p=cfg.packages.find(function(x){return x.id===pkg.value;}), z=cfg.zones.find(function(x){return x.id===zone.value;});
	      ex.querySelectorAll('input').forEach(function(i){var inc=p.incl.indexOf(i.value)>-1;i.disabled=inc;if(inc)i.checked=false;i.parentNode.classList.toggle('rd-incl',inc);i.nextElementSibling.textContent=cfg.extras.find(function(x){return x.id===i.value;}).name+(inc?' (im Paket enthalten)':' (+'+eur(cfg.extras.find(function(x){return x.id===i.value;}).price)+')');});
	      if(!date.value){offer.innerHTML='';return;}
	      var d=new Date(date.value+'T12:00:00'), m=d.getMonth()+1, wd=d.getDay();
	      var off=(m>=11||m<=3), week=(wd>=1&&wd<=4), disc=(off||week)?Math.round(p.price*0.10):0;
	      var rows=[['Paket „'+p.name+'“<br><small>'+p.desc+'</small>',p.price]];
	      if(disc) rows.push(['Rabatt 10 % ('+(off?'Nebensaison':'Montag–Donnerstag')+')',-disc]);
	      var extrasSel=[];
	      ex.querySelectorAll('input:checked').forEach(function(i){var x=cfg.extras.find(function(e){return e.id===i.value;});rows.push([x.name,x.price]);extrasSel.push(x.name);});
	      rows.push(['Anfahrt'+(z.note?'<br><small>'+z.note+'</small>':''),z.price]);
	      var total=rows.reduce(function(s,r){return s+r[1];},0);
	      var html='<table>'+rows.map(function(r){return '<tr><td>'+r[0]+'</td><td>'+(r[1]?eur(r[1]):'inklusive')+'</td></tr>';}).join('')+
	        '<tr class="rd-total"><td>Gesamt'+(z.note?' (ab)':'')+'</td><td>'+eur(total)+'</td></tr></table>'+
	        '<p class="rd-small">Unverbindliches Angebot. Anzahlung 30 % ('+eur(Math.round(total*0.3))+') nach Vertragsschluss, Rest 4 Wochen vor der Hochzeit. Gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Gebucht wird nach einem persönlichen Kennenlernen.</p>';
	      var dtxt=d.toLocaleDateString('de-DE',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
	      var msg='Hallo Rui, wir heiraten am '+dtxt+' und interessieren uns für das Paket „'+p.name+'“'+(extrasSel.length?' mit '+extrasSel.join(', '):'')+' (Angebot von der Website: '+eur(total)+'). Wann können wir uns kennenlernen?';
	      if(avail!=='belegt') html+='<div class="rd-wcheck-actions"><a href="'+cfg.contact+'">Kennenlernen anfragen</a><a class="rd-alt" href="https://wa.me/491738505311?text='+encodeURIComponent(msg)+'">Per WhatsApp anfragen</a></div>';
	      offer.innerHTML=html;
	    }
	    function check(){
	      if(!date.value){return;}
	      var my=++seq, dtxt=new Date(date.value+'T12:00:00').toLocaleDateString('de-DE',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
	      st.className='rd-wcheck-status rd-wcheck-wait'; st.textContent='Ich schaue in meinen Kalender …'; avail=null; render();
	      fetch(cfg.api+(cfg.api.indexOf('?')>-1?'&':'?')+'date='+date.value,{headers:{'X-WP-Nonce':cfg.nonce},credentials:'same-origin'})
	        .then(function(r){return r.json();}).then(function(j){
	          if(my!==seq)return; avail=j.status;
	          var t={frei:'Ich bin am '+dtxt+' noch frei!',teilweise:'Am '+dtxt+' habe ich bereits einen kürzeren Termin. Schreibt mir, oft passt es trotzdem.',belegt:'Am '+dtxt+' bin ich leider schon gebucht. Schaut gern in den Kalender nach einem anderen Tag.'}[j.status]||'Das konnte ich gerade nicht prüfen. Schreibt mir einfach.';
	          st.className='rd-wcheck-status rd-wcheck-'+({frei:1,teilweise:1,belegt:1}[j.status]?j.status:'wait'); st.innerHTML=t+(j.status==='belegt'?' <a href="'+cfg.calendar+'">Zum Kalender</a>':''); render();
	        }).catch(function(){ if(my!==seq)return; st.className='rd-wcheck-status rd-wcheck-wait'; st.textContent='Das konnte ich gerade nicht prüfen. Schreibt mir einfach.'; render(); });
	    }
	    date.addEventListener('change',check); [pkg,zone].forEach(function(e){e.addEventListener('change',render);}); ex.addEventListener('change',render); render();
	  });
	})();
	</script>
	<?php
	return ob_get_clean();
} );
