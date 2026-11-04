<?php
/* Interaktive dele fra Nordic Osteoporose-udkastet, som Elementor gratis ikke kan lave: risikotesten og T-score-skalaen.
   Tekster og logik er ordret fra udkastet (Risikotest.dc.html og Skala.dc.html). Bruges som [nordic_risikotest] og [nordic_tscore]. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nordic_rt_questions() {
	return array(
		array( 'alder', 'Er du kvinde efter overgangsalderen eller mand over 70 år?', '', 'Kvinde efter overgangsalderen eller mand over 70 år', 0 ),
		array( 'brud', 'Har du haft et brud efter et mindre fald, efter du fyldte 50 år?', 'Fx et fald fra stående højde. Brud på fingre, tæer og kranie tæller ikke med.', 'Brud efter et mindre fald efter 50 år', 1 ),
		array( 'foraeldre', 'Har din mor eller far haft et hoftebrud?', '', 'Forældre med hoftebrud', 0 ),
		array( 'pred', 'Har du taget binyrebarkhormon, fx prednisolon, som tabletter i mere end 3 måneder?', 'Gælder også tidligere behandling. Spray, creme og øjendråber tæller ikke med.', 'Binyrebarkhormon i mere end 3 måneder', 1 ),
		array( 'gigt', 'Har du leddegigt eller en anden betændelsesagtig gigtsygdom?', '', 'Leddegigt eller anden betændelsesagtig gigtsygdom', 0 ),
		array( 'tidlig', 'Kom du i overgangsalderen før 45-årsalderen?', 'Mænd svarer nej.', 'Overgangsalder før 45 år', 0 ),
		array( 'hoejde', 'Er du blevet mere end 3 cm lavere, eller har du fået en mere krum ryg?', '', 'Højdetab eller mere krum ryg', 1 ),
		array( 'bmi', 'Har du lav kropsvægt (BMI under 19)?', 'Fx under 55 kg, hvis du er 170 cm høj.', 'Lav kropsvægt', 0 ),
		array( 'rygning', 'Ryger du dagligt?', '', 'Daglig rygning', 0 ),
		array( 'alkohol', 'Drikker du 3 eller flere genstande alkohol om dagen?', '', '3 eller flere genstande om dagen', 0 ),
		array( 'sygdom', 'Har du en sygdom eller får du medicin, der kan påvirke knoglerne?', 'Fx tarmsygdom, cøliaki, for højt stofskifte eller visse behandlinger mod bryst- eller prostatakræft.', 'Sygdom eller medicin, der påvirker knoglerne', 0 ),
		array( 'fald', 'Er du faldet mere end én gang inden for det seneste år?', '', 'Flere fald det seneste år', 0 ),
		array( 'tidligere', 'Har en læge tidligere sagt, at du har lav knogletæthed, osteopeni eller osteoporose?', '', 'Tidligere lav knogletæthed, osteopeni eller osteoporose', 1 ),
		array( 'dvitamin', 'Har du fået påvist D-vitaminmangel, eller er du sjældent udendørs i dagslys?', '', 'D-vitaminmangel eller sjældent udendørs', 0 ),
		array( 'inaktiv', 'Er du fysisk inaktiv, fx sidder du det meste af dagen og går sjældent ture?', '', 'Fysisk inaktiv', 0 ),
	);
}

add_shortcode( 'nordic_risikotest', function () {
	$e = 'esc_html';
	$btn = 'background: #FFFFFF; color: #183A70; border-color: #CBD6E4';
	$h = '<div class="nrt" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr)); gap: clamp(28px, 4vw, 56px); align-items: start; font-family: Manrope, sans-serif; color: #1C2B45; font-size: 15px; line-height: 1.6">';
	$h .= '<ol style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 14px">';
	foreach ( nordic_rt_questions() as $i => $q ) {
		$h .= '<li class="nrt-q" data-id="' . $q[0] . '" style="border: 1px solid #E3EAF3; background: #FFFFFF; border-radius: 18px; padding: 18px 20px; display: flex; gap: 14px; align-items: flex-start">';
		$h .= '<span aria-hidden="true" style="width: 32px; height: 32px; border-radius: 100px; background: #102A4C; color: #FFFFFF; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0">' . ( $i + 1 ) . '</span>';
		$h .= '<div role="group" aria-label="' . esc_attr( $q[1] ) . '" style="display: flex; flex-direction: column; gap: 10px; min-width: 0; flex: 1">';
		$h .= '<span style="font-size: 16px; font-weight: 700; color: #102A4C; line-height: 1.4">' . $e( $q[1] ) . '</span>';
		if ( $q[2] ) { $h .= '<span style="font-size: 13.5px; color: #5E6D82; margin-top: -4px">' . $e( $q[2] ) . '</span>'; }
		$h .= '<div style="display: flex; flex-wrap: wrap; gap: 8px">';
		foreach ( array( 'ja' => 'Ja', 'nej' => 'Nej', 'ved' => 'Ved ikke' ) as $v => $l ) {
			$h .= '<button type="button" class="rt-btn" data-v="' . $v . '" aria-pressed="false" style="' . $btn . '">' . $l . '</button>';
		}
		$h .= '</div></div></li>';
	}
	$h .= '</ol>';
	$h .= '<div style="position: sticky; top: 24px; display: flex; flex-direction: column; gap: 16px">';
	$h .= '<div class="nrt-res" aria-live="polite" style="background: #FFFFFF; color: #1C2B45; border: 2px solid #E3EAF3; border-radius: 22px; padding: clamp(22px, 3vw, 32px); display: flex; flex-direction: column; gap: 12px">';
	$h .= '<span class="nrt-prog" style="font-size: 12px; letter-spacing: 2.4px; text-transform: uppercase; font-weight: 700; color: #7A5C2E">Besvaret 0 af 15</span>';
	$h .= '<div class="nrt-track" aria-hidden="true" style="height: 6px; border-radius: 100px; background: #E3EAF3; overflow: hidden"><div class="nrt-bar" style="height: 100%; width: 0%; background: #C79A5B; border-radius: 100px"></div></div>';
	$h .= '<h2 class="nrt-t" style="margin: 4px 0 0; font-family: \'Cormorant Garamond\', serif; font-weight: 700; font-size: clamp(28px, 3vw, 36px); line-height: 1.1; color: inherit">Dit resultat vises her</h2>';
	$h .= '<p class="nrt-x" style="margin: 0; font-size: 15.5px; color: inherit">Start med det første spørgsmål. Du kan ændre dine svar undervejs.</p>';
	$h .= '<div class="nrt-f" hidden style="display: flex; flex-direction: column; gap: 6px"><span style="font-size: 13.5px; font-weight: 700">Du har svaret ja til:</span><ul style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 6px"></ul></div>';
	$h .= '<div class="nrt-cta" hidden style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px"><a href="/anmod-om-tid/" style="background: #C79A5B; color: #102A4C; text-decoration: none; padding: 14px 22px; border-radius: 100px; font-weight: 700; font-size: 14px">Anmod om tid</a><a class="nrt-c2" href="/udredning/" style="border: 1px solid #183A70; color: #1C2B45; text-decoration: none; padding: 13px 22px; border-radius: 100px; font-weight: 600; font-size: 14px">Se osteoporoseudredning</a></div>';
	$h .= '<button type="button" class="rt-reset" hidden style="align-self: flex-start; margin-top: 4px">Nulstil svarene</button>';
	$h .= '</div>';
	$h .= '<p style="margin: 0; font-size: 13.5px; color: #5E6D82; background: #F8FAFD; border: 1px solid #E3EAF3; border-radius: 16px; padding: 14px 16px">Testen er ikke en diagnose og erstatter ikke en lægevurdering. Den bygger på de risikofaktorer, som lægen også spørger til ved en udredning og i FRAX. Dine svar gemmes ikke og sendes ikke til os.</p>';
	$h .= '</div></div>';
	$q = array();
	foreach ( nordic_rt_questions() as $x ) { $q[] = array( 'id' => $x[0], 'short' => $x[3], 'strong' => (bool) $x[4] ); }
	$h .= '<style>.nrt [hidden]{display:none!important}.rt-btn{cursor:pointer;font:inherit;font-size:14px;font-weight:700;border-radius:100px;padding:10px 18px;border:1px solid #CBD6E4;transition:background .15s ease,color .15s ease,border-color .15s ease;line-height:1.6}.rt-btn:focus-visible{outline:2px solid #C79A5B;outline-offset:2px}.rt-reset{cursor:pointer;font:inherit;background:none;border:none;padding:0;color:#183A70;font-size:14px;font-weight:700;border-bottom:2px solid #C79A5B;padding-bottom:2px}.rt-reset:focus-visible{outline:2px solid #183A70;outline-offset:3px}</style>';
	$h .= '<script>(function(){var Q=' . wp_json_encode( $q, JSON_UNESCAPED_UNICODE ) . ';'
		. 'var R={start:["Dit resultat vises her","Start med det første spørgsmål. Du kan ændre dine svar undervejs."],'
		. 'igang:["Svar på resten af spørgsmålene","Når du har svaret på alle 15 spørgsmål, ser du dit samlede resultat."],'
		. 'strong:["Vi anbefaler, at du får undersøgt dine knogler","Du har svaret ja til mindst én risikofaktor, der i sig selv er en god grund til en undersøgelse. Tal med din egen læge, eller anmod om en osteoporoseudredning med DEXA-scanning og FRAX."],'
		. 'flere:["En udredning kan give dig et klart svar","Du har flere risikofaktorer. Tilsammen kan de øge risikoen for brud. En DEXA-scanning og en FRAX-beregning viser, hvor du står."],'
		. 'en:["En DEXA-scanning kan give dig et klart svar","Du har én risikofaktor. Én risikofaktor betyder ikke, at du har osteoporose, men en DEXA-scanning af hofte og ryg er en enkel undersøgelse, der viser, hvordan dine knogler har det."],'
		. 'ingen:["Du har ingen af de almindelige risikofaktorer","Hold knoglerne stærke med motion, nok kalk og D-vitamin. Er du alligevel bekymret for dine knogler, kan du anmode om en DEXA-scanning. Får du et brud efter et mindre fald, bør du blive undersøgt."]};'
		. 'var root=(function(e){while(e&&!(e.classList&&e.classList.contains("nrt")))e=e.previousElementSibling;return e})(document.currentScript),a={};if(!root)return;'
		. 'function $(s){return root.querySelector(s)}'
		. 'function draw(){var ans=0,yes=[];Q.forEach(function(q){if(a[q.id])ans++;if(a[q.id]==="ja")yes.push(q)});'
		. 'var st=yes.filter(function(q){return q.strong}).length,ot=yes.length-st,done=ans===Q.length,lv="start";'
		. 'if(st>0)lv="strong";else if(ot>=2)lv="flere";else if(ot===1&&done)lv="en";else if(done)lv="ingen";else if(ans>0)lv="igang";'
		. 'var dark=lv==="strong"||lv==="flere"||lv==="en",r=$(".nrt-res");'
		. 'r.style.background=dark?"#102A4C":"#FFFFFF";r.style.color=dark?"#FFFFFF":"#1C2B45";r.style.borderColor=dark?"#102A4C":(lv==="start"||lv==="igang"?"#E3EAF3":"#C79A5B");'
		. '$(".nrt-prog").textContent=done?"Dit resultat":"Besvaret "+ans+" af "+Q.length;$(".nrt-prog").style.color=dark?"#E9C894":"#7A5C2E";'
		. '$(".nrt-track").style.background=dark?"rgba(255,255,255,0.18)":"#E3EAF3";$(".nrt-bar").style.width=Math.round(ans/Q.length*100)+"%";'
		. '$(".nrt-t").textContent=R[lv][0];$(".nrt-x").textContent=R[lv][1];'
		. 'var f=$(".nrt-f"),ul=f.querySelector("ul");f.hidden=!yes.length;ul.innerHTML="";yes.forEach(function(q){var li=document.createElement("li");li.style.cssText="display:flex;gap:10px;align-items:flex-start;font-size:14.5px";li.innerHTML=\'<span aria-hidden="true" style="width:8px;height:8px;border-radius:100px;background:#C79A5B;flex-shrink:0;margin-top:8px"></span><span></span>\';li.lastChild.textContent=q.short;ul.appendChild(li)});'
		. '$(".nrt-cta").hidden=!(dark||lv==="ingen");var c2=$(".nrt-c2");c2.style.borderColor=dark?"rgba(255,255,255,0.6)":"#183A70";c2.style.color=dark?"#FFFFFF":"#1C2B45";'
		. 'var rs=$(".rt-reset");rs.hidden=!ans;rs.style.color=dark?"#FFFFFF":"#1C2B45";'
		. 'root.querySelectorAll(".nrt-q").forEach(function(li){var v=a[li.getAttribute("data-id")];li.style.background=v?"#F8FAFD":"#FFFFFF";li.style.borderColor=v?"#D5DFEC":"#E3EAF3";'
		. 'li.querySelectorAll(".rt-btn").forEach(function(b){var on=b.getAttribute("data-v")===v;b.setAttribute("aria-pressed",on?"true":"false");b.style.background=on?"#183A70":"#FFFFFF";b.style.color=on?"#FFFFFF":"#183A70";b.style.borderColor=on?"#183A70":"#CBD6E4"})})}'
		. 'root.addEventListener("click",function(e){var b=e.target.closest(".rt-btn");if(b){var id=b.closest(".nrt-q").getAttribute("data-id"),v=b.getAttribute("data-v");a[id]=a[id]===v?undefined:v;draw();return}if(e.target.closest(".rt-reset")){a={};draw()}});'
		. '})();</script>';
	return $h;
} );

add_shortcode( 'nordic_tscore', function () {
	$h = '<div class="nts" style="font-family: Manrope, sans-serif; color: #1C2B45; font-size: 15px; line-height: 1.55; width: 100%; box-sizing: border-box; background: #FFFFFF; border: 1px solid #E3EAF3; border-radius: 22px; padding: clamp(20px, 3vw, 34px); display: flex; flex-direction: column; gap: 22px">';
	$h .= '<div style="display: flex; flex-wrap: wrap; gap: 14px 24px; align-items: flex-end">';
	$h .= '<label style="display: flex; flex-direction: column; gap: 6px; font-size: 14px; font-weight: 700; color: #102A4C">Din T-score <input class="ts-in" type="text" inputmode="decimal" autocomplete="off" placeholder="fx -2,1" style="width: 170px; box-sizing: border-box; border: 1px solid #CBD6E4; border-radius: 12px; padding: 13px 16px; font: inherit; font-size: 17px; font-weight: 600; color: #1C2B45; background: #FFFFFF"></label>';
	$h .= '<p class="ts-res" aria-live="polite" style="margin: 0; flex: 1 1 280px; min-height: 48px; display: flex; align-items: center; font-size: 15.5px; color: #1C2B45">Skriv tallet fra din scanningsrapport, fx -2,1. Står der flere, så brug den laveste.</p>';
	$h .= '</div>';
	$h .= '<div style="position: relative; padding-top: 44px">';
	$h .= '<div class="ts-mark" aria-hidden="true" hidden style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center"><span class="ts-lab" style="background: #102A4C; color: #FFFFFF; font-size: 13px; font-weight: 700; padding: 4px 10px; border-radius: 100px; white-space: nowrap"></span><span style="width: 2px; height: 14px; background: #102A4C"></span></div>';
	$h .= '<div role="img" aria-label="T-score-skala: -2,5 eller lavere er osteoporose, mellem -1,0 og -2,5 er osteopeni, -1,0 eller højere er normal knogletæthed" style="display: flex; height: 56px; border-radius: 14px; overflow: hidden; font-weight: 700; font-size: clamp(12px, 1.4vw, 15px)">'
		. '<div style="flex: 30 1 0; min-width: 0; background: #102A4C; color: #FFFFFF; display: flex; align-items: center; justify-content: center; text-align: center; padding: 0 6px">Osteoporose</div>'
		. '<div style="flex: 30 1 0; min-width: 0; background: #C79A5B; color: #102A4C; display: flex; align-items: center; justify-content: center; text-align: center; padding: 0 6px">Osteopeni</div>'
		. '<div style="flex: 40 1 0; min-width: 0; background: #EEF4FB; color: #102A4C; display: flex; align-items: center; justify-content: center; text-align: center; padding: 0 6px; box-shadow: inset 0 0 0 1px #D5DFEC; border-radius: 0 14px 14px 0">Normal</div></div>';
	$h .= '<div aria-hidden="true" style="position: relative; height: 24px; margin-top: 8px; font-size: 13px; font-weight: 600; color: #5E6D82"><span style="position: absolute; left: 0">-4,0</span><span style="position: absolute; left: 30%; transform: translateX(-50%)">-2,5</span><span style="position: absolute; left: 60%; transform: translateX(-50%)">-1,0</span><span style="position: absolute; right: 0">+1,0</span></div>';
	$h .= '</div>';
	$li = function ( $bg, $extra, $a, $b ) {
		return '<li style="display: flex; gap: 12px; align-items: flex-start"><span aria-hidden="true" style="width: 16px; height: 16px; border-radius: 5px; background: ' . $bg . ';' . $extra . ' flex-shrink: 0; margin-top: 3px"></span><span><strong style="color: #102A4C">' . $a . '</strong><br><span style="color: #3A4A63">' . $b . '</span></span></li>';
	};
	$h .= '<ul style="margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 14px 24px">'
		. $li( '#102A4C', '', '-2,5 eller lavere', 'Osteoporose' )
		. $li( '#C79A5B', '', 'Mellem -1,0 og -2,5', 'Osteopeni (let nedsat knogletæthed)' )
		. $li( '#EEF4FB', ' box-shadow: inset 0 0 0 1px #B9C7DA;', '-1,0 eller højere', 'Normal knogletæthed' ) . '</ul>';
	$h .= '<p style="margin: 0; font-size: 13.5px; color: #5E6D82">WHO\'s grænser for T-score i hofte eller lænderyg hos kvinder efter overgangsalderen og mænd over 50 år. Et lavenergibrud i ryg eller hofte kan give diagnosen osteoporose uanset T-scoren. Lægen vurderer altid det samlede billede.</p>';
	$h .= '</div>';
	$h .= '<style>.nts [hidden]{display:none!important}.ts-in:focus{outline:2px solid #183A70;outline-offset:1px;border-color:#183A70}</style>';
	$h .= '<script>(function(){var root=(function(e){while(e&&!(e.classList&&e.classList.contains("nts")))e=e.previousElementSibling;return e})(document.currentScript);if(!root)return;var inp=root.querySelector(".ts-in"),res=root.querySelector(".ts-res"),mk=root.querySelector(".ts-mark"),lab=root.querySelector(".ts-lab");'
		. 'inp.addEventListener("input",function(){var raw=(inp.value||"").trim().replace(/−/g,"-").replace(",","."),t="Skriv tallet fra din scanningsrapport, fx -2,1. Står der flere, så brug den laveste.",show=false;'
		. 'if(raw!==""){var v=Number(raw);if(!isFinite(v)||v<-6||v>4){t="Skriv et tal mellem -6 og +4, fx -2,1."}else{var fmt=Math.abs(v)<0.05?"0,0":(v>0?"+":"")+v.toFixed(1).replace(".",","),cat="normalområdet (-1,0 eller højere)";'
		. 'if(v<=-2.5)cat="osteoporose-området (-2,5 eller lavere)";else if(v<-1.0)cat="osteopeni-området (mellem -1,0 og -2,5)";'
		. 't="En T-score på "+fmt+" ligger i "+cat+". Lægen vurderer altid det samlede billede.";var pct=Math.min(97,Math.max(3,(v+4)/5*100));mk.style.left=pct.toFixed(1)+"%";lab.textContent="Din T-score "+fmt;show=true}}'
		. 'res.textContent=t;mk.hidden=!show})})();</script>';
	return $h;
} );
