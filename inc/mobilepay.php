<?php
/* Vipps MobilePay ePayment. Oversætter bookingens Stripe-kald til MobilePays API, ligesom sumup.php.
   Aktiveres med define( 'NORDIC_PAY_PROVIDER', 'mobilepay+sumup' ) (patienten vælger MobilePay eller kort)
   eller 'mobilepay' (kun MobilePay). Nøgler kun i wp-config.php:
   NORDIC_MP_CLIENT_ID, NORDIC_MP_CLIENT_SECRET, NORDIC_MP_SUBSCRIPTION_KEY, NORDIC_MP_MSN.
   Testmiljø: define( 'NORDIC_MP_TEST', true ). Rigtig aftale kræver desuden NORDIC_BOOKING_LIVE.
   Betalingen reserveres i appen og trækkes (capture) med det samme, så forløbet er som ved kort. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_mp_cfg( $k ) { $c = 'NORDIC_MP_' . $k; return defined( $c ) ? (string) constant( $c ) : ''; }
function nb_mp_test() { return defined( 'NORDIC_MP_TEST' ) && NORDIC_MP_TEST; }
function nb_mp_base() { return nb_mp_test() ? 'https://apitest.vipps.no' : 'https://api.vipps.no'; }
function nb_mp_has_keys() { return '' !== nb_mp_cfg( 'CLIENT_ID' ) && '' !== nb_mp_cfg( 'CLIENT_SECRET' ) && '' !== nb_mp_cfg( 'SUBSCRIPTION_KEY' ) && '' !== nb_mp_cfg( 'MSN' ); }
function nb_mp_ready() {
	if ( ! nb_mp_has_keys() || ! nb_key() ) { return false; }
	return nb_mp_test() || ( defined( 'NORDIC_BOOKING_LIVE' ) && NORDIC_BOOKING_LIVE );
}
function nb_mp_hook_token() { return substr( hash_hmac( 'sha256', 'nb-mobilepay-hook', wp_salt( 'auth' ) ), 0, 32 ); }
function nb_mp_hook_url() { return add_query_arg( 't', nb_mp_hook_token(), rest_url( 'nordic-booking/v1/mobilepay' ) ); }
/* MobilePay-reference: 8-64 tegn, kun bogstaver, tal og bindestreg. Afledt af bookingens tilfældige reference. */
function nb_mp_ref( $ref ) { return substr( 'nb-' . preg_replace( '/[^A-Za-z0-9-]/', '-', (string) $ref ), 0, 64 ); }
function nb_mp_is( $id ) { return 0 === strpos( (string) $id, 'mp_' ); }

function nb_mp_token() {
	$tk = 'nb_mp_token_' . md5( nb_mp_cfg( 'CLIENT_ID' ) . nb_mp_base() );
	$t = get_transient( $tk );
	if ( $t ) { return $t; }
	$r = wp_remote_post( nb_mp_base() . '/accesstoken/get', array( 'timeout' => 20, 'body' => '', 'headers' => array(
		'client_id' => nb_mp_cfg( 'CLIENT_ID' ), 'client_secret' => nb_mp_cfg( 'CLIENT_SECRET' ),
		'Ocp-Apim-Subscription-Key' => nb_mp_cfg( 'SUBSCRIPTION_KEY' ), 'Merchant-Serial-Number' => nb_mp_cfg( 'MSN' ),
	) ) );
	if ( is_wp_error( $r ) ) { return $r; }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['access_token'] ) ) { return new WP_Error( 'nb_mp', 'MobilePay: kunne ikke hente adgangsnøgle (' . (int) wp_remote_retrieve_response_code( $r ) . ')' ); }
	set_transient( $tk, $j['access_token'], max( 60, (int) ( $j['expires_in'] ?? 3600 ) - 120 ) );
	return $j['access_token'];
}

function nb_mp_req( $method, $path, $body = null, $idem = null ) {
	if ( ! nb_mp_has_keys() ) { return new WP_Error( 'nb_nokey', 'MobilePay-nøgler mangler' ); }
	$tok = nb_mp_token(); if ( is_wp_error( $tok ) ) { return $tok; }
	$h = array(
		'Authorization' => 'Bearer ' . $tok, 'Ocp-Apim-Subscription-Key' => nb_mp_cfg( 'SUBSCRIPTION_KEY' ), 'Merchant-Serial-Number' => nb_mp_cfg( 'MSN' ),
		'Vipps-System-Name' => 'nordic-booking', 'Vipps-System-Version' => defined( 'NB_VER' ) ? NB_VER : '0', 'Vipps-System-Plugin-Name' => 'nordic-booking', 'Vipps-System-Plugin-Version' => defined( 'NB_VER' ) ? NB_VER : '0',
		'Accept' => 'application/json',
	);
	if ( $idem ) { $h['Idempotency-Key'] = substr( preg_replace( '/[^A-Za-z0-9-]/', '-', $idem ), 0, 50 ); }
	$args = array( 'method' => $method, 'timeout' => 30, 'headers' => $h );
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); $args['headers']['Content-Type'] = 'application/json'; }
	$r = wp_remote_request( nb_mp_base() . $path, $args );
	if ( is_wp_error( $r ) ) { return $r; }
	$code = (int) wp_remote_retrieve_response_code( $r );
	$raw = wp_remote_retrieve_body( $r ); $j = '' === $raw ? array() : json_decode( $raw, true );
	if ( $code >= 300 ) {
		$msg = is_array( $j ) ? ( $j['detail'] ?? ( $j['title'] ?? ( $j['message'] ?? 'ukendt fejl' ) ) ) : 'ukendt fejl';
		return new WP_Error( 'nb_mp', 'MobilePay ' . $code . ': ' . $msg, array( 'code' => $code ) );
	}
	return is_array( $j ) ? $j : array();
}

/* MobilePay-betaling -> samme form som en Stripe Checkout-session. Reserveret beløb trækkes her (idempotent). */
function nb_mp_to_cs( array $p, $orig_ref = '' ) {
	$mref = (string) ( $p['reference'] ?? '' );
	$st = (string) ( $p['state'] ?? '' );
	$auth = (int) ( $p['aggregate']['authorizedAmount']['value'] ?? 0 );
	$cap = (int) ( $p['aggregate']['capturedAmount']['value'] ?? 0 );
	$canc = (int) ( $p['aggregate']['cancelledAmount']['value'] ?? 0 );
	if ( 'AUTHORIZED' === $st && $auth > 0 && $cap < $auth && $canc < $auth ) {
		$c = nb_mp_req( 'POST', '/epayment/v1/payments/' . rawurlencode( $mref ) . '/capture', array( 'modificationAmount' => array( 'currency' => (string) ( $p['amount']['currency'] ?? 'DKK' ), 'value' => $auth - $cap ) ), 'nb-cap-' . $mref );
		if ( is_wp_error( $c ) ) { nb_log( null, 'mp_capture_error', $c->get_error_message() ); }
		else { $cap = (int) ( $c['aggregate']['capturedAmount']['value'] ?? $auth ); }
	}
	$paid = $cap > 0 && $cap >= $auth;
	$ref = $orig_ref ?: (string) ( $p['metadata']['nb_ref'] ?? '' );
	if ( '' === $ref && function_exists( 'nb_get' ) ) {
		// Reserve: find bookingen eller gavekortet på betalingens id, hvis metadata ikke kommer med tilbage.
		$bk = nb_get( 'session_id', 'mp_' . $mref );
		if ( $bk ) { $ref = $bk->ref; }
		elseif ( function_exists( 'nb_gift_get' ) && ( $g = nb_gift_get( 'session_id', 'mp_' . $mref ) ) ) { $ref = 'g_' . $g->ref; }
	}
	return array(
		'id'                  => 'mp_' . $mref,
		'object'              => 'checkout.session',
		'provider'            => 'mobilepay',
		'url'                 => (string) ( $p['redirectUrl'] ?? '' ),
		'status'              => $paid ? 'complete' : ( in_array( $st, array( 'ABORTED', 'EXPIRED', 'TERMINATED' ), true ) ? 'expired' : 'open' ),
		'payment_status'      => $paid ? 'paid' : 'unpaid',
		'sumup_status'        => $paid ? 'CAPTURED' : $st, // bruges som hændelses-id i nb_sumup_sync()
		'client_reference_id' => $ref,
		'metadata'            => array( 'nb_ref' => $ref ),
		'payment_intent'      => 'mp_' . $mref,
		'amount_total'        => (int) ( $p['amount']['value'] ?? 0 ),
	);
}

function nb_mp_compat( $method, $path, $p ) {
	if ( 'POST' === $method && 'checkout/sessions' === $path ) {
		$ref = (string) $p['client_reference_id']; $mref = nb_mp_ref( $ref );
		$li = $p['line_items'][0]['price_data'];
		nb_mp_ensure_webhook();
		$body = array(
			'amount'             => array( 'currency' => strtoupper( (string) $li['currency'] ), 'value' => (int) $li['unit_amount'] ),
			'paymentMethod'      => array( 'type' => 'WALLET' ),
			'reference'          => $mref,
			'returnUrl'          => (string) $p['success_url'],
			'userFlow'           => 'WEB_REDIRECT',
			'paymentDescription' => mb_substr( (string) $li['product_data']['name'], 0, 100 ),
			'metadata'           => array( 'nb_ref' => $ref ), // Kun bookingens tilfældige reference. Intet navn, ingen helbredsoplysninger.
		);
		$r = nb_mp_req( 'POST', '/epayment/v1/payments', $body, 'nb-cs-' . $mref );
		if ( is_wp_error( $r ) ) { return $r; }
		return array( 'id' => 'mp_' . $mref, 'object' => 'checkout.session', 'provider' => 'mobilepay', 'url' => (string) ( $r['redirectUrl'] ?? '' ), 'status' => 'open', 'payment_status' => 'unpaid', 'client_reference_id' => $ref, 'payment_intent' => 'mp_' . $mref );
	}
	if ( preg_match( '#^checkout/sessions/([^/]+)(/expire)?$#', $path, $x ) ) {
		$mref = substr( rawurldecode( $x[1] ), 3 );
		if ( ! empty( $x[2] ) ) {
			$c = nb_mp_req( 'POST', '/epayment/v1/payments/' . rawurlencode( $mref ) . '/cancel', new stdClass(), 'nb-exp-' . $mref );
			if ( is_wp_error( $c ) ) { return $c; } // Kalderen slår så status op.
		}
		$g = nb_mp_req( 'GET', '/epayment/v1/payments/' . rawurlencode( $mref ) );
		return is_wp_error( $g ) ? $g : nb_mp_to_cs( $g );
	}
	if ( 'GET' === $method && 0 === strpos( $path, 'payment_intents/' ) ) {
		return array( 'id' => rawurldecode( substr( $path, 16 ) ) );
	}
	if ( 'POST' === $method && 'refunds' === $path ) {
		$mref = substr( (string) $p['payment_intent'], 3 );
		$g = nb_mp_req( 'GET', '/epayment/v1/payments/' . rawurlencode( $mref ) );
		if ( is_wp_error( $g ) ) { return $g; }
		$left = (int) ( $g['aggregate']['capturedAmount']['value'] ?? 0 ) - (int) ( $g['aggregate']['refundedAmount']['value'] ?? 0 );
		$amt = isset( $p['amount'] ) ? min( (int) $p['amount'], $left ) : $left;
		if ( $amt <= 0 ) { return new WP_Error( 'nb_mp', 'MobilePay: intet at refundere' ); }
		$r = nb_mp_req( 'POST', '/epayment/v1/payments/' . rawurlencode( $mref ) . '/refund', array( 'modificationAmount' => array( 'currency' => (string) ( $g['amount']['currency'] ?? 'DKK' ), 'value' => $amt ) ), 'nb-ref-' . $mref . '-' . ( (int) ( $g['aggregate']['refundedAmount']['value'] ?? 0 ) ) . '-' . $amt );
		if ( is_wp_error( $r ) ) { return $r; }
		return array( 'id' => 'mobilepay-refund-' . substr( $mref, 0, 12 ) . '-' . time() );
	}
	return new WP_Error( 'nb_mp', 'MobilePay: ukendt kald ' . $method . ' ' . $path );
}

/* Webhook registreres én gang pr. adresse. Fejler det, virker betalingen stadig: status hentes, når patienten
   kommer tilbage, og ellers af oprydningen, før tiden frigives. */
function nb_mp_ensure_webhook() {
	$url = nb_mp_hook_url(); $o = get_option( 'nb_mp_webhook' );
	if ( is_array( $o ) && ( $o['url'] ?? '' ) === $url && ( $o['base'] ?? '' ) === nb_mp_base() ) { return; }
	if ( get_transient( 'nb_mp_wh_try' ) ) { return; }
	set_transient( 'nb_mp_wh_try', 1, HOUR_IN_SECONDS );
	$ev = array( 'epayments.payment.authorized.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1', 'epayments.payment.cancelled.v1' );
	$r = nb_mp_req( 'POST', '/webhooks/v1/webhooks', array( 'url' => $url, 'events' => $ev ) );
	if ( is_wp_error( $r ) ) { nb_log( null, 'mp_webhook_error', $r->get_error_message() ); return; }
	update_option( 'nb_mp_webhook', array( 'id' => (string) ( $r['id'] ?? '' ), 'url' => $url, 'base' => nb_mp_base() ), false ); // Hemmeligheden gemmes ikke: status hentes altid fra API'et.
	nb_log( null, 'mp_webhook', 'registreret' );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'nordic-booking/v1', '/mobilepay', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $r ) {
		if ( ! in_array( 'mobilepay', nb_methods(), true ) || ! hash_equals( nb_mp_hook_token(), (string) $r->get_param( 't' ) ) ) { return new WP_REST_Response( array( 'result' => 'afvist' ), 403 ); }
		$j = json_decode( $r->get_body(), true );
		$mref = is_array( $j ) ? (string) ( $j['reference'] ?? '' ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9-]{8,64}$/', $mref ) ) { return new WP_REST_Response( array( 'result' => 'ugyldig' ), 400 ); }
		list( $code, $msg ) = nb_sumup_sync( 'mp_' . $mref );
		return new WP_REST_Response( array( 'result' => $msg ), $code );
	} ) );
} );
