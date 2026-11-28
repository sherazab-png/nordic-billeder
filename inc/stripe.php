<?php
/* Stripe: API-kald, webhook-verifikation og hændelser. Testtilstand (mock) uden nøgler kun på testmiljø. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_secret() { return defined( 'NORDIC_STRIPE_SECRET' ) ? (string) NORDIC_STRIPE_SECRET : ''; }
function nb_whsec() { return defined( 'NORDIC_STRIPE_WEBHOOK_SECRET' ) ? (string) NORDIC_STRIPE_WEBHOOK_SECRET : ( nb_mock() ? 'whsec_mock_' . wp_hash( 'nb-mock' ) : '' ); }
function nb_mock() { return defined( 'NORDIC_BOOKING_MOCK' ) && NORDIC_BOOKING_MOCK && '' === nb_secret() && '' === nb_su_key() && ! nb_mp_has_keys() && 'production' !== wp_get_environment_type(); }
function nb_live_key() { if ( 'stripe' !== nb_provider() ) { $l = false; foreach ( nb_methods() as $m ) { if ( 'sumup' === $m ) { $l = $l || ( '' !== nb_su_key() && ! nb_su_sandbox() ); } if ( 'mobilepay' === $m ) { $l = $l || ( nb_mp_has_keys() && ! nb_mp_test() ); } } return $l; } return 0 === strpos( nb_secret(), 'sk_live_' ) || 0 === strpos( nb_secret(), 'rk_live_' ); }
/* Betaling er kun mulig med testnøgle, mock på testmiljø, eller livenøgle når NORDIC_BOOKING_LIVE er sat bevidst. */
function nb_payments_ready() {
	if ( nb_mock() ) { return true; }
	if ( 'stripe' !== nb_provider() ) { return nb_pay_ready(); }
	if ( '' === nb_secret() || '' === nb_whsec() ) { return false; }
	if ( nb_live_key() && ! ( defined( 'NORDIC_BOOKING_LIVE' ) && NORDIC_BOOKING_LIVE ) ) { return false; }
	return (bool) nb_key();
}

function nb_stripe( $method, $path, $params = array(), $idem = null ) {
	if ( nb_mock() ) { return nb_mock_api( $method, $path, $params ); }
	if ( 'stripe' !== nb_provider() ) {
		// Flere udbydere: eksisterende betalinger genkendes på id'et (mp_...), nye på den valgte betalingsmåde.
		$id = (string) ( $params['payment_intent'] ?? '' );
		if ( '' === $id && preg_match( '#^checkout/sessions/([^/]+)#', $path, $x ) ) { $id = rawurldecode( $x[1] ); }
		if ( '' === $id && 0 === strpos( $path, 'payment_intents/' ) ) { $id = rawurldecode( substr( $path, 16 ) ); }
		if ( '' !== $id ) { return nb_mp_is( $id ) ? nb_mp_compat( $method, $path, $params ) : nb_sumup_compat( $method, $path, $params ); }
		$m = $GLOBALS['nb_pay_method'] ?? nb_methods()[0];
		return 'mobilepay' === $m ? nb_mp_compat( $method, $path, $params ) : nb_sumup_compat( $method, $path, $params );
	}
	if ( '' === nb_secret() ) { return new WP_Error( 'nb_nokey', 'Stripe-nøgle mangler' ); }
	$args = array( 'method' => $method, 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . nb_secret() ) );
	$url = 'https://api.stripe.com/v1/' . $path;
	if ( 'GET' === $method ) { if ( $params ) { $url .= '?' . http_build_query( $params ); } }
	else { $args['body'] = http_build_query( $params ); $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded'; }
	if ( $idem ) { $args['headers']['Idempotency-Key'] = $idem; }
	$r = wp_remote_request( $url, $args );
	if ( is_wp_error( $r ) ) { return $r; }
	$code = wp_remote_retrieve_response_code( $r );
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 || ! is_array( $j ) ) { return new WP_Error( 'nb_stripe', 'Stripe ' . $code . ': ' . ( $j['error']['message'] ?? 'ukendt fejl' ) ); }
	return $j;
}

function nb_create_checkout( $b, $return_url ) {
	$s = nb_settings();
	$p = array(
		'mode'                => 'payment',
		'client_reference_id' => $b->ref,
		'customer_email'      => $b->email,
		'locale'              => 'da',
		'expires_at'          => time() + max( 30, (int) $s['hold_min'] ) * 60 + 5,
		'success_url'         => add_query_arg( array( 'nb' => 'ok', 'ref' => $b->ref ), $return_url ),
		'cancel_url'          => add_query_arg( array( 'nb' => 'cancel', 'ref' => $b->ref ), $return_url ),
		'line_items'          => array( array( 'quantity' => 1, 'price_data' => array( 'currency' => $s['currency'], 'unit_amount' => (int) $s['price_ore'], 'product_data' => array( 'name' => $s['product'] ) ) ) ),
		// Kun booking-referencen sendes. Ingen helbredsoplysninger, intet navn.
		'metadata'            => array( 'nb_ref' => $b->ref ),
		'payment_intent_data' => array( 'metadata' => array( 'nb_ref' => $b->ref ), 'receipt_email' => $b->email ),
	);
	return nb_stripe( 'POST', 'checkout/sessions', $p, 'nb-cs-' . $b->ref );
}

/* ---------- Webhook ---------- */
function nb_verify_sig( $payload, $header, $secret, $tolerance = 300 ) {
	if ( '' === $secret || '' === (string) $header ) { return false; }
	$t = null; $sigs = array();
	foreach ( explode( ',', $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) { continue; }
		if ( 't' === $kv[0] ) { $t = (int) $kv[1]; } elseif ( 'v1' === $kv[0] ) { $sigs[] = $kv[1]; }
	}
	if ( ! $t || ! $sigs || abs( time() - $t ) > $tolerance ) { return false; }
	$exp = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
	foreach ( $sigs as $s ) { if ( hash_equals( $exp, $s ) ) { return true; } }
	return false;
}

/* Returnerer array( http_code, tekst ). */
function nb_webhook_process( $payload, $sig ) {
	global $wpdb;
	if ( ! nb_verify_sig( $payload, $sig, nb_whsec() ) ) { nb_log( null, 'webhook_badsig', '' ); return array( 400, 'ugyldig signatur' ); }
	$ev = json_decode( $payload, true );
	if ( ! is_array( $ev ) || empty( $ev['id'] ) || empty( $ev['type'] ) ) { return array( 400, 'ugyldig hændelse' ); }
	// Hver hændelse behandles kun én gang.
	$ins = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . nb_t( 'events' ) . ' (id, type, received) VALUES (%s, %s, %s)', $ev['id'], $ev['type'], nb_now() ) );
	if ( 1 !== (int) $ins ) { return array( 200, 'allerede behandlet' ); }
	try {
		nb_handle_event( $ev );
	} catch ( Throwable $e ) {
		$wpdb->delete( nb_t( 'events' ), array( 'id' => $ev['id'] ) ); // Så Stripe prøver igen.
		nb_log( null, 'webhook_error', $ev['type'] . ': ' . $e->getMessage() );
		return array( 500, 'fejl' );
	}
	return array( 200, 'ok' );
}

function nb_handle_event( array $ev ) {
	$o = $ev['data']['object'] ?? array();
	switch ( $ev['type'] ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
			if ( 0 === strpos( (string) ( $o['client_reference_id'] ?? '' ), 'g_' ) ) { nb_gift_paid( substr( $o['client_reference_id'], 2 ), $o ); return; }
			$b = nb_get( 'ref', $o['client_reference_id'] ?? ( $o['metadata']['nb_ref'] ?? '' ) );
			if ( ! $b ) { nb_log( null, 'webhook_unknown', $ev['type'] ); return; }
			if ( ( $o['payment_status'] ?? '' ) === 'paid' ) { nb_mark_paid( $b, $o ); }
			else { nb_log( $b->id, 'webhook_unpaid', (string) ( $o['payment_status'] ?? '' ) ); }
			return;
		case 'checkout.session.expired':
		case 'checkout.session.async_payment_failed':
			$b = nb_get( 'ref', $o['client_reference_id'] ?? ( $o['metadata']['nb_ref'] ?? '' ) );
			if ( $b ) { nb_expire( $b, $ev['type'] ); }
			return;
		case 'charge.refunded':
			$b = nb_get( 'pi_id', (string) ( $o['payment_intent'] ?? '' ) );
			if ( ! $b ) {
				// Ubrugt gavekort refunderet direkte i Stripe.
				$g = nb_gift_get( 'pi_id', (string) ( $o['payment_intent'] ?? '' ) );
				if ( $g && (int) ( $o['amount_refunded'] ?? 0 ) >= (int) $g->amount ) { nb_gift_mark_refunded( $g->id, 'stripe' ); }
				return;
			}
			if ( $b->gift_id && (int) ( $o['amount_refunded'] ?? 0 ) >= (int) $b->amount ) { nb_gift_mark_refunded( $b->gift_id, 'booking' ); }
			$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'UPDATE ' . nb_t( 'bookings' ) . ' SET refunded=GREATEST(refunded, %d), updated_at=%s WHERE id=%d', (int) ( $o['amount_refunded'] ?? 0 ), nb_now(), $b->id ) );
			nb_log( $b->id, 'refunded_stripe', (string) ( $o['amount_refunded'] ?? '' ) );
			// Fuld refusion lavet direkte i Stripe: frigiv tiden og marker bookingen.
			if ( (int) ( $o['amount_refunded'] ?? 0 ) >= (int) $b->amount && in_array( $b->status, array( 'paid', 'approved' ), true ) ) {
				$nb = nb_transition( $b, array( 'paid', 'approved' ), 'refunded' );
				if ( $nb ) { nb_release_slot( $nb ); }
			}
			return;
		case 'charge.dispute.created':
			$b = nb_get( 'pi_id', (string) ( $o['payment_intent'] ?? '' ) );
			nb_log( $b ? $b->id : null, 'dispute', (string) ( $o['reason'] ?? '' ) );
			nb_mail_admin( 'Indsigelse mod betaling', 'Der er oprettet en indsigelse (dispute) mod en betaling' . ( $b ? ' for booking ' . $b->ref : '' ) . '. Se Stripe-oversigten.' );
			return;
		case 'payment_intent.payment_failed':
			nb_log( null, 'payment_failed', (string) ( $o['metadata']['nb_ref'] ?? '' ) );
			return;
	}
}

/* ---------- Testtilstand uden Stripe (kun testmiljø, ingen nøgler) ---------- */
function nb_mock_store( $set = null ) {
	if ( null !== $set ) { update_option( 'nb_mock', $set, false ); return $set; }
	return (array) get_option( 'nb_mock', array( 'cs' => array(), 'pi' => array(), 're' => array() ) );
}
function nb_mock_api( $method, $path, $p ) {
	$m = nb_mock_store();
	if ( 'POST' === $method && 'checkout/sessions' === $path ) {
		foreach ( $m['cs'] as $cs ) { if ( ( $cs['client_reference_id'] ?? '' ) === $p['client_reference_id'] ) { return $cs; } } // Idempotens.
		$id = 'cs_test_mock_' . wp_generate_password( 16, false );
		$cs = array( 'id' => $id, 'object' => 'checkout.session', 'status' => 'open', 'payment_status' => 'unpaid', 'client_reference_id' => $p['client_reference_id'], 'metadata' => $p['metadata'], 'amount_total' => $p['line_items'][0]['price_data']['unit_amount'], 'expires_at' => $p['expires_at'], 'success_url' => $p['success_url'], 'cancel_url' => $p['cancel_url'], 'payment_intent' => null,
			'url' => add_query_arg( 'cs', $id, rest_url( 'nordic-booking/v1/mock/checkout' ) ) );
		$m['cs'][ $id ] = $cs; nb_mock_store( $m ); return $cs;
	}
	if ( preg_match( '#^checkout/sessions/([^/]+)(/expire)?$#', $path, $x ) ) {
		$id = rawurldecode( $x[1] );
		if ( empty( $m['cs'][ $id ] ) ) { return new WP_Error( 'nb_stripe', 'Stripe 404: No such checkout.session' ); }
		if ( ! empty( $x[2] ) && 'open' === $m['cs'][ $id ]['status'] ) { $m['cs'][ $id ]['status'] = 'expired'; nb_mock_store( $m ); }
		return $m['cs'][ $id ];
	}
	if ( 'GET' === $method && 0 === strpos( $path, 'payment_intents/' ) ) {
		$id = rawurldecode( substr( $path, 16 ) );
		return array( 'id' => $id, 'latest_charge' => array( 'receipt_url' => 'https://pay.stripe.com/receipts/test-mock/' . $id ) );
	}
	if ( 'POST' === $method && 'refunds' === $path ) {
		$pi = $p['payment_intent'];
		if ( empty( $m['pi'][ $pi ] ) ) { return new WP_Error( 'nb_stripe', 'Stripe 404: No such payment_intent' ); }
		$left = $m['pi'][ $pi ]['amount'] - $m['pi'][ $pi ]['refunded'];
		$p['amount'] = $p['amount'] ?? $left;
		if ( $p['amount'] > $left ) { return new WP_Error( 'nb_stripe', 'Stripe 400: Refund exceeds remaining amount' ); }
		$m['pi'][ $pi ]['refunded'] += $p['amount'];
		$rid = 're_mock_' . wp_generate_password( 12, false );
		$m['re'][ $rid ] = array( 'id' => $rid, 'amount' => $p['amount'], 'payment_intent' => $pi );
		nb_mock_store( $m );
		nb_mock_emit( 'charge.refunded', array( 'object' => 'charge', 'payment_intent' => $pi, 'amount' => $m['pi'][ $pi ]['amount'], 'amount_refunded' => $m['pi'][ $pi ]['refunded'] ) );
		return $m['re'][ $rid ];
	}
	return new WP_Error( 'nb_stripe', 'mock: ukendt kald ' . $method . ' ' . $path );
}
/* Sender en signeret hændelse gennem den rigtige webhook-behandling (samme kode som i drift). */
function nb_mock_emit( $type, array $obj, $id = null ) {
	$ev = array( 'id' => $id ?: 'evt_mock_' . wp_generate_password( 16, false ), 'object' => 'event', 'type' => $type, 'created' => time(), 'data' => array( 'object' => $obj ) );
	$payload = wp_json_encode( $ev );
	$t = time();
	$sig = 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $payload, nb_whsec() );
	update_option( 'nb_mock_last', array( 'payload' => $payload, 'sig' => $sig ), false );
	return nb_webhook_process( $payload, $sig );
}
function nb_mock_pay( $cs_id ) {
	$m = nb_mock_store();
	if ( empty( $m['cs'][ $cs_id ] ) || 'open' !== $m['cs'][ $cs_id ]['status'] ) { return false; }
	if ( $m['cs'][ $cs_id ]['expires_at'] < time() ) { $m['cs'][ $cs_id ]['status'] = 'expired'; nb_mock_store( $m ); return false; }
	$pi = 'pi_mock_' . wp_generate_password( 16, false );
	$m['pi'][ $pi ] = array( 'id' => $pi, 'amount' => $m['cs'][ $cs_id ]['amount_total'], 'refunded' => 0 );
	$m['cs'][ $cs_id ]['status'] = 'complete'; $m['cs'][ $cs_id ]['payment_status'] = 'paid'; $m['cs'][ $cs_id ]['payment_intent'] = $pi;
	nb_mock_store( $m );
	return $m['cs'][ $cs_id ];
}
