<?php
/* REST: ledige tider, reservation og betaling, status, annullering, Stripe-webhook og testtilstand. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	$ns = 'nordic-booking/v1';
	register_rest_route( $ns, '/slots', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_slots' ) );
	register_rest_route( $ns, '/hold', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_hold' ) );
	register_rest_route( $ns, '/status', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_status' ) );
	register_rest_route( $ns, '/cancel', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_cancel' ) );
	register_rest_route( $ns, '/stripe', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_webhook' ) );
	if ( nb_mock() ) {
		register_rest_route( $ns, '/mock/checkout', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_mock_page' ) );
		register_rest_route( $ns, '/mock/act', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_mock_act' ) );
	}
} );

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
	if ( ! nb_rate_ok() ) { return nb_err( 'nb_rate', 'For mange forsøg. Prøv igen om lidt, eller ring til os.', 429 ); }
	if ( ! nb_payments_ready() ) { return nb_err( 'nb_off', 'Online booking er ikke åben endnu. Ring til os på 77 34 00 36.', 503 ); }

	$v = (array) ( $p['visit'] ?? array() );
	$formaal = (string) ( $v['formaal'] ?? '' );
	if ( ! in_array( $formaal, array( 'kost', 'sport', 'opf', 'andet' ), true ) ) { return nb_err( 'nb_val', 'Vælg formålet med scanningen.', 422 ); }
	if ( 'nej' !== ( $v['gravid'] ?? '' ) ) { return nb_err( 'nb_stop', 'DEXA-scanning udføres ikke på gravide. Ring til os, hvis du har spørgsmål.', 422 ); }
	if ( 'nej' !== ( $v['graense'] ?? '' ) ) { return nb_err( 'nb_stop', 'Kontakt os, så finder vi den rigtige løsning.', 422 ); }
	$note = mb_substr( sanitize_textarea_field( (string) ( $v['note'] ?? '' ) ), 0, 1000 );
	$name = mb_substr( sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 0, 190 );
	$email = sanitize_email( (string) ( $p['email'] ?? '' ) );
	$phone = preg_replace( '/[^0-9+ ]/', '', (string) ( $p['phone'] ?? '' ) );
	if ( mb_strlen( $name ) < 2 || ! is_email( $email ) || strlen( preg_replace( '/\D/', '', $phone ) ) < 8 ) { return nb_err( 'nb_val', 'Udfyld navn, e-mail og telefonnummer.', 422 ); }
	if ( empty( $p['accept'] ) ) { return nb_err( 'nb_val', 'Du skal acceptere handelsbetingelser og privatlivspolitik.', 422 ); }
	$page = (int) ( $p['page'] ?? 0 );
	if ( ! $page || ! has_shortcode( (string) get_post_field( 'post_content', $page ), 'nordic_booking' ) ) { return nb_err( 'nb_val', 'Ugyldig side.', 400 ); }
	$slot = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . nb_t( 'slots' ) . ' WHERE id=%d', (int) ( $p['slot_id'] ?? 0 ) ) );
	if ( ! $slot ) { return nb_err( 'nb_slot', 'Tiden findes ikke længere. Vælg en anden tid.', 409 ); }

	$visit = nb_encrypt( array( 'formaal' => $formaal, 'gravid' => 'nej', 'graense' => 'nej', 'note' => $note, 'v' => 1 ) );
	if ( false === $visit ) { nb_log( null, 'crypto_error', 'nøgle mangler' ); return nb_err( 'nb_off', 'Online booking er midlertidigt lukket. Ring til os.', 503 ); }
	$s = nb_settings(); $now = nb_now();
	$ref = wp_generate_password( 24, false, false );
	$wpdb->insert( nb_t( 'bookings' ), array( 'ref' => $ref, 'slot_id' => $slot->id, 'starts_utc' => $slot->starts_utc, 'status' => 'hold', 'name' => $name, 'email' => $email, 'phone' => $phone, 'visit' => $visit, 'amount' => (int) $s['price_ore'], 'currency' => $s['currency'], 'created_at' => $now, 'updated_at' => $now ) );
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
	return array( 'status' => $b->status, 'label' => nb_status_label( $b->status ), 'when' => nb_human( $b->starts_utc ) );
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
