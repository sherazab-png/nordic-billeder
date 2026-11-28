<?php
/* Supplerende spørgsmål (visitation), version 3: samme indhold som klinikkens spørgeskema på /dexa-scanning-form/.
   Navn, e-mail, telefon og fødselsdato (CPR) er allerede givet i "Dine oplysninger" og spørges ikke igen.
   Svarene krypteres og vises kun for lægerollen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_visit_opts() {
	return array(
		'henvist' => array( 'selv' => 'Nej, jeg henvender mig selv', 'ja' => 'Ja' ),
		'gravid'  => array( 'nej' => 'Det er ikke relevant for mig / jeg er ikke gravid', 'ja' => 'Jeg er gravid' ),
		'billed'  => array( 'nej' => 'Nej', 'ja' => 'Ja' ),
		'helbred' => array(
			'knogle'  => 'Knogleskørhed (osteoporose/osteopeni): risiko, forebyggelse eller kontrol - fx knogleskørhed i familien, brud ved mindre fald, træthedsbrud eller udeblevet menstruation ved hård træning',
			'muskel'  => 'Sygdom eller behandling, der påvirker muskler, fedt eller vægt - fx muskelsvind, gigtsygdom, kræftbehandling, fedmeoperation, spiseforstyrrelse eller undervægt',
			'hormon'  => 'Hormon- eller stofskifteforhold - fx overgangsalder, stofskiftesygdom, type 2-diabetes eller forstadie, lavt testosteron eller længere tids behandling med binyrebarkhormon (fx prednisolon)',
			'vaegt'   => 'Uforklarede ændringer i vægt eller kropssammensætning, som jeg eller min behandler ønsker undersøgt',
			'andet'   => 'Andet helbredsforhold (beskriv nedenfor)',
		),
		'formaal' => array(
			'forloeb' => array( 'Udgangspunkt før et trænings- eller kostforløb', 'Beskriv dit trænings- eller kostforløb og dine mål. Hvad skal scanningen bruges til i forløbet?' ),
			'opf'     => array( 'Opfølgning på en tidligere DXA-scanning', 'Hvad har ændret sig siden din tidligere DXA-scanning, og hvordan vil du bruge det nye resultat?' ),
			'sport'   => array( 'Sport og præstationsudvikling', 'Hvilken sport dyrker du og på hvilket niveau? Beskriv, hvordan du eller din træner vil bruge resultatet i din træning, kost eller sæsonplanlægning.' ),
			'tjek'    => array( 'Sundhedstjek eller helbredsundersøgelse', 'Hvad ønsker du at få afklaret med scanningen? Beskriv eventuelle ændringer i vægt, helbred eller fysisk funktion, som har betydning for dit ønske.' ),
			'andet'   => array( 'Andet formål', 'Hvorfor ønsker du en DXA-scanning, og hvad vil du bruge resultatet til? Beskriv kort din baggrund og dit mål.' ),
		),
		'samtykke' => array(
			'info'    => 'Jeg har læst informationen om undersøgelsen og stråledosis og ønsker at få foretaget DXA-scanningen. Jeg ved, at jeg kan stille spørgsmål og fortryde når som helst.',
			'journal' => 'Jeg er orienteret om, at klinikken gemmer mine oplysninger, herunder CPR-nr. og helbredsoplysninger, i min patientjournal, som loven kræver.',
			'rigtig'  => 'Jeg bekræfter, at mine svar er rigtige efter bedste overbevisning.',
		),
	);
}

function nb_visit_form_html() {
	$o = nb_visit_opts();
	$radio = function ( $name, $opts ) { $h = ''; foreach ( $opts as $k => $l ) { $h .= '<label class="opt"><input type="radio" name="' . $name . '" value="' . esc_attr( $k ) . '"> <span>' . esc_html( $l ) . '</span></label>'; } return $h; };
	$check = function ( $name, $k, $l ) { return '<label class="opt"><input type="checkbox" name="' . $name . '" value="' . esc_attr( $k ) . '"> <span>' . esc_html( $l ) . '</span></label>'; };
	$h = '<div class="nbd-info" style="background:var(--bg2)"><b>Om undersøgelsen</b><br>En DXA-scanning er en hurtig og skånsom undersøgelse, der måler kroppens knoglemasse, fedtmasse og muskelmasse ved hjælp af en meget lav dosis røntgenstråling.<br><br>På vores GE Lunar Prodigy-scanner er strålebelastningen særdeles lav. En DXA-scanning indebærer typisk en effektiv stråledosis i størrelsesordenen 0,003-0,010 mSv, afhængigt af undersøgelsestype og scanningsprotokol. Til sammenligning modtager vi omkring 0,008 mSv dagligt fra naturlig baggrundsstråling.<br><br>Selvom strålebelastningen er meget lav, foretager vi altid en individuel sundhedsfaglig vurdering for at sikre, at undersøgelsen er relevant og berettiget for dig.</div>';
	$h .= '<h3>1. Om dig</h3><div class="nbd-two">'
		. '<div class="fld"><label for="nbv-hoejde">Højde (cm)</label><input id="nbv-hoejde" type="number" inputmode="numeric" min="100" max="230" step="1"><span class="nbd-hint">Angiv din aktuelle højde i centimeter.</span></div>'
		. '<div class="fld"><label for="nbv-vaegt">Vægt (kg)</label><input id="nbv-vaegt" type="number" inputmode="decimal" min="30" max="300" step="0.1"><span class="nbd-hint">Angiv din aktuelle vægt i kilogram.</span></div></div>'
		. '<div class="nbd-stop" data-stop="hv" hidden>Scanneren giver de mest nøjagtige resultater op til 197 cm og 159 kg. Lægen kontakter dig om den rigtige løsning.</div>'
		. '<fieldset><legend>Er du henvist af en læge eller anden behandler?</legend>' . $radio( 'nbv_henvist', $o['henvist'] ) . '</fieldset>'
		. '<div class="fld" data-if="henvist" hidden><label for="nbv-henviser">Hvem har henvist dig? (navn og klinik/afdeling)</label><input id="nbv-henviser" type="text" maxlength="200"></div>';
	$h .= '<h3>2. Sikkerhed</h3>'
		. '<fieldset><legend>Graviditet</legend>' . $radio( 'nbv_gravid', $o['gravid'] ) . '<div class="nbd-stop" data-stop="g" hidden>Kontakt venligst klinikken før din tid. Scanningen kan ikke gennemføres under graviditet.</div></fieldset>'
		. '<fieldset><legend>Har du fået lavet DXA-scanning, røntgen eller CT-scanning inden for de seneste 12 måneder?</legend>' . $radio( 'nbv_billed', $o['billed'] ) . '</fieldset>'
		. '<div class="fld" data-if="billed" hidden><label for="nbv-billedtekst">Hvilken undersøgelse, og hvornår (cirka)?</label><input id="nbv-billedtekst" type="text" maxlength="200"></div>';
	$h .= '<h3>3. Helbred</h3><fieldset><legend>Sæt kryds ved det, der passer. Passer intet, så gå videre til afsnit 4.</legend>';
	foreach ( $o['helbred'] as $k => $l ) { $h .= $check( 'nbv_helbred', $k, $l ); }
	$h .= '</fieldset><div class="fld" data-if="helbred" hidden><label for="nbv-helbredtekst">Beskriv kort: Hvilken sygdom, behandling eller hvilke symptomer drejer det sig om, og hvem har anbefalet undersøgelsen (fx din egen læge)?</label><textarea id="nbv-helbredtekst" rows="3" maxlength="1000"></textarea></div>';
	$h .= '<h3>4. Sundhedstjek, træning og livsstil</h3><p class="nbd-hint">Dine svar hjælper klinikkens læge med at vurdere, om scanningen er relevant for dig.</p><fieldset><legend>Hvad er formålet med din scanning?</legend>';
	foreach ( $o['formaal'] as $k => $l ) { $h .= $check( 'nbv_formaal', $k, $l[0] ); }
	$h .= '</fieldset>';
	foreach ( $o['formaal'] as $k => $l ) { $h .= '<div class="fld" data-if="f-' . $k . '" hidden><label for="nbv-f-' . $k . '">' . esc_html( $l[1] ) . '</label><textarea id="nbv-f-' . $k . '" rows="3" maxlength="1000"></textarea></div>'; }
	$h .= '<h3>5. Samtykke og bekræftelse</h3><fieldset><legend class="screen-reader-text">Samtykke</legend>';
	foreach ( $o['samtykke'] as $k => $l ) { $h .= $check( 'nbv_samtykke', $k, $l ); }
	$h .= '</fieldset><div class="fld"><label for="nbv-note">Øvrige bemærkninger (valgfrit)</label><textarea id="nbv-note" rows="3" maxlength="1000"></textarea></div>';
	return $h;
}

/* Validér og normalisér svarene fra formularen. Returnerer array eller WP_Error. */
function nb_visit_parse( array $v ) {
	$o = nb_visit_opts();
	$t = function ( $k, $n = 1000 ) use ( $v ) { return mb_substr( sanitize_textarea_field( (string) ( $v[ $k ] ?? '' ) ), 0, $n ); };
	$hoejde = (int) ( $v['hoejde'] ?? 0 ); $vaegt = round( (float) str_replace( ',', '.', (string) ( $v['vaegt'] ?? 0 ) ), 1 );
	if ( $hoejde < 100 || $hoejde > 230 || $vaegt < 30 || $vaegt > 300 ) { return new WP_Error( 'nb_val', 'Angiv højde i cm og vægt i kg.' ); }
	$one = function ( $k ) use ( $v, $o ) { $x = (string) ( $v[ $k ] ?? '' ); return isset( $o[ $k ][ $x ] ) ? $x : ''; };
	$many = function ( $k ) use ( $v, $o ) { return array_values( array_intersect( array_keys( $o[ $k ] ), array_map( 'strval', (array) ( $v[ $k ] ?? array() ) ) ) ); };
	$d = array( 'v' => 3, 'hoejde' => $hoejde, 'vaegt' => $vaegt, 'henvist' => $one( 'henvist' ), 'henviser' => $t( 'henviser', 200 ), 'gravid' => $one( 'gravid' ), 'billed' => $one( 'billed' ), 'billedtekst' => $t( 'billedtekst', 200 ),
		'helbred' => $many( 'helbred' ), 'helbredtekst' => $t( 'helbredtekst' ), 'formaal' => $many( 'formaal' ), 'formaaltekst' => array(), 'samtykke' => $many( 'samtykke' ), 'note' => $t( 'note' ) );
	if ( ! $d['henvist'] || ! $d['gravid'] || ! $d['billed'] ) { return new WP_Error( 'nb_val', 'Besvar alle spørgsmål i afsnit 1 og 2.' ); }
	if ( ! $d['formaal'] ) { return new WP_Error( 'nb_val', 'Vælg mindst ét formål med scanningen.' ); }
	foreach ( $d['formaal'] as $k ) {
		$x = mb_substr( sanitize_textarea_field( (string) ( $v['formaaltekst'][ $k ] ?? '' ) ), 0, 1000 );
		if ( mb_strlen( trim( $x ) ) < 3 ) { return new WP_Error( 'nb_val', 'Beskriv kort: ' . $o['formaal'][ $k ][0] . '.' ); }
		$d['formaaltekst'][ $k ] = $x;
	}
	if ( count( $d['samtykke'] ) !== count( $o['samtykke'] ) ) { return new WP_Error( 'nb_val', 'Sæt kryds ved alle tre punkter under samtykke og bekræftelse.' ); }
	if ( 'ja' !== $d['henvist'] ) { $d['henviser'] = ''; }
	if ( 'ja' !== $d['billed'] ) { $d['billedtekst'] = ''; }
	if ( ! $d['helbred'] ) { $d['helbredtekst'] = ''; }
	return $d;
}

/* Kræver svarene lægefaglig opfølgning før scanningen? (gælder også gamle svar, version 2) */
/* $stop = true: kun forhold, der kan forhindre scanningen (graviditet, højde/vægt). Bruges i beskeden til patienten. */
function nb_visit_flag( $v, $stop = false ) {
	if ( ! is_array( $v ) ) { return false; }
	if ( 3 === (int) ( $v['v'] ?? 0 ) ) { return 'ja' === $v['gravid'] || $v['hoejde'] > 197 || $v['vaegt'] > 159 || ( ! $stop && 'ja' === $v['billed'] ); }
	return 'ja' === ( $v['gravid'] ?? '' ) || 'ja' === ( $v['graense'] ?? '' );
}

/* Svarene som linjer [spørgsmål, svar] til lægens visning og klinikmailen. */
function nb_visit_lines( $v ) {
	if ( 3 !== (int) ( $v['v'] ?? 0 ) ) {
		$f = array( 'kost' => 'Kostomlægning eller vægttab', 'sport' => 'Optimering af sportspræstation', 'opf' => 'Opfølgning efter sygdom eller behandling', 'andet' => 'Andet' );
		return array( array( 'Formål', $f[ $v['formaal'] ?? '' ] ?? ( $v['formaal'] ?? '' ) ), array( 'Gravid', $v['gravid'] ?? '' ), array( 'Over 197 cm eller 159 kg', $v['graense'] ?? '' ), array( 'Bemærkning', '' !== ( $v['note'] ?? '' ) ? $v['note'] : '(ingen)' ) );
	}
	$o = nb_visit_opts(); $L = array();
	$L[] = array( 'Højde / vægt', $v['hoejde'] . ' cm / ' . str_replace( '.', ',', (string) $v['vaegt'] ) . ' kg' . ( $v['hoejde'] > 197 || $v['vaegt'] > 159 ? ' (over 197 cm eller 159 kg)' : '' ) );
	$L[] = array( 'Henvist', $o['henvist'][ $v['henvist'] ] . ( $v['henviser'] ? ': ' . $v['henviser'] : '' ) );
	$L[] = array( 'Graviditet', $o['gravid'][ $v['gravid'] ] );
	$L[] = array( 'DXA/røntgen/CT seneste 12 mdr.', $o['billed'][ $v['billed'] ] . ( $v['billedtekst'] ? ': ' . $v['billedtekst'] : '' ) );
	$L[] = array( 'Helbred', $v['helbred'] ? implode( '; ', array_map( function ( $k ) use ( $o ) { return trim( explode( ' - ', $o['helbred'][ $k ] )[0] ); }, $v['helbred'] ) ) : 'Intet af det nævnte' );
	if ( $v['helbredtekst'] ) { $L[] = array( 'Helbred, beskrivelse', $v['helbredtekst'] ); }
	foreach ( $v['formaal'] as $k ) { $L[] = array( 'Formål: ' . $o['formaal'][ $k ][0], $v['formaaltekst'][ $k ] ?? '' ); }
	$L[] = array( 'Samtykke og bekræftelse', count( $v['samtykke'] ) === count( $o['samtykke'] ) ? 'Alle tre punkter bekræftet' : 'Ufuldstændigt' );
	$L[] = array( 'Øvrige bemærkninger', '' !== $v['note'] ? $v['note'] : '(ingen)' );
	return $L;
}
function nb_visit_text( $v ) { return implode( "\n", array_map( function ( $l ) { return $l[0] . ': ' . $l[1]; }, nb_visit_lines( $v ) ) ) . ( nb_visit_flag( $v ) ? "\n\nOBS: Svarene kræver lægefaglig opfølgning før scanningen." : '' ); }
