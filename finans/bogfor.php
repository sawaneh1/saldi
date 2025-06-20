<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------finans/bogfor.php---------- patch 4.1.1 --- 2025.06.12 ---
//                           LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20121122 - Åbne poster udlignes ikke mere automatisk hvis forskelligt projektnummer. Søg 20121122
// 20130210 - Break ændret til break 1
// 20130224 - Ny saldo for kreditorer og debitorer vises nu i aktuelle regnskabsaar.
// 20130404 - addslashes erstattet af db_escape_string.  
// 20130404 - Blokerer nu for postering hvis max. antal overskredet.
// 20130802 - Tilføjet simulering - i betatest - knap ikke synlig.
// 20130814 - Lukker istedet for returnering til kladde v. luk af simulering.
// 20131028 - Fejl v. manglende diffkonto til valuta. tilføjet !. Søg 20131028.
// 20131115	-	Fejlbogføring ved modsvarende differencer i forslellige bilag. Søg 20131115 
// 20131117	-	Tilføjet !$debet[$i]||!$kredit[$i]. Kunne ikke bogføre 1 linjers posteringer. Søg 20131117
// 20140228 -	Det er nu atter muligt at bogføre selvom de enlekte bilag ikke stemmer, hvis summen gør # 20140228  
// 20140428 -	Afrundingsfejl ved eu moms (PHR Danosoft jf http://forum.saldi.dk/viewtopic.php?f=5&t=1130)# Søg 20140428  
// 20141128 -	Fejl i kontrolfunktion minus ændret til plus.
// 20150527	- Som ovenstående minus ændret til plus.
// 20170516 - Linjer uden indhold i debet og kredit slettes fra kladde ved bøgføring. 20170516
// 20190116 MSC - Rettet topmenu design til og rettet isset fejl
// 20190201 MSC - Rettet topmenu design til og gjort bogføring mere overskueligt med ny style.
// 20190205 MSC - Rettet isset fejl
// 20190213 MSC - Rettet topmenu design til
// 20190321 PHR Added call to equalizeMatchingRecords (in 'genberegn.php') to matches open records.
// 20190426 PHR Added 'genberegn' below includes includes to ensure that values in kontoplan is up to date.
// 20201205 PHR Added alert for missing account nr in EU VAT
// 20201205 PHR Lots of cleanup and rewriting of currency diff. handling
// 20210319 LOE - Translated some of these texts from Danish to English
// 20221018	PHR - Some cleanup.
// 20230324	PHR - Correted errors in 'findtekst' (wrong text ID) 
// 20230626 PHR - Moved this part into 'if ($r = db_fetch_array($q)) {' from below as amount was aligned
// 20250612 PHR	- Extra diff controle

@session_start();
$s_id=session_id();

$fejltext=NULL;$valutadiff=0;
$tjeksum=array();
$css="../css/standard.css";

$modulnr=2;
$title="Bogfør kassekladde";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");
include("../includes/topline_settings.php");

genberegn($regnaar);

$funktion=if_isset($_GET['funktion']);
$kladde_id=if_isset($_GET['kladde_id']);
if (($_POST) && ($_POST['kladde_id'])) $kladde_id = $_POST['kladde_id'];

if (!isset ($onclick)) $onclick = NULL;

#if ($popup) $returside="../includes/luk.php";
#else $returside="kassekladde.php?kladde_id=$kladde_id";
$returside="kassekladde.php?kladde_id=$kladde_id";

$r =db_fetch_array(db_select("select * from grupper where kodenr = '$regnaar' and art = 'RA'",__FILE__ . " linje " . __LINE__));
$regnstart=$r['box2']."-".$r['box1']."-01";
$rsaar=$r['box4'];
$rsmd=$r['box3'];
$rsdd=31;
while (!checkdate($rsmd,$rsdd,$rsaar)) {
	$regnslut=$rsaar."-".$rsmd."-".$rsdd;
	$rsdd--;
	if ($rsdd<28) break 1;
}
$regnslut=$rsaar."-".$rsmd."-".$rsdd;

if ($kladde_id) {	
	$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id",__FILE__ . " linje " . __LINE__));
	if ($row['bogfort']=='V') {
		print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogf&oslash;rt - kladden lukkes')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		exit;
	}
}
if ($funktion=='bogfor') {
	$overskrift = findtekst('2209|Bogfør kassekladde', $sprog_id);
	$href="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>";
} elseif ($funktion=='simuler') {
	$overskrift="".findtekst(1085,$sprog_id)." ".findtekst(1086,$sprog_id).", ".findtekst(1087,$sprog_id)." $kladde_id"; #20210319
	$href="<a href=$returside accesskey=L>";
} else $href="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>";
if ($menu=='T') {
	print "";
} else {
	print "<center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
}
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";
	print "<td width='10%'>$href<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst('30|Tilbage',$sprog_id)."</button></a></td>";
	print "<td width='80%' align='center' style='$topStyle'>$overskrift</td>";
	print "<td width='10%' align='center' style='$topStyle'></td>";
	print "</tbody></table>";
	print "</td></tr>";
} else {
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$href ".findtekst(30,$sprog_id)."</a></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$overskrift</td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></a></td>";
print "</tbody></table>";
print "</td></tr>";
}

if (!isset ($_POST['bogfor'])) $_POST['bogfor'] = 0;
if (!isset ($_POST['simuler'])) $_POST['simuler'] = 0;
if (!isset ($_POST['luk'])) $_POST['luk'] = 0;
if (!isset ($_POST['kladdenote'])) $_POST['kladdenote'] = NULL;

if ($_POST['bogfor'] || $_POST['simuler']) {
	$bogfor = trim($_POST['bogfor']);
	$simuler = trim($_POST['simuler']);
	$opdater = if_isset($_POST['opdater']);
	$bestil = if_isset($_POST['bestil']);
	$stop = if_isset($_POST['stop']);
	$kladde_id = $_POST['kladde_id'];
	$kladdenote = db_escape_string(trim($_POST['kladdenote']));
	if ($opdater=='1000') {
		include("../includes/connect.php");
		db_modify("update regnskab set posteringer = posteringer+1000 where db='$db'",__FILE__ . " linje " . __LINE__);
		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
		mail("saldi@saldi.dk", "SALDI Opgradering af $regnskab / $db", "$brugernavn har bestilt 1000 posteringer til regnskab $db", "$headers");
		include("../includes/online.php");
	} elseif ($stop) {
		print "<td align=\"center\">
			<iframe style=\"width:800px;height:100%\"
			src=\"../doc/gratis2proff.php\"
			frameborder=\"1\"></iframe>
		</td>";
		$bogfor=NULL;
		exit;
		#		include("../includes/connect.php");
#		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
#		mail("saldi@saldi.dk", "SALDI bestilling af professionelt regnskab for $regnskab / $db", "$brugernavn har bestilt opgradering af $regnskab til professionelt regnskab", "$headers");
#		include("../includes/online.php");
	} elseif ($bestil) {
		include("../includes/connect.php");
		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
		mail("saldi@saldi.dk", "SALDI bestilling af professionelt regnskab for $regnskab / $db", "$brugernavn har bestilt opgradering af $regnskab til professionelt regnskab", "$headers");
		include("../includes/online.php");
	}
	if ($bogfor) {
		transaktion('begin');
		bogfor($kladde_id, $kladdenote,'');
		db_modify("delete from tmpkassekl where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		genberegn($regnaar);
		equalizeMatchingRecords();
		if ($popup) print "<BODY onLoad=\"javascript=opener.location.reload();\">";
	} elseif ($simuler) {
		transaktion('begin');
		bogfor($kladde_id, $kladdenote,'on');
#		db_modify("delete from tmpkassekl where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
		if ($popup) {
			print "<BODY onLoad=\"javascript=opener.location.reload();\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		}
	}
	if ($funktion=='bogfor' || $funktion=='simuler') {
		if ($bogfor || $simuler) print "<meta http-equiv=\"refresh\" content=\"0;URL=kladdeliste.php\">";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
	}
} elseif ($_POST['luk']) {
	#	if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	#	else print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
$x=0;
$debetsum=0;
$kreditsum=0;
$valutadiff=0;
$valutaposter=0;

$dkkamount=array();
if ($kladde_id) {
	$posteringer=0;
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id and amount !=0 order by bilag",__FILE__ . " linje " . __LINE__);
	while ($row =	db_fetch_array($query)){
		if ($row['debet']||$row['kredit']) {
			$posteringer++;
			$bilag[$posteringer]=$row['bilag'];
			$y=$row['bilag'];
			if (!isset($tjeksum[$y])) $tjeksum[$y]=0;
			$d_type[$posteringer]=trim($row['d_type']);
			$debet[$posteringer]=$row['debet']*1;
			$k_type[$posteringer]=trim($row['k_type']);
			$kredit[$posteringer]=$row['kredit']*1;
			$faktura[$posteringer]=trim($row['faktura']);
			$amount[$posteringer]=$row['amount']*1;
			$momsfri[$posteringer]=trim($row['momsfri']);
			$valuta[$posteringer]=trim($row['valuta']);
			if (!$debet[$posteringer]) $d_type[$posteringer]='F';
			if (!$kredit[$posteringer]) $k_type[$posteringer]='F';
			if ($row['valuta'] && $row['amount'] && ($row['debet']||$row['kredit']))  {
				$valutaposter++;
				list($dkkamount[$posteringer],$diffkonto,$valutakurs)=valutaopslag($amount[$posteringer],$row['valuta'],$row['transdate']);
#				if ($row['debet']) $valutadiff=$valutadiff+$dkkamount[$posteringer];
#				if ($row['kredit']) $valutadiff=$valutadiff-$dkkamount[$posteringer];
#				$valutadiff=round($valutadiff+0.0001,3);
#				echo "VS2 $valutadiff<br>";		
			} else $dkkamount[$posteringer]=$amount[$posteringer];
			if($debet[$posteringer]) {
				$tjeksum[$y]=afrund($tjeksum[$y]+$dkkamount[$posteringer],3);
				$debetsum=$debetsum+$dkkamount[$posteringer];
			}
			if($kredit[$posteringer]) {
				$tjeksum[$y]=afrund($tjeksum[$y]-$dkkamount[$posteringer],3);
				$kreditsum=$kreditsum+$dkkamount[$posteringer];
			}
		} else db_modify("delete from kassekladde where id='$row[id]'",__FILE__ . " linje " . __LINE__); 
	}	
} else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	exit;
}

$x=0;
$debitor=array();
for ($y=1;$y<=$posteringer;$y++) {
	if (strstr($d_type[$y],'D')) {
		$d_debitor[$y]=$debet[$y];	
		if (!in_array($debet[$y],$debitor)) {
			$x++;
			$debitor[$x]=$debet[$y];
			$debitoramount[$x]=0;
		}
	} if (strstr($k_type[$y],'D')) {
		$k_debitor[$y]=$kredit[$y];	
		if (!in_array($kredit[$y],$debitor)) {
			$x++;
			$debitor[$x]=$kredit[$y];
			$debitoramount[$x]=0;
		}
	}
}
$debitorantal=$x;
$x=0;
$kreditor=array();
for ($y=1;$y<=$posteringer;$y++) {
	if (strstr($d_type[$y],'K')) {
		$d_kreditor[$y]=$debet[$y];	
		if (!in_array($debet[$y],$kreditor)) {
			$x++;
			$kreditor[$x]=$debet[$y];
			$kreditoramount[$x]=0;
		}
	} if (strstr($k_type[$y],'K')) {
		$k_kreditor[$y]=$kredit[$y];	
		if (!in_array($kredit[$y],$kreditor)) {
			$x++;
			$kreditor[$x]=$kredit[$y];
			$kreditoramount[$x]=0;
		}
	}
}
$kreditorantal=$x;

# kontrollerer om der er tale om en debitor eller kreditor konto hvor der skal beregnes moms 
# Konti fra kontoplanen bliver forbig&aring;et i funktionen
for ($y=1; $y<=$posteringer; $y++) {
	if (strlen($debet[$y])>0){
		list ($debet[$y], $d_momsart[$y]) =gruppeopslag($d_type[$y], $debet[$y]);
	}
	if (strlen($kredit[$y])>0){
		list ($kredit[$y], $k_momsart[$y])=gruppeopslag($k_type[$y], $kredit[$y]);
	}
}

# Funktionen momsberegning finder momssatsen og beregner momsen. 
for ($y=1; $y<=$posteringer; $y++) {
	$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
	$debet[$y]=str_replace(" ","",$debet[$y]);
	$kredit[$y]=str_replace(" ","",$kredit[$y]);
	if ($debet[$y]>0)  $d_amount[$y]=$dkkamount[$y];
	if ($kredit[$y]>0) $k_amount[$y]=$dkkamount[$y];
	if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)) {
	list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y]);
	}
	if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)){
		list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y]);
	}
}
/*
Alle posteringer loebes igennem igen - Hvis der er tale en en postering med EU moms er der en modkonto (x-modkto)- 
Hvis der samtidig er en modpostering flyttes modposteringen op "for enden" af posteringsraekken og antallet af posteringer oeges med en.
Denne flytning sker KUN naar den er tale om en dobbeltpostering hvor den ene eller begge er konti fra kontoplanen med EU moms
*/
for ($y=1; $y<=$posteringer; $y++) {
	if (!isset($d_modkto[$y]))$d_modkto[$y]=0;
	if (!isset($k_modkto[$y]))$k_modkto[$y]=0;
	if (!isset($d_momskto[$y]))$d_momskto[$y]=0;
	if (!isset($k_momskto[$y]))$k_momskto[$y]=0;
	if ($d_modkto[$y]>0) {	
		if ($k_moms[$y]) {
			$posteringer++;
			$k_momskto[$posteringer]=$k_momskto[$y];
			$k_moms[$posteringer]=$k_moms[$y];
		}
		$k_moms[$y]=$d_moms[$y];
		$k_momskto[$y]=$d_modkto[$y];
	}
	if ($k_modkto[$y]>0) {
		if ($d_moms[$y]) { 
			$posteringer++;
			$d_momskto[$posteringer]=$d_momskto[$y];
			$d_moms[$posteringer]=$d_moms[$y];
		}
		$d_moms[$y]=$k_moms[$y];
		$d_momskto[$y]=$k_modkto[$y];
	}
}
$kontoantal=0;
$kontoliste=array()	;
for ($y=1; $y<=$posteringer; $y++) {
	if ((!in_array($debet[$y], $kontoliste))&&($debet[$y]>0)) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$debet[$y];
	}
	if ((!in_array($kredit[$y], $kontoliste))&&($kredit[$y]>0)) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$kredit[$y];
	}
	if (($d_momskto[$y])&&(!in_array($d_momskto[$y], $kontoliste))) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$d_momskto[$y];
	}
	if (($k_momskto[$y])&&(!in_array($k_momskto[$y], $kontoliste))) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$k_momskto[$y];
	}
}
sort($kontoliste);
$kontodebet=array();
$kontokredit=array();
for ($y=0; $y<$kontoantal; $y++) {
	if (!isset($kontodebet[$y]))$kontodebet[$y]=0;
	if (!isset($kontokredit[$y]))$kontokredit[$y]=0;
	for($z=1; $z<=$posteringer; $z++) {
		if ($kontoliste[$y]==$debet[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_amount[$z];}
		if ($kontoliste[$y]==$kredit[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_amount[$z];}
		if ($kontoliste[$y]==$d_momskto[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_moms[$z];}
		if ($kontoliste[$y]==$k_momskto[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_moms[$z];}
	}
}

print "<form name=kassekladde action=bogfor.php?funktion=$funktion method=post>";
if ($funktion=='bogfor') {
	$query = db_select("select kladdenote from kladdeliste where id=$kladde_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	print "<td align=center valign=\"top\" height=10px><center><b><font face=\"Helvetica, Arial, sans-serif\">Bem&aelig;rkning:&nbsp;</b><input type=text size=95 name=kladdenote value='$row[kladdenote]'></center></td>";
	print "</tr><tr><td height=10px><hr></td></tr>";
}
$d_sum=0; $k_sum=0;
print "<tr><td align = center valign=\"top\"><center><table class='dataTableSmall' border=1 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td colspan=\"6\" class='tableHeader'><b>".findtekst(1088,$sprog_id)."</b></td></tr>
	<tr><td class='tableText'>$font ".findtekst(440,$sprog_id)."</td>
	<td class='tableText'>$font ".(findtekst(914,$sprog_id))."</td>
	<td align=\"center\" class='tableText'>$font ".findtekst(1073,$sprog_id)."</td>
	<td align=\"center\" class='tableText'>$font ".ucfirst(findtekst(1000,$sprog_id))."</td>
	<td align=\"center\" class='tableText'>$font ".ucfirst(findtekst(1001,$sprog_id))."</td>
	<td align=\"center\" class='tableText'>$font ".findtekst(39,$sprog_id)." ".lcfirst(findtekst(1073,$sprog_id))."</td></tr>";
for ($y=0; $y<$kontoantal; $y++) {
	$d_sum=$d_sum+afrund($kontodebet[$y],2);
	$k_sum=$k_sum+afrund($kontokredit[$y],2);

	$query = db_select("select * from kontoplan where kontonr='$kontoliste[$y]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$saldo=$row['saldo'];
		$a=dkdecimal($saldo);
		$b=dkdecimal($kontodebet[$y]);
		$c=dkdecimal($kontokredit[$y]);
		$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
		$beskrivelse=db_escape_string($row['beskrivelse']);
		print "<tr><td>$font $kontoliste[$y]</td><td>$font $beskrivelse</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
	}
	else {
		print "<tr><td>$font $kontoliste[$y]</td><td>FINDES IKKE !!</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
		$fejltext = "OBS:Kontonr: $kontoliste[$y] FINDES IKKE !!";
	}
}
#valutadiff=round($valutadiff+0.0001,3);
$diff=afrund($debetsum-$kreditsum,3);
if (abs($diff) < $valutaposter/100) {
	$valutadiff=$diff;
} 

if ($valutadiff && abs($valutadiff) < $valutaposter/100) {
	if (isset($diffkonto)&&(!in_array($diffkonto,$kontoliste) && $diffkonto>0)) { 
		$y++;
		$kontoliste[$y]=$diffkonto;
		$kontokredit[$y]=0;
		$kontodebet[$y]=0;
	}
	if ($valutadiff > 0) {
		$k_sum=$k_sum+$valutadiff;
		$kontokredit[$y]=$valutadiff;
		$diff=$diff-$valutadiff;
	} else {
		$d_sum=$d_sum-$valutadiff;
		$kontodebet[$y]=$valutadiff*-1;
		$diff=$diff-$valutadiff;
	}
	$query = db_select("select * from kontoplan where kontonr='$diffkonto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$saldo=$row['saldo'];
		$a=dkdecimal($saldo);
		$b=dkdecimal($kontodebet[$y]);
		$c=dkdecimal($kontokredit[$y]);
		$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
		$beskrivelse=db_escape_string($row['beskrivelse']);
		print "<tr><td>$font $kontoliste[$y]</td><td>$font $beskrivelse</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
	}
	else {
		print "<tr><td>$font $kontoliste[$y]</td><td>FINDES IKKE !!</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
		$fejltext = "OBS:Kontonr: $kontoliste[$y] FINDES IKKE !!";
	}
}
$b=dkdecimal($d_sum);
$c=dkdecimal($k_sum);
print "<tr><td><br></td><td>$font ".findtekst(1084,$sprog_id)."</td><td align=right><br></td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right><br></td></tr>";

# 20131115 ->
$x=0;
$diffbilag=array();

for ($y=1;$y<=$posteringer;$y++) {
	if (isset ($bilag[$y-1]) && $bilag[$y]!=$bilag[$y-1]) {
		if (afrund($b_sum[$x],2) && afrund($bvSum[$x],2)) $diffbilag[$y-1]=afrund($b_sum[$x],2);
		$x++;
	}
	if (!isset ($b_sum[$x])) $b_sum[$x] = 0;
	if (!isset ($bvSum[$x])) $bvSum[$x] = 0;
	if (!isset ($dkkamount[$y])) $dkkamount[$y] = 0;
	if (!isset ($amount[$y])) $amount[$y] = 0;
	if ($debet[$y]>0) {
		$b_sum[$x]+=afrund($dkkamount[$y],2);
		$bvSum[$x]+=afrund($amount[$y],2);
	}
	if ($kredit[$y]>0) {
		$b_sum[$x]-=afrund($dkkamount[$y],2);
		$bvSum[$x]-=afrund($amount[$y],2);
	}
}
if (afrund($b_sum[$x],2)) $diffbilag[$y-1]=afrund($b_sum[$x],2);
# <- 20131115
$fejl=0; #20140228
if (abs($diff)>=0.01 || count($diffbilag))  { #20131115 ( || count($diffbilag))
	print "<tr><td colspan=6><br>";
	print "<table width=100% border=1><tbody>"; 
	print "<tr><td align=center colspan=2>Der er differencer p&aring; følgende bilag</td></tr>";
	print "<tr><td align=center>Bilag</td><td align=center>Difference</td></tr>";
	$tmp=NULL;
	for ($x=1; $x<=$posteringer; $x++) {
		$y=$bilag[$x];
		if ($tjeksum[$y]!=0) {
			 print "<tr><td align=right>$y</td><td align=right>".dkdecimal($tjeksum[$y])."</td></tr>";
			 $tmp=$y;
			 $tjeksum[$y]=0;
		} elseif (isset($diffbilag[$x]) && $diffbilag[$x] && $tmp!=$y) { #20131115
			 print "<tr><td align=right>$y</td><td align=right>".dkdecimal($diffbilag[$x]). "</td></tr>";
		}
	}
	print "</tbody></table></td></tr>";
	#$fejl=1;
	if (abs($diff)>=0.01) $fejl=1;
} elseif ($b!=$c || abs($d_sum - $k_sum) >= 0.01) {
echo "$b!=$c $d_sum != $k_sum<br>";
	$message=$db." | Uoverensstemmelse i posteringssum | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
	$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
	mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
	print "<BODY onLoad=\"javascript:alert('Uoverensstemmelse i posteringssum - Kontakt venligst SALDI teamet p&aring; telefon 4690 2208')\">";
	$fejl=1; #20140228
} elseif ($fejltext) {
	print "<tr><td colspan=6><br></td></tr><tr><td align=center colspan=6>$fejltext</td></tr>";
	$fejl=1; #20140228
}
if (!$fejl) { #20140228
	$query = db_select("select * from kladdeliste where id = $kladde_id and bogfort = 'V'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "Kladden er bogført!";
		genberegn($regnaar);
		exit;	
	}
	for ($x=1;$x<=$debitorantal;$x++) {
		$debitordebet[$x]=0;
		$debitorkredit[$x]=0;
		for ($y=1;$y<=$posteringer;$y++) {
			if (isset($d_debitor[$y]) && $debitor[$x]==$d_debitor[$y]) $debitordebet[$x]+=$dkkamount[$y];   
			if (isset($k_debitor[$y]) && $debitor[$x]==$k_debitor[$y]) $debitorkredit[$x]+=$dkkamount[$y];  
		}
		$r=db_fetch_array(db_select("select id,firmanavn from adresser where kontonr='$debitor[$x]' and art='D'",__FILE__ . " linje " . __LINE__));
		$debitor_id[$x]=$r['id']*1;
		$debitor_navn[$x]=$r['firmanavn'];
		$debitor_pre[$x]=0;
		$q=db_select("select amount,valutakurs from openpost where konto_id='$debitor_id[$x]' and transdate<='$regnslut'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if ($r['valutakurs']) $debitor_pre[$x]+=afrund($r['amount']*$r['valutakurs']/100,2);
			else $debitor_pre[$x]+=afrund($r['amount'],2);
			
		}
		$debitor_post[$x]=$debitor_pre[$x]+$debitordebet[$x]-$debitorkredit[$x];
	}

	if ($debitorantal) {
		print "<tr><td colspan=\"6\" class='tableHeader'><b>Debitorbev&aelig;gelser</b></td></tr>";
		print "<tr><td class='tableText'>$font Konto</td>
			<td class='tableText'>$font Beskrivelse</td>
			<td align=\"center\" class='tableText'>$font Saldo</td>
			<td align=\"center\" class='tableText'>$font Debet</td>
			<td align=\"center\" class='tableText'>$font Kredit</td>
			<td align=\"center\" class='tableText'>$font Ny saldo</td></tr>";
		for ($x=1;$x<=$debitorantal;$x++) {
			print "<tr><td>$debitor[$x]</td>
				<td>$debitor_navn[$x]</td>
				<td align=\"right\">".dkdecimal($debitor_pre[$x])."</td>
				<td align=\"right\">".dkdecimal($debitordebet[$x])."</td>
				<td align=\"right\">".dkdecimal($debitorkredit[$x])."</td>
				<td align=\"right\">".dkdecimal($debitor_post[$x])."</td></tr>";
		}
	}

	for ($x=1;$x<=$kreditorantal;$x++) {
		$kreditordebet[$x]=0;
		$kreditorkredit[$x]=0;
		for ($y=1;$y<=$posteringer;$y++) {
			if (isset($d_kreditor[$y]) && $kreditor[$x]==$d_kreditor[$y]) $kreditordebet[$x]+=$dkkamount[$y];  
			if (isset($k_kreditor[$y]) && $kreditor[$x]==$k_kreditor[$y]) $kreditorkredit[$x]+=$dkkamount[$y];  
		}
		$r=db_fetch_array(db_select("select id,firmanavn from adresser where kontonr='$kreditor[$x]' and art='K'",__FILE__ . " linje " . __LINE__));
		$kreditor_id[$x]=$r['id']*1;
		$kreditor_navn[$x]=$r['firmanavn'];
		$kreditor_pre[$x]=0;
		$q=db_select("select amount,valutakurs from openpost where konto_id='$kreditor_id[$x]' and transdate <= '$regnslut'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if ($r['valutakurs']) $kreditor_pre[$x]+=afrund($r['amount']*$r['valutakurs']/100,2);
			else $kreditor_pre[$x]+=afrund($r['amount'],2);

		}
		$kreditor_post[$x]=$kreditor_pre[$x]+$kreditordebet[$x]-$kreditorkredit[$x];
	}
	if ($kreditorantal) {
		print "<tr><td colspan=\"6\" class='tableHeader'><b>Kreditorbev&aelig;gelser</b></td></tr>";
		print "<tr><td class='tableText'>$font Konto</td>
			<td class='tableText'>$font Beskrivelse</td>
			<td align=\"center\" class='tableText'>$font Saldo</td>
			<td align=\"center\" class='tableText'>$font Debet</td>
			<td align=\"center\" class='tableText'>$font Kredit</td>
			<td align=\"center\" class='tableText'>$font Ny saldo</td></tr>";
		for ($x=1;$x<=$kreditorantal;$x++) {
			print "<tr><td>$kreditor[$x]</td>
				<td>$kreditor_navn[$x]</td>
				<td align=\"right\">".dkdecimal($kreditor_pre[$x])."</td>
				<td align=\"right\">".dkdecimal($kreditordebet[$x])."</td>
				<td align=\"right\">".dkdecimal($kreditorkredit[$x])."</td>
				<td align=\"right\">".dkdecimal($kreditor_post[$x])."</td></tr>";
		}
	}
#	else {
	print "<input type=hidden name=kladde_id value=$kladde_id>";
	if ($funktion=='bogfor') {
		$onclick="";
		if (($sqdb=="saldi" || $sqdb=='gratis'|| $sqdb=='develop') && $max_posteringer && $max_posteringer<=8500 && $d_sum>0) {
			$y=date('Y')-1;$m=date('m');$d=date('d');
			while (!checkdate($m, $d, $y)) { #Skudår !
				$d=$d-1;
				if ($d<28) break 1;
			}
			$tmp=$y."-".$m."-".$d;
			$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where transdate>='$tmp'",__FILE__ . " linje " . __LINE__));
			$transantal=$r['transantal']*1;
			if ($transantal > $max_posteringer) {
				if ($sqdb=='saldi' || $sqdb=='develop') {
					$txt = "Dit maksikale posteringsantal ($max_posteringer) er overskredet.\\n";
					$txt.= "Der er i alt foretaget $transantal posteringer inden for de sidste 12 mdr.\\n";
					$txt.= "Klik OK for at opdatere med yderligere 1000 &aring;rlige posteringer.\\nDet koster kr. 30,- pr. m&aring;ned excl. moms.";
					$onclick= "onclick=\"return confirm('$txt')\"";
					print "<input type=\"hidden\" name=\"opdater\" value=\"1000\">";
				} elseif ($sqdb=="gratis" || $sqdb=="develop") {
					$txt = "Dit maksimale antal gratis posteringer ($max_posteringer) er overskredet.\\n";
					$txt.= "Der er i alt foretaget $transantal posteringer inden for de sidste 12 m&aring;neder, og der er ikke flere gratis posteringer.\\n";
					$txt.= "For at komme videre kan du foretage en sikkerhedskopi, bestille et professionelt regnskab p&aring; http://saldi.dk/bestilling \\n";
					$txt.= "og indlæse din sikkerhedskopi af hele dit regnskab der.\\n";
					$txt.= "Alternativt kan du kontakte Saldi.dk ApS på tlf 4690 2208 og høre om mulighederne for ekstra gratis posteringer,\\n";
					$txt.= "som kan fås ved at linke til saldi.dk fra din hjemmeside\\n";
					$onclick= "onclick=\"return confirm('$txt')\"";
					print "<input type=\"hidden\" name=\"stop\" value=\"on\">";
				}
			} 
		}
		print "<tr><td colspan=6 class='tableHeader'><br></td></tr><tr><td colspan=6 align=center><input class='button green medium' type=submit $onclick accesskey=\"b\" value=\"".findtekst('1065|Bogfør', $sprog_id)."\" name=\"bogfor\"> <input class='button red medium' type=submit accesskey=\"l\" value=\"".findtekst('30|Tilbage', $sprog_id)."\" name=\"luk\"></td></tr>";
	} else {
		print "<tr><td colspan=6 class='tableHeader'><br></td></tr><tr><td colspan=6 align=center><input class='button gray medium' type=submit $onclick accesskey=\"b\" value=\"".findtekst('1085|Simuleret',$sprog_id)." ".findtekst('1086|Bogføring', $sprog_id)."\" name=\"simuler\"> <input class='button red medium' type=submit accesskey=\"l\" value=\"".findtekst('30|Tilbage', $sprog_id)."\" name=\"luk\"></td></tr>";
	}
	print "</form>";
}
print "</td></tr></tbody></table>";
######################################################################################################################################
function bogfor($kladde_id,$kladdenote,$simuler) {
	global $connection;
	global $regnaar;
	global $brugernavn;

	$CurencyCheckSum=$tjeksum=$posteringer=$transantal=$transtjek=0;

	if (!isset ($valutakurs)) $valutakurs = NULL;
	if (!isset ($b_antal)) $b_antal = NULL;
	if (!isset ($y)) $y = NULL;
	if (!isset ($momsart)) $momsart = NULL;

	($simuler)?$tabel='simulering':$tabel='transaktioner';
	
	$r=db_fetch_array(db_select("select max(id) as id from transaktioner where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogført!')\">";
		return("Kladden er allerede bogført");
		exit;
	}
	
	$d_momsart=array(); $k_momsart=array();
	if ($kladdenote) db_modify("update kladdeliste set kladdenote = '$kladdenote' where id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
	$y=0;
	$v_antal=0;
	$b_diff=0;
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag",__FILE__ . " linje " . __LINE__);
	while ($row =	db_fetch_array($query)) {
		if ($row['debet'] || $row['kredit']) {
			$y++;
			$postid[$y]=$row['id'];
			if ($row['debet']>0) $transantal++;
			if ($row['kredit']>0) $transantal++;
			$eufaktnr[$y]="!@&/(=bh#jH%Tf)D"; # maa ikke vaere en vaerdi som kan risikere at vaere et relt fakturanr.
			$bilag[$y]=$row['bilag'];
			$beskrivelse[$y]=db_escape_string($row['beskrivelse']);
			$d_type[$y]=$row['d_type'];
			$debet[$y]=$row['debet'];
			$k_type[$y]=$row['k_type'];
			$kredit[$y]=$row['kredit'];
			if (!$debet[$y]) $d_type[$y]='F';
			if (!$kredit[$y]) $k_type[$y]='F';
			$faktura[$y]=db_escape_string($row['faktura']);
			$amount[$y]=$row['amount'];
			if ($row['valuta'] && $row['amount'] && ($row['debet']||$row['kredit'])) {
				list($dkkamount[$y],$diffkonto[$y],$valutakurs[$y])=valutaopslag($amount[$y],$row['valuta'],$row['transdate']);
			} else $dkkamount[$y]=$amount[$y];
			$momsfri[$y]=$row['momsfri'];
			$afd[$y]=$row['afd'];
			$ansat[$y]=$row['ansat']*1;
			$projekt[$y]=$row['projekt'];
			$valuta[$y]=$row['valuta']*1;
			$ordre_id[$y]=$row['ordre_id']*1;
			if (!isset ($valutakurs[$y])) $valutakurs[$y] = NULL;
			if (!$valutakurs[$y]) $valutakurs[$y]=100;
			$transdate[$y]=$row['transdate'];
			$forfaldsdate[$y]=$row['forfaldsdate'];
			$betal_id[$y]=$row['betal_id'];
			if (!isset ($bilag[$y-1])) $bilag[$y-1] = NULL;
			if ($bilag[$y]==$bilag[$y-1]) {
				if ($valuta[$y]!='DKK') {
					$b_afd[$b_antal]=$afd[$y];
					$b_ansat[$b_antal]=$ansat[$y];
					$b_projekt[$b_antal]=$projekt[$y];
					$b_valuta[$b_antal]=$valuta[$y];
					$b_ordre_id[$b_antal]=$ordre_id[$y];
					$b_kurs[$b_antal]=$valutakurs[$y];
					$b_diffkonto[$b_antal]=$diffkonto[$y];
				}
				if ($row['debet']) {
					$b_sum[$b_antal]+=$dkkamount[$y];
					$b_diff+=$dkkamount[$y];
					$bv_sum[$b_antal]+=$amount[$y];
					$bv_diff+=$amount[$y];
				}
				if ($row['kredit']) {
					$b_sum[$b_antal]-=$dkkamount[$y];
					$b_diff-=$dkkamount[$y];
					$bv_sum[$b_antal]-=$amount[$y];
					$bv_diff-=$amount[$y];
				}
			} else {
				$b_antal++;
				$b_bilag[$b_antal]=$bilag[$y];
				$b_sum[$b_antal]=0;
				$b_faktura[$b_antal]=$faktura[$y];
				$b_transdate[$b_antal]=$transdate[$y];
				if ($valuta[$y]!='DKK') {
					$b_afd[$b_antal]=$afd[$y];
					$b_ansat[$b_antal]=$ansat[$y];
					$b_projekt[$b_antal]=$projekt[$y];
					$b_valuta[$b_antal]=$valuta[$y];
					$b_ordre_id[$b_antal]=$ordre_id[$y];
					$b_kurs[$b_antal]=$valutakurs[$y];
					$b_diffkonto[$b_antal]=$diffkonto[$y];
				}
				if ($row['debet']) {
					$b_sum[$b_antal]+=$dkkamount[$y];
					$b_diff+=$dkkamount[$y];
					$bv_sum[$b_antal]+=$amount[$y];
					$bv_diff+=$amount[$y];
				}
				if ($row['kredit']) {
					$b_sum[$b_antal]-=$dkkamount[$y];
					$b_diff-=$dkkamount[$y];
					$bv_sum[$b_antal]-=$amount[$y];
					$bv_diff-=$amount[$y];
				}
#				if ($row['debet'])  $b_sum[$b_antal]+=$dkkamount[$y];
#				if ($row['kredit']) $b_sum[$b_antal]-=$dkkamount[$y];
			}
			if (((strstr($d_type[$y],'D'))||(strstr($d_type[$y],'K'))) && $debet[$y]>0) {
				if (!$simuler) openpost($d_type[$y], $debet[$y], $bilag[$y], $faktura[$y], $amount[$y], $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y]);
				list ($debet[$y], $d_momsart[$y]) =gruppeopslag($d_type[$y], $debet[$y]);
				if (($d_momsart[$y]=='E')||($d_momsart[$y]=='Y')) $eufaktnr[$y]=$faktura[$y]; # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			}
			if ((($k_type[$y]=='D')||($k_type[$y]=='K')) && $kredit[$y]>0) {
				if (!$simuler) openpost($k_type[$y], $kredit[$y], $bilag[$y], $faktura[$y], $amount[$y]*-1, $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y]);
				list ($kredit[$y], $k_momsart[$y])=gruppeopslag($k_type[$y], $kredit[$y]);
				if (($k_momsart[$y]=='E')||($k_momsart[$y]=='Y')) $eufaktnr[$y]=$faktura[$y];  # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			}
			$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
			$debet[$y]=str_replace(" ","",$debet[$y]);
			$kredit[$y]=str_replace(" ","",$kredit[$y]);
			$d_amount[$y]=$d_moms[$y]=$d_momskto[$y]=$d_modkto[$y]=0;
			$k_amount[$y]=$k_moms[$y]=$k_momskto[$y]=$k_modkto[$y]=0;
			if ($debet[$y]>0)  $d_amount[$y]=$dkkamount[$y];
			if ($kredit[$y]>0) $k_amount[$y]=$dkkamount[$y];
			$logdate=date("Y-m-d");
			$logtime=date("H:i");
			list ($x, $month, $x)=explode('-', $transdate[$y]);
			if (!$afd[$y]){$afd[$y]=0;}
			if (!isset ($d_momsart[$y])) $d_momsart[$y] = NULL;
			if (!isset ($k_momsart[$y])) $k_momsart[$y] = NULL;
			if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)&&(substr($momsart,0,1)!='E')&&(substr($momsart,0,1)!='Y')) list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y]);
			if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)&&(substr($momsart,0,1)!='E')&&(substr($momsart,0,1)!='Y')) list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y]);
		} elseif (!$row['debet'] && !$row['kredit'] && $row['id']) { #20170516
			db_modify("delete from kassekladde where id = '$row[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
	# end while
	$posteringer=$y;
	for ($y=1; $y<=$posteringer; $y++) {
		$d_moms[$y]*=1;
		$k_moms[$y]*=1;
		if (($d_modkto[$y]>0)&&($eufaktnr[$y]!=$faktura[$y])){
			if ($k_moms[$y]) {
				$posteringer++;
				$k_momskto[$posteringer]=$k_momskto[$y];
				$k_moms[$posteringer]=$k_moms[$y];
				$bilag[$posteringer]=$bilag[$y];
				$beskrivelse[$posteringer]=$beskrivelse[$y];
				$faktura[$posteringer]=$faktura[$y];
				$afd[$posteringer]=$afd[$y];
				$transdate[$posteringer]=$transdate[$y];
				$ansat[$posteringer]=$ansat[$y];
				$projekt[$posteringer]=$projekt[$y];
				$ordre_id[$posteringer]=$ordre_id[$y];
				$valutakurs[$posteringer]=$valutakurs[$y];
				$valuta[$posteringer]=$valuta[$y];
			}
			$k_moms[$y]=$d_moms[$y];
			$k_momskto[$y]=$d_modkto[$y];
			$transantal++;
		}
			
		if (($k_modkto[$y]>0)&&($eufaktnr[$y]!=$faktura[$y])){
			if ($d_moms[$y]) { 
				$posteringer++;
				$d_momskto[$posteringer]=$d_momskto[$y];
				$d_moms[$posteringer]=$d_moms[$y];
				$bilag[$posteringer]=$bilag[$y];
				$beskrivelse[$posteringer]=$beskrivelse[$y];
				$faktura[$posteringer]=$faktura[$y];
				$afd[$posteringer]=$afd[$y];
				$transdate[$posteringer]=$transdate[$y];
				$transdate[$posteringer]=$transdate[$y];
				$ansat[$posteringer]=$ansat[$y];
				$projekt[$posteringer]=$projekt[$y];
				$ordre_id[$posteringer]=$ordre_id[$y];
				$valutakurs[$posteringer]=$valutakurs[$y];
				$valuta[$posteringer]=$valuta[$y];
			}
			$d_moms[$y]=$k_moms[$y];
			$d_momskto[$y]=$k_modkto[$y];
			$transantal++;
		}
		if ($d_momskto[$y]>0) $transantal++;
		
		if ($k_momskto[$y]>0) $transantal++; 
		if ($eufaktnr[$y]!=$faktura[$y]&&$d_momskto[$y]>0&&$k_momskto[$y]>0&&$d_momskto[$y]!=$k_momskto[$y]) $transantal--; # indsat 280807 grundet fejl ved konti (i kontoplan) m. eumoms 
		if ($debet[$y]>0) {
			#$tjeksum=$tjeksum+$d_amount[$y];
			($d_momskto[$y])?$tmp=$d_moms[$y]*1:$tmp=0;
			$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,faktura,";
			$qtxt.= "kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= "values ";
			$qtxt.= "($debet[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$d_amount[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]', '$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','$tmp')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr='$debet[$y]' and bilag='$bilag[$y]' and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and debet='$d_amount[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id='$kladde_id' and afd='$afd[$y]'";
			if (db_fetch_array(db_select($qtxt ,__FILE__ . " linje " . __LINE__))) {
				$transtjek++;
				$qtxt = "select id, saldo from kontoplan where kontonr='$debet[$y]' and regnskabsaar=$regnaar";
				$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$kasklid[$transtjek]=$row['id'];
				$kasklmonth[$transtjek]=$row['saldo'];
				$transamount[$transtjek]=$d_amount[$y];
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], debetkonto: $debet[$y]!</td></tr>";
		}
		if ($kredit[$y]>0) {
			#$tjeksum=$tjeksum-$k_amount[$y];
			($k_momskto[$y])?$tmp=$k_moms[$y]*-1:$tmp=0;
			$qtxt = "insert into $tabel "; 
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,kredit,faktura,";
			$qtxt.= "kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= " values ";
			$qtxt.= "($kredit[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$k_amount[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]','$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','$tmp')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr='$kredit[$y]' and bilag=$bilag[$y] and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and kredit='$k_amount[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$transtjek++;
				$qtxt = "select id, saldo from kontoplan where kontonr='$kredit[$y]' and regnskabsaar=$regnaar";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$r = db_fetch_array($q);
				$kasklid[$transtjek]=$r['id'];
				$kasklmonth[$transtjek]=$r['saldo'];
				$transamount[$transtjek]=$k_amount[$y]*-1;
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], kreditkonto: $kredit[$y]!</td></tr>";
		}
		
		if ($d_momskto[$y]>0) { #moms af debetpostering 
			$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,faktura,";
			$qtxt.= "kladde_id,afd,ansat, projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= " values ";
			$qtxt.= "($d_momskto[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$d_moms[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]','$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','0')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr=$d_momskto[$y] and bilag=$bilag[$y] and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and debet='$d_moms[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ( db_fetch_array($q)) {
				$transtjek++;
				$qtxt = "select id, saldo from kontoplan where kontonr='$d_momskto[$y]' and regnskabsaar=$regnaar";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$r= db_fetch_array($q);
				$kasklid[$transtjek]=$r['id'];
				$kasklmonth[$transtjek]=$r['saldo'];
				$transamount[$transtjek]=$d_moms[$y];
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], debetkonto: $d_momskto[$y]!</td></tr>";
		}
		if ($k_momskto[$y]>0) { #moms af kreditpostering
						$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,kredit,faktura,";
			$qtxt.= "kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= " values ";
			$qtxt.= "($k_momskto[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$k_moms[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]','$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','0')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr=$k_momskto[$y] and bilag=$bilag[$y] and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and kredit='$k_moms[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if (db_fetch_array($q)){
				$transtjek++;
				$query = db_select("select id, saldo from kontoplan where kontonr='$k_momskto[$y]' and regnskabsaar=$regnaar",__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$kasklid[$transtjek]=$row['id'];
				$kasklmonth[$transtjek]=$row['saldo'];
				$transamount[$transtjek]=$k_moms[$y]*-1;
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], kreditkonto: $k_momskto[$y]!</td></tr>";
		}
	}
	if ($b_diff) {
		$kontoliste=array();
		# 20131115 ->
		$kontokredit[$y]=array(); 
		$kontodebet[$y]=array();  
		for ($i=1;$i<=$b_antal;$i++) {
			if ($bilag[$i]!=$bilag[$i-1] && $bilag[$i]!=$bilag[$i+1] && (!$debet[$i]||!$kredit[$i]) && $valuta[$i] != $valuta[$i-1]) { # 20131117 -- 20140228 tilføjet: && $valuta[$i] != $valuta[$i-1] 
				print "<BODY onLoad=\"javascript:alert('Manglende modpostering i bilag $bilag[$i]!')\">";
				exit;
			}
		# <- 20131115
			if (!isset($valutasum[$i])) $valutasum[$i] = 0;
			$valutasum[$i]=afrund($valutasum[$i],3);
			$CurencyCheckSum+=$valutasum[$i];
			$b_sum[$i]=afrund($b_sum[$i],2);
			if (!in_array($b_diffkonto[$i],$kontoliste)) { 
				$y++;
				$kontoliste[$y]=$b_diffkonto[$i];
				$kontokredit[$y]=0;
				$kontodebet[$y]=0;
			}
			if ($b_sum[$i] > 0) {
				$k_sum=$k_sum+$b_sum[$i];
				$kontokredit[$y]=$kontokredit[$y]+$b_sum[$i];
				$tjeksum=$tjeksum+$b_sum[$i]; #20141128
			} else {
				$d_sum=$d_sum-$b_sum[$i];
				$kontodebet[$y]=$kontodebet[$y]-$b_sum[$i];
				$tjeksum=$tjeksum+$b_sum[$i]; #20150527
			}

			if ($b_sum[$i] && abs($b_sum[$i]) < 0.1 && !$bvSum[$i] && $b_diffkonto[$i]) {
					$debet=$kredit=0;
					$beskrivelse ='valutadiff';
					($b_sum[$i] < 0)?$debet=$b_sum[$i]:$kredit=$b_sum[$i];
					$qtxt = "insert into $tabel "; 
					$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,";
					$qtxt.= "valuta,valutakurs,ordre_id,moms)";
					$qtxt.= " values ";
					$qtxt.= "('$b_diffkonto[$i]','$b_bilag[$i]','$b_transdate[$i]','$logdate','$logtime','$beskrivelse','$debet',";
					$qtxt.= "'$kredit','$b_faktura[$i]','$kladde_id','$b_afd[$i]','$b_ansat[$i]','$b_projekt[$i]','DKK',";
					$qtxt.= "'100','$b_ordre_id[$i]','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$tjeksum -= $b_sum[$i];
/*
				} elseif (($kontokredit[$y] || $kontodebet[$y]) && $b_diffkonto[$i]) {
					$qtxt="select * from kontoplan where kontonr='$b_diffkonto[$i]' and regnskabsaar='$regnaar'";
					$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) {
						$saldo=$row['saldo'];
						$a=dkdecimal($saldo);
						$b=dkdecimal($kontodebet[$y]);
						$c=dkdecimal($kontokredit[$y]);
						$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
						$beskrivelse=db_escape_string($row['beskrivelse']);
						$qtxt = "insert into $tabel "; 
						$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,";
						$qtxt.= "valuta,valutakurs,ordre_id,moms)";
						$qtxt.= " values ";
						$qtxt.= "('$b_diffkonto[$i]','$b_bilag[$i]','$b_transdate[$i]','$logdate','$logtime','$beskrivelse','$kontodebet[$y]',";
						$qtxt.= "'$kontokredit[$y]','$b_faktura[$i]','$kladde_id','$b_afd[$i]','$b_ansat[$i]','$b_projekt[$i]','$b_valuta[$i]',";
						$qtxt.= "'$b_kurs[$i]','$b_ordre_id[$i]','0')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);

					} else {
						print "<BODY onLoad=\"javascript:alert('Konto $b_diffkonto[$i] til valutadifferncer eksisterer ikke!')\">";
						exit;
					} 
*/					
				} elseif (($kontokredit[$y] || $kontodebet[$y]) && !$b_diffkonto[$i] && $valuta[$i])  { #20131028 -- 20140228 tilføjet: && $valuta[$i
				print "<BODY onLoad=\"javascript:alert('Manglende konto til valutadiffencer! (bilag: $b_bilag[$i])')\">";
				exit;
			} 
		}
	}
	if (abs($tjeksum)<=0.01) { # && $transtjek==$transantal){
		$dato=date("Y-m-d");
		if ($simuler) {
			$qtxt="update kladdeliste set bogfort = 'S', bogforingsdate = '$dato', bogfort_af = '$brugernavn' where id = '$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt="delete from kassekladde where debet='0' and kredit='0' and kladde_id='$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="update kladdeliste set bogfort = 'V', bogforingsdate = '$dato', bogfort_af = '$brugernavn' where id = '$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			for ($x=1; $x<=$transtjek; $x++) {
				$query = db_select("select saldo from kontoplan where id='$kasklid[$x]'",__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$temp=$row['saldo'];
				if (!$temp) {$temp=0;}
				$transamount[$x]=($temp+$transamount[$x]);
				db_modify("update kontoplan set saldo = $transamount[$x] where id = '$kasklid[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
#xit;
	} else {
		print "<tr><td align=center>$font Der er konstateret en afvigelse!\nKladde ikke bogført\nKontakt venligst Saldi's udviklerteam!</td></tr>";
		exit;
	}
#xit;	
} #endfunc bogfor
######################################################################################################################################
function openpost($art,$debet,$bilag,$faktura,$amount,$beskrivelse,$transdate,$bilag_id,$valutakode,$valutakurs,$forfaldsdate,$betal_id,$projekt){
	global $connection;
	global $regnaar;
	global $kladde_id;

## Finder kreditorens valuta;
	if ($valutakode) {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valutakode'",__FILE__ . " linje " . __LINE__));
		$valuta=$r['box1'];
	} else $valuta='DKK';
	$udlignet=0;
	$dato=date("Y-m-d");
	$belob=$amount*-1;
	$debet=str_replace(" ","",$debet);
	$qtxt = "select id from adresser where kontonr = '$debet' and art ='$art'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($q)){
		$konto_id=$row['id'];
		$query = db_select("select MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
# -> 2009.05.04		
		$min=$belob-0.005; 
		$max=$belob+0.005;
		if (strpos($faktura,";")) {
			$invoices = explode(';',$faktura);
		} else $invoices[0] = $faktura; 
		
# 20121122 >>and projekt='$projekt'<< indsat herunder.
		if ($udlign_date<$transdate) $udlign_date=$transdate;
		for ($x=0;$x<count($invoices);$x++) {
			$qtxt = "select id,transdate from openpost where konto_id='$konto_id' and projekt='$projekt' and udlignet!='1' and ";
			$qtxt.= " faktnr='$invoices[$x]'";
			if (count($invoices) == 1) $qtxt.= " and amount >= '$min' and amount < '$max'";  
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ($r = db_fetch_array($q)) {
# $udlign_date infort 2011.02.22 -udligningsdato skal altid vaere seneste dato. 
				$udlign_date=$r['transdate'];
				$qtxt = "update openpost set udlignet = '1',udlign_date= '$udlign_date',udlign_id='$udlign_id' ";
				$qtxt.= "where id = '$r[id]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				// 20230626 -->
				$qtxt = "insert into openpost
				(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,udlign_date,kladde_id,";
				if ($forfaldsdate) $qtxt.= "forfaldsdate,betal_id,";
				$qtxt.= "transdate,udlign_id,bilag_id,valuta,valutakurs,projekt) "; 
				$qtxt.= "values ";
				$qtxt.= "('$konto_id','$debet','$faktura','$amount','$bilag','$beskrivelse','1','$udlign_date','$kladde_id',";
				if ($forfaldsdate) $qtxt.= "'$forfaldsdate','$betal_id',";
				$qtxt.= "'$transdate','$udlign_id','$bilag_id','$valuta','$valutakurs','$projekt')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$udlignet=1;
				// <-- 20230626
			}
		}
	}
	if ($udlignet<1) {
		if ($faktura=="-") $faktura="";
		$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,";
		if ($forfaldsdate) $qtxt.= "forfaldsdate,betal_id,";
		$qtxt.= "bilag_id,valuta,valutakurs,projekt)";
		$qtxt.= " values ('$konto_id','$debet','$faktura','$amount','$bilag','$beskrivelse','0','$transdate','$kladde_id',";
		if ($forfaldsdate) $qtxt.= "'$forfaldsdate','$betal_id',";
		$qtxt.= "'$bilag_id','$valuta','$valutakurs','$projekt')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
######################################################################################################################################
function momsberegning($konto,$amount,$momsart,$kontrol) {
	global $connection;
	global $regnaar;
	global $db;
	global $brugernavn;
	
	$nettoamount=$amount;
	$errorTxt=$moms=$momskto=$modkto=NULL;
	
	$a=substr($momsart,0,1); #Foerste tegn i strengen
	$b=substr($momsart,1,1); #Andet tegn i strengen
	
	$r=db_fetch_array(db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
	if (trim($r['moms'])) {
		if ((($a=='E')||($a=='Y')) && $b) {
			$c=$a.'M';
			$qtxt = "select box1,box2,box3 from grupper where ";
			$qtxt.= "kode='$a' and kodenr='$b' and art='$c' and fiscal_year = '$regnaar'";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if($row =	db_fetch_array($query)) { # S�er der moms p�kontoen
				$qtxt="select box1,box2,box3 from grupper where ";
				$qtxt.= "kode='$a' and kodenr='$b' and art='$c' and fiscal_year = '$regnaar'";
				$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$x=$row['box2'];
				$moms=$amount/100*$x;
				$momskto=trim($row['box1']);
				$modkto=trim($row['box3']);
			}
		} else {	
			$qtxt = "select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if($row =	db_fetch_array($query)){
				$a=substr($row['moms'],0,1);
				$b=substr($row['moms'],1);
			}
#Hvis en momspligtig vare koebes i EU beregnes der EU moms. $kontrol er kun sat hvis der er tale om en kreditor
# og nedenst&aring;ende tr&aelig;der s&aring;ledes ikke i kraft naar der er tale om en finanskonto med EU moms.
			if ($a && ($a!='E' || $a!='Y') && (substr($kontrol,0,1)=='E' || substr($kontrol,0,1)=='Y')) {
				$a=substr($kontrol,0,1);	
				$b=substr($kontrol,1.1);
			} 
			$c=$a.'M';
			$qtxt = "select box1,box2,box3 from grupper where kode='$a' and kodenr='$b' and art='$c'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if($r =	db_fetch_array($q)) { # Saa er der moms paa kontoen
				if (($a=='E' || $a=='Y') && (!$r['box1'] || !$r['box2'] || !$r['box3'])) alert("Fejl i kontoopsætning for EU moms");
				$qtxt="select box1,box2,box3 from grupper where kode='$a' and kodenr='$b' and art='$c'";
				$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$x=$r['box2'];
				if ($a=='E' || $a=='Y'){
					$moms=$amount/100*$x;
					$momskto=trim($r['box3']);
					$modkto=trim($r['box1']);
				} elseif (substr($kontrol,0,1)=='E' || substr($kontrol,0,1)=='Y'){
					$momskto=trim($r['box1']);
					$modkto=trim($r['box1']);
					$moms=$amount/100*$x;
				} else {
					$momskto=trim($r['box1']);
					$moms=$amount-($amount/((100+$x)/100));
					$nettoamount=$amount-$moms;
				}
			}
		}
	} 
# 2009.05.06 afrundingsdecimal rettet fra 3 til 2 grundet problem med Zen 
	$amount=afrund($amount,2); 
	$nettoamount=afrund($nettoamount,2); 
	$moms=afrund($moms,2);
	if ($a!='E' && $a!='Y') { #20140428
		$tmp=afrund($amount-($nettoamount+$moms),2);
		# Nedenstaaende tilfojet 20090902 jvf saldi_2_20090902-1446.sdat
		if ($tmp>0) $moms=$moms+0.01; 
		elseif ($tmp<0) $moms=$moms-0.01;
		$tmp=afrund($amount-($nettoamount+$moms),2);
		if (abs($tmp)>=0.01) { # 20140428 "fjernet $a!='E' && $a!='Y' &&" 
			$message=$db." | Afvigelse ved momsberegning | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Bogforingsfejl', $message, $headers);
			print "<BODY onLoad=\"javascript:alert('Afvigelse ved momsberegning! Kontakt venligst Saldi teamet p&aring; telefon 4690 2208')\">";
			exit;
		}	
	}
// #	$svar=array($amount,0,$momskto,$modkto);
	$svar=array($nettoamount,$moms,$momskto,$modkto);
	return $svar;
}
######################################################################################################################################
function gruppeopslag($type, $konto) {

	global $connection, $regnaar;
	$art=NULL;$momsart=NULL;
	
	if ($type=='D') $art='DG';
	elseif ($type=='K') $art='KG';
	if ($art){
	$tmp=substr($art,0,1);
		$query = db_select("select gruppe from adresser where kontonr = '$konto' and art='$tmp'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query))	{
			$query = db_select("select box1, box2 from grupper where art='$art' and kodenr='$row[gruppe]' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__);
			if ($row =db_fetch_array($query)) {	
				$konto=$row['box2'];
				$momsart=$row['box1'];
			}
		}
	}
	$svar=array($konto, $momsart);
	return $svar;
}
######################################################################################################################################
function valutaopslag($amount, $valuta, $transdate)
{
	global $connection;
	global $fejltext;
	
	$r = db_fetch_array(db_select("select * from valuta where gruppe = '$valuta' and valdate <= '$transdate' order by valdate desc",__FILE__ . " linje " . __LINE__));
	if ($r['kurs']) {
		$kurs=$r['kurs'];
		$amount=afrund($amount*$kurs/100,2); # decimal rettet fra 3 til 2 20090617 grundet fejl i saldi_58_20090617-2224
	} else {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
		$tmp=dkdato($transdate);
// 		$fejltext="---";
		print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for $r[box1] den $tmp')\">";	
	}
	$r = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
	$diffkonto=$r['box3'];
	
	return array($amount,$diffkonto,$kurs); # 3'die parameter tilfojet 2009.02.10
}
######################################################################################################################################

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>
