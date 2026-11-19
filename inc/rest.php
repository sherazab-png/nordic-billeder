<?php
/* REST: ledige tider, reservation og betaling, status, annullering, Stripe-webhook og testtilstand. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	$ns = 'nordic-booking/v1';
	register_rest_route( $ns, '/slots', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_slots' ) );
	register_rest_route( $ns, '/hold', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_hold' ) );
	register_rest_route( $ns, '/status', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_status' ) );
	register_rest_route( $ns, '/visit', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_visit' ) );
	register_rest_route( $ns, '/cancel', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_cancel' ) );
	register_rest_route( $ns, '/stripe', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_webhook' ) );
	if ( nb_mock() ) {
		register_rest_route( $ns, '/mock/checkout', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_mock_page' ) );
		register_rest_route( $ns, '/mock/tool', array( 'methods' => 'POST', 'permission_callback' => 'nb_mock_tool_ok', 'callback' => 'nb_rest_mock_tool' ) );
		register_rest_route( $ns, '/mock/act', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_mock_act' ) );
	}
} );

/* LiteSpeed cacher ellers REST-svar (ledige tider ville blive vist forkert). */
add_filter( 'rest_pre_dispatch', function ( $r, $server, $req ) {
	if ( 0 === strpos( $req->get_route(), '/nordic-booking/' ) ) { do_action( 'litespeed_control_set_nocache', 'nordic booking' ); if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); } }
	return $r;
}, 1, 3 );
add_filter( 'rest_post_dispatch', function ( $res, $server, $req ) {
	if ( 0 === strpos( $req->get_route(), '/nordic-booking/' ) && $res instanceof WP_REST_Response ) { $res->header( 'Cache-Control', 'no-store, private' ); }
	return $res;
}, 10, 3 );

function nb_err( $code, $msg, $status ) { return new WP_Error( $code, $msg, array( 'status' => $status ) ); }

function nb_rest_slots( WP_REST_Request $r ) {
	$from = (string) $r->get_param( 'from' ); $to = (string) $r->get_param( 'to' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) { return nb_err( 'nb_bad', 'Ugyldig dato', 400 ); }
	if ( strtotime( $to ) - strtotime( $from ) > 62 * DAY_IN_SECONDS ) { return nb_err( 'nb_bad', 'For langt interval', 400 ); }
	return array( 'slots' => nb_free_slots( $from, $to ), 'ready' => nb_payments_ready() );
}

function nb_rate_ok() {
	$k = 'nb_rl_' . md5( wp_salt() . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$n = (int) get_transient( $k );
	if ( $n >= 6 ) { return false; }
	set_transient( $k, $n + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}

function nb_rest_hold( WP_REST_Request $r ) {
	global $wpdb;
	$p = (array) $r->get_json_params();
	if ( ! empty( $p['hp'] ) || ( time() - (int) ( $p['t'] ?? 0 ) ) < 3 ) { return nb_err( 'nb_bot', 'Kunne ikke behandle bookingen.', 400 ); }
	if ( ! ( function_exists( 'nb_mock_tool_ok' ) && nb_mock_tool_ok() ) && ! nb_rate_ok() ) { return nb_err( 'nb_rate', 'For mange forsøg. Prøv igen om lidt, eller ring til os.', 429 ); }
	if ( ! nb_payments_ready() ) { return nb_err( 'nb_off', 'Online booking er ikke åben endnu. Ring til os på 77 34 00 36.', 503 ); }

	$name = mb_substr( sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 0, 190 );
	$email = sanitize_email( (string) ( $p['email'] ?? '' ) );
	$phone = preg_replace( '/[^0-9+ ]/', '', (string) ( $p['phone'] ?? '' ) );
	if ( mb_strlen( $name ) < 2 || ! is_email( $email ) || strlen( preg_replace( '/\D/', '', $phone ) ) < 8 ) { return nb_err( 'nb_val', 'Udfyld navn, e-mail og telefonnummer.', 422 ); }
	if ( empty( $p['accept'] ) ) { return nb_err( 'nb_val', 'Du skal acceptere handelsbetingelser og privatlivspolitik.', 422 ); }
	$page = (int) ( $p['page'] ?? 0 );
	if ( ! $page || ! has_shortcode( (string) get_post_field( 'post_content', $page ), 'nordic_booking' ) ) { return nb_err( 'nb_val', 'Ugyldig side.', 400 ); }
	$slot = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . nb_t( 'slots' ) . ' WHERE id=%d', (int) ( $p['slot_id'] ?? 0 ) ) );
	if ( ! $slot ) { return nb_err( 'nb_slot', 'Tiden findes ikke længere. Vælg en anden tid.', 409 ); }

	if ( ! nb_key() ) { nb_log( null, 'crypto_error', 'nøgle mangler' ); return nb_err( 'nb_off', 'Online booking er midlertidigt lukket. Ring til os.', 503 ); }
	$s = nb_settings(); $now = nb_now();
	$ref = wp_generate_password( 24, false, false );
	$wpdb->insert( nb_t( 'bookings' ), array( 'ref' => $ref, 'slot_id' => $slot->id, 'starts_utc' => $slot->starts_utc, 'status' => 'hold', 'name' => $name, 'email' => $email, 'phone' => $phone, 'amount' => (int) $s['price_ore'], 'currency' => $s['currency'], 'created_at' => $now, 'updated_at' => $now ) );
	$bid = (int) $wpdb->insert_id;
	if ( ! $bid ) { return nb_err( 'nb_db', 'Teknisk fejl. Prøv igen, eller ring til os.', 500 ); }
	$held = gmdate( 'Y-m-d H:i:s', time() + max( 30, (int) $s['hold_min'] ) * 60 );
	if ( ! nb_claim_slot( $slot->id, $bid, $held ) ) {
		$wpdb->delete( nb_t( 'bookings' ), array( 'id' => $bid ) );
		return nb_err( 'nb_taken', 'Tiden er netop blevet booket af en anden. Vælg en anden tid.', 409 );
	}
	$b = nb_get( 'id', $bid );
	nb_log( $bid, 'hold', nb_local( $slot->starts_utc ) );
	$cs = nb_create_checkout( $b, get_permalink( $page ) );
	if ( is_wp_error( $cs ) || empty( $cs['url'] ) ) {
		nb_log( $bid, 'checkout_error', is_wp_error( $cs ) ? $cs->get_error_message() : 'ingen url' );
		nb_release_slot( $b ); $wpdb->delete( nb_t( 'bookings' ), array( 'id' => $bid ) );
		return nb_err( 'nb_pay', 'Betalingen kunne ikke startes. Prøv igen, eller ring til os.', 502 );
	}
	$wpdb->update( nb_t( 'bookings' ), array( 'session_id' => $cs['id'] ), array( 'id' => $bid ) );
	return array( 'url' => $cs['url'] );
}

function nb_rest_status( WP_REST_Request $r ) {
	$b = nb_get( 'ref', (string) $r->get_param( 'ref' ) );
	if ( ! $b ) { return nb_err( 'nb_nf', 'Ukendt booking', 404 ); }
	return array( 'status' => $b->status, 'label' => nb_status_label( $b->status ), 'when' => nb_human( $b->starts_utc ), 'visit' => (bool) $b->visit_at );
}

/* Visitation efter betaling. Bookingnummeret (24 tegn, kun i patientens link) giver adgang, og kun én gang. */
function nb_rest_visit( WP_REST_Request $r ) {
	global $wpdb;
	$p = (array) $r->get_json_params();
	$b = nb_get( 'ref', (string) ( $p['ref'] ?? '' ) );
	if ( ! $b ) { return nb_err( 'nb_nf', 'Ukendt booking', 404 ); }
	if ( $b->visit_at ) { return nb_err( 'nb_done', 'Spørgeskemaet er allerede modtaget. Ring til os, hvis du vil rette noget.', 409 ); }
	if ( ! in_array( $b->status, array( 'hold', 'paid' ), true ) ) { return nb_err( 'nb_state', 'Bookingen er ikke aktiv.', 409 ); }
	$v = (array) ( $p['visit'] ?? array() );
	$formaal = (string) ( $v['formaal'] ?? '' );
	$gravid = (string) ( $v['gravid'] ?? '' ); $graense = (string) ( $v['graense'] ?? '' );
	if ( ! in_array( $formaal, array( 'kost', 'sport', 'opf', 'andet' ), true ) || ! in_array( $gravid, array( 'ja', 'nej' ), true ) || ! in_array( $graense, array( 'ja', 'nej' ), true ) ) { return nb_err( 'nb_val', 'Besvar alle spørgsmål.', 422 ); }
	$note = mb_substr( sanitize_textarea_field( (string) ( $v['note'] ?? '' ) ), 0, 1000 );
	$enc = nb_encrypt( array( 'formaal' => $formaal, 'gravid' => $gravid, 'graense' => $graense, 'note' => $note, 'v' => 2 ) );
	if ( false === $enc ) { return nb_err( 'nb_off', 'Teknisk fejl. Ring til os.', 503 ); }
	$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'bookings' ) . ' SET visit=%s, visit_at=%s, updated_at=%s WHERE id=%d AND visit_at IS NULL', $enc, nb_now(), nb_now(), $b->id ) );
	if ( 1 !== (int) $n ) { return nb_err( 'nb_done', 'Spørgeskemaet er allerede modtaget.', 409 ); }
	nb_log( $b->id, 'visit', 'modtaget' );
	$b = nb_get( 'id', $b->id );
	if ( 'paid' === $b->status ) { nb_mail_clinic_new( $b ); }
	return array( 'ok' => 1, 'flag' => ( 'ja' === $gravid || 'ja' === $graense ) );
}

function nb_rest_cancel( WP_REST_Request $r ) {
	$b = nb_get( 'ref', (string) ( $r->get_json_params()['ref'] ?? '' ) );
	if ( ! $b ) { return nb_err( 'nb_nf', 'Ukendt booking', 404 ); }
	if ( 'hold' !== $b->status ) { return array( 'status' => $b->status ); }
	if ( $b->session_id ) {
		$cs = nb_stripe( 'POST', 'checkout/sessions/' . rawurlencode( $b->session_id ) . '/expire', array(), 'nb-exp-' . $b->ref );
		if ( is_wp_error( $cs ) ) {
			// Sessionen kan allerede være gennemført eller udløbet. Slå den op.
			$cs = nb_stripe( 'GET', 'checkout/sessions/' . rawurlencode( $b->session_id ) );
			if ( ! is_wp_error( $cs ) && 'complete' === ( $cs['status'] ?? '' ) && 'paid' === ( $cs['payment_status'] ?? '' ) ) { $b = nb_mark_paid( $b, $cs ); return array( 'status' => $b->status ); }
		}
	}
	$b = nb_expire( $b, 'annulleret af patient' ) ?: nb_get( 'id', $b->id );
	return array( 'status' => $b->status );
}

function nb_rest_webhook( WP_REST_Request $r ) {
	list( $code, $msg ) = nb_webhook_process( $r->get_body(), (string) $r->get_header( 'stripe-signature' ) );
	return new WP_REST_Response( array( 'result' => $msg ), $code );
}

/* ---------- Testtilstand: falsk betalingsside ---------- */
function nb_rest_mock_page( WP_REST_Request $r ) {
	$id = (string) $r->get_param( 'cs' ); $m = nb_mock_store(); $cs = $m['cs'][ $id ] ?? null;
	header( 'Content-Type: text/html; charset=utf-8' ); header( 'Cache-Control: no-store' );
	$act = esc_url( rest_url( 'nordic-booking/v1/mock/act' ) );
	echo '<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>Testbetaling</title><body style="font:16px system-ui;max-width:520px;margin:40px auto;padding:0 16px">';
	echo '<p style="background:#B42318;color:#fff;padding:8px;border-radius:6px">TESTTILSTAND - ingen rigtig betaling. Erstattes af Stripe Checkout, når testnøgler er indsat.</p>';
	if ( ! $cs ) { echo '<p>Ukendt session.</p>'; exit; }
	echo '<h1>Betal ' . esc_html( number_format( $cs['amount_total'] / 100, 2, ',', '.' ) ) . ' kr.</h1><p>Status: ' . esc_html( $cs['status'] ) . '</p>';
	foreach ( array( 'pay' => 'Betal (gennemført)', 'pay_dup' => 'Betal og send webhook to gange', 'pay_nohook' => 'Betal, men webhook går tabt', 'cancel' => 'Fortryd og gå tilbage' ) as $k => $l ) {
		echo '<form method=post action="' . $act . '" style="margin:8px 0"><input type=hidden name=cs value="' . esc_attr( $id ) . '"><input type=hidden name=act value="' . esc_attr( $k ) . '"><button style="font:inherit;padding:10px 14px;width:100%">' . esc_html( $l ) . '</button></form>';
	}
	exit;
}
function nb_rest_mock_act( WP_REST_Request $r ) {
	$id = (string) $r->get_param( 'cs' ); $act = (string) $r->get_param( 'act' );
	$m = nb_mock_store(); $cs = $m['cs'][ $id ] ?? null;
	if ( ! $cs ) { return nb_err( 'nb_nf', 'ukendt', 404 ); }
	if ( 'cancel' === $act ) { wp_redirect( $cs['cancel_url'] ); exit; }
	$paid = nb_mock_pay( $id );
	if ( ! $paid ) { wp_redirect( $cs['cancel_url'] ); exit; }
	if ( 'pay_nohook' !== $act ) {
		$evid = 'evt_mock_' . wp_generate_password( 16, false );
		nb_mock_emit( 'checkout.session.completed', $paid, $evid );
		if ( 'pay_dup' === $act ) { nb_mock_emit( 'checkout.session.completed', $paid, $evid ); }
	}
	wp_redirect( $cs['success_url'] ); exit;
}

/* Testværktøj (kun testtilstand og kun med testmiljøets adgangskode-cookie). */
function nb_mock_tool_ok() {
	return nb_mock() && defined( 'NORDIC_DEV_PASS' ) && NORDIC_DEV_PASS && isset( $_COOKIE['nordic_dev'] ) && hash_equals( hash_hmac( 'sha256', 'ok', NORDIC_DEV_PASS ), (string) $_COOKIE['nordic_dev'] );
}
function nb_rest_mock_tool( WP_REST_Request $r ) {
	global $wpdb;
	$p = (array) $r->get_json_params(); $act = (string) ( $p['act'] ?? '' );
	if ( 'age' === $act ) { return array( 'n' => $wpdb->query( 'UPDATE ' . nb_t( 'slots' ) . ' SET held_until=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR) WHERE held_until IS NOT NULL' ) ); }
	if ( 'cron' === $act ) { nb_cleanup_holds(); nb_purge(); return array( 'ok' => 1 ); }
	if ( 'replay' === $act ) { $l = get_option( 'nb_mock_last' ); return $l ? nb_webhook_process( $l['payload'], $l['sig'] ) : array(); }
	if ( 'badsig' === $act ) { $l = get_option( 'nb_mock_last' ); return $l ? nb_webhook_process( $l['payload'], 't=' . time() . ',v1=' . str_repeat( 'a', 64 ) ) : array(); }
	if ( 'oldsig' === $act ) { $l = get_option( 'nb_mock_last' ); if ( ! $l ) { return array(); } $t = time() - 3600; return nb_webhook_process( $l['payload'], 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $l['payload'], nb_whsec() ) ); }
	if ( 'mailfail' === $act ) { update_option( 'nordic_dev_mail_fail', empty( $p['on'] ) ? 0 : 1 ); return array( 'mailfail' => (int) get_option( 'nordic_dev_mail_fail' ) ); }
	if ( 'mails' === $act ) { return array_map( function ( $m ) { return array( 'to' => $m['to'], 's' => $m['subject'], 'fail' => $m['fail'], 'body' => mb_substr( $m['body'], 0, 600 ) ); }, array_slice( (array) get_option( 'nordic_dev_mail', array() ), 0, (int) ( $p['n'] ?? 10 ) ) ); }
	if ( 'state' === $act ) {
		$b = nb_get( 'ref', (string) ( $p['ref'] ?? '' ) );
		if ( ! $b ) { return array(); }
		$slot = $wpdb->get_row( $wpdb->prepare( 'SELECT booking_id, held_until FROM ' . nb_t( 'slots' ) . ' WHERE id=%d', $b->slot_id ), ARRAY_A );
		$v = nb_decrypt( $b->visit );
		return array( 'status' => $b->status, 'refunded' => (int) $b->refunded, 'mail' => $b->mail_state, 'pi' => (bool) $b->pi_id, 'slot' => $slot, 'visit_at' => $b->visit_at, 'visit_encrypted' => 0 === strpos( (string) $b->visit, 'v1:' ), 'visit_readable' => is_array( $v ), 'visit' => $v, 'log' => $wpdb->get_col( $wpdb->prepare( 'SELECT CONCAT(action, " ", detail) FROM ' . nb_t( 'log' ) . ' WHERE booking_id=%d ORDER BY id', $b->id ) ) );
	}
	if ( 'refs' === $act ) { return $wpdb->get_results( 'SELECT ref, status, starts_utc FROM ' . nb_t( 'bookings' ) . ' ORDER BY id DESC LIMIT 10', ARRAY_A ); }
	if ( in_array( $act, array( 'approve', 'reject', 'cancel', 'complete', 'resend', 'retry_refund' ), true ) ) {
		$b = nb_get( 'ref', (string) ( $p['ref'] ?? '' ) );
		if ( ! $b ) { return array( 'msg' => 'ukendt' ); }
		wp_set_current_user( 0 );
		return array( 'msg' => nb_do_action( $b, $act, isset( $p['amount'] ) ? (int) $p['amount'] : null ) );
	}
	return array( 'msg' => 'ukendt' );
}
