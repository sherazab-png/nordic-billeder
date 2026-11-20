<?php
/* E-mails. Ingen helbredsoplysninger i e-mails til patienten. Kun klinikmailen indeholder alle oplysninger. Fejl logges og vises i admin, og kan sendes igen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_send( $b, $kind, $to, $subject, $body ) {
	global $wpdb;
	$ok = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	if ( $b ) {
		$wpdb->update( nb_t( 'bookings' ), array( 'mail_state' => $ok ? 'ok:' . $kind : 'fejl:' . $kind ), array( 'id' => $b->id ) );
		nb_log( $b->id, $ok ? 'mail_ok' : 'mail_fejl', $kind );
	}
	return $ok;
}
function nb_visit_link( $b ) { $s = nb_settings(); return add_query_arg( array( 'nb' => 'visit', 'ref' => $b->ref ), $s['page_url'] ?: home_url( '/' ) ); }
function nb_sig() { $s = nb_settings(); return "\n\nVenlig hilsen\n" . $s['address'] . "\nTlf. " . $s['phone']; }

function nb_mail_paid( $b ) {
	$s = nb_settings();
	return nb_send( $b, 'paid', $b->email, 'Vi har modtaget din booking - ' . nb_human( $b->starts_utc ),
		"Hej " . $b->name . "\n\nTak for din booking af " . $s['product'] . " " . nb_human( $b->starts_utc ) . ".\n\n" . ( $b->gift_id ? 'Dit gavekort er registreret, og der skal ikke betales mere.' : 'Vi har modtaget din betaling.' ) . "\n\n" . ( $b->visit_at ? "En læge gennemgår nu dine svar på de supplerende spørgsmål, fordi en DEXA-scanning skal være berettiget. Du får en bekræftelse, når det er sket." : "Besvar venligst de supplerende spørgsmål, hvis du ikke allerede har gjort det. En DEXA-scanning skal være berettiget, og en læge skal godkende den, før din tid er endeligt bekræftet:\n" . nb_visit_link( $b ) ) . "\n\n" . ( $b->gift_id ? 'Kan scanningen ikke godkendes, refunderer vi beløbet til den, der købte gavekortet.' : 'Kan scanningen ikke godkendes, refunderer vi hele beløbet.' ) . ( $b->gift_id ? '' : "\n\nDin kvittering kommer i en separat e-mail fra vores betalingsudbyder Stripe." ) . "\n\nBookingnummer: " . $b->ref . nb_sig() );
}
/* Samlet mail til klinikken med alle oplysninger, så tiden kan overføres til klinikkens kalender.
   Indeholder CPR og helbredssvar: sendes via sitets Microsoft 365-forbindelse (TLS) til klinikkens egen postkasse,
   og FluentSMTP må ikke gemme en kopi af denne mail i WordPress. */
function nb_mail_clinic_new( $b ) {
	global $wpdb;
	$s = nb_settings();
	$f = array( 'kost' => 'Kostomlægning eller vægttab', 'sport' => 'Optimering af sportspræstation', 'opf' => 'Opfølgning efter sygdom eller behandling', 'andet' => 'Andet' );
	$v = $b->visit_at ? nb_decrypt( $b->visit ) : null;
	$c = nb_decrypt( $b->cpr );
	$dur = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT dur_min FROM ' . nb_t( 'slots' ) . ' WHERE id=%d', $b->slot_id ) ) ?: 20;
	$L = array();
	$L[] = 'NY ONLINE BOOKING - ' . mb_strtoupper( $s['product'] );
	$L[] = '';
	$L[] = 'TID';
	$L[] = ucfirst( nb_human( $b->starts_utc ) ) . ' (' . $dur . ' min.)';
	$L[] = '';
	$L[] = 'PATIENT';
	$L[] = 'Navn: ' . $b->name;
	$L[] = 'Fødselsdato (CPR): ' . ( $c['cpr'] ?? '-' );
	$L[] = 'Telefon: ' . $b->phone;
	$L[] = 'E-mail: ' . $b->email;
	$L[] = '';
	$L[] = 'BETALING';
	$gift = $b->gift_id ? nb_gift_get( 'id', $b->gift_id ) : null;
	if ( $gift ) {
		$L[] = 'Betalt med gavekort ****-' . $gift->code_last4 . ' (' . number_format( $gift->amount / 100, 2, ',', '.' ) . ' kr., købt ' . nb_local( $gift->paid_at, 'd.m.Y' ) . ' af ' . $gift->buyer_name . ', ' . $gift->buyer_email . ')';
		$L[] = 'Betalings-id for gavekortet (Stripe): ' . ( $gift->pi_id ?: '-' );
	} else {
		$L[] = 'Godkendt: ' . number_format( $b->amount / 100, 2, ',', '.' ) . ' kr. ' . ( $b->paid_at ? nb_local( $b->paid_at, 'd.m.Y H:i' ) : '' );
		$L[] = 'Betalings-id (Stripe): ' . ( $b->pi_id ?: '-' );
		$L[] = 'Kvittering: ' . ( $b->receipt_url ?: 'sendes af Stripe til patienten' );
	}
	$L[] = 'Bookingnummer: ' . $b->ref;
	$L[] = '';
	$L[] = 'SUPPLERENDE SPØRGSMÅL';
	if ( $v ) {
		$L[] = 'Formål: ' . ( $f[ $v['formaal'] ] ?? $v['formaal'] );
		$L[] = 'Gravid: ' . $v['gravid'];
		$L[] = 'Over 197 cm eller 159 kg: ' . $v['graense'];
		$L[] = 'Bemærkning: ' . ( '' !== $v['note'] ? $v['note'] : '(ingen)' );
		if ( 'ja' === $v['gravid'] || 'ja' === $v['graense'] ) { $L[] = ''; $L[] = 'OBS: Svarene kræver lægefaglig opfølgning før scanningen.'; }
	} else {
		$L[] = 'Ikke besvaret endnu. Patienten har fået link og påmindelse. Svarene sendes i en ny mail, når de kommer.';
	}
	$L[] = '';
	$L[] = 'Godkend eller afvis scanningen:';
	$L[] = admin_url( 'admin.php?page=nb-bookings&b=' . (int) $b->id );
	$L[] = '';
	$L[] = 'Kalenderinvitation er vedhæftet (.ics).';
	$L[] = 'FORTROLIGT: indeholder CPR- og helbredsoplysninger. Må ikke videresendes uden for klinikken.';
	// Kalenderfil (uden helbredsoplysninger).
	$st = gmdate( 'Ymd\THis\Z', strtotime( $b->starts_utc . ' UTC' ) ); $en = gmdate( 'Ymd\THis\Z', strtotime( $b->starts_utc . ' UTC' ) + $dur * 60 );
	$esc = function ( $x ) { return addcslashes( str_replace( "\n", '\n', $x ), ',;' ); };
	$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Nordic DEXA Scan//Booking//DA\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:" . $b->ref . "@nordicdexascan.dk\r\nDTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\nDTSTART:$st\r\nDTEND:$en\r\nSUMMARY:" . $esc( 'DEXA Basis - ' . $b->name ) . "\r\nLOCATION:" . $esc( str_replace( "\n", ', ', $s['address'] ) ) . "\r\nDESCRIPTION:" . $esc( 'Online booking ' . $b->ref . '. Tlf. ' . $b->phone ) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	$tmp = trailingslashit( get_temp_dir() ) . 'dexa-booking-' . $b->ref . '.ics';
	file_put_contents( $tmp, $ics );
	add_filter( 'fluentmail_will_log_email', '__return_false', 999 );
	$ok = wp_mail( $s['clinic_email'], 'Ny online booking ' . nb_local( $b->starts_utc, 'd.m.Y H:i' ) . ' - ' . $b->name, implode( "\n", $L ), array( 'Content-Type: text/plain; charset=UTF-8' ), array( $tmp ) );
	remove_filter( 'fluentmail_will_log_email', '__return_false', 999 );
	@unlink( $tmp );
	$wpdb->update( nb_t( 'bookings' ), array( 'clinic_mail_at' => nb_now() ), array( 'id' => $b->id ) );
	nb_log( $b->id, $ok ? 'mail_ok' : 'mail_fejl', 'klinik' . ( $v ? '' : ' (uden svar)' ) );
	return $ok;
}
/* Svar modtaget efter at klinikmailen allerede er sendt uden svar. */
function nb_mail_clinic_answers( $b ) {
	$s = nb_settings();
	$f = array( 'kost' => 'Kostomlægning eller vægttab', 'sport' => 'Optimering af sportspræstation', 'opf' => 'Opfølgning efter sygdom eller behandling', 'andet' => 'Andet' );
	$v = nb_decrypt( $b->visit ); if ( ! $v ) { return false; }
	add_filter( 'fluentmail_will_log_email', '__return_false', 999 );
	$ok = wp_mail( $s['clinic_email'], 'Svar modtaget: booking ' . nb_local( $b->starts_utc, 'd.m.Y H:i' ) . ' - ' . $b->name,
		"SUPPLERENDE SPØRGSMÅL\nFormål: " . ( $f[ $v['formaal'] ] ?? $v['formaal'] ) . "\nGravid: " . $v['gravid'] . "\nOver 197 cm eller 159 kg: " . $v['graense'] . "\nBemærkning: " . ( '' !== $v['note'] ? $v['note'] : '(ingen)' ) . ( 'ja' === $v['gravid'] || 'ja' === $v['graense'] ? "\n\nOBS: Svarene kræver lægefaglig opfølgning før scanningen." : '' ) . "\n\nGodkend eller afvis:\n" . admin_url( 'admin.php?page=nb-bookings&b=' . (int) $b->id ) . "\n\nFORTROLIGT: indeholder helbredsoplysninger.", array( 'Content-Type: text/plain; charset=UTF-8' ) );
	remove_filter( 'fluentmail_will_log_email', '__return_false', 999 );
	nb_log( $b->id, $ok ? 'mail_ok' : 'mail_fejl', 'klinik svar' );
	return $ok;
}
function nb_mail_approved( $b ) {
	$s = nb_settings();
	return nb_send( $b, 'approved', $b->email, 'Bekræftelse: DEXA-scanning ' . nb_human( $b->starts_utc ),
		"Hej " . $b->name . "\n\nDin scanning er godkendt, og din tid er bekræftet:\n\n" . nb_human( $b->starts_utc ) . "\n" . $s['address'] . "\n\nForberedelse: der kræves ingen særlig forberedelse. Kom gerne ca. 15 minutter før din tid i let, løstsiddende tøj uden metaldele, og medbring dit sygesikringsbevis. Selve scanningen tager ca. 10 minutter.\n\nDu får din skriftlige rapport inden for 24 timer.\n\nAfbestilling: " . $s['cancel_text'] . "\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_reminder( $b ) {
	return nb_send( $b, 'reminder', $b->email, 'Påmindelse: besvar de supplerende spørgsmål før din DEXA-scanning',
		"Hej " . $b->name . "\n\nTak for din booking " . nb_human( $b->starts_utc ) . ".\n\nVi mangler stadig dine svar på de supplerende spørgsmål. En DEXA-scanning skal være berettiget, og en læge skal godkende den, før din tid er endeligt bekræftet. Det tager kun et par minutter:\n" . nb_visit_link( $b ) . "\n\nRing gerne til os, hvis du har spørgsmål.\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_rejected( $b ) {
	return nb_send( $b, 'rejected', $b->email, 'Din booking af DEXA-scanning',
		"Hej " . $b->name . "\n\nEfter lægefaglig vurdering kan vi desværre ikke tilbyde dig scanningen på det foreliggende grundlag. Ring gerne til os, hvis du har spørgsmål.\n\n" . ( $b->gift_id ? 'Bookingen var betalt med et gavekort. Beløbet refunderes til den, der købte gavekortet.' : 'Hele beløbet er refunderet. Det kan tage 5-10 bankdage, før pengene står på din konto.' ) . "\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_cancelled( $b, $amount ) {
	return nb_send( $b, 'cancelled', $b->email, 'Din booking er afbestilt',
		"Hej " . $b->name . "\n\nDin booking " . nb_human( $b->starts_utc ) . " er afbestilt.\n\n" . ( $b->gift_id ? ( $amount > 0 ? "Beløbet for gavekortet refunderes til den, der købte det.\n" : "Dit gavekort er gyldigt igen og kan bruges til en ny booking.\n" ) : ( $amount > 0 ? 'Vi har refunderet ' . number_format( $amount / 100, 2, ',', '.' ) . " kr. Det kan tage 5-10 bankdage, før pengene står på din konto.\n" : '' ) ) . "\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_conflict( $b ) {
	nb_mail_admin( 'Booking-konflikt refunderet', 'En betaling kom ind, efter at tiden var givet til en anden. Betalingen er refunderet automatisk. Booking ' . $b->ref . '.' );
	return nb_send( $b, 'conflict', $b->email, 'Din booking kunne ikke gennemføres',
		"Hej " . $b->name . "\n\nDin betaling blev gennemført, efter at reservationen af tiden var udløbet, og tiden var i mellemtiden blevet booket af en anden. Vi har derfor refunderet hele beløbet.\n\nBook gerne en ny tid, eller ring til os.\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_admin( $subject, $body ) { $s = nb_settings(); return wp_mail( $s['clinic_email'], '[Booking] ' . $subject, $body ); }

/* Gensend den sidst fejlede e-mail for en booking. */
function nb_mail_resend( $b ) {
	$kind = preg_replace( '/^(ok|fejl):/', '', (string) $b->mail_state );
	switch ( $kind ) {
		case 'paid': return nb_mail_paid( $b );
		case 'approved': return nb_mail_approved( $b );
		case 'rejected': return nb_mail_rejected( $b );
		case 'cancelled': return nb_mail_cancelled( $b, (int) $b->refunded );
		case 'conflict': return nb_mail_conflict( $b );
		case 'reminder': return nb_mail_reminder( $b );
	}
	return false;
}
