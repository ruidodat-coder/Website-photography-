/**
 * Front-end motion: scroll reveals, count-up numbers, header state, parallax bands, hero scroll fade.
 * Content stays fully visible without JS; everything is skipped for prefers-reduced-motion.
 * Written as closures only so re-saving the snippet never redeclares anything.
 */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	(function(){
	  var d=document, b=d.body, reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	  // header: solid once the page is scrolled
	  var onScroll=function(){ b.classList.toggle('rd-scrolled', window.scrollY>60); };
	  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});
	  // scroll cue under the hero
	  d.querySelectorAll('.rd-cue').forEach(function(c){ c.addEventListener('click', function(){
	    var hero=c.closest('.rd-hero'); window.scrollTo({top:hero.offsetTop+hero.offsetHeight-60, behavior:reduce?'auto':'smooth'}); }); });
	  var vids=d.querySelectorAll('.rd-video video');
	  if(reduce){ vids.forEach(function(v){ v.removeAttribute('autoplay'); v.pause(); }); return; }
	  vids.forEach(function(v){ var p=v.play(); if(p&&p.catch) p.catch(function(){}); });

	  // reveal on scroll: items of grids get a stagger, everything else a soft rise
	  var root=d.querySelector('.wp-block-post-content'); if(!root) return;
	  var groups='.rd-cards,.rd-steps,.rd-usps,.rd-stats,.rd-gallery,.wp-block-columns';
	  var targets=[];
	  root.querySelectorAll('.rd-section,.rd-band').forEach(function(sec){
	    Array.prototype.forEach.call(sec.children, function(el){
	      if(el.matches(groups)){ Array.prototype.forEach.call(el.children, function(c,i){ c.style.setProperty('--rd-d', Math.min(i,5)*90+'ms'); targets.push(c); }); }
	      else if(el.matches('.wp-block-group') && el.querySelector(groups)){ /* nested wrappers */ el.querySelectorAll(groups).forEach(function(g){ Array.prototype.forEach.call(g.children,function(c,i){ c.style.setProperty('--rd-d', Math.min(i,5)*90+'ms'); targets.push(c); }); }); }
	      else targets.push(el);
	    });
	  });
	  var vh=window.innerHeight;
	  targets=targets.filter(function(el){ return el.getBoundingClientRect().top>vh*0.92; });
	  targets.forEach(function(el){ el.classList.add('rd-reveal'); });
	  var count=function(el){ var to=+el.dataset.to, t0=null, dur=1400;
	    var step=function(ts){ if(!t0)t0=ts; var k=Math.min((ts-t0)/dur,1), e=1-Math.pow(1-k,3); el.textContent=Math.round(to*e).toLocaleString('de-DE'); if(k<1) requestAnimationFrame(step); };
	    el.textContent='0'; requestAnimationFrame(step); };
	  var io=new IntersectionObserver(function(es){ es.forEach(function(e){ if(!e.isIntersecting) return;
	      var t=e.target; t.classList.add('rd-in'); t.querySelectorAll('.rd-num').forEach(count); io.unobserve(t);
	      setTimeout(function(){ t.style.removeProperty('--rd-d'); }, 1800); }); },
	    {rootMargin:'0px 0px -8% 0px', threshold:0.08});
	  targets.forEach(function(el){ io.observe(el); });

	  // hero: content drifts up and the video zooms slightly while scrolling away; bands get parallax
	  var heroes=d.querySelectorAll('.rd-hero-video'), bands=d.querySelectorAll('.rd-parallax'), ticking=false;
	  var frame=function(){ ticking=false; var h=window.innerHeight;
	    heroes.forEach(function(hr){ var r=hr.getBoundingClientRect(); var p=Math.min(Math.max(-r.top/r.height,0),1); hr.style.setProperty('--rd-p', p.toFixed(3)); });
	    bands.forEach(function(bd){ var r=bd.getBoundingClientRect(); if(r.bottom<0||r.top>h) return; var p=(h-r.top)/(h+r.height); bd.style.backgroundPosition='50% '+(20+p*60).toFixed(1)+'%'; });
	  };
	  window.addEventListener('scroll', function(){ if(!ticking){ ticking=true; requestAnimationFrame(frame); } }, {passive:true}); frame();
	})();
	</script>
	<?php
}, 50 );
