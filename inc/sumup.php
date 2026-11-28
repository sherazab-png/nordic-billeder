<?php
/* SumUp som betalingsudbyder (Hosted Checkout). Oversætter de få Stripe-kald, bookingen bruger, til SumUps API,
   så resten af bookingen er uændret. Aktiveres med define( 'NORDIC_PAY_PROVIDER', 'sumup' ) i wp-config.php.
   Nøgler ligger kun i wp-config.php: NORDIC_SUMUP_API_KEY og NORDIC_SUMUP_MERCHANT.
   Testkonto: define( 'NORDIC_SUMUP_SANDBOX', true ). Rigtig konto kræver desuden NORDIC_BOOKING_LIVE. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_provider() {
	$p = defined( 'NORDIC_PAY_PROVIDER' ) ? (string) NORDIC_PAY_PROVIDER : 'stripe';
	return in_array( $p, array( 'sumup', 'mobilepay', 'mobilepay+sumup' ), true ) ? $p : 'stripe';
}
/* Betalingsmåder, patienten kan vælge imellem: stripe, sumup (kort), mobilepay. */
function nb_methods() { $p = nb_provider(); return 'mobilepay+sumup' === $p ? array( 'mobilepay', 'sumup' ) : array( $p ); }
function nb_method_label( $m ) { return array( 'mobilepay' => 'MobilePay', 'sumup' => 'Betalingskort', 'stripe' => 'Kort eller MobilePay' )[ $m ] ?? $m; }
/* Valgt betalingsmåde for den betaling, der oprettes nu (sættes af booking- og gavekortkaldet). */
function nb_set_method( $m ) { $ms = nb_methods(); $GLOBALS['nb_pay_method'] = in_array( $m, $ms, true ) ? $m : $ms[0]; return $GLOBALS['nb_pay_method']; }
function nb_pay_name() { return implode( ' og ', array_map( function ( $m ) { return array( 'mobilepay' => 'MobilePay', 'sumup' => 'SumUp', 'stripe' => 'Stripe' )[ $m ]; }, nb_methods() ) ); }
function nb_paid_via( $pi ) { return nb_mp_is( $pi ) ? 'MobilePay' : ( 'stripe' === nb_provider() ? 'Stripe' : 'kort via SumUp' ); }
function nb_pay_hint() { $p = nb_provider(); return 'sumup' === $p ? 'kort' : ( 'mobilepay' === $p ? 'MobilePay' : 'kort eller MobilePay' ); }
/* Valg af betalingsmåde i formularerne (kun når der er mere end én). */
function nb_method_picker() {
	$ms = nb_methods(); if ( count( $ms ) < 2 ) { return ''; }
	$h = '<fieldset class="nbd-pm"><legend>Vælg betaling</legend>';
	foreach ( $ms as $i => $m ) { $h .= '<label class="opt"><input type="radio" name="nbpm" value="' . esc_attr( $m ) . '"' . ( 0 === $i ? ' checked' : '' ) . '> <span>' . esc_html( nb_method_label( $m ) ) . '</span></label>'; }
	return $h . '</fieldset>';
}
function nb_su_key() { return defined( 'NORDIC_SUMUP_API_KEY' ) ? (string) NORDIC_SUMUP_API_KEY : ''; }
function nb_su_mc() { return defined( 'NORDIC_SUMUP_MERCHANT' ) ? (string) NORDIC_SUMUP_MERCHANT : ''; }
function nb_su_sandbox() { return defined( 'NORDIC_SUMUP_SANDBOX' ) && NORDIC_SUMUP_SANDBOX; }
function nb_pay_ready() {
	foreach ( nb_methods() as $m ) { if ( 'sumup' === $m && ! nb_su_ready() ) { return false; } if ( 'mobilepay' === $m && ! nb_mp_ready() ) { return false; } }
	return true;
}
function nb_su_ready() {
	if ( '' === nb_su_key() || '' === nb_su_mc() || ! nb_key() ) { return false; }
	return nb_su_sandbox() || ( defined( 'NORDIC_BOOKING_LIVE' ) && NORDIC_BOOKING_LIVE );
}
/* Hemmelig del af webhook-adressen, så tilfældige kald afvises hurtigt. Selve status hentes altid fra SumUp. */
function nb_su_hook_token() { return substr( hash_hmac( 'sha256', 'nb-sumup-hook', wp_salt( 'auth' ) ), 0, 32 ); }
function nb_su_hook_url() { return add_query_arg( 't', nb_su_hook_token(), rest_url( 'nordic-booking/v1/sumup' ) ); }

function nb_su_req( $method, $path, $body = null ) {
	if ( '' === nb_su_key() ) { return new WP_Error( 'nb_nokey', 'SumUp-nøgle mangler' ); }
	$args = array( 'method' => $method, 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . nb_su_key(), 'Accept' => 'application/json' ) );
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); $args['headers']['Content-Type'] = 'application/json'; }
	$r = wp_remote_request( 'https://api.sumup.com/' . $path, $args );
	if ( is_wp_error( $r ) ) { return $r; }
	$code = (int) wp_remote_retrieve_response_code( $r );
	$raw = wp_remote_retrieve_body( $r );
	$j = '' === $raw ? array() : json_decode( $raw, true );
	if ( $code >= 300 ) {
		$msg = is_array( $j ) ? ( $j['detail'] ?? ( $j['message'] ?? ( $j['error_code'] ?? ( $j[0]['message'] ?? 'ukendt fejl' ) ) ) ) : 'ukendt fejl';
		return new WP_Error( 'nb_sumup', 'SumUp ' . $code . ': ' . $msg, array( 'code' => $code, 'body' => $j ) );
	}
	return is_array( $j ) ? $j : array();
}

/* SumUp-checkout -> samme form som en Stripe Checkout-session, så resten af koden kan bruge den. */
function nb_su_to_cs( array $co ) {
	$st = (string) ( $co['status'] ?? '' );
	$txn = (string) ( $co['transaction_id'] ?? '' ); $code = (string) ( $co['transaction_code'] ?? '' );
	foreach ( (array) ( $co['transactions'] ?? array() ) as $t ) {
		if ( 'SUCCESSFUL' === ( $t['status'] ?? '' ) ) { $txn = $txn ?: (string) ( $t['id'] ?? '' ); $code = $code ?: (string) ( $t['transaction_code'] ?? '' ); }
	}
	return array(
		'id'                  => (string) ( $co['id'] ?? '' ),
		'object'              => 'checkout.session',
		'provider'            => 'sumup',
		'url'                 => (string) ( $co['hosted_checkout_url'] ?? '' ),
		// FAILED betyder kun, at seneste forsøg fejlede. Patienten kan prøve igen, indtil tiden udløber.
		'status'              => 'PAID' === $st ? 'complete' : ( 'EXPIRED' === $st ? 'expired' : 'open' ),
		'payment_status'      => 'PAID' === $st ? 'paid' : 'unpaid',
		'sumup_status'        => $st,
		'client_reference_id' => (string) ( $co['checkout_reference'] ?? '' ),
		'metadata'            => array( 'nb_ref' => (string) ( $co['checkout_reference'] ?? '' ) ),
		'payment_intent'      => $txn,
		'transaction_code'    => $code,
		'amount_total'        => (int) round( (float) ( $co['amount'] ?? 0 ) * 100 ),
	);
}

/* Oversætter de Stripe-kald, bookingen bruger. */
function nb_sumup_compat( $method, $path, $p ) {
	if ( 'POST' === $method && 'checkout/sessions' === $path ) {
		$ref = (string) $p['client_reference_id'];
		$li = $p['line_items'][0]['price_data'];
		$body = array(
			'checkout_reference' => $ref, // Kun bookingens tilfældige reference. Intet navn, ingen helbredsoplysninger.
			'amount'             => round( (int) $li['unit_amount'] / 100, 2 ),
			'currency'           => strtoupper( (string) $li['currency'] ),
			'merchant_code'      => nb_su_mc(),
			'description'        => (string) $li['product_data']['name'],
			'return_url'         => nb_su_hook_url(),
			'redirect_url'       => (string) $p['success_url'],
			'valid_until'        => gmdate( 'Y-m-d\TH:i:s\Z', (int) $p['expires_at'] ),
			'hosted_checkout'    => array( 'enabled' => true ),
		);
		$co = nb_su_req( 'POST', 'v0.1/checkouts', $body );
		if ( is_wp_error( $co ) && 409 === (int) ( $co->get_error_data()['code'] ?? 0 ) ) {
			// Findes allerede (gentaget kald): hent den eksisterende.
			$l = nb_su_req( 'GET', 'v0.1/checkouts?checkout_reference=' . rawurlencode( $ref ) );
			$co = ( ! is_wp_error( $l ) && ! empty( $l[0] ) ) ? $l[0] : $co;
		}
		return is_wp_error( $co ) ? $co : nb_su_to_cs( $co );
	}
	if ( preg_match( '#^checkout/sessions/([^/]+)(/expire)?$#', $path, $x ) ) {
		$id = rawurldecode( $x[1] );
		$co = nb_su_req( empty( $x[2] ) ? 'GET' : 'DELETE', 'v0.1/checkouts/' . rawurlencode( $id ) );
		return is_wp_error( $co ) ? $co : nb_su_to_cs( $co );
	}
	if ( 'GET' === $method && 0 === strpos( $path, 'payment_intents/' ) ) {
		return array( 'id' => rawurldecode( substr( $path, 16 ) ) ); // SumUp har ikke et kvitteringslink at hente her.
	}
	if ( 'POST' === $method && 'refunds' === $path ) {
		$txn = (string) $p['payment_intent'];
		$body = isset( $p['amount'] ) ? array( 'amount' => (int) $p['amount'] ) : new stdClass();
		$r = nb_su_req( 'POST', 'v1.0/merchants/' . rawurlencode( nb_su_mc() ) . '/payments/' . rawurlencode( $txn ) . '/refunds', $body );
		if ( is_wp_error( $r ) ) { return $r; }
		// SumUp sender ingen refusions-webhook. Ved gavekort markeres refusionen i gift.php, ved booking i nb_refund().
		return array( 'id' => 'sumup-refund-' . substr( $txn, 0, 8 ) . '-' . time() );
	}
	return new WP_Error( 'nb_sumup', 'SumUp: ukendt kald ' . $method . ' ' . $path );
}

/* Webhook (return_url): SumUp sender kun checkoutens id. Status hentes altid fra SumUp, så falske kald ikke kan markere noget som betalt. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'nordic-booking/v1', '/sumup', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'nb_rest_sumup_hook' ) );
} );
function nb_rest_sumup_hook( WP_REST_Request $r ) {
	if ( ! in_array( 'sumup', nb_methods(), true ) || ! hash_equals( nb_su_hook_token(), (string) $r->get_param( 't' ) ) ) { return new WP_REST_Response( array( 'result' => 'afvist' ), 403 ); }
	$j = json_decode( $r->get_body(), true );
	$id = is_array( $j ) ? (string) ( $j['id'] ?? ( $j['payload']['checkout_id'] ?? '' ) ) : '';
	if ( '' === $id || ! preg_match( '/^[A-Za-z0-9-]{8,64}$/', $id ) ) { return new WP_REST_Response( array( 'result' => 'ugyldig' ), 400 ); }
	list( $code, $msg ) = nb_sumup_sync( $id );
	return new WP_REST_Response( array( 'result' => $msg ), $code );
}
/* Henter checkouten og behandler den som den tilsvarende Stripe-hændelse. Idempotent via hændelsestabellen. */
function nb_sumup_sync( $checkout_id ) {
	global $wpdb;
	$cs = nb_stripe( 'GET', 'checkout/sessions/' . rawurlencode( $checkout_id ) );
	if ( is_wp_error( $cs ) ) { nb_log( null, 'webhook_error', $cs->get_error_message() ); return array( 500, 'fejl' ); }
	if ( 'paid' === $cs['payment_status'] ) { $type = 'checkout.session.completed'; }
	elseif ( 'expired' === $cs['status'] ) { $type = 'checkout.session.expired'; }
	else { return array( 200, 'ingen ændring' ); }
	$evid = 'su_' . $cs['id'] . '_' . $cs['sumup_status'];
	$ins = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . nb_t( 'events' ) . ' (id, type, received) VALUES (%s, %s, %s)', $evid, $type, nb_now() ) );
	if ( 1 !== (int) $ins ) { return array( 200, 'allerede behandlet' ); }
	try {
		nb_handle_event( array( 'id' => $evid, 'type' => $type, 'data' => array( 'object' => $cs ) ) );
	} catch ( Throwable $e ) {
		$wpdb->delete( nb_t( 'events' ), array( 'id' => $evid ) );
		nb_log( null, 'webhook_error', $type . ': ' . $e->getMessage() );
		return array( 500, 'fejl' );
	}
	return array( 200, 'ok' );
}

/* Patienten kommer tilbage via "Tilbage til butikken" (redirect_url): synkroniser med det samme i stedet for at vente på webhooken. */
add_action( 'template_redirect', function () {
	if ( 'stripe' === nb_provider() || nb_mock() || empty( $_GET['ref'] ) || ! ( isset( $_GET['nb'] ) || isset( $_GET['gk'] ) ) ) { return; }
	$ref = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
	$b = isset( $_GET['nb'] ) ? nb_get( 'ref', $ref ) : null;
	$g = isset( $_GET['gk'] ) && function_exists( 'nb_gift_get' ) ? nb_gift_get( 'ref', $ref ) : null;
	if ( $b && 'hold' === $b->status && $b->session_id ) { nb_sumup_sync( $b->session_id ); }
	if ( $g && 'pending' === $g->status && ! empty( $g->session_id ) ) { nb_sumup_sync( $g->session_id ); }
}, 5 );
