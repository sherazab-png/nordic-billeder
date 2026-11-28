<?php
/* Gavekort: én Basis helkrops-DEXA, gyldigt 1 år. Køb via Stripe Checkout. Indløses ved booking i stedet for betaling.
   Afvises scanningen, refunderes købet til køberen. Koden gemmes som hash (opslag) og krypteret (genudsendelse). */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_gift_months() { return 12; }

add_action( 'nb_install_tables', function ( $c ) {
	dbDelta( 'CREATE TABLE ' . nb_t( 'gifts' ) . " (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ref CHAR(24) NOT NULL,
		code_hash CHAR(64) NULL,
		code_enc TEXT NULL,
		code_last4 CHAR(4) NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'pending',
		buyer_name VARCHAR(190) NOT NULL,
		buyer_email VARCHAR(190) NOT NULL,
		recipient VARCHAR(190) NOT NULL DEFAULT '',
		message VARCHAR(400) NOT NULL DEFAULT '',
		amount INT UNSIGNED NOT NULL,
		session_id VARCHAR(255) NULL,
		pi_id VARCHAR(255) NULL,
		booking_id BIGINT UNSIGNED NULL,
		created_at DATETIME NOT NULL,
		paid_at DATETIME NULL,
		expires_at DATETIME NULL,
		redeemed_at DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY ref (ref),
		UNIQUE KEY code_hash (code_hash),
		KEY status (status)
	) $c;" );
} );

function nb_gift_hash( $code ) { return hash_hmac( 'sha256', strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) ), wp_salt( 'auth' ) . 'nb-gift' ); }
function nb_gift_get( $field, $val ) {
	global $wpdb;
	if ( ! in_array( $field, array( 'id', 'ref', 'code_hash', 'pi_id', 'booking_id', 'session_id' ), true ) || '' === (string) $val ) { return null; }
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . nb_t( 'gifts' ) . " WHERE $field=%s", $val ) );
}
function nb_gift_status_label( $s ) {
	$l = array( 'pending' => 'Afventer betaling', 'active' => 'Aktivt', 'reserved' => 'Brugt til booking', 'refunded' => 'Refunderet til køber', 'expired' => 'Udløbet', 'abandoned' => 'Ikke betalt' );
	return $l[ $s ] ?? $s;
}
/* Kode som DEXA-XXXX-XXXX uden tegn, der kan forveksles. */
function nb_gift_code() {
	$a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $c = '';
	for ( $i = 0; $i < 8; $i++ ) { $c .= $a[ random_int( 0, strlen( $a ) - 1 ) ]; }
	return 'DEXA-' . substr( $c, 0, 4 ) . '-' . substr( $c, 4 );
}

/* ---------- Køb ---------- */
function nb_rest_gift_buy( WP_REST_Request $r ) {
	global $wpdb;
	$p = (array) $r->get_json_params();
	if ( ! empty( $p['hp'] ) || ( time() - (int) ( $p['t'] ?? 0 ) ) < 3 ) { return nb_err( 'nb_bot', 'Kunne ikke behandle købet.', 400 ); }
	if ( ! ( function_exists( 'nb_mock_tool_ok' ) && nb_mock_tool_ok() ) && ! nb_rate_ok() ) { return nb_err( 'nb_rate', 'For mange forsøg. Prøv igen om lidt.', 429 ); }
	if ( ! nb_payments_ready() ) { return nb_err( 'nb_off', 'Gavekort kan ikke købes online endnu. Ring til os på 77 34 00 36.', 503 ); }
	$name = mb_substr( sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 0, 190 );
	$email = sanitize_email( (string) ( $p['email'] ?? '' ) );
	$to = mb_substr( sanitize_text_field( (string) ( $p['recipient'] ?? '' ) ), 0, 190 );
	$msg = mb_substr( sanitize_textarea_field( (string) ( $p['message'] ?? '' ) ), 0, 400 );
	if ( mb_strlen( $name ) < 2 || ! is_email( $email ) ) { return nb_err( 'nb_val', 'Udfyld dit navn og din e-mail.', 422 ); }
	if ( empty( $p['accept'] ) ) { return nb_err( 'nb_val', 'Du skal acceptere betingelserne for gavekort.', 422 ); }
	$page = (int) ( $p['page'] ?? 0 );
	if ( ! $page || ! has_shortcode( (string) get_post_field( 'post_content', $page ), 'nordic_gavekort' ) ) { return nb_err( 'nb_val', 'Ugyldig side.', 400 ); }
	$s = nb_settings();
	$ref = wp_generate_password( 24, false, false );
	$wpdb->insert( nb_t( 'gifts' ), array( 'ref' => $ref, 'status' => 'pending', 'buyer_name' => $name, 'buyer_email' => $email, 'recipient' => $to, 'message' => $msg, 'amount' => (int) $s['price_ore'], 'created_at' => nb_now() ) );
	$gid = (int) $wpdb->insert_id;
	if ( ! $gid ) { return nb_err( 'nb_db', 'Teknisk fejl. Prøv igen.', 500 ); }
	$ret = get_permalink( $page );
	nb_set_method( (string) ( $p['method'] ?? '' ) );
	$cs = nb_stripe( 'POST', 'checkout/sessions', array(
		'mode' => 'payment', 'client_reference_id' => 'g_' . $ref, 'customer_email' => $email, 'locale' => 'da',
		'expires_at' => time() + 30 * 60 + 5,
		'success_url' => add_query_arg( array( 'gk' => 'ok', 'ref' => $ref ), $ret ),
		'cancel_url' => add_query_arg( array( 'gk' => 'cancel' ), $ret ),
		'line_items' => array( array( 'quantity' => 1, 'price_data' => array( 'currency' => $s['currency'], 'unit_amount' => (int) $s['price_ore'], 'product_data' => array( 'name' => 'Gavekort: ' . $s['product'] ) ) ) ),
		'metadata' => array( 'nb_gift' => $ref ),
		'payment_intent_data' => array( 'metadata' => array( 'nb_gift' => $ref ), 'receipt_email' => $email ),
	), 'nb-gcs-' . $ref );
	if ( is_wp_error( $cs ) || empty( $cs['url'] ) ) { $wpdb->delete( nb_t( 'gifts' ), array( 'id' => $gid ) ); return nb_err( 'nb_pay', 'Betalingen kunne ikke startes. Prøv igen.', 502 ); }
	$wpdb->update( nb_t( 'gifts' ), array( 'session_id' => $cs['id'] ), array( 'id' => $gid ) );
	nb_log( null, 'gift_start', 'gavekort #' . $gid );
	return array( 'url' => $cs['url'] );
}

/* Betaling modtaget (webhook). Idempotent: koden oprettes kun én gang. */
function nb_gift_paid( $ref, array $session ) {
	global $wpdb;
	$g = nb_gift_get( 'ref', $ref );
	if ( ! $g || 'pending' !== $g->status || 'paid' !== ( $session['payment_status'] ?? '' ) ) { return $g; }
	$pi = is_array( $session['payment_intent'] ?? null ) ? ( $session['payment_intent']['id'] ?? '' ) : (string) ( $session['payment_intent'] ?? '' );
	for ( $i = 0; $i < 5; $i++ ) {
		$code = nb_gift_code();
		$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'gifts' ) . " SET status='active', code_hash=%s, code_enc=%s, code_last4=%s, pi_id=%s, paid_at=%s, expires_at=%s WHERE id=%d AND status='pending'",
			nb_gift_hash( $code ), nb_encrypt( array( 'code' => $code ) ), substr( $code, -4 ), $pi, nb_now(), gmdate( 'Y-m-d H:i:s', strtotime( '+' . nb_gift_months() . ' months' ) ), $g->id ) );
		if ( 1 === (int) $n ) { break; }
		if ( false !== $n ) { return nb_gift_get( 'id', $g->id ); } // allerede aktiveret af en anden forespørgsel
	}
	$g = nb_gift_get( 'id', $g->id );
	nb_log( null, 'gift_paid', 'gavekort #' . $g->id . ' ****' . $g->code_last4 );
	nb_mail_gift( $g );
	$s = nb_settings();
	wp_mail( $s['clinic_email'], '[Booking] Gavekort solgt', 'Gavekort til ' . $s['product'] . " er solgt.\nKøber: " . $g->buyer_name . ' (' . $g->buyer_email . ")\nKode slutter på: " . $g->code_last4 . "\nGyldigt til: " . nb_local( $g->expires_at, 'd.m.Y' ) . "\n\n" . admin_url( 'admin.php?page=nb-gifts' ) );
	return $g;
}

/* Gavekortets side til visning/udskrift (adgang via købets hemmelige reference). */
function nb_gift_view_url( $g ) { $s = nb_settings(); return add_query_arg( array( 'gk' => 'vis', 'ref' => $g->ref ), $s['gift_page_url'] ?: home_url( '/' ) ); }

function nb_mail_gift( $g ) {
	$s = nb_settings(); $c = nb_decrypt( $g->code_enc );
	$book = $s['page_url'] ?: home_url( '/' );
	return wp_mail( $g->buyer_email, 'Dit gavekort til ' . $s['product'],
		"Hej " . $g->buyer_name . "\n\nTak for dit køb. Her er gavekortet" . ( $g->recipient ? ' til ' . $g->recipient : '' ) . ":\n\n"
		. "Gavekort: 1 x " . $s['product'] . "\nKode: " . ( $c['code'] ?? '' ) . "\nGyldigt til og med: " . nb_local( $g->expires_at, 'd.m.Y' ) . "\n\n"
		. "Se og udskriv gavekortet:\n" . nb_gift_view_url( $g ) . "\n\n"
		. "Sådan bruges det: Book en tid på " . $book . " og skriv koden under \"Betaling\". Der skal ikke betales mere.\n\n"
		. "Kan scanningen ikke godkendes efter lægefaglig vurdering, refunderer vi beløbet til dig som køber.\n\n"
		. ( 'stripe' !== nb_provider() ? 'Kvittering: ' . number_format( $g->amount / 100, 2, ',', '.' ) . ' kr. er betalt med ' . nb_paid_via( $g->pi_id ) . ' den ' . nb_local( $g->paid_at, 'd.m.Y' ) . '.' : 'Din kvittering kommer i en separat e-mail fra Stripe.' ) . nb_sig(), array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

/* ---------- Indløsning ved booking ---------- */
function nb_gift_rate_ok() {
	$k = 'nb_grl_' . md5( wp_salt() . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	return (int) get_transient( $k ) < 8;
}
function nb_gift_rate_fail() {
	$k = 'nb_grl_' . md5( wp_salt() . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	set_transient( $k, (int) get_transient( $k ) + 1, HOUR_IN_SECONDS );
}
/* Tjek kode (uden at bruge den). */
function nb_rest_gift_check( WP_REST_Request $r ) {
	if ( ! nb_gift_rate_ok() ) { return nb_err( 'nb_rate', 'For mange forsøg. Prøv igen senere, eller ring til os.', 429 ); }
	$g = nb_gift_get( 'code_hash', nb_gift_hash( (string) ( $r->get_json_params()['code'] ?? '' ) ) );
	if ( ! $g ) { nb_gift_rate_fail(); return nb_err( 'nb_gift', 'Koden blev ikke fundet. Tjek stavemåden.', 404 ); }
	if ( 'active' !== $g->status ) { return nb_err( 'nb_gift', 'Gavekortet kan ikke bruges (' . mb_strtolower( nb_gift_status_label( $g->status ) ) . ').', 409 ); }
	if ( strtotime( $g->expires_at . ' UTC' ) < time() ) { return nb_err( 'nb_gift', 'Gavekortet er udløbet.', 409 ); }
	return array( 'ok' => 1, 'expires' => nb_local( $g->expires_at, 'd.m.Y' ) );
}
/* Brug gavekortet til en booking i status hold. Atomisk: koden kan kun bruges én gang. */
function nb_gift_redeem( $b, $code ) {
	global $wpdb;
	if ( ! nb_gift_rate_ok() ) { return new WP_Error( 'nb_rate', 'For mange forsøg. Prøv igen senere, eller ring til os.' ); }
	$g = nb_gift_get( 'code_hash', nb_gift_hash( $code ) );
	if ( ! $g ) { nb_gift_rate_fail(); return new WP_Error( 'nb_gift', 'Gavekortets kode blev ikke fundet.' ); }
	$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'gifts' ) . " SET status='reserved', booking_id=%d, redeemed_at=%s WHERE id=%d AND status='active' AND expires_at > %s", $b->id, nb_now(), $g->id, nb_now() ) );
	if ( 1 !== (int) $n ) { return new WP_Error( 'nb_gift', 'Gavekortet er allerede brugt eller udløbet.' ); }
	$s = nb_settings();
	$purge = gmdate( 'Y-m-d H:i:s', strtotime( $b->starts_utc . ' UTC' ) + (int) $s['purge_days'] * DAY_IN_SECONDS );
	// Bookingen får gavekortets betaling, så en refusion ved afvisning går tilbage til køberen.
	$wpdb->update( nb_t( 'bookings' ), array( 'gift_id' => $g->id, 'amount' => $g->amount ), array( 'id' => $b->id ) );
	$nb = nb_transition( nb_get( 'id', $b->id ), array( 'hold' ), 'paid', array( 'pi_id' => $g->pi_id, 'paid_at' => nb_now(), 'purge_after' => $purge ) );
	if ( ! $nb ) { $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'gifts' ) . " SET status='active', booking_id=NULL, redeemed_at=NULL WHERE id=%d", $g->id ) ); return new WP_Error( 'nb_gift', 'Bookingen kunne ikke gennemføres.' ); }
	nb_fix_slot( $nb );
	nb_log( $nb->id, 'gift_redeem', '****' . $g->code_last4 );
	nb_mail_paid( $nb );
	return $nb;
}
/* Afbestilling uden refusion: gavekortet bliver aktivt igen. */
function nb_gift_reactivate( $b ) {
	global $wpdb;
	if ( ! $b->gift_id ) { return false; }
	$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'gifts' ) . " SET status='active', booking_id=NULL, redeemed_at=NULL WHERE id=%d AND status='reserved' AND booking_id=%d", $b->gift_id, $b->id ) );
	if ( $n ) { nb_log( $b->id, 'gift_reactivated', '' ); }
	return (bool) $n;
}
/* Refusion af gavekortet til køberen (ved afvisning, eller fortrydelse af et ubrugt gavekort). */
function nb_gift_mark_refunded( $gift_id, $why ) {
	global $wpdb;
	$g = nb_gift_get( 'id', $gift_id ); if ( ! $g || 'refunded' === $g->status ) { return; }
	$wpdb->update( nb_t( 'gifts' ), array( 'status' => 'refunded' ), array( 'id' => $g->id ) );
	nb_log( $g->booking_id ?: null, 'gift_refunded', '****' . $g->code_last4 . ' ' . $why );
	$s = nb_settings();
	wp_mail( $g->buyer_email, 'Refusion af gavekort', "Hej " . $g->buyer_name . "\n\nDit gavekort til " . $s['product'] . " (kode slutter på " . $g->code_last4 . ") er refunderet med " . number_format( $g->amount / 100, 2, ',', '.' ) . " kr. Det kan tage 5-10 bankdage, før pengene står på din konto.\n\nGavekortet kan ikke længere bruges." . nb_sig(), array( 'Content-Type: text/plain; charset=UTF-8' ) );
}
function nb_gift_refund_unused( $g ) {
	if ( 'active' !== $g->status || ! $g->pi_id ) { return 'Kun aktive, ubrugte gavekort kan refunderes her.'; }
	$r = nb_stripe( 'POST', 'refunds', array( 'payment_intent' => $g->pi_id, 'metadata' => array( 'nb_gift' => $g->ref ) ), 'nb-grf-' . $g->ref );
	if ( is_wp_error( $r ) ) { nb_log( null, 'gift_refund_error', $r->get_error_message() ); return 'Refusionen fejlede: ' . $r->get_error_message(); }
	nb_gift_mark_refunded( $g->id, 'fortrudt' );
	return 'Gavekortet er refunderet til køberen.';
}

/* Udløb (dagligt). */
add_action( 'nb_cron_daily', function () {
	global $wpdb;
	$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'gifts' ) . " SET status='expired' WHERE status='active' AND expires_at < %s", nb_now() ) );
	$m = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'gifts' ) . " SET status='abandoned' WHERE status='pending' AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
	if ( $n || $m ) { nb_log( null, 'gift_cron', 'udløbet ' . (int) $n . ', ikke betalt ' . (int) $m ); }
} );

/* ---------- REST ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'nordic-booking/v1', '/gift/buy', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_gift_buy' ) );
	register_rest_route( 'nordic-booking/v1', '/gift/check', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_gift_check' ) );
	register_rest_route( 'nordic-booking/v1', '/gift/status', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $r ) {
		$g = nb_gift_get( 'ref', (string) $r->get_param( 'ref' ) );
		return $g ? array( 'status' => $g->status, 'email' => $g->buyer_email ) : nb_err( 'nb_nf', 'Ukendt', 404 );
	} ) );
} );

/* ---------- Side: [nordic_gavekort] ---------- */
add_action( 'template_redirect', function () {
	if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'nordic_gavekort' ) ) { return; }
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	do_action( 'litespeed_control_set_nocache', 'nordic gavekort' );
	nocache_headers();
	$s = nb_settings(); $u = get_permalink( get_queried_object_id() );
	if ( $u && $s['gift_page_url'] !== $u ) { $s['gift_page_url'] = $u; update_option( 'nb_settings', $s ); }
	// Gavekortets visningsside må ikke indekseres.
	if ( isset( $_GET['gk'] ) ) { add_filter( 'wp_robots', function ( $r ) { $r['noindex'] = true; $r['nofollow'] = true; return $r; } ); }
	wp_enqueue_style( 'nordic-booking', plugins_url( 'assets/booking.css', NB_DIR . '/nordic-booking.php' ), array(), NB_VER );
}, 1 );

add_shortcode( 'nordic_gavekort', function () {
	$s = nb_settings();
	$price = number_format( $s['price_ore'] / 100, 0, ',', '.' ) . ' kr.';
	$gk = sanitize_key( $_GET['gk'] ?? '' );
	if ( 'vis' === $gk ) {
		$g = nb_gift_get( 'ref', sanitize_text_field( wp_unslash( $_GET['ref'] ?? '' ) ) );
		if ( ! $g || ! in_array( $g->status, array( 'active', 'reserved', 'expired' ), true ) ) { return '<div class="nbd"><p>Gavekortet blev ikke fundet.</p></div>'; }
		$c = nb_decrypt( $g->code_enc );
		ob_start(); ?>
<div class="nbd nbd-gk-view">
	<style>@media print{body *{visibility:hidden}.nbd-gk-card,.nbd-gk-card *{visibility:visible}.nbd-gk-card{position:absolute;left:0;top:0;width:100%}.nbd-gk-noprint{display:none}}</style>
	<div class="nbd-gk-card">
		<p class="nbd-gk-brand">Nordic DEXA Scan</p>
		<h1>Gavekort</h1>
		<?php if ( $g->recipient ) : ?><p class="nbd-gk-to">Til <?php echo esc_html( $g->recipient ); ?></p><?php endif; ?>
		<p class="nbd-gk-what">1 x <?php echo esc_html( $s['product'] ); ?></p>
		<?php if ( $g->message ) : ?><p class="nbd-gk-msg"><?php echo nl2br( esc_html( $g->message ) ); ?></p><?php endif; ?>
		<p class="nbd-gk-code"><?php echo esc_html( $c['code'] ?? '' ); ?></p>
		<p class="nbd-gk-meta">Gyldigt til og med <?php echo esc_html( nb_local( $g->expires_at, 'd.m.Y' ) ); ?><br>Book på nordicdexascan.dk og skriv koden under "Betaling".<br><?php echo esc_html( str_replace( "\n", ', ', $s['address'] ) ); ?> - tlf. <?php echo esc_html( $s['phone'] ); ?></p>
		<?php if ( 'active' !== $g->status ) : ?><p class="nbd-err"><?php echo esc_html( nb_gift_status_label( $g->status ) ); ?></p><?php endif; ?>
	</div>
	<p class="nbd-gk-noprint"><button type="button" class="b" onclick="window.print()">Udskriv gavekort</button></p>
</div>
		<?php
		return ob_get_clean();
	}
	ob_start(); ?>
<div class="nbd" id="nbd-gk">
<?php if ( nb_mock() ) : ?><p class="nbd-demo"><b>Testtilstand:</b> betaling simuleres.</p><?php endif; ?>
<div class="nbd-hero">
	<div>
		<h1>Giv et gavekort til en DEXA-scanning</h1>
		<p class="nbd-lead">En præcis kortlægning af kropssammensætningen med lægefaglig vurdering. Gavekortet gælder til én <?php echo esc_html( $s['product'] ); ?> og er gyldigt i 1 år.</p>
		<span class="nbd-price"><?php echo esc_html( $price ); ?></span>
	</div>
	<ul class="nbd-chk"><li>Helkrops DEXA-scanning</li><li>Fedtprocent, muskelmasse og visceralt fedt</li><li>Knoglemineralindhold</li><li>Skriftlig rapport inden for 24 timer</li><li>Sendes straks på e-mail - klar til udskrift</li></ul>
</div>
<div class="nbd-box" id="nbd-gk-form">
	<?php if ( 'ok' === $gk ) : ?>
		<h2>Tak for dit køb</h2><p id="nbd-gk-st">Vi sender gavekortet til din e-mail, så snart betalingen er registreret.</p>
	<?php else : ?>
		<?php if ( 'cancel' === $gk ) : ?><p class="nbd-err">Betalingen blev afbrudt. Du kan prøve igen.</p><?php endif; ?>
		<h2>Køb gavekort</h2>
		<div class="fld"><label for="gk-n">Dit navn</label><input id="gk-n" type="text" autocomplete="name" maxlength="190"></div>
		<div class="fld"><label for="gk-e">Din e-mail (gavekortet sendes hertil)</label><input id="gk-e" type="email" autocomplete="email" maxlength="190"></div>
		<div class="fld"><label for="gk-r">Til (valgfrit)</label><input id="gk-r" type="text" maxlength="190"></div>
		<div class="fld"><label for="gk-m">Hilsen på gavekortet (valgfrit)</label><textarea id="gk-m" rows="3" maxlength="400"></textarea></div>
		<div class="nbd-hp" aria-hidden="true"><label>Lad dette felt stå tomt <input id="gk-hp" type="text" tabindex="-1" autocomplete="off"></label></div>
		<div class="nbd-info" style="background:var(--bg2)">Gavekortet er gyldigt i 1 år fra købsdatoen og kan ikke ombyttes til kontanter. Modtageren booker selv tid og besvarer de supplerende spørgsmål. Kan scanningen ikke godkendes efter lægefaglig vurdering, refunderer vi beløbet til dig som køber. Tilbuddet er for voksne.</div>
		<label class="opt" style="margin-top:12px"><input type="checkbox" id="gk-ok"> <span>Jeg har læst betingelserne for gavekort<?php echo $s['terms_url'] ? ' og <a href="' . esc_url( $s['terms_url'] ) . '" target="_blank" rel="noopener">handelsbetingelserne</a>' : ''; ?>.</span></label>
		<?php echo nb_method_picker(); // phpcs:ignore ?>
		<div class="nbd-err" id="gk-err" role="alert" hidden></div>
		<div class="nbd-btns"><span></span><button type="button" class="b" id="gk-buy" disabled>Køb gavekort - <?php echo esc_html( $price ); ?></button></div>
	<?php endif; ?>
</div>
<script>
(function(){var api=<?php echo wp_json_encode( esc_url_raw( rest_url( 'nordic-booking/v1/' ) ) ); ?>,page=<?php echo (int) get_queried_object_id(); ?>,t=<?php echo time(); ?>;
var R=document.getElementById('nbd-gk');function q(s){return R.querySelector(s);}
var u=new URLSearchParams(location.search);
if(u.get('gk')==='ok'&&u.get('ref')){var n=0;(function poll(){fetch(api+'gift/status?ref='+encodeURIComponent(u.get('ref'))+'&_='+Date.now()).then(function(r){return r.json();}).then(function(j){var st=q('#nbd-gk-st');if(j.status==='active'){st.textContent='Gavekortet er sendt til '+j.email+'. Tjek også din spam-mappe.';try{if(window.gtag){window.gtag('event','gift_card_purchase');}}catch(x){}}else if(n++<10){setTimeout(poll,2000);}});})();return;}
var b=q('#gk-buy');if(!b)return;
function ok(){b.disabled=!(q('#gk-n').value.trim().length>1&&/\S+@\S+\.\S+/.test(q('#gk-e').value)&&q('#gk-ok').checked);}
R.addEventListener('input',ok);R.addEventListener('change',ok);
b.addEventListener('click',function(){b.disabled=true;var e=q('#gk-err');e.hidden=true;
fetch(api+'gift/buy',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:q('#gk-n').value,email:q('#gk-e').value,recipient:q('#gk-r').value,message:q('#gk-m').value,accept:q('#gk-ok').checked,hp:q('#gk-hp').value,t:t,page:page,method:(q('input[name=nbpm]:checked')||{}).value||''})})
.then(function(r){return r.json();}).then(function(j){if(j.url){location.href=j.url;return;}e.textContent=j.message||'Købet kunne ikke startes.';e.hidden=false;ok();}).catch(function(){e.textContent='Forbindelsen fejlede.';e.hidden=false;ok();});});
})();
</script>
</div>
	<?php
	return ob_get_clean();
} );

/* ---------- Admin ---------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'nb-bookings', 'Gavekort', 'Gavekort', 'nb_manage', 'nb-gifts', 'nb_admin_gifts' );
}, 20 );
function nb_admin_gifts() {
	global $wpdb; $msg = '';
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'nb_gifts' ) ) {
		$g = nb_gift_get( 'id', (int) $_POST['g'] );
		if ( $g && isset( $_POST['refund'] ) ) { $msg = nb_gift_refund_unused( $g ); }
		if ( $g && isset( $_POST['resend'] ) && in_array( $g->status, array( 'active', 'reserved' ), true ) ) { $msg = nb_mail_gift( $g ) ? 'Gavekortet er sendt igen.' : 'E-mailen fejlede.'; }
	}
	echo '<div class="wrap"><h1>Gavekort</h1>' . ( $msg ? '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>' : '' );
	echo '<p class="description">Gavekort gælder 1 år. Ubrugte gavekort kan refunderes til køberen (fx ved fortrydelse inden for 14 dage).</p>';
	echo '<table class="widefat striped"><thead><tr><th>Købt</th><th>Kode</th><th>Køber</th><th>Til</th><th>Status</th><th>Gyldig til</th><th>Booking</th><th></th></tr></thead><tbody>';
	foreach ( $wpdb->get_results( 'SELECT * FROM ' . nb_t( 'gifts' ) . " WHERE status<>'abandoned' ORDER BY id DESC LIMIT 300" ) as $g ) {
		echo '<tr><td>' . esc_html( $g->paid_at ? nb_local( $g->paid_at, 'd.m.Y' ) : '-' ) . '</td><td>' . ( $g->code_last4 ? '****-' . esc_html( $g->code_last4 ) : '-' ) . '</td><td>' . esc_html( $g->buyer_name ) . '<br><small>' . esc_html( $g->buyer_email ) . '</small></td><td>' . esc_html( $g->recipient ) . '</td><td>' . esc_html( nb_gift_status_label( $g->status ) ) . '</td><td>' . esc_html( $g->expires_at ? nb_local( $g->expires_at, 'd.m.Y' ) : '-' ) . '</td><td>' . ( $g->booking_id ? '<a href="' . esc_url( admin_url( 'admin.php?page=nb-bookings&b=' . (int) $g->booking_id ) ) . '">Åbn</a>' : '' ) . '</td><td>';
		if ( in_array( $g->status, array( 'active', 'reserved' ), true ) ) {
			echo '<form method="post" style="display:inline">'; wp_nonce_field( 'nb_gifts' ); echo '<input type="hidden" name="g" value="' . (int) $g->id . '"><button class="button" name="resend" value="1">Send igen</button> ';
			if ( 'active' === $g->status ) { echo '<button class="button" name="refund" value="1" onclick="return confirm(\'Refunder gavekortet til køberen? Koden kan derefter ikke bruges.\')">Refunder</button>'; }
			echo '</form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}
