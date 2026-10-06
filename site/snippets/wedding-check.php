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
		'details'  => home_url( '/hochzeit/pakete-preise/' ),
		'packages' => array(
			array( 'id' => 'jawort', 'name' => 'Ja-Wort', 'price' => 890, 'feat' => array( 'Standesamt, 2,5 Stunden', 'ca. 150 Bilder', 'Online-Galerie' ), 'incl' => array() ),
			array( 'id' => 'herz', 'name' => 'Herzstück', 'price' => 1890, 'feat' => array( '7 Stunden (Trauung bis Eröffnungstanz)', 'ca. 450 Bilder', 'Sneak Peek in 72 h', 'Gästegalerie' ), 'incl' => array() ),
			array( 'id' => 'immer', 'name' => 'Für Immer', 'price' => 2690, 'feat' => array( '10 Stunden', 'ca. 700 Bilder', 'Paarshooting inklusive', 'Sneak Peek &amp; Gästegalerie' ), 'incl' => array( 'paar' ), 'popular' => true ),
			array( 'id' => 'grenzenlos', 'name' => 'Grenzenlos', 'price' => 3790, 'feat' => array( '12 Stunden, 2 Fotografen', 'Paarshooting inklusive', 'Album 30×30, 40 Seiten', '2 Elternalben' ), 'incl' => array( 'paar', 'zweit', 'album' ) ),
		),
		'extras'   => array(
			array( 'id' => 'zweit', 'name' => 'Zweitfotograf/in', 'price' => 590 ),
			array( 'id' => 'album', 'name' => 'Hochzeitsalbum 30×30', 'price' => 590 ),
			array( 'id' => 'paar', 'name' => 'Paarshooting', 'price' => 190 ),
			array( 'id' => 'drohne', 'name' => 'Drohnenaufnahmen', 'price' => 249 ),
			array( 'id' => 'stunde', 'name' => '1 zusätzliche Stunde', 'price' => 190 ),
		),
	);
	ob_start();
	?>
	<div class="rd-wcheck" data-cfg="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
		<form class="rd-wcheck-date" onsubmit="return false">
			<label for="rd-wdate">Euer Hochzeitsdatum</label>
			<div class="rd-wcheck-row"><input id="rd-wdate" type="date" name="date" required><button type="submit">Verfügbarkeit prüfen</button></div>
		</form>
		<p class="rd-wcheck-status" aria-live="polite" hidden></p>
		<div class="rd-wcheck-offer" hidden>
			<p class="rd-wcheck-h">1. Paket wählen</p>
			<div class="rd-wcheck-pkgs" role="radiogroup"></div>
			<p class="rd-wcheck-h">2. Extras dazu?</p>
			<div class="rd-wcheck-extras"></div>
			<div class="rd-wcheck-sum"></div>
		</div>
	</div>
	<style>
	.rd-wcheck{max-width:1100px;margin:0 auto;text-align:left}
	.rd-wcheck-date{max-width:560px;margin:0 auto;background:#fff;padding:1.4rem;border-radius:4px;border:1px solid #eadfd8}
	.rd-wcheck-date label{display:block;font-weight:600;font-size:.85rem;letter-spacing:.04em;margin-bottom:.4rem}
	.rd-wcheck-row{display:flex;gap:.6rem;flex-wrap:wrap}
	.rd-wcheck-row input{flex:1 1 200px;min-width:0;box-sizing:border-box;padding:.8rem;font:inherit;font-size:1.05rem;border:1px solid #d8cfc8;border-radius:2px;background:#fffdfb}
	.rd-wcheck button,.rd-wcheck-actions a{display:inline-block;padding:.9em 1.4em;font:inherit;font-size:.8rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;border:0;border-radius:2px;background:var(--rd-accent,#b4877a);color:#fff;cursor:pointer}
	@media (max-width:520px){.rd-wcheck-row button{width:100%}}
	.rd-wcheck-status{max-width:560px;margin:1rem auto 0;padding:.9rem 1rem;border-radius:2px;font-weight:600;text-align:center}
	.rd-wcheck-frei{background:#e7f1e6;color:#2d5a2b}.rd-wcheck-teilweise{background:#fbf1de;color:#7a5410}.rd-wcheck-belegt{background:#f7e3e0;color:#8a2f25}.rd-wcheck-wait{background:#f6f1ee;color:#777}
	.rd-wcheck-h{font-weight:600;letter-spacing:.04em;margin:2rem 0 .8rem;text-align:center}
	.rd-wcheck-pkgs{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
	@media (max-width:900px){.rd-wcheck-pkgs{grid-template-columns:1fr 1fr}}
	@media (max-width:520px){.rd-wcheck-pkgs{grid-template-columns:1fr}}
	.rd-wcheck button.rd-wpkg{position:relative;display:flex;flex-direction:column;justify-content:flex-start;align-items:flex-start;width:100%;background:#fff;border:2px solid #eadfd8;border-radius:4px;padding:1.2rem;cursor:pointer;font:inherit;font-weight:400;text-transform:none;letter-spacing:normal;text-align:left;color:#3a2f2a}
	.rd-wcheck button.rd-wpkg:hover{border-color:#d6bfb5;background:#fff}
	.rd-wcheck button.rd-wpkg[aria-checked="true"]{border-color:var(--rd-accent,#b4877a);box-shadow:0 4px 18px rgba(120,80,60,.12)}
	.rd-wpkg[aria-checked="true"]::after{content:"✓";position:absolute;top:.8rem;right:.9rem;width:1.5rem;height:1.5rem;line-height:1.5rem;text-align:center;border-radius:50%;background:var(--rd-accent,#b4877a);color:#fff;font-size:.85rem}
	.rd-wpkg-badge{display:inline-block;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--rd-accent,#b4877a);margin-bottom:.2rem}
	.rd-wpkg-name{display:block;text-transform:none;letter-spacing:normal;font-weight:400;font-family:var(--wp--preset--font-family--heading,serif);font-size:1.35rem;margin:0 0 .2rem}
	.rd-wpkg-price{display:block;font-size:1.25rem;font-weight:700}.rd-wpkg-price s{font-weight:400;font-size:.9rem;opacity:.55;margin-right:.35rem}
	.rd-wpkg ul{text-transform:none;letter-spacing:normal;font-weight:400;margin:.7rem 0 0;padding-left:1.1rem;font-size:.88rem;line-height:1.5}
	.rd-wcheck-extras{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:center}
	.rd-wcheck-extras label{display:flex;gap:.45rem;align-items:center;background:#fff;border:1px solid #eadfd8;border-radius:30px;padding:.5rem .9rem;font-size:.9rem;cursor:pointer}
	.rd-wcheck-extras label.rd-on{border-color:var(--rd-accent,#b4877a);background:#fbf4f1}
	.rd-wcheck-extras label.rd-incl{opacity:.5;cursor:default}
	.rd-wcheck-sum{max-width:640px;margin:2rem auto 0;background:#fff;border-radius:4px;padding:1.4rem;border:1px solid #eadfd8;text-align:center}
	.rd-wcheck-total{font-size:2rem;font-weight:700;margin:.2rem 0}
	.rd-wcheck-line{font-size:.95rem}
	.rd-wcheck-small{font-size:.8rem;opacity:.75;margin:.6rem 0 0}
	.rd-wcheck-actions{display:flex;gap:.8rem;flex-wrap:wrap;justify-content:center;margin-top:1.1rem}
	.rd-wcheck-actions a.rd-alt{background:transparent;color:inherit;border:1.5px solid currentColor}
	</style>
	<script>
	(function(){
	  document.querySelectorAll('.rd-wcheck').forEach(function(root){
	    var cfg=JSON.parse(root.dataset.cfg), $=function(s){return root.querySelector(s);};
	    var eur=function(v){return v.toLocaleString('de-DE',{style:'currency',currency:'EUR',maximumFractionDigits:0});};
	    var date=$('input[name=date]'), st=$('.rd-wcheck-status'), offer=$('.rd-wcheck-offer'), pk=$('.rd-wcheck-pkgs'), ex=$('.rd-wcheck-extras'), sum=$('.rd-wcheck-sum');
	    var tmr=new Date(); tmr.setDate(tmr.getDate()+1); var max=new Date(); max.setMonth(max.getMonth()+24);
	    var iso=function(d){return d.toISOString().slice(0,10);}; date.min=iso(tmr); date.max=iso(max);
	    var sel='immer', chosen={}, disc=0, discWhy='', seq=0, dtxt='';
	    cfg.extras.forEach(function(x){var l=document.createElement('label');l.innerHTML='<input type="checkbox" value="'+x.id+'"><span></span>';ex.appendChild(l);});
	    function pkg(){return cfg.packages.find(function(p){return p.id===sel;});}
	    function render(){
	      pk.innerHTML=cfg.packages.map(function(p){var d=Math.round(p.price*disc);
	        return '<button type="button" class="rd-wpkg" role="radio" aria-checked="'+(p.id===sel)+'" data-id="'+p.id+'">'+(p.popular?'<span class="rd-wpkg-badge">Beliebt</span>':'')+
	        '<span class="rd-wpkg-name">'+p.name+'</span><span class="rd-wpkg-price">'+(d?'<s>'+eur(p.price)+'</s>':'')+eur(p.price-d)+'</span><ul>'+p.feat.map(function(f){return '<li>'+f+'</li>';}).join('')+'</ul></button>';}).join('');
	      var p=pkg(), total=p.price-Math.round(p.price*disc), names=[];
	      ex.querySelectorAll('input').forEach(function(i){var x=cfg.extras.find(function(e){return e.id===i.value;}), inc=p.incl.indexOf(x.id)>-1;
	        i.disabled=inc; i.checked=!inc&&!!chosen[x.id]; i.parentNode.classList.toggle('rd-incl',inc); i.parentNode.classList.toggle('rd-on',i.checked);
	        i.nextElementSibling.textContent=x.name+(inc?' · im Paket':' · +'+eur(x.price));
	        if(i.checked){total+=x.price;names.push(x.name);}});
	      var msg='Hallo Rui, wir heiraten am '+dtxt+' und interessieren uns für das Paket „'+p.name+'“'+(names.length?' mit '+names.join(', '):'')+' (Preis laut Website: '+eur(total)+'). Wann können wir uns kennenlernen?';
	      sum.innerHTML='<div class="rd-wcheck-line">'+p.name+(names.length?' + '+names.join(' + '):'')+(disc?' · '+discWhy:'')+'</div>'+
	        '<div class="rd-wcheck-total">'+eur(total)+'</div>'+
	        '<p class="rd-wcheck-small">Anfahrt bis 60 km ab Germersheim inklusive, darüber nach Entfernung. Anzahlung 30 % nach Vertragsschluss, Rest 4 Wochen vor der Hochzeit. Gemäß § 19 UStG ohne Umsatzsteuer. Unverbindlich: gebucht wird nach einem persönlichen Kennenlernen. <a href="'+cfg.details+'">Alle Details zu den Paketen</a></p>'+
	        '<div class="rd-wcheck-actions"><a href="'+cfg.contact+'">Kennenlernen anfragen</a><a class="rd-alt" href="https://wa.me/491738505311?text='+encodeURIComponent(msg)+'">Per WhatsApp</a></div>';
	    }
	    pk.addEventListener('click',function(e){var b=e.target.closest('.rd-wpkg');if(b){sel=b.dataset.id;render();}});
	    ex.addEventListener('change',function(e){chosen[e.target.value]=e.target.checked;render();});
	    function check(){
	      if(!date.value){date.reportValidity();return;}
	      var my=++seq, d=new Date(date.value+'T12:00:00'), m=d.getMonth()+1, wd=d.getDay();
	      dtxt=d.toLocaleDateString('de-DE',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
	      var off=(m>=11||m<=3), week=(wd>=1&&wd<=4); disc=(off||week)?0.10:0; discWhy=off?'10 % Nebensaison-Rabatt':'10 % Rabatt Mo–Do';
	      st.hidden=false; st.className='rd-wcheck-status rd-wcheck-wait'; st.textContent='Ich schaue in meinen Kalender …';
	      fetch(cfg.api+(cfg.api.indexOf('?')>-1?'&':'?')+'date='+date.value,{headers:{'X-WP-Nonce':cfg.nonce},credentials:'same-origin'})
	        .then(function(r){return r.json();}).then(function(j){show(my,j.status);}).catch(function(){show(my,'unbekannt');});
	    }
	    function show(my,s){
	      if(my!==seq)return;
	      var t={frei:'Ich bin am '+dtxt+' noch frei! Hier eure Optionen:',teilweise:'Am '+dtxt+' habe ich schon einen kürzeren Termin. Schreibt mir, oft passt es trotzdem.',belegt:'Am '+dtxt+' bin ich leider schon gebucht.'}[s]||'Das konnte ich gerade nicht prüfen. Schreibt mir einfach. Hier schon mal die Preise:';
	      st.className='rd-wcheck-status rd-wcheck-'+({frei:1,teilweise:1,belegt:1}[s]?s:'wait');
	      st.innerHTML=t+(s==='belegt'?' <a href="'+cfg.calendar+'">Freie Tage im Kalender ansehen</a>':'');
	      offer.hidden=(s==='belegt'); if(!offer.hidden){render();}
	    }
	    $('.rd-wcheck-date').addEventListener('submit',check); date.addEventListener('change',check);
	  });
	})();
	</script>
	<?php
	return ob_get_clean();
} );
