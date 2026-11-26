<?php
/**
 * Testside: prototype af online booking (Basis helkrops-DEXA, 999 kr.).
 * Kun visuel prototype. Ingen data sendes eller gemmes, ingen betaling.
 * Shortcoden vises kun for indloggede redaktører, så intet bliver synligt for besøgende,
 * selv hvis siden ved en fejl bliver udgivet.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'nordic_booking_demo' ) ) {
		$r['noindex'] = true; $r['nofollow'] = true;
	}
	return $r;
} );

add_shortcode( 'nordic_booking_demo', function () {
	if ( ! current_user_can( 'edit_pages' ) ) { return ''; }
	ob_start();
	?>
<div class="nbd" id="nbd">
<style>
.nbd{--p:var(--e-global-color-primary,#183A70);--s:var(--e-global-color-secondary,#102A4C);--t:var(--e-global-color-text,#1C2B45);--m:var(--e-global-color-c7e7a47,#66758A);--l:var(--e-global-color-0a99f9e,#E3EAF3);--bg:var(--e-global-color-800c90e,#F8FAFD);--bg2:var(--e-global-color-ff47909,#EEF4FB);--ok:var(--e-global-color-a5898d2,#2F9D69);--a:var(--e-global-color-accent,#C79A5B);font-family:Manrope,system-ui,sans-serif;color:var(--t);max-width:1180px;margin:0 auto;padding:24px 16px 48px;box-sizing:border-box}
.nbd *{box-sizing:border-box}
.nbd-demo{background:#FFF7E8;border:1px solid var(--a);border-radius:10px;padding:10px 14px;font-size:14px;margin-bottom:20px}
.nbd h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:600;color:var(--s);font-size:clamp(32px,4.5vw,52px);line-height:1.05;margin:0 0 10px}
.nbd h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:600;color:var(--s);font-size:28px;margin:0 0 14px}
.nbd h3{font-size:16px;margin:0 0 8px;color:var(--s)}
.nbd-hero{display:grid;grid-template-columns:1.3fr 1fr;gap:28px;align-items:start;margin-bottom:24px}
.nbd-lead{font-size:18px;color:var(--m);margin:0 0 16px;max-width:52ch}
.nbd-price{display:inline-block;background:var(--bg2);border-radius:12px;padding:8px 16px;font-size:34px;font-weight:700;color:var(--s)}
.nbd-chk{list-style:none;margin:0;padding:0;display:grid;gap:6px;font-size:15px}
.nbd-chk li{padding-left:24px;position:relative}
.nbd-chk li:before{content:"";position:absolute;left:2px;top:4px;width:10px;height:6px;border-left:2px solid var(--ok);border-bottom:2px solid var(--ok);transform:rotate(-45deg)}
.nbd-box{background:#fff;border:1px solid var(--l);border-radius:14px;padding:22px;box-shadow:0 6px 24px rgba(16,42,76,.06)}
.nbd-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:0 0 22px;padding:0;list-style:none;counter-reset:s}
.nbd-steps li{background:var(--bg2);color:var(--m);border-radius:8px;padding:10px 8px;font-size:14px;font-weight:600;text-align:center;counter-increment:s}
.nbd-steps li:before{content:counter(s);display:inline-grid;place-items:center;width:22px;height:22px;border-radius:50%;background:#fff;color:var(--s);margin-right:6px;font-size:12px}
.nbd-steps li.on{background:var(--s);color:#fff}
.nbd-steps li.done{background:#E6F4EC;color:var(--ok)}
.nbd-main{display:grid;grid-template-columns:1.6fr 1fr;gap:22px}
.nbd-pane[hidden]{display:none}
.nbd fieldset{border:0;padding:0;margin:0 0 16px}
.nbd legend{font-weight:700;margin-bottom:8px;color:var(--s)}
.nbd label.opt{display:flex;gap:8px;align-items:flex-start;padding:8px 10px;border:1px solid var(--l);border-radius:8px;margin-bottom:6px;cursor:pointer;background:#fff}
.nbd label.opt:has(input:checked){border-color:var(--p);background:var(--bg2)}
.nbd .fld{display:grid;gap:4px;margin-bottom:12px;font-size:14px}
.nbd input[type=text],.nbd input[type=email],.nbd input[type=tel],.nbd textarea{width:100%;padding:10px 12px;border:1px solid var(--l);border-radius:8px;font:inherit}
.nbd-hint{font-size:13px;color:var(--m);margin:4px 0 0}
.nbd-stop{background:#FDECEC;border:1px solid #E7B4B4;border-radius:8px;padding:10px 12px;font-size:14px;margin:8px 0}
.nbd-cal{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.nbd-cal table{width:100%;border-collapse:collapse;text-align:center;font-size:14px}
.nbd-cal th{color:var(--m);font-weight:600;padding:4px 0}
.nbd-cal td{padding:2px}
.nbd-cal button.d{width:100%;aspect-ratio:1;max-width:42px;border:0;border-radius:8px;background:var(--bg2);color:var(--s);font:inherit;font-weight:600;cursor:pointer}
.nbd-cal button.d:disabled{background:none;color:#B8C2D0;cursor:default}
.nbd-cal button.d.sel{background:var(--s);color:#fff}
.nbd-times{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.nbd-times button{padding:10px 0;border:1px solid var(--l);border-radius:8px;background:#fff;font:inherit;cursor:pointer;color:var(--s)}
.nbd-times button.sel{background:var(--s);color:#fff;border-color:var(--s)}
.nbd-times button:disabled{text-decoration:line-through;color:#B8C2D0;cursor:default}
.nbd-btns{display:flex;gap:10px;justify-content:space-between;margin-top:18px;flex-wrap:wrap}
.nbd .b{border:0;border-radius:10px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--s);color:#fff}
.nbd .b.sec{background:#fff;color:var(--s);border:1px solid var(--s)}
.nbd .b:disabled{opacity:.45;cursor:not-allowed}
.nbd-sum{background:var(--bg);border:1px solid var(--l);border-radius:12px;padding:18px;align-self:start;position:sticky;top:20px}
.nbd-sum dl{margin:0;display:grid;gap:10px;font-size:14px}
.nbd-sum dt{color:var(--m);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.nbd-sum dd{margin:0;font-weight:600}
.nbd-row{display:flex;justify-content:space-between;align-items:baseline;border-bottom:1px solid var(--l);padding-bottom:10px;margin-bottom:12px}
.nbd-row b{font-size:22px;color:var(--s)}
.nbd-pay{font-size:13px;color:var(--m);margin-top:10px}
.nbd-info{background:#E6F4EC;border-radius:10px;padding:12px 14px;font-size:14px;margin-top:14px}
.nbd-faq{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:22px;font-size:14px}
.nbd-faq p{margin:0;color:var(--m)}
@media(max-width:900px){.nbd-hero,.nbd-main,.nbd-cal,.nbd-faq{grid-template-columns:1fr}.nbd-sum{position:static}}
@media(max-width:520px){.nbd-steps li{font-size:0;padding:8px 4px}.nbd-steps li:before{margin:0;font-size:12px}.nbd-times{grid-template-columns:repeat(3,1fr)}.nbd-box{padding:16px}.nbd-btns .b{flex:1}}
</style>

<p class="nbd-demo"><b>Testside - kun synlig for indloggede.</b> Prototype til godkendelse. Intet sendes, intet gemmes, og der trækkes ingen penge. Tider er eksempler. Spørgsmålene i visitationen skal godkendes lægefagligt før brug.</p>

<div class="nbd-hero">
	<div>
		<h1>Book Basis helkrops-DEXA</h1>
		<p class="nbd-lead">Præcis kortlægning af din kropssammensætning med lægefaglig vurdering. Ingen henvisning, ingen ventetid.</p>
		<span class="nbd-price">999 kr.</span>
	</div>
	<ul class="nbd-chk">
		<li>Helkrops DEXA-scanning</li>
		<li>Fedtprocent og fedtfordeling</li>
		<li>Visceralt fedt</li>
		<li>Segmentanalyse (arme/ben/torso)</li>
		<li>Muskelmasse og muskelbalance</li>
		<li>Knoglemineralindhold</li>
		<li>Skriftlig rapport inden for 24 timer</li>
	</ul>
</div>

<div class="nbd-box">
	<ol class="nbd-steps" aria-label="Trin">
		<li data-s="1" class="on">Visitation</li><li data-s="2">Vælg tid</li><li data-s="3">Dine oplysninger</li><li data-s="4">Betaling</li>
	</ol>
	<div class="nbd-main">
		<div>
			<section class="nbd-pane" data-p="1">
				<h2>Kort visitation</h2>
				<p class="nbd-hint" style="margin:-6px 0 14px">Det er et krav i strålebeskyttelseslovgivningen, at en DEXA-scanning skal være berettiget. Dine svar læses kun af klinikkens sundhedspersonale.</p>
				<fieldset><legend>Hvad er formålet med scanningen?</legend>
					<label class="opt"><input type="radio" name="nbd_f" value="kost"> Kostomlægning eller vægttab</label>
					<label class="opt"><input type="radio" name="nbd_f" value="sport"> Optimering af sportspræstation</label>
					<label class="opt"><input type="radio" name="nbd_f" value="opf"> Opfølgning efter sygdom eller behandling</label>
					<label class="opt"><input type="radio" name="nbd_f" value="andet"> Andet</label>
				</fieldset>
				<fieldset><legend>Er du gravid?</legend>
					<label class="opt"><input type="radio" name="nbd_g" value="nej"> Nej</label>
					<label class="opt"><input type="radio" name="nbd_g" value="ja"> Ja</label>
					<div class="nbd-stop" data-stop="g" hidden>DEXA-scanning udføres ikke på gravide. Ring til os på 77 34 00 36, hvis du har spørgsmål.</div>
				</fieldset>
				<fieldset><legend>Er du over 197 cm eller over 159 kg?</legend>
					<label class="opt"><input type="radio" name="nbd_h" value="nej"> Nej</label>
					<label class="opt"><input type="radio" name="nbd_h" value="ja"> Ja</label>
					<div class="nbd-stop" data-stop="h" hidden>Scanneren giver et præcist resultat op til 197 cm og 159 kg. Kontakt os, så finder vi den rigtige løsning.</div>
				</fieldset>
				<div class="fld"><label for="nbd-x">Er der andet, lægen bør vide? (valgfrit)</label><textarea id="nbd-x" rows="3"></textarea><span class="nbd-hint">Skriv kun det nødvendige.</span></div>
			</section>

			<section class="nbd-pane" data-p="2" hidden>
				<h2>Vælg tid</h2>
				<div class="nbd-cal">
					<div>
						<div class="nbd-row" style="border:0;margin:0"><button type="button" class="b sec" data-m="-1" aria-label="Forrige måned">&lsaquo;</button><b id="nbd-mon"></b><button type="button" class="b sec" data-m="1" aria-label="Næste måned">&rsaquo;</button></div>
						<table><thead><tr><th>Ma</th><th>Ti</th><th>On</th><th>To</th><th>Fr</th><th>Lø</th><th>Sø</th></tr></thead><tbody id="nbd-days"></tbody></table>
					</div>
					<div>
						<h3 id="nbd-dlab">Vælg en dag</h3>
						<div class="nbd-times" id="nbd-times"></div>
						<p class="nbd-hint">Eksempeltider. Overstregede tider er optaget.</p>
					</div>
				</div>
			</section>

			<section class="nbd-pane" data-p="3" hidden>
				<h2>Dine oplysninger</h2>
				<div class="fld"><label for="nbd-n">Navn</label><input id="nbd-n" type="text" autocomplete="off"></div>
				<div class="fld"><label for="nbd-e">E-mail</label><input id="nbd-e" type="email" autocomplete="off"></div>
				<div class="fld"><label for="nbd-t">Telefon</label><input id="nbd-t" type="tel" autocomplete="off"></div>
				<label class="opt"><input type="checkbox" id="nbd-ok"> Jeg har læst handelsbetingelser, afbestillingsregler og privatlivspolitik.</label>
			</section>

			<section class="nbd-pane" data-p="4" hidden>
				<h2>Betaling</h2>
				<p>Du sendes videre til en sikker betalingsside hos Stripe, hvor du kan betale med kort eller MobilePay.</p>
				<div class="nbd-info"><b>Beløbet reserveres nu og trækkes først, når en læge har godkendt din visitation.</b> Godkendes scanningen ikke, frigives reservationen, og du betaler intet.</div>
				<p class="nbd-hint">Kortoplysninger indtastes kun hos Stripe og gemmes aldrig hos os.</p>
			</section>

			<section class="nbd-pane" data-p="5" hidden>
				<h2>Tak - din tid er reserveret</h2>
				<p>Vi har modtaget din visitation. En læge gennemgår den, typisk inden for [svartid aftales]. Du får en e-mail, når den er godkendt, og først da trækkes beløbet.</p>
				<p class="nbd-hint">Dette er en prototype. Ingen booking er oprettet.</p>
			</section>

			<div class="nbd-btns">
				<button type="button" class="b sec" id="nbd-back" hidden>Tilbage</button>
				<button type="button" class="b" id="nbd-next" disabled>Videre</button>
			</div>
		</div>

		<aside class="nbd-sum" aria-label="Din booking">
			<div class="nbd-row"><span>Basis helkrops-DEXA</span><b>999 kr.</b></div>
			<dl>
				<div><dt>Tid</dt><dd id="nbd-st">Ikke valgt</dd></div>
				<div><dt>Sted</dt><dd>Nordic DEXA Scan<br>Laurits Olsens Vej 1, 1. sal<br>2650 Hvidovre</dd></div>
				<div><dt>Varighed</dt><dd>Scanningen tager cirka 10 minutter</dd></div>
			</dl>
			<p class="nbd-pay">Betaling: kort eller MobilePay via Stripe. Beløbet trækkes efter lægefaglig godkendelse.</p>
		</aside>
	</div>
</div>

<div class="nbd-faq">
	<div><h3>Spørg først</h3><p>Har du spørgsmål om scanningen, så ring på 77 34 00 36 eller skriv til info@nordicspecialhospital.dk, før du booker.</p></div>
	<div><h3>Forberedelse</h3><p>Ingen særlig forberedelse. Kom gerne i let tøj uden metal.</p></div>
	<div><h3>Afbestilling</h3><p>[Afbestillingsregler indsættes efter godkendelse.]</p></div>
</div>

<script>
(function(){
var R=document.getElementById('nbd'),step=1,sel={d:null,t:null},view=new Date();view.setDate(1);
var MON=['januar','februar','marts','april','maj','juni','juli','august','september','oktober','november','december'];
var TIMES=['08:30','09:00','09:30','10:00','10:30','11:00','13:00','13:30','14:00','14:30','15:00','15:30'];
function q(s){return R.querySelector(s);} function qa(s){return R.querySelectorAll(s);}
function v(n){var e=R.querySelector('input[name='+n+']:checked');return e?e.value:'';}
function valid(){
 if(step===1){var g=v('nbd_g'),h=v('nbd_h');q('[data-stop=g]').hidden=g!=='ja';q('[data-stop=h]').hidden=h!=='ja';return !!v('nbd_f')&&g==='nej'&&h==='nej';}
 if(step===2)return !!(sel.d&&sel.t);
 if(step===3)return q('#nbd-n').value.trim()&&/\S+@\S+\.\S+/.test(q('#nbd-e').value)&&q('#nbd-t').value.trim()&&q('#nbd-ok').checked;
 return true;}
function show(){
 qa('.nbd-pane').forEach(function(p){p.hidden=+p.dataset.p!==step;});
 qa('.nbd-steps li').forEach(function(l){var s=+l.dataset.s;l.className=s===step?'on':(s<step?'done':'');});
 q('#nbd-back').hidden=step===1||step===5;
 var n=q('#nbd-next');n.hidden=step===5;n.textContent=step===4?'Reserver 999 kr. (demo)':'Videre';n.disabled=!valid();}
function cal(){
 var y=view.getFullYear(),m=view.getMonth(),t=new Date();t.setHours(0,0,0,0);
 q('#nbd-mon').textContent=MON[m]+' '+y;var first=(new Date(y,m,1).getDay()+6)%7,days=new Date(y,m+1,0).getDate(),h='<tr>';
 for(var i=0;i<first;i++)h+='<td></td>';
 for(var d=1;d<=days;d++){var dt=new Date(y,m,d),wd=(dt.getDay()+6)%7,ok=dt>=t&&wd<5,k=y+'-'+(m+1)+'-'+d;
  h+='<td><button type="button" class="d'+(sel.d===k?' sel':'')+'" data-d="'+k+'"'+(ok?'':' disabled')+'>'+d+'</button></td>';
  if((first+d)%7===0&&d<days)h+='</tr><tr>';}
 q('#nbd-days').innerHTML=h+'</tr>';}
function times(){
 var box=q('#nbd-times');if(!sel.d){box.innerHTML='';return;}
 var p=sel.d.split('-'),dt=new Date(+p[0],+p[1]-1,+p[2]);q('#nbd-dlab').textContent='Ledige tider '+dt.toLocaleDateString('da-DK',{weekday:'long',day:'numeric',month:'long'});
 box.innerHTML=TIMES.map(function(t,i){var busy=(+p[2]+i)%4===0;return '<button type="button" data-t="'+t+'"'+(busy?' disabled':'')+(sel.t===t?' class="sel"':'')+'>'+t+'</button>';}).join('');}
function sum(){if(!sel.d||!sel.t){q('#nbd-st').textContent='Ikke valgt';return;}var p=sel.d.split('-');q('#nbd-st').textContent=new Date(+p[0],+p[1]-1,+p[2]).toLocaleDateString('da-DK',{weekday:'long',day:'numeric',month:'long'})+' kl. '+sel.t;}
R.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;
 if(b.dataset.m){view.setMonth(view.getMonth()+(+b.dataset.m));cal();return;}
 if(b.dataset.d){sel.d=b.dataset.d;sel.t=null;cal();times();sum();show();return;}
 if(b.dataset.t){sel.t=b.dataset.t;times();sum();show();return;}
 if(b.id==='nbd-next'&&!b.disabled){step++;show();R.scrollIntoView({behavior:'smooth'});return;}
 if(b.id==='nbd-back'){step--;show();}});
R.addEventListener('change',show);R.addEventListener('input',show);
cal();show();
})();
</script>
</div>
	<?php
	return ob_get_clean();
} );
