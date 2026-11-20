<?php
/* Bookingsiden: shortcode [nordic_booking]. Siden caches aldrig. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_is_booking_page() {
	return is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'nordic_booking' );
}
add_action( 'template_redirect', function () {
	if ( ! nb_is_booking_page() ) { return; }
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	do_action( 'litespeed_control_set_nocache', 'nordic booking' );
	nocache_headers();
	$s = nb_settings(); $u = get_permalink( get_queried_object_id() );
	if ( $u && $s['page_url'] !== $u ) { $s['page_url'] = $u; update_option( 'nb_settings', $s ); }
}, 1 );
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nb_is_booking_page() ) { return; }
	wp_enqueue_style( 'nordic-booking', plugins_url( 'assets/booking.css', NB_DIR . '/nordic-booking.php' ), array(), NB_VER );
	wp_enqueue_script( 'nordic-booking', plugins_url( 'assets/booking.js', NB_DIR . '/nordic-booking.php' ), array(), NB_VER, true );
	$s = nb_settings();
	wp_localize_script( 'nordic-booking', 'NB', array( 'api' => esc_url_raw( rest_url( 'nordic-booking/v1/' ) ), 'page' => get_queried_object_id(), 't' => time(), 'price' => (int) $s['price_ore'] ) );
} );

add_shortcode( 'nordic_booking', function () {
	$s = nb_settings();
	$price = number_format( $s['price_ore'] / 100, 0, ',', '.' ) . ' kr.';
	$terms = $s['terms_url'] ? '<a href="' . esc_url( $s['terms_url'] ) . '" target="_blank" rel="noopener">handelsbetingelser og afbestillingsregler</a>' : 'handelsbetingelser og afbestillingsregler';
	$priv = $s['privacy_url'] ? '<a href="' . esc_url( $s['privacy_url'] ) . '" target="_blank" rel="noopener">privatlivspolitik</a>' : 'privatlivspolitik';
	ob_start(); ?>
<div class="nbd" id="nbd">
<?php if ( ! nb_payments_ready() ) : ?><p class="nbd-demo"><b>Online booking er ikke åben endnu.</b> Ring på <?php echo esc_html( $s['phone'] ); ?>, så finder vi en tid.</p><?php endif; ?>
<?php if ( nb_mock() ) : ?><p class="nbd-demo"><b>Testtilstand:</b> betaling simuleres. Ingen rigtige penge.</p><?php endif; ?>
<div class="nbd-hero">
	<div>
		<h1>Book <?php echo esc_html( $s['product'] ); ?></h1>
		<p class="nbd-lead">Præcis kortlægning af din kropssammensætning med lægefaglig vurdering. Ingen henvisning, ingen ventetid.</p>
		<span class="nbd-price"><?php echo esc_html( $price ); ?></span>
	</div>
	<ul class="nbd-chk">
		<li>Helkrops DEXA-scanning</li><li>Fedtprocent og fedtfordeling</li><li>Visceralt fedt</li><li>Segmentanalyse (arme/ben/torso)</li><li>Muskelmasse og muskelbalance</li><li>Knoglemineralindhold</li><li>Skriftlig rapport inden for 24 timer</li>
	</ul>
</div>

<div class="nbd-box" id="nbd-flow">
	<ol class="nbd-steps" aria-label="Trin">
		<li data-s="1" class="on">Vælg tid</li><li data-s="2">Dine oplysninger</li><li data-s="3">Betaling</li><li data-s="4">Supplerende spørgsmål</li>
	</ol>
	<div class="nbd-main">
		<div>
			<section class="nbd-pane" data-p="1">
				<h2>Vælg tid</h2>
				<div class="nbd-cal">
					<div>
						<div class="nbd-row" style="border:0;margin:0"><button type="button" class="b sec" data-m="-1" aria-label="Forrige måned">&lsaquo;</button><b id="nbd-mon" aria-live="polite"></b><button type="button" class="b sec" data-m="1" aria-label="Næste måned">&rsaquo;</button></div>
						<table><thead><tr><th>Ma</th><th>Ti</th><th>On</th><th>To</th><th>Fr</th><th>Lø</th><th>Sø</th></tr></thead><tbody id="nbd-days"></tbody></table>
					</div>
					<div>
						<h3 id="nbd-dlab">Vælg en dag</h3>
						<div class="nbd-times" id="nbd-times"></div>
						<p class="nbd-hint" id="nbd-none" hidden>Ingen ledige tider i denne måned. Prøv næste måned, eller ring til os.</p>
					</div>
				</div>
			</section>
			<section class="nbd-pane" data-p="2" hidden>
				<h2>Dine oplysninger</h2>
				<div class="fld"><label for="nbd-n">Navn</label><input id="nbd-n" type="text" autocomplete="name" maxlength="190"></div>
				<div class="fld"><label for="nbd-e">E-mail</label><input id="nbd-e" type="email" autocomplete="email" maxlength="190"></div>
				<div class="fld"><label for="nbd-t">Telefon</label><input id="nbd-t" type="tel" autocomplete="tel" maxlength="20"></div>
				<div class="fld"><label for="nbd-c">Fødselsdato (ddmmåå-xxxx)</label><input id="nbd-c" type="text" inputmode="numeric" autocomplete="off" maxlength="11" placeholder="ddmmåå-xxxx" pattern="[0-9]{6}-[0-9]{4}" aria-describedby="nbd-c-h"><span class="nbd-hint" id="nbd-c-h">Skriv som ddmmåå-xxxx, fx 010180-1234. Behandles fortroligt og sendes ikke til betalingsudbyderen.</span></div>
				<div class="nbd-hp" aria-hidden="true"><label>Lad dette felt stå tomt <input id="nbd-hp" type="text" tabindex="-1" autocomplete="off"></label></div>
				<div class="nbd-info" style="background:var(--bg2)"><b>Før du betaler:</b> Tilbuddet er for voksne. DEXA-scanning udføres ikke under graviditet, men amning er som udgangspunkt ikke en hindring. Scanneren giver de mest nøjagtige resultater op til 197 cm i højde og 159 kg i vægt. Efter betalingen besvarer du nogle korte supplerende spørgsmål, som en læge gennemgår.</div>
				<label class="opt" style="margin-top:12px"><input type="checkbox" id="nbd-ok"> <span>Jeg har læst <?php echo $terms; // phpcs:ignore ?> samt <?php echo $priv; // phpcs:ignore ?>.</span></label>
			</section>
			<section class="nbd-pane" data-p="3" hidden>
				<h2>Betaling</h2>
				<p>Du sendes videre til en sikker betalingsside hos Stripe, hvor du kan betale med kort eller MobilePay.</p>
				<div class="nbd-info">Beløbet trækkes nu. Bagefter besvarer du nogle korte supplerende spørgsmål, og en læge vurderer, om scanningen er berettiget. <b>Kan scanningen ikke godkendes, refunderer vi hele beløbet.</b></div>
				<p class="nbd-hint">Kortoplysninger indtastes kun hos Stripe og gemmes aldrig hos os. Tiden holdes til dig i 30 minutter, mens du betaler.</p>
			</section>
			<section class="nbd-pane" data-p="4" hidden>
				<h2>Supplerende spørgsmål</h2>
				<p class="nbd-ok-pay" id="nbd-paid" hidden>Tak - din betaling er modtaget. Besvar de supplerende spørgsmål, så lægen kan godkende din scanning. Det tager kun et par minutter.</p>
				<p class="nbd-hint" style="margin:0 0 14px">Det er et krav i strålebeskyttelseslovgivningen, at en DEXA-scanning skal være berettiget. Dine svar læses kun af klinikkens sundhedspersonale.</p>
				<fieldset><legend>Hvad er formålet med scanningen?</legend>
					<label class="opt"><input type="radio" name="nbd_f" value="kost"> Kostomlægning eller vægttab</label>
					<label class="opt"><input type="radio" name="nbd_f" value="sport"> Optimering af sportspræstation</label>
					<label class="opt"><input type="radio" name="nbd_f" value="opf"> Opfølgning efter sygdom eller behandling</label>
					<label class="opt"><input type="radio" name="nbd_f" value="andet"> Andet</label>
				</fieldset>
				<fieldset><legend>Er du gravid?</legend>
					<label class="opt"><input type="radio" name="nbd_g" value="nej"> Nej</label>
					<label class="opt"><input type="radio" name="nbd_g" value="ja"> Ja</label>
					<div class="nbd-stop" data-stop="g" hidden>DEXA-scanning udføres ikke under graviditet. Send gerne svaret alligevel: lægen kontakter dig, og kan scanningen ikke udføres, refunderer vi hele beløbet.</div>
				</fieldset>
				<fieldset><legend>Er du over 197 cm eller over 159 kg?</legend>
					<label class="opt"><input type="radio" name="nbd_h" value="nej"> Nej</label>
					<label class="opt"><input type="radio" name="nbd_h" value="ja"> Ja</label>
					<div class="nbd-stop" data-stop="h" hidden>Scanneren giver de mest nøjagtige resultater op til 197 cm og 159 kg. Lægen kontakter dig om den rigtige løsning.</div>
				</fieldset>
				<div class="fld"><label for="nbd-x">Er der andet, lægen bør vide? (valgfrit)</label><textarea id="nbd-x" rows="3" maxlength="1000"></textarea><span class="nbd-hint">Skriv kun det nødvendige.</span></div>
			</section>
			<div class="nbd-err" id="nbd-err" role="alert" hidden></div>
			<div class="nbd-btns">
				<button type="button" class="b sec" id="nbd-back" hidden>Tilbage</button>
				<button type="button" class="b" id="nbd-next" disabled>Videre</button>
			</div>
		</div>
		<aside class="nbd-sum" aria-label="Din booking">
			<div class="nbd-row"><span><?php echo esc_html( $s['product'] ); ?></span><b><?php echo esc_html( $price ); ?></b></div>
			<dl>
				<div><dt>Tid</dt><dd id="nbd-st">Ikke valgt</dd></div>
				<div><dt>Sted</dt><dd><?php echo nl2br( esc_html( $s['address'] ) ); ?></dd></div>
				<div><dt>Varighed</dt><dd>Selve scanningen tager ca. 10 minutter</dd></div>
			</dl>
			<p class="nbd-pay">Betaling med kort eller MobilePay via Stripe.</p>
		</aside>
	</div>
</div>

<div class="nbd-box" id="nbd-result" hidden aria-live="polite"><h2 id="nbd-rh"></h2><p id="nbd-rt"></p></div>

<div class="nbd-faq">
	<div><h3>Spørg først</h3><p>Har du spørgsmål om scanningen, så ring på <?php echo esc_html( $s['phone'] ); ?> eller skriv til <?php echo esc_html( $s['clinic_email'] ); ?>, før du booker.</p></div>
	<div><h3>Forberedelse</h3><p>Der kræves ingen særlig forberedelse. Kom gerne ca. 15 minutter før din tid i let, løstsiddende tøj uden metaldele, og medbring dit sygesikringsbevis.</p></div>
	<div><h3>Sundhedsforsikring?</h3><p>Online booking er for privatbetalende. Dækker din sundhedsforsikring undersøgelsen, så <a href="/anmod-om-tid/">anmod om tid</a> eller ring til os.</p></div>
	<div><h3>Afbestilling</h3><p><?php echo esc_html( $s['cancel_text'] ); ?></p></div>
</div>
</div>
	<?php
	return ob_get_clean();
} );
