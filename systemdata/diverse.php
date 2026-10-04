<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverse.php -----patch 4.1.1 ----2026-09-17------------
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 2012.09.20 Tilføjet integration med ebconnect
// 2013.01.19 funktioner lagt i selvstændig fil (../includes/sys_div_func.php)
// 2013.05.23 varelaterede rettet til varerelaterede valg.
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 20131210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.04.29	Ændret teksten så siden er mere overskuelig. Claus Agerskov ca@saldi.dk
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.04.11	Tilføjet labelprint.Søg label
// 20150424 CA  Ændret link til funktionsfilen sys_div_func.php    Søg 20150424a
// 20150424 CA  Ændret link til funktionsfilen konv_lager.php      Søg 20150424b
// 20150424 CA  Benytter funktionen skriv_formtabel til formularer Søg 20150424c
// 20150612 CA  Databasehåndtering af prislister (ej afsluttet)    Søg 20150612
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg 20150907 & $saetvareid
// 20151006 PHR Labelprint ændret fra php til html og kontrol for php indsat.
// 20160116 PHR Indsat kontrol for ftp adgang v ebconnect integration
// 20160412 PHR Opdelt vare_valg i vare_valg, labels & shop_valg
// 20160601	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 20161118	PHR	Tilføjet default bord som option for kasse i funktion pos_valg. Søg bordvalg
// 20161125 PHR Indført html som formulargenerator som alternativ til postscript i funktion div_. Søg pv_box3
// 20170123 PHR Tilføjet API_valg
// 20170314 PHR POS Valg - tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 20170404 PHR ordre_valg - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 20170731 PHR Tilføjet 'Nulstil regnskab - 20170731
// 20171009 PHR Tilføjet pos_font_size under pos_valg.
// 20181029 CA  Tilføjet voucher og tilgodehavende tilknyttet id  søg 20181029
// 20181126 PHR	Variant_valg lagt i egen funktion.
// 20181126 PHR	Tilvalg - Marker vare som udgået når beholdning går i minus (vare_valg). Søg DisItemIfNeg
// 20181129 PHR	Tilføjet mulighed for at sætte tidszone i regnskabet.
// 20181216 PHR	Tilføjet 'card_enabled' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$card_enabled'
// 20190107 PHR	Tilføjet 'change_cardvalue' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$change_cardvalue'
// 20190129 PHR	(vare_valg) Changed 'Momskode for salgspriser på varekort' to 'Vis priser med moms på varekort'. Search '$vatOnItemCard'
// 20190225 MSC Rettet topmenu design og isset fejl
// 20190322 LN Added tables to be deleted when pressing the "Nulstil" button
// 20190411 LN Call funtion in chooseProvision.php to save default provision value
// 20190421 PHR Added confirmDescriptionChange, in 'vare_valg'
// 20190614 LN Added argument to the function saveProvisionForItemGroup -> $defaultProvision
// 20200827 PHR Added shop_varer & shop_addresser to truncate in 'Nulstil'
// 20190921 PHR Added confirmStockChange, in 'vare_valg'
// 20201128 PHR Added labelType in.  'Label'
// 20210110 PHR Section Vare_valg. Added commission. 
// 20210213 PHR Some cleanup
// 20210303 CA  Added reservation of consignment for Danske Fragtmænd - search dfm_
// 20210305 CA  Added the selection to use debtor number as phone number in orders - search debtor2orderphone
// 20210312 PHR Changed intern_ftp til internFTP
// 20210410 PHR Correction of minor error in alertcondition in 'vare_valg'.
// 20210513 LOE	These texts were translated but not entered here previously
// 20210801 CA  Added the selection to use order notes in ordre_valg - search orderNoteEnabled
// 20210802 LOE Translated the remaining alert texts
// 20211123 PHR added paperflow
// 20211123 PHR added paperflowId & paperflowBearer
// 20220514 PHR mailText is now removed when account is reset.  
// 20221231 PHR	sektion 'bilag' box3 (ftp passwd) is now urlencoded as it failed with special characters in password. 
// 20231228 PBLM Added mobilePay (diverse valg)
// 20240126 PBLM Added nemhandel (diverse valg)
// 20240827 LOE 'personlige_valg' readujsted to use userSettings.
// 20241220 LOE Initialized tenantID and $key to null if not set
// 20250105 PBLM Added a second file to api_valg
// 20250414 LOE Updated barcodescan location and updated some variables
// 20250513 Sawaneh add max user update in kontoindstillinger()
// 20250526 PHR 'nyt_navn' changed to 'newName'
// 20251124 PHR	modified 'betalingslister' to choose between none / debitor / kreditor / both
// 20260223 Sawaneh SD-335 added buttonname field to DFM pickup addresses
// 20260304 Sawaneh SD-369 fixed- API URL instead of duplicate Danske Fragtmænd agreement number
// 20260306 Sawaneh - Added Simple guides feature: sidebar overlay with hardcoded Finance + Scaffolding PDF links
// 20260326 Sawaneh -Added ourRefStockSwitch setting
// 20260708 NTR - Changed how we convert id1 to a int, to avoid a fatal error.
// 20260709 Sawaneh Save "Show both delivery address and Extra fields on open orders" setting (showBothAddrExtra)
// 20260710 SZ Added Settings search box (settingsSearch.php/.js/.css)
// 20260818 CL/LH Use stable Stripe settings include paths.
// 20260819 CL/NTR Label saving routed through saveLabelText() so 'Standard' also reaches the labels
//                 table that lager/labelprint.php prints from, and new labels get account_id 0.
// 20260824 CL/NTR Label deletion only removes global rows (account_id 0 or null), matching what the
//                 label editor shows.
// 20260811 Sawaneh Save 'batchExpiryEnabled' setting (batch/expiry date section on the item card)
// 20260826 CL/SZ  saveLabel now refuses to save when the label's current template isn't reproducible
//                 by the visual editor's field model - it was silently discarding formatting it
//                 doesn't understand on every save (MB-18).
// 20260915 CL/NTR Bank Integration settings button only shown when the API credentials
//                 are configured (bankIntegrationEnabled()).
// 20260916 CDX/PHR Reset additional account data and skip tables absent from the installed schema.
// 20260916 CDX/PHR Set users and active sessions to financial year 1 after reset.
// 20260917 CDX/PHR Keep settings usable when the optional bank integration helper is absent.
// 20260917 CL/LH Report a failed account reset as a message instead of an uncaught error page.
// 20260916 Sawaneh Personal settings moved to systemdata/personalSettings.php; sektion=userSettings
//                  now redirects there and the Diverse menu entry links to the new page.
// 20260916 Sawaneh Phase 3: SQL tool, API, SMTP/e-mail, integrations and import/export sections
//                  gated by their own permission keys via require_permission().

@session_start();
$s_id = session_id();

// Generate CSRF token if not already created
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
// 20260928 Sawaneh Security 4.0 (A12): every posted form in the output gets the token, also on early exits.
$diverseToken = "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token, ENT_QUOTES) . "'>";
ob_start(function ($buffer) use ($diverseToken) {
	return preg_replace_callback('/<form\b[^>]*>/i', function ($m) use ($diverseToken) {
		return (stripos($m[0], 'method') !== false && stripos($m[0], 'post') !== false) ? $m[0] . $diverseToken : $m[0];
	}, $buffer);
});
$title      = "Diverse Indstillinger";
$modulnr    = 1;
$css        = "../css/standard.css";
$diffkto    = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
if (is_file(__DIR__ . '/../bank_integration/includes/enabled.php')) {
	include_once(__DIR__ . '/../bank_integration/includes/enabled.php');
}
include("sys_div_func.php"); # 20150424a
include("skriv_formtabel.inc.php"); # 20150424c

$defaultProvision = $sqlstreng = NULL;
if (!isset($_SESSION['UserName']) && isset($brugernavn)) {
	$_SESSION['UserName'] = $brugernavn;
}

include("top.php");

if (!isset($exec_path)) $exec_path = "/usr/bin";
$sektion    = if_isset($_GET, null, 'sektion');
$pricelists = if_isset($_POST, null, 'pricelists');
if ($sektion == 'personlige_valg') $sektion = 'userSettings';
if ($sektion == 'userSettings' && $_SERVER['REQUEST_METHOD'] != 'POST') {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=personalSettings.php\">";
	exit;
}
// 20260929 Sawaneh Phase 4a (R6): sections that have landed in the generated settings redirect there;
// the toast on the new page says where the old page went (spec §8.10).
$landedSections = array(
	'ordre_valg'     => 'settingsSection.php?s=sales.orders&moved=ordre_valg',
	'massefakt'      => 'settingsSection.php?s=sales.orders&moved=massefakt#sub-mass',
	'provision'      => 'settingsSection.php?s=organisation.commission&moved=provision',
	'productOptions' => 'settingsSection.php?s=items.stock&moved=productOptions',
	'orediff'        => 'settingsSection.php?s=finance.cash_journal&moved=orediff#sub-rounding',
	'api_valg'       => 'settingsSection.php?s=integrations.connections&moved=api_valg',
	'smtp'           => 'settingsSection.php?s=documents.email&moved=smtp#sub-server',
	'rykker_valg'    => 'settingsSection.php?s=sales.reminders&moved=rykker_valg',
	'bilag'          => 'settingsSection.php?s=finance.document_storage&moved=bilag',
	'pricelists'     => 'settingsSection.php?s=purchase.pricelists&moved=pricelists',
);
if (isset($landedSections[$sektion]) && !($sektion == 'api_valg' && !empty($_GET['varesync']))) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=" . $landedSections[$sektion] . "\">";
	exit;
}
// The old "Diverse" landing list is replaced by the settings front page (phase 4).
if (!$sektion && $_SERVER['REQUEST_METHOD'] != 'POST') {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=settings.php\">";
	exit;
}
// 20260916 Phase 3 (spec R6): dangerous sections are gated by their own permission key.
// 20260928 Sawaneh Phase 4: frame switch reduced to top.php, the Diverse sub-menu column and landing list
//                  replaced by the registry-driven frame/front page, dead userSettings/personlige_valg code removed.
// 20260928 Sawaneh Key names follow the settings redesign spec (settings.integrations.keys, settings.import_export).
// 20261002 Sawaneh Keys per settings redesign §11.1: settings.integrations and settings.email; reset/delete need settings.company.danger.
// 20260928 Sawaneh Security 4.0: CSRF check on POST (token injected into every posted form), pickup debug log removed,
//                  SQL tool (Dataudtræk), DocuBizz and Paperflow removed, MobilePay/QuickPay secrets write-only.
// 20261001 Sawaneh One row per setting (unique index on settings): KDS colours saved as color_1, color_2...;
//                  item options look their row up when the form has no id.
// 20261002 Sawaneh Phase 4b batch 1: provision, productOptions and orediff moved to the generated sections (their
//                  save code is gone); div_valg no longer saves mySale, print, payment lists, payment days or voucher dates.
// 20261002 Sawaneh Phase 4b batch 2: api_valg landed in Integrationer (only the shop sync still runs here); div_valg no longer
//                  saves GLS, Danske Fragtmænd, QuickPay, MobilePay, Flatpay, Vibrant or Copayone.
// 20261003 Sawaneh G6.3: the SMTP save and the dead 'email' (MAIL/1) save are gone; smtp redirects to Dokumenter & e-mail » E-mail.
// 20261004 Sawaneh G4.3: pricelists landed in Køb » Leverandørprislister (diverseIncludes/pricelists.php is no longer reached).
// 20261004 Sawaneh G2.6: bilag landed in Finans » Bilagsopbevaring; its save code is gone.
// 20261003 Sawaneh G3.4: rykker_valg landed in Salg » Betalingsbetingelser & rykkere; its save code is gone.
// Users without a role inherit these from the Indstillinger bit, so nothing changes for
// them; a role only gets them when an administrator grants them explicitly.
$dangerousSections = array(
	'api_valg' => 'settings.integrations',
	'email' => 'settings.email',
	'smtp' => 'settings.email',
	'stripe_valg' => 'settings.integrations',
	'shop_valg' => 'settings.integrations',
	'adresser_io' => 'settings.import_export',
	'formular_io' => 'settings.import_export',
	'kontoplan_io' => 'settings.import_export',
	'solar_io' => 'settings.import_export',
	'varer_io' => 'settings.import_export',
	'variant_valg_import_types' => 'settings.import_export',
	'variant_valg_import_values' => 'settings.import_export',
);
if (isset($dangerousSections[$sektion]) && function_exists('require_permission')) {
	require_permission($dangerousSections[$sektion], ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'write' : 'read');
}
// Resetting or deleting the company is the danger zone of G1.4 (settings redesign §11.1): Administrator only.
if ($sektion == 'kontoindstillinger' && $_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['nulstil']) || isset($_POST['slet'])) && function_exists('require_permission')) {
	require_permission('settings.company.danger', 'write');
}
$skiftnavn  = if_isset($_GET['skiftnavn']);
// 20260928 Sawaneh Security 4.0 (A12): every POST to this shared entry must carry the session's
// CSRF token; the token is injected into every posted form by the output filter at the end of the file.
if ($_SERVER['REQUEST_METHOD'] == "POST" && (!isset($_POST['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']))) {
	audit_log('csrf', 'diverse.php?sektion=' . $sektion);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=" . urlencode((string) $sektion) . "\">";
	exit;
}
if ($_POST && $_SERVER['REQUEST_METHOD'] == "POST") {
	if ($sektion == 'div_valg') {
		$id          = (int) $_POST['id'];
		$box1        = '';                #kept from the stored row below (Salg → Debitorkort)
		$box2        = '';                #kept from the stored row below
		$box3        = $_POST['box3'];    #extra_ansat
		$box4        = '';                #kept from the stored row below (Finans → Kassekladde & betalinger)
		$box5        = '';                #kept from the stored row below
		$box6        = '';                #was DocuBizz - integration removed 20260928, column kept until the 4f cleanup
		$box7        = '';                #kept from the stored row below
//		$box8        = $_POST['box8'];    #ebconnect
		$box8        = '';                #kept from the stored row below
		$box9        = $_POST['box9'];    #ledig
		$box10       = '';                #kept from the stored row below
		$box12       = $_POST['box12'];
		// GLS, Danske Fragtmænd, QuickPay, MobilePay, Flatpay and Vibrant are saved by Indstillinger » Integrationer (phase 4b);
		// only the pick-up addresses are still posted here.
		// Multiple pickup addresses - now handled as arrays
		$dfm_pickup_group_ids   = isset($_POST['dfm_pickup_group_id'])   && is_array($_POST['dfm_pickup_group_id'])   ? $_POST['dfm_pickup_group_id']   : array();
		$dfm_pickup_addrs       = isset($_POST['dfm_pickup_addr'])       && is_array($_POST['dfm_pickup_addr'])       ? $_POST['dfm_pickup_addr']       : array();
		$dfm_pickup_name1s      = isset($_POST['dfm_pickup_name1'])      && is_array($_POST['dfm_pickup_name1'])      ? $_POST['dfm_pickup_name1']      : array();
		$dfm_pickup_name2s      = isset($_POST['dfm_pickup_name2'])      && is_array($_POST['dfm_pickup_name2'])      ? $_POST['dfm_pickup_name2']      : array();
		$dfm_pickup_street1s    = isset($_POST['dfm_pickup_street1'])    && is_array($_POST['dfm_pickup_street1'])    ? $_POST['dfm_pickup_street1']    : array();
		$dfm_pickup_street2s    = isset($_POST['dfm_pickup_street2'])    && is_array($_POST['dfm_pickup_street2'])    ? $_POST['dfm_pickup_street2']    : array();
		$dfm_pickup_towns       = isset($_POST['dfm_pickup_town'])       && is_array($_POST['dfm_pickup_town'])       ? $_POST['dfm_pickup_town']       : array();
		$dfm_pickup_zipcodes    = isset($_POST['dfm_pickup_zipcode'])    && is_array($_POST['dfm_pickup_zipcode'])    ? $_POST['dfm_pickup_zipcode']    : array();
		$dfm_pickup_buttonnames = isset($_POST['dfm_pickup_buttonname']) && is_array($_POST['dfm_pickup_buttonname']) ? $_POST['dfm_pickup_buttonname'] : array();
		
		$dfm_pickup_ids         = isset($_POST['dfm_pickup_id'])         && is_array($_POST['dfm_pickup_id'])         ? $_POST['dfm_pickup_id']         : array();
		$dfm_pickup_users       = isset($_POST['dfm_pickup_user'])       && is_array($_POST['dfm_pickup_user'])       ? $_POST['dfm_pickup_user']       : array();
		$dfm_pickup_passes      = isset($_POST['dfm_pickup_pass'])       && is_array($_POST['dfm_pickup_pass'])       ? $_POST['dfm_pickup_pass']       : array();
		$dfm_pickup_agrees      = isset($_POST['dfm_pickup_agree'])      && is_array($_POST['dfm_pickup_agree'])      ? $_POST['dfm_pickup_agree']      : array();
		$dfm_pickup_urls        = isset($_POST['dfm_pickup_url'])        && is_array($_POST['dfm_pickup_url'])        ? $_POST['dfm_pickup_url']        : array();
		$dfm_pickup_hubs        = isset($_POST['dfm_pickup_hub'])        && is_array($_POST['dfm_pickup_hub'])        ? $_POST['dfm_pickup_hub']        : array();
		$dfm_pickup_ships       = isset($_POST['dfm_pickup_ship'])       && is_array($_POST['dfm_pickup_ship'])       ? $_POST['dfm_pickup_ship']       : array();
		$dfm_pickup_goods       = isset($_POST['dfm_pickup_good'])       && is_array($_POST['dfm_pickup_good'])       ? $_POST['dfm_pickup_good']       : array();
		$dfm_pickup_gooddess    = isset($_POST['dfm_pickup_gooddes'])    && is_array($_POST['dfm_pickup_gooddes'])    ? $_POST['dfm_pickup_gooddes']    : array();
		$dfm_pickup_pays        = isset($_POST['dfm_pickup_pay'])        && is_array($_POST['dfm_pickup_pay'])        ? $_POST['dfm_pickup_pay']        : array();
		$dfm_pickup_sercodes    = isset($_POST['dfm_pickup_sercode'])    && is_array($_POST['dfm_pickup_sercode'])    ? $_POST['dfm_pickup_sercode']    : array();
		
		// if ($box8) {
		// 	ftptest($_POST['oiourl'], $_POST['oiobruger'], $_POST['oiokode']);
		// 	$box8 = $_POST['oiourl'] . chr(9) . $_POST['oiobruger'] . chr(9) . $_POST['oiokode'];
		// }
		if (($id == 0) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='2'", __FILE__ . " linje " . __LINE__))))
			$id = $r['id'];
		if ($keep = db_fetch_array(db_select("select box1, box2, box4, box5, box7, box8, box10 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__))) {
			foreach (array('box1', 'box2', 'box4', 'box5', 'box7', 'box8', 'box10') as $kept) {
				$$kept = $keep[$kept];
			}
		}
		if ($id == 0) {
			// db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12')", __FILE__ . " linje " . __LINE__);
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12')", __FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			// db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
			$qtxt = "update grupper set  ";
			$qtxt.= "box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12' ";
			$qtxt.= "WHERE id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		
		// Handle multiple DFM pickup addresses
		// First, get all existing group_ids from DFM_Pickup to know which to delete
		$existing_group_ids = array();
		$qtxt = "select distinct group_id from settings where var_grp='DFM_Pickup'";
		$q    = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$existing_group_ids[] = $r['group_id'];
		}
		
		// Track which group_ids we're keeping/updating
		$updated_group_ids = array();
		
		// Process each submitted pickup address
		if (is_array($dfm_pickup_group_ids)) {
			foreach ($dfm_pickup_group_ids as $idx => $group_id) {
				// Skip if no name1 provided (empty entry)
				if (empty($dfm_pickup_name1s[$idx])) continue;
				
				// Determine the actual group_id to use
				if (strpos((string)$group_id, 'new_') === 0) {
					// New address - find the next available group_id
					$qtxt = "select coalesce(max(group_id), 0) + 1 as next_id from settings where var_grp='DFM_Pickup'";
					$r    = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					$actual_group_id = $r['next_id'];
				} else {
					$actual_group_id = intval($group_id);
				}
				
				$updated_group_ids[] = $actual_group_id;
				
				// Pickup address fields to save
				$pickup_fields = array(
					'dfm_pickup_addr'       => isset($dfm_pickup_addrs[$idx])       ? $dfm_pickup_addrs[$idx]       : '1',
					'dfm_pickup_name1'      => isset($dfm_pickup_name1s[$idx])      ? $dfm_pickup_name1s[$idx]      : '',
					'dfm_pickup_name2'      => isset($dfm_pickup_name2s[$idx])      ? $dfm_pickup_name2s[$idx]      : '',
					'dfm_pickup_street1'    => isset($dfm_pickup_street1s[$idx])    ? $dfm_pickup_street1s[$idx]    : '',
					'dfm_pickup_street2'    => isset($dfm_pickup_street2s[$idx])    ? $dfm_pickup_street2s[$idx]    : '',
					'dfm_pickup_town'       => isset($dfm_pickup_towns[$idx])       ? $dfm_pickup_towns[$idx]       : '',
					'dfm_pickup_zipcode'    => isset($dfm_pickup_zipcodes[$idx])    ? $dfm_pickup_zipcodes[$idx]    : '',
					'dfm_pickup_buttonname' => isset($dfm_pickup_buttonnames[$idx]) ? $dfm_pickup_buttonnames[$idx] : '',
					'dfm_id'                => isset($dfm_pickup_ids[$idx])         ? $dfm_pickup_ids[$idx]         : '',
					'dfm_user'              => isset($dfm_pickup_users[$idx])       ? $dfm_pickup_users[$idx]       : '',
					'dfm_pass'              => isset($dfm_pickup_passes[$idx])      ? $dfm_pickup_passes[$idx]      : '',
					'dfm_agree'             => isset($dfm_pickup_agrees[$idx])      ? $dfm_pickup_agrees[$idx]      : '',
					'dfm_url'               => isset($dfm_pickup_urls[$idx])        ? $dfm_pickup_urls[$idx]        : '',
					'dfm_hub'               => isset($dfm_pickup_hubs[$idx])        ? $dfm_pickup_hubs[$idx]        : '',
					'dfm_ship'              => isset($dfm_pickup_ships[$idx])       ? $dfm_pickup_ships[$idx]       : '',
					'dfm_good'              => isset($dfm_pickup_goods[$idx])       ? $dfm_pickup_goods[$idx]       : '',
					'dfm_gooddes'           => isset($dfm_pickup_gooddess[$idx])    ? $dfm_pickup_gooddess[$idx]    : '',
					'dfm_pay'               => isset($dfm_pickup_pays[$idx])        ? $dfm_pickup_pays[$idx]        : '',
					'dfm_sercode'           => isset($dfm_pickup_sercodes[$idx])    ? $dfm_pickup_sercodes[$idx]    : ''
				);
				
				foreach ($pickup_fields as $field_name => $field_value) {
					$escaped_value = db_escape_string($field_value);
					$qtxt = "select id from settings where var_grp='DFM_Pickup' and var_name='$field_name' and group_id='$actual_group_id'";
					if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
						$qtxt = "update settings set var_value='$escaped_value' where id='$r[id]'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					} else {
						$qtxt = "insert into settings (var_grp, var_name, var_value, var_description, group_id) values ";
						$qtxt .= "('DFM_Pickup', '$field_name', '$escaped_value', 'DFM pickup address field', '$actual_group_id')";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		
		// Delete pickup addresses that were removed (not in updated list)
		foreach ($existing_group_ids as $old_group_id) {
			if (!in_array($old_group_id, $updated_group_ids)) {
				$qtxt = "delete from settings where var_grp='DFM_Pickup' and group_id='$old_group_id'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		#######################################################################################
	} elseif ($sektion == 'ordre_valg') {
		$vatPrivateCustomers  = if_isset($_POST['vatPrivateCustomers']);
		$vatBusinessCustomers = if_isset($_POST['vatBusinessCustomers']);
		$box2                 = if_isset($_POST['box2']); #Rabatvarenr
		$box3                 = if_isset($_POST['box3']); #folge_s_tekst
		$box4                 = if_isset($_POST['box4']); #hurtigfakt
		$box5                 = if_isset($_POST['straks_deb']) . ";" . if_isset($_POST['straks_kred']); #straks_bogf
		$box6                 = if_isset($_POST['box6']); #fifo
		$box7                 = if_isset($_POST['box7']); #
		$box8                 = if_isset($_POST['box8']); #vis_nul_lev
		$box9                 = if_isset($_POST['box9']); #negativt_lager
		$box10                = if_isset($_POST['box10']); #
		$box11                = if_isset($_POST['box11']); #advar_lav_beh
		$box12                = if_isset($_POST['box12']); #$procentfakt
		$box13                = if_isset($_POST['procenttillag']) . chr(9) . if_isset($_POST['procentvare']);
		$box14                = if_isset($_POST['box14']);
		$rabatvarenr          = if_isset($_POST['rabatvarenr']);
		$kostmetode           = if_isset($_POST['kostmetode']);
		$saetvarenr           = if_isset($_POST['saetvarenr']); #20150907
		$orderNoteEnabled     = if_isset($_POST, null, 'orderNoteEnabled');
		$debitoripad          = if_isset($_POST, null, 'debitoripad');
		$portovarenr          = if_isset($_POST, null, 'portovarenr');
		$showDB               = if_isset($_POST, null, 'showDB');
		$showDG               = if_isset($_POST, null, 'showDG');
		$pluklisteEmail       = if_isset($_POST, null, 'pluklisteEmail');
		$lockPayment          = if_isset($_POST["lockPayment"]);
		$ordreAutocomplete    = if_isset($_POST, null, 'ordreAutocomplete');
		$gs1parsing           = if_isset($_POST, null, 'gs1_parsing');
		$ourRefStockSwitch    = if_isset($_POST, null, 'ourRefStockSwitch');
		$stockWarningEnabled  = if_isset($_POST, null, 'stockWarningEnabled');
		
		$showBothAddrExtra    = if_isset($_POST, null, 'showBothAddrExtra');


		update_settings_value("debitoripad", "ordre", $debitoripad, "Weather or not to include the debitor ipad system");
		update_settings_value("pluklisteEmail", "ordre", $pluklisteEmail, "Email address to send plukliste to");
		update_settings_value("porto_varnr", "ordre", $portovarenr, "Varenr to autmatically include on new orders");
		update_settings_value("showDB", "ordre", $showDB, "Weather or not to show the DB on the order page");
		update_settings_value("showDG", "ordre", $showDG, "Weather or not to show the DG on the order page");
		update_settings_value("lockedInvoiceButton", "debitor", $lockPayment, "Locks the invoice button until payment has occured");
		update_settings_value("ordreAutocomplete", "ordre", $ordreAutocomplete, "Enable or disable autocomplete search on order pages", $bruger_id);
		update_settings_value("gs1_parsing", "ordre", $gs1parsing, "Enable GS1 barcode parsing on order line item entry");
		update_settings_value("ourRefStockSwitch", "ordre", $ourRefStockSwitch, "Update order stock/warehouse from Our ref when the reference changes"); // Removed single quotes from description to avoid SQL syntax error
		update_settings_value("stockWarningEnabled", "ordre", $stockWarningEnabled, "Show popup and require approval note when selling out-of-stock items (POS + Debtor/Order)");
		update_settings_value("showBothAddrExtra", "ordre", $showBothAddrExtra, "Show both delivery address and extra fields simultaneously on open orders");
		if ($box2 && $r = db_fetch_array(db_select("select id from varer WHERE varenr = '$box2'", __FILE__ . " linje " . __LINE__))) {
			$box2 = $r['id'];
		} elseif ($box2) {
			$txt = str_replace('XXXXX', $box2, findtekst('289|Varenr. XXXXX eksisterer ikke.', $sprog_id));
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($box14 && !$box2) {
			$txt = findtekst('1875|Samlet pris forudsætter at der er et varenr. til rabat', $sprog_id); #20210820
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			$box14 = '';
		}
		#20150907 ->
		$saetvareid = 0;
		$qtxt = "select id from varer WHERE varenr = '$saetvarenr'";
		if ($saetvarenr && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
			$saetvareid = $r['id'];
		if ($saetvarenr && !$saetvareid) {
			$txt = findtekst('1876|Varenummer for sæt eksisterer ikke', $sprog_id);
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
/*
		if ($kostmetode) {
			if ($r=db_fetch_array(db_select("select id from grupper WHERE art = 'VG' and box1 != box2",__FILE__ . " linje " . __LINE__))) {
				$txt = findtekst(733, $sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/konv_lager.php\">\n"; # 20140424b
				exit;
			}
		}
*/
		# <- 20150907
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='3'", __FILE__ . " linje " . __LINE__))) {
			$id   = $r['id'];
			$qtxt = "update grupper set  box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',";
			$qtxt.= "box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',";
			$qtxt.= "box14='$box14' WHERE id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,";
			$qtxt.= "box12,box13,box14) values ('Div_valg (Ordrer)','3','DIV','$box2','$box3','$box4','$box5','$box6',";
			$qtxt.= "'$box7','$box8','$box9','$box10','$box11','$box12','$box13','$box14')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		
		// Save VAT options to settings table
		update_settings_value("vatPrivateCustomers", "ordre", $vatPrivateCustomers, "Show VAT on orders for private customers");
		update_settings_value("vatBusinessCustomers", "ordre", $vatBusinessCustomers, "Show VAT on orders for business customers");
		
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'", __FILE__ . " linje " . __LINE__))) {
			$id = $r['id'];
			db_modify("update grupper set box6='$kostmetode',box8='$saetvareid' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13) ";
			$qtxt.= "values ('Div_valg','5','DIV','','','','','','','$kostmetode','','','','','','')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		if ($r = db_fetch_array(db_select("select id from settings where var_name='orderNoteEnabled'", __FILE__ . " linje " . __LINE__))) { #20210729
			$id   = $r['id'];
			$qtxt = "update settings set var_value='$orderNoteEnabled' WHERE id='$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into settings (var_name, var_value) values ('orderNoteEnabled','$orderNoteEnabled')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		update_settings_value("debitoripad", "ordre", $debitoripad, "Weather or not to include the debitor ipad system");

		#######################################################################################
	} elseif ($sektion == 'variant_valg') {
		$id                   = if_isset($_POST['id']);
		$variant_beskrivelse  = if_isset($_POST['variant_beskrivelse']);
		$variant_id           = if_isset($_POST['variant_id']);
		$var_type_beskrivelse = if_isset($_POST['var_type_beskrivelse']);
		$variant_antal        = if_isset($_POST['variant_antal']);
		$rename_varianter     = if_isset($_POST['rename_varianter']);
		$rename_var_type      = if_isset($_POST['rename_var_type']);
		if ($rename_var_type) {
			db_modify("update variant_typer set  beskrivelse='$var_type_beskrivelse' WHERE id = '$rename_var_type'", __FILE__ . " linje " . __LINE__);
		} elseif ($rename_varianter) {
			db_modify("update varianter set  beskrivelse='$variant_beskrivelse' WHERE id = '$rename_varianter'", __FILE__ . " linje " . __LINE__);
		} elseif ($variant_beskrivelse)
			db_modify("insert into varianter (beskrivelse) values ('$variant_beskrivelse')", __FILE__ . " linje " . __LINE__);
		for ($x = 1; $x <= $variant_antal; $x++) {
			if ($var_type_beskrivelse[$x] && $variant_id[$x])
				db_modify("insert into variant_typer (beskrivelse,variant_id) values ('$var_type_beskrivelse[$x]','$variant_id[$x]')", __FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion == 'variant_valg_import_types') {
		// Import variant types (main categories like Color, Size)
		$imported = 0;
		$skipped  = 0;
		if (isset($_FILES['variant_types_file']) && $_FILES['variant_types_file']['error'] == 0) {
			$filnavn = $_FILES['variant_types_file']['tmp_name'];
			if (($handle = fopen($filnavn, "r")) !== FALSE) {
				while (($line = fgets($handle)) !== FALSE) {
					$line = trim($line);
					// Handle both semicolon and comma separated, but expect single column
					$parts = preg_split('/[;,\t]/', $line);
					$variant_name = trim($parts[0]);
					
					// Convert encoding if needed
					if ($db_encode == "UTF8" && !mb_check_encoding($variant_name, 'UTF-8')) {
						$variant_name = mb_convert_encoding($variant_name, 'UTF-8', 'ISO-8859-1');
					}
					
					if (!empty($variant_name)) {
						// Check if variant already exists
						$escaped_name = db_escape_string($variant_name);
						$existing = db_fetch_array(db_select("SELECT id FROM varianter WHERE LOWER(beskrivelse) = LOWER('$escaped_name')", __FILE__ . " linje " . __LINE__));
						if (!$existing) {
							db_modify("INSERT INTO varianter (beskrivelse) VALUES ('$escaped_name')", __FILE__ . " linje " . __LINE__);
							$imported++;
						} else {
							$skipped++;
						}
					}
				}
				fclose($handle);
			}
		}
		$_SESSION['variant_import_message'] = "Importeret: $imported varianttyper. Sprunget over (eksisterer allerede): $skipped";
		header("Location: diverse.php?sektion=variant_valg");
		exit;
	#######################################################################################
	} elseif ($sektion == 'variant_valg_import_values') {
		// Import variant values (values for existing types like Red, Blue for Color)
		$imported  = 0;
		$skipped   = 0;
		$not_found = 0;
		if (isset($_FILES['variant_values_file']) && $_FILES['variant_values_file']['error'] == 0) {
			$filnavn = $_FILES['variant_values_file']['tmp_name'];
			if (($handle = fopen($filnavn, "r")) !== FALSE) {
				while (($line = fgets($handle)) !== FALSE) {
					$line = trim($line);
					// Expect format: variant_name;value or variant_name,value
					$parts = preg_split('/[;,\t]/', $line, 2);
					if (count($parts) >= 2) {
						$variant_name = trim($parts[0]);
						$value_name = trim($parts[1]);
						
						// Convert encoding if needed
						if ($db_encode == "UTF8") {
							if (!mb_check_encoding($variant_name, 'UTF-8')) {
								$variant_name = mb_convert_encoding($variant_name, 'UTF-8', 'ISO-8859-1');
							}
							if (!mb_check_encoding($value_name, 'UTF-8')) {
								$value_name = mb_convert_encoding($value_name, 'UTF-8', 'ISO-8859-1');
							}
						}
						
						if (!empty($variant_name) && !empty($value_name)) {
							// Find the variant by name
							$escaped_variant = db_escape_string($variant_name);
							$variant = db_fetch_array(db_select("SELECT id FROM varianter WHERE LOWER(beskrivelse) = LOWER('$escaped_variant')", __FILE__ . " linje " . __LINE__));
							
							if ($variant) {
								$variant_id = $variant['id'];
								$escaped_value = db_escape_string($value_name);
								
								// Check if value already exists for this variant
								$existing = db_fetch_array(db_select("SELECT id FROM variant_typer WHERE variant_id = '$variant_id' AND LOWER(beskrivelse) = LOWER('$escaped_value')", __FILE__ . " linje " . __LINE__));
								if (!$existing) {
									db_modify("INSERT INTO variant_typer (beskrivelse, variant_id) VALUES ('$escaped_value', '$variant_id')", __FILE__ . " linje " . __LINE__);
									$imported++;
								} else {
									$skipped++;
								}
							} else {
								$not_found++;
							}
						}
					}
				}
				fclose($handle);
			}
		}
		$_SESSION['variant_import_message'] = "Importeret: $imported værdier. Sprunget over (eksisterer allerede): $skipped. Variant ikke fundet: $not_found";
		header("Location: diverse.php?sektion=variant_valg");
		exit;
	#######################################################################################
	} 
	
// 	elseif ($sektion == 'shop_valg') {
// 		$id = if_isset($_POST['id']);
// #		$box1 = if_isset($_POST['box1']);   #incl_moms (legacy - not used for VAT anymore)
// 		$box2 = if_isset($_POST['box2']);   #Shop url
// 		$box3 = if_isset($_POST['box3']);   #shop valg
// 		$box4 = if_isset($_POST['box4']);   #merchant id
// 		$box5 = if_isset($_POST['box5']);   #md5 secret
// #		$box6 = if_isset($_POST['box6']);   #Bruges ved productOptions
// 		$box7 = if_isset($_POST['box7']);   #Tegnsæt for webshop
// #		$box8 = if_isset($_POST['box8']);   #Bruges ved ordre_valg
// 		$box9 = if_isset($_POST['box9']);   #Agreement ID
// 		$box10 = if_isset($_POST['box10']); #ledig

// 		if ($box3 == '1')
// 			$box2 = '!';
// 		$qtxt = NULL;
// 		if ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'", __FILE__ . " linje " . __LINE__))))
// 			$id = $r['id'];
// 		if (!$id) {
// 			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box3,box4,box5,box7,box9) values ('Div_valg (Varer)','5','DIV','$box2','$box3','$box4','$box5','$box7','$box9')";
// 		} elseif ($id > 0) {
// 			$qtxt = "update grupper set box2='$box2',box3='$box3',box4='$box4',box5='$box5',box7='$box7',box9='$box9' WHERE id = '$id'";
// 		}
// 		if ($qtxt)
// 			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
// 		#######################################################################################
// 	} 
	elseif ($sektion == 'stripe_valg') {
		include_once(__DIR__ . '/diverseIncludes/stripeValg.php');
		stripeValgSave();
		#######################################################################################
	} elseif ($sektion == 'labels') {
		// Generate template from form data
		$valg           = if_isset($_GET['valg']);
    	$labelName      = if_isset($_POST['labelName']);
    	$newLabelName   = if_isset($_POST['newLabelName']);
    	$labelTemplate  = if_isset($_POST['labelTemplate']);
    	$saveLabel      = if_isset($_POST['saveLabel']);
    	$saveRawHTML    = if_isset($_POST['saveRawHTML']);
    	$deleteLabel    = if_isset($_POST['deleteLabel']);
    	$createNewLabel = if_isset($_POST['createNewLabel']);
    	$switchToVisual = if_isset($_POST['switchToVisual']);
        // Ensure labelName is preserved when switching between editors
    if ($switchToVisual && !$labelName) {
        $labelName = if_isset($_GET['labelName'], 'Standard');
    }
	
    if ($createNewLabel && $newLabelName && $labelTemplate) {
        // Create new label from template
        $templateFile = "../importfiler/$labelTemplate";
        if (file_exists($templateFile)) {
            $templateContent = file_get_contents($templateFile);
            saveLabelText($valg, $newLabelName, $templateContent, 'sheet');
            $labelName = $newLabelName;
        }
    } elseif ($saveRawHTML) {
        // Save raw HTML
        $rawHTML   = if_isset($_POST['rawHTML'], '');
        $labelType = if_isset($_POST, 'sheet', ['labelType']);
        saveLabelText($valg, $labelName, $rawHTML, $labelType);
    } elseif ($switchToVisual) {
		// When switching from raw HTML to visual editor, we need to save the raw HTML first
		$rawHTML   = if_isset($_POST['rawHTML'], '');
		$labelType = if_isset($_POST['labelType'], 'sheet');
		saveLabelText($valg, $labelName, $rawHTML, $labelType);
	} elseif ($saveLabel) {
		// saveVisualLabelEdit() (sys_div_func.php) refuses when the label's CURRENT template
		// has formatting the visual editor's field model can't reproduce (imported Brother/Dymo
		// templates, hand-written raw HTML, ...) - regenerating from that narrow model would
		// silently discard whatever it doesn't understand, which is exactly MB-18 ("changing
		// any setting destroys the whole configuration"). The UI already hides the visual
		// editor for such labels (see labels() in sys_div_func.php); this is the authoritative
		// check a form submit cannot bypass.
		if (!saveVisualLabelEdit($valg, $labelName, $_POST)) {
			// Refused - the template changed underneath this submit (e.g. another tab/admin, or
			// a back-button re-post) into something the visual editor can no longer safely
			// regenerate. Nothing is saved; labels() below re-renders the current stored
			// template in raw-HTML mode with an explicit "not saved" message instead of silently
			// discarding the user's submitted values (MB-18 review).
			$saveLabelRefused = true;
		}
		} elseif ($deleteLabel && $labelName != 'Standard') {
			$qtxt = "DELETE FROM labels WHERE labelname = '" . db_escape_string($labelName) . "'";
			$qtxt.= " and (account_id = '0' or account_id is null)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$labelName = 'Standard';
		}
		#######################################################################################
	} elseif ($pricelists) {
		$id          = $_POST['id'];
		$beskrivelse = $_POST['beskrivelse'];
		$box1        = $_POST['lev_id'];
		$box2        = $_POST['prisfil'];
		$box3        = $_POST['opdateret'];
		$box4        = $_POST['aktiv'];
		$box5        = $_POST['rabatter'];
		$box6        = $_POST['rabat'];
		$box7        = $_POST['grupper'];
		$box8        = $_POST['gruppe'];
		$box9        = $_POST['filtype'];
		$slet        = $_POST['slet'];
		$antal       = $_POST['antal'];

		for ($x = 0; $x < count($id); $x++) {
#			if (!$box4[$x]) $box1[$x]=''; # 20160225

			$id[$x] *= 1;
			$qtxt = NULL;
			$q_txt = "select id from grupper WHERE art='PL' and beskrivelse='$beskrivelse[$x]'";
			if ($id[$x] == 0 && $box4[$x] && $r = db_fetch_array(db_select($q_txt, __FILE__ . " linje " . __LINE__))) {
				$id[$x] = $r['id'];
			} elseif ($id[$x] == 0 && $box4[$x] && $beskrivelse[$x]) {
				$box4[$x] = 0; # 20150612
				$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box4,box6,box8,box9) values ";
				$qtxt.= "('$beskrivelse[$x]','0','PL','$box2[$x]','$box4[$x]','$box6[$x]','$box8[$x]','$box9[$x]')";
			} elseif ($id[$x] && $slet[$x] == "Slet") {
				$slet[$x] = $slet[$x];
			} elseif ($id[$x] > 0) {
				$qtxt = "update grupper set beskrivelse='$beskrivelse[$x]',box1='$box1[$x]',box2='$box2[$x]',box4='$box4[$x]',";
				$qtxt.= "box6='$box6[$x]',box8='$box8[$x]',box9='$box9[$x]' WHERE id='$id[$x]'";
			}
			if ($qtxt)
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion == 'posOptions') {
		$id1            = (int) if_isset($_POST, 0, ['id1']);
		$box1           = if_isset($_POST['kasseantal']) * 1;
		$afd_nr         = if_isset($_POST['afd_nr']);
		$kassekonti     = if_isset($_POST['kassekonti']);
		$box4           = if_isset($_POST['kortantal']) * 1;
		$korttyper      = if_isset($_POST['korttyper']);
		$kortkonti      = if_isset($_POST['kortkonti']);
		$moms_nr        = if_isset($_POST['moms_nr']);
		$rabatvarenr    = if_isset($_POST['rabatvarenr']);
		$box9           = if_isset($_POST['straksbogfor']);
		$box10          = if_isset($_POST['udskriv_bon']);
		$box11          = if_isset($_POST['vis_kontoopslag']);
		$box12          = if_isset($_POST['vis_hurtigknap']);
		$box13          = if_isset($_POST['timeout']);
		$box14          = if_isset($_POST['vis_indbetaling']);

		$ValutaKode     = if_isset($_POST['ValutaKode']);
		$ValutaKonti    = if_isset($_POST['ValutaKonti']);
		$ValutaMlKonti  = if_isset($_POST['ValutaMlKonti']);
		$ValutaDifKonti = if_isset($_POST['ValutaDifKonti']);

		if (!$ValutaKode)
			$ValutaKode = array();


		$id2                = (int) if_isset($_POST['id2']);
		$enabled            = if_isset($_POST['enabled']);
		$change_cardvalue   = if_isset($_POST['change_cardvalue']);
		$deactivateBonprint = if_isset($_POST['deactivateBonprint']);
		$betalingskort      = if_isset($_POST['betalingskort']); #20131210

		$planamount         = if_isset($_POST['planamount']);
		$plan               = if_isset($_POST['plan'], array());
		$bord               = if_isset($_POST['bord']); #20140508
		$bordantal          = if_isset($_POST['bordantal']); #20140508
		$bordvalg           = if_isset($_POST['bordvalg']);

		$diffkonti          = if_isset($_POST['diffkonti']);
		$div_kort_kto       = if_isset($_POST['div_kort_kto']); #20140129
		$jump2price         = if_isset($_POST['jump2price']);
		$kasseprimo         = if_isset($_POST['kasseprimo']);
		$kasseprimo         = (int) usdecimal($kasseprimo);
		$koekkenprinter     = if_isset($_POST['koekkenprinter']);
		$kortno             = if_isset($_POST['kortno'], array());
		$mellemkonti        = if_isset($_POST['mellemkonti']);
		$optalassist        = if_isset($_POST['optalassist']);
		$printer_ip         = if_isset($_POST['printer_ip']);
		$terminal_type      = if_isset($_POST['terminal_type']);
		$terminal_ip        = if_isset($_POST['terminal_ip']);
		$varenr             = if_isset($_POST['varenr']);
		$vis_saet           = if_isset($_POST['vis_saet']);
		$voucher            = if_isset($_POST['voucher']); #20181029
		$voucherText        = if_isset($_POST['voucherText']); #20181029
		$voucherItemNo      = if_isset($_POST['voucherItemNo']); #20210116

		$box14_2            = if_isset($_POST['udtag0']);

		$kdscolorindex      = if_isset($_POST['kdscolorindex']);
		$kdscolor           = if_isset($_POST['kdscolor']);

		update_settings_value("show_big_sum", "POS", if_isset($_POST['show_big_sum'], "off"), "Shows a big sum ");
		update_settings_value("show_stock", "POS", if_isset($_POST['lagerbeh'], "0"), "Weather or not to show the stock level on sale in the POS system");

		update_settings_value("activated", "KDS", if_isset($_POST['kdsactive'], "off"), "The KDS system is activated");
		update_settings_value("activated", "kitchen-print", if_isset($_POST['printactive'], "off"), "The physical kitchen print is acitaved");

		update_settings_value("columns", "KDS", if_isset($_POST['kdscolumns'], "5"), "The amount of columns in the KDS system");
		update_settings_value("height", "KDS", if_isset($_POST['kdsheight'], "20"), "The lineheight of each element in the KDS system");

		# KDS Color setup
		// One row per colour, named color_1, color_2... (one row per setting name).
		db_modify("DELETE FROM settings WHERE var_name like 'color%' AND var_grp='KDS'", __FILE__ . " linje " . __LINE__);
		$kdsColourNo = 0;
		for ($i = 0; $i < count($kdscolorindex); $i++) {
			if ($kdscolorindex[$i] != "") {
				$kdsColourNo++;
				db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('color_$kdsColourNo', 'KDS', '$kdscolorindex[$i]-$kdscolor[$i]', 'The color of KDS header at set minute interval')", __FILE__ . " linje " . __LINE__);
			}
		}



		# Table plan logic
		# Get the amount of tables currently in the system
		$plans = 1;
		$q = db_select("select id, name from table_pages ORDER BY id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$plans++;
		}
		# If the system has more plans than the inp
		if ($planamount > $plans - 1) {
			# For every difference in plans we add an empty one
			for ($i = 0; $i <= $planamount - $plans; $i++) {
				$id   = $plans + $i;
				$qtxt = "INSERT INTO table_pages(id, name) VALUES ($id, '')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			# If it has less plans
		} else if ($planamount < $plans - 1) {
			db_modify("DELETE FROM table_pages WHERE id > $planamount", __FILE__ . " linje " . __LINE__);
		}
		# Save the created fields
		for ($i = 1; $i <= count($plan) + 1; $i++) {
			if (isset($plan[$i])) {
				$id   = $i;
				$name = $plan[$i];
				$qtxt = "UPDATE table_pages SET name='$name' WHERE id=$id";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		for ($x = 0; $x < count($kortno); $x++) { // Removes noise in log
			if (!isset($betalingskort[$x]))
				$betalingskort[$x] = '';
			if (!isset($enabled[$x]))
				$enabled[$x] = '';
			if (!isset($voucher[$x]))
				$voucher[$x] = '';
			if (!isset($voucherText[$x]))
				$voucherText[$x] = '';
		}
		$kort = array();
		for ($x = 0; $x < count($kortno); $x++) { // hjemmelavet sortering da array_multisort flytter '$betalingskort'
			if ($kortno[$x] <= 9)
				$kortno[$x] = '0' . $kortno[$x];
			$kort[$x] = "$kortno[$x]"  . chr(9) . "$korttyper[$x]"   . chr(9) . "$kortkonti[$x]" . chr(9) . "$betalingskort[$x]" . chr(9);
			$kort[$x].= "$voucher[$x]" . chr(9) . "$voucherText[$x]" . chr(9) . "$enabled[$x]";
		}
//		array_multisort($kortno, $korttyper, $kortkonti, $betalingskort, $voucher, $voucherText, $enabled);
		sort($kort);
		for ($x = 0; $x < count($kortno); $x++) {
			list($kortno[$x], $korttyper[$x], $kortkonti[$x], $betalingskort[$x], $voucher[$x], $voucherText[$x], $enabled[$x]) = explode(chr(9), $kort[$x]);
		}

		$id3          = if_isset($_POST['id3']) * 1;
		$box1_3       = if_isset($_POST['brugervalg']);
		$pfs          = if_isset($_POST['pfs']);
		$box3_3       = if_isset($_POST['kundedisplay']);
		$postEachSale = if_isset($_POST['postEachSale']);
		$mobilpos     = if_isset($_POST['mobilpos']);
		$mobilwidth   = if_isset($_POST['mobilwidth']);
		$mobilzoom    = if_isset($_POST['mobilzoom']);
		$omv_menu     = if_isset($_POST['omv_menu']);
		$box2         = NULL;
		$box3         = NULL;
		$box7         = NULL;
		$box8         = NULL;
		$box3_2       = NULL;
		$box4_2       = NULL;
		$box11_2      = NULL;
		for ($x = 0; $x < $box1; $x++) {
			if (!isset($bordvalg[$x]))
				$bordvalg[$x] = NULL;
			if (!isset($postEachSale[$x]))
				$postEachSale[$x] = NULL;
			if (!isset($mobilpos[$x]))
				$mobilpos[$x] = NULL;
			if (!isset($mobilwidth[$x]))
				$mobilwidth[$x] = NULL;
			if (!isset($mobilzoom[$x]))
				$mobilzoom[$x] = NULL;

			$qtxt = "select id from kontoplan WHERE kontonr = '$kassekonti[$x]'";
			if (($kassekonti[$x] && is_numeric($kassekonti[$x]) && db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))));
			else {
				if ($kassekonti[$x])
					$txt = str_replace("<variable>", $kassekonti[$x], findtekst('277|Kontonr. <variable> eksisterer ikke', $sprog_id));
				else
					$txt = findtekst('278|Kontonr. skal udfyldes for alle kasser', $sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
			$txt  = '';
			$qtxt = "select id from kontoplan WHERE kontonr = '$mellemkonti[$x]'";
			if (($mellemkonti[$x] && is_numeric($mellemkonti[$x]) && db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))));
			else {
				if ($mellemkonti[$x])
					$txt = str_replace("<variable>", $mellemkonti[$x], findtekst('717|Mellemkonto <variable> eksisterer ikke', $sprog_id));
				else
					$txt = findtekst('718|Mellemkonto skal udfyldes for alle kasser', $sprog_id);
				if ($txt)
					print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}
			$qtxt = "select id from kontoplan WHERE kontonr = '$diffkonti[$x]'";
			if (($diffkonti[$x] && is_numeric($diffkonti[$x]) && db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))));
			else {
				if ($mellemkonti[$x])
					$txt = str_replace("<variable>", $diffkonti[$x], findtekst('723|Difference konto <variable> eksisterer ikke', $sprog_id));
				else
					$txt = findtekst('718|Mellemkonto skal udfyldes for alle kasser', $sprog_id);
				if ($txt)
					print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			}


			# Set terminal type
			$kasse_id = $x + 1;
			$qtxt     = "SELECT var_value FROM settings WHERE pos_id=$kasse_id and var_name='terminal_type'";
			$r        = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$termtype = $terminal_type[$x];

			# Check if a payment type has been setup on this term
			if ($r) {
				# It does exist, edit the row
				$qtxt = "UPDATE settings SET var_value='$termtype' WHERE pos_id=$kasse_id and var_name='terminal_type'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			} else {
				# It has not been setup yet, create the row
				$qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description, pos_id) 
                 VALUES ('terminal_type', 'POS', '$termtype', 'What the main payment system should be.', $kasse_id)";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}


			# ################
			# Mobilpos gem
			# ################
			update_settings_value("mobilepos", "POS", $mobilpos[$x], "Use the mobile pos system?", null, $x + 1);
			if ($mobilpos[$x] == "on") {
				update_settings_value("mobilwidth", "POS", $mobilwidth[$x], "The width of the mobile device", null, $x + 1);
				update_settings_value("mobilzoom", "POS", $mobilzoom[$x], "The zoom of the mobile device", null, $x + 1);
			}
			update_settings_value("omv_menu", "POS", $omv_menu[$x], "If the system should swap main and sub main menus", null, $x + 1);



			if ($box2) {
				$box2    .= chr(9) . $kassekonti[$x];
				$box3    .= chr(9) . $afd_nr[$x];
				$box7    .= chr(9) . $moms_nr[$x];
				$box3_2  .= chr(9) . $printer_ip[$x];
				$box4_2  .= chr(9) . $terminal_ip[$x];
				$box8_2  .= chr(9) . $mellemkonti[$x];
				$box9_2  .= chr(9) . $diffkonti[$x];
				$box10_2 .= chr(9) . $koekkenprinter[$x];
				$box13_2 .= chr(9) . $bordvalg[$x];	 #20161116
				$box2_3  .= chr(9) . $pfs[$x];	 #20161116
				$poEaSa  .= chr(9) . $postEachSale[$x];
				for ($y = 0; $y < count($ValutaKode); $y++) {
					$VKbox4[$y] .= chr(9) . $ValutaKonti[$x][$y];
					$VKbox5[$y] .= chr(9) . $ValutaMlKonti[$x][$y];
					$VKbox6[$y] .= chr(9) . $ValutaDifKonti[$x][$y];
				}
			} else {
				$box2    = $kassekonti[$x];
				$box3    = $afd_nr[$x];
				$box7    = $moms_nr[$x];
				$box3_2  = $printer_ip[$x];
				$box4_2  = $terminal_ip[$x];
				$box8_2  = $mellemkonti[$x];
				$box9_2  = $diffkonti[$x];
				$box10_2 = $koekkenprinter[$x];
				$box13_2 = $bordvalg[$x];	 #20161116
				$box2_3  = $pfs[$x];
				$poEaSa  = $postEachSale[$x];
				for ($y = 0; $y < count($ValutaKode); $y++) {
					$VKbox4[$y] = $ValutaKonti[$x][$y];
					$VKbox5[$y] = $ValutaMlKonti[$x][$y];
					$VKbox6[$y] = $ValutaDifKonti[$x][$y];
				}
			}
		}
		$box5 = NULL;
		$box6 = NULL;

		setcookie('saldi_pfs', '', time() - 60, '/');
		if ($box3_2) {
			if (isset($_COOKIE['saldi_printserver']))
				setcookie("saldi_printserver", '', time() - 60, '/');
			if (isset($_COOKIE['salditerm']))
				setcookie("salditerm", '', time() - 60, '/'); #setcookie("salditerm","0",time()-1);
		}

		for ($x = 0; $x < count($ValutaKode); $x++) {
			$qtxt = "update grupper set box4='$VKbox4[$x]',box5='$VKbox5[$x]',box6='$VKbox6[$x]' WHERE art='VK' and box1='$ValutaKode[$x]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		for ($x = 0; $x < $box4; $x++) {
			if ($korttyper[$x]) {
				$kortkonti[$x] *= 1;
				if (!db_fetch_array(db_select("select id from kontoplan WHERE kontonr = '$kortkonti[$x]'", __FILE__ . " linje " . __LINE__))) {
					if ($kortkonti[$x])
						$txt = str_replace("<variable>", $kortkonti[$x], findtekst('277|Kontonr. <variable> eksisterer ikke', $sprog_id));
					else
						$txt = findtekst('278|Kontonr. skal udfyldes for alle kasser', $sprog_id);
					print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				}
				if (isset($voucherItemNo[$x])) {
					$qtxt = "select id from varer where lower(varenr) = '" . db_escape_string(strtolower($voucherItemNo[$x])) . "'";
					$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					if ($r['id'])
						$voucherItemId[$x] = $r['id'];
					else {
						$voucherItemId[$x] = '0';
						$alerttxt = "" . findtekst('320|Varenummer', $sprog_id) . " $voucherItemNo[$x] " . findtekst('1740|ikke fundet', $sprog_id) . "";
						alert($alerttxt);
					}
				} else
					$voucherItemId[$x] = '0';
				if ($box5) {
					$box5         .= chr(9) . trim($korttyper[$x]);
					$box6         .= chr(9) . trim($kortkonti[$x]);
					$box5_2       .= chr(9) . trim($betalingskort[$x]); #20121210
					$box4_3       .= chr(9) . trim($voucher[$x]);	    #20181029
					$box5_3       .= chr(9) . trim($voucherText[$x]);   #20181029
					$voucherItems .= chr(9) . trim($voucherItemId[$x]); #20200116
					$card_enabled .= chr(9) . trim($enabled[$x]);	    #20181215
				} else {
					$box5         = trim($korttyper[$x]);
					$box6         = trim($kortkonti[$x]);
					$box5_2       = trim($betalingskort[$x]); #20121210
					$box4_3       = trim($voucher[$x]);       #20181029
					$box5_3       = trim($voucherText[$x]);	  #20181029
					$voucherItems = trim($voucherItemId[$x]); #20200116
					$card_enabled = trim($enabled[$x]);       #20181215
				}
			}
		}
		$box7_2 = NULL;
		for ($x = 0; $x < $bordantal; $x++) { #20140508
			$tmp = $x + 1;
			if (!isset($bord[$x]) || !$bord[$x])
				$bord[$x] = "Bord " . $tmp;
			($box7_2) ? $box7_2 .= chr(9) . $bord[$x] : $box7_2 = trim($bord[$x]);
		}
		if ($varenr && $r = db_fetch_array(db_select("select id from varer WHERE varenr = '$varenr'", __FILE__ . " linje " . __LINE__))) {
			$box11_2 = $r['id'];
		} elseif ($varenr) {
			$txt = str_replace('XXXXX', $varenr, findtekst('289|Varenr. XXXXX eksisterer ikke.', $sprog_id));
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($rabatvarenr && $r = db_fetch_array(db_select("select id from varer WHERE varenr = '$rabatvarenr'", __FILE__ . " linje " . __LINE__))) {
			$box8 = $r['id'];
		} elseif ($rabatvarenr) {
			$txt = str_replace('XXXXX', $rabatvarenr, findtekst('289|Varenr. XXXXX eksisterer ikke.', $sprog_id));
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		$qtxt = "select id from grupper WHERE art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'";
		if (($id1 == 0) && ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))))
			$id1 = $r['id'];
		elseif ($id1 == 0) {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14,fiscal_year) values ('POS_valg','1','POS','$box1','$box2','$box3','$box4','$box5','$box6','$box7','','$box9','$box10','$box11','$box12','$box13','$box14','$regnaar')", __FILE__ . " linje " . __LINE__);
		} elseif ($id1 > 0) {
			db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',box14='$box14' WHERE id = '$id1'", __FILE__ . " linje " . __LINE__);
		}
		if ($id2) {
			$qtxt = "update grupper set box1 = '$kasseprimo',box2='$optalassist',box3='$box3_2',box4='$box4_2',";
			$qtxt.= "box5='$box5_2',box6='$div_kort_kto',box7='$box7_2',box8='$box8_2',box9='$box9_2',box10='$box10_2',";
			$qtxt.= "box11='$box11_2',box12='$vis_saet',box13='$box13_2',box14='$box14_2' WHERE id = '$id2'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if ($id3) {
			$qtxt = "update grupper set box1='$box1_3',box2='$box2_3',box3='$box3_3',box4='$box4_3',box5='$box5_3',box6='',box7='',";	#20181029
			$qtxt.= "box8='',box9='',box10='',box11='',box12='',box13='',box14='' WHERE id = '$id3'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		$qtxt = "select id from settings where var_name='card_enabled'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt = "update settings set var_value='$card_enabled' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('card_enabled','Paycards','$card_enabled','Tab separated list showing enabled paymentcards ";
			$qtxt.= "(Disabled cards are used to specify account# in API orders and returns from payment terminal)','0')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "select id from settings where var_name='change_cardvalue'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt = "update settings set var_value='$change_cardvalue' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('change_cardvalue','Paycards','$change_cardvalue','Allow changes in cardsums when doing cash summary";
			$qtxt.= "(Disabled cards are used to specify account# in API orders and returns from payment terminal)','0')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		# voucherItems
		$qtxt = "select id from settings where var_name='voucherItems'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt = "update settings set var_value='$voucherItems' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('voucherItems','Paycards','$voucherItems','Tab separated list showing which item representing the voucher','0')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "select id from settings where var_name='deactivateBonprint'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt = "update settings set var_value='$deactivateBonprint' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('deactivateBonprint','globals','$deactivateBonprint','Deactivates the receipt printer','0')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "select id from settings where var_name='postEachSale'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt = "update settings set var_value='$poEaSa' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('postEachSale','POS','$poEaSa','Post to transactions immediately when finishing sale','0')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "select id from settings where var_name='jump2price'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$qtxt = "update settings set var_value='$jump2price' where id='$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.= " values ";
			$qtxt.= "('jump2price','globals','$jump2price','Cursor jumps direct to price and sets quantity to 1 if price is 0','0')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);

		#######################################################################################
	} elseif ($sektion == 'massefakt') {
		$id         = if_isset($_POST['id']);
		$brug_mfakt = if_isset($_POST['brug_mfakt']);
		if ($brug_mfakt) {
			$brug_dellev = if_isset($_POST['brug_dellev']);
			$levfrist    = if_isset($_POST['levfrist']);
		} else {
			$brug_dellev = NULL;
			$levfrist    = 0;
		}
		if ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'MFAKT'", __FILE__ . " linje " . __LINE__))))
			$id = $r['id'];
		elseif (!$id) {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3) values ('Massefakturering','1','MFAKT','$brug_mfakt','$brug_dellev','$levfrist')", __FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$brug_mfakt',box2='$brug_dellev',box3='$levfrist' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		}


######################################################################################


######################################################################################
	} elseif ($sektion == 'kontoplan_io') {
		if (strstr($_POST['submit']) == "Eksport") {
			list($tmp) = explode(":", $_POST['regnskabsaar']);
			print "<BODY onLoad=\"javascript:exporter_kontoplan=window.open('exporter_kontoplan.php?aar=$tmp','lager','scrollbars=yes,resizable=yes,dependent=yes');exporter_kontoplan.focus();\">";
		} elseif (strstr($_POST['submit']) == "Import") {
			print "<BODY onLoad=\"javascript:importer_kontoplan=window.open('importer_kontoplan.php','kontoplan','scrollbars=yes,resizable=yes,dependent=yes');importer_kontoplan.focus();\">";
		}
	} elseif ($sektion == 'adresser_io') {
		if (strstr($_POST['submit']) == "Eksport") {
			print "<BODY onLoad=\"javascript:exporter_adresser=window.open('exporter_adresser.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_adresser.focus();\">";
		} elseif (strstr($_POST['submit']) == "Import") {
			print "<BODY onLoad=\"javascript:importer_debitor=window.open('importer_debitor.php','debitor','scrollbars=yes,resizable=yes,dependent=yes');importer_debitor.focus();\">";
		}
	} elseif ($sektion == 'varer_io') {
		if (strstr($_POST['submit']) == "Eksport") {
			print "<BODY onLoad=\"javascript:exporter_varer=window.open('exporter_varer.php?aar=$tmp','debitor','scrollbars=yes,resizable=yes,dependent=yes');exporter_varer.focus();\">";
		} elseif (strstr($_POST['submit']) == "Import") {
			print "<BODY onLoad=\"javascript:importer_varer=window.open('importer_varer.php','importer_varer','scrollbars=yes,resizable=yes,dependent=yes');importer_varer.focus();\">";
		}
	} elseif ($sektion == 'solar_io') {
		if (strstr($_POST['submit']) == "Import") {
			print "<BODY onLoad=\"javascript:solarvvs=window.open('solarvvs.php','solarvvs','scrollbars=yes,resizable=yes,dependent=yes');solarvvs.focus();\">";
		}
	} elseif ($sektion == 'formular_io') {
		if (strstr($_POST['submit']) == "Eksport") {
			print "<BODY onLoad=\"javascript:exporter_formular=window.open('exporter_formular.php','exporter_formular','scrollbars=yes,resizable=yes,dependent=yes');exporter_formular.focus();\">";
		} elseif (strstr($_POST['submit']) == "Import") {
			print "<BODY onLoad=\"javascript:importer_formular=window.open('importer_formular.php','importer_formular','scrollbars=yes,resizable=yes,dependent=yes');importer_formular.focus();\">";
		}
	} elseif ($sektion == 'kontoindstillinger') {

		if (isset($_POST['update_max_users'])) {
		
			$new_max_users = isset($_POST['max_users']) ? (int)$_POST['max_users'] : 0;
			if ($new_max_users <= 0 || $new_max_users > 1000) {
				echo "<p style='color: red;'>Invalid number of users. Must be between 1 and 1000.</p>";
				exit;
			}
			
			$ch       = curl_init();
			$curl_url = "https://saldi.dk/locator/locator.php?action=insertUserCount&userCount=$new_max_users&dbName=$db";
			curl_setopt($ch, CURLOPT_URL, $curl_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Accept: application/json'
			));
			$response  = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($http_code != 200) {
				echo "<p style='color: red;'>Failed to update max users. Please try again later.</p>";
				error_log("Failed to update max users via API. HTTP code: $http_code");
				exit;
			}

			$current_regnskab_name = $regnskab;
			$current_username      = $brugernavn;
			$update_successful     = false;
			$old_max_users_value   = null;
		
			$masterDb = $sqdb;
			include("../includes/connect.php");
				$query_select  = "SELECT brugerantal FROM regnskab WHERE db = '$db'";
				$result_select = db_select($query_select, __FILE__ . " linje " . __LINE__);
		
				if ($result_select && db_num_rows($result_select) > 0) {
					$row = db_fetch_array($result_select);
					$old_max_users_value = (int) $row['brugerantal'];
		
					if ($new_max_users !== $old_max_users_value) {
						$query_update = "UPDATE regnskab SET brugerantal = $new_max_users WHERE db = '$db'";
						db_modify($query_update, __FILE__ . " linje " . __LINE__);
		
							$update_successful = true;
							echo "<p style='color: blue;'>Max users updated successfully to $new_max_users.</p>";
						
							$subject = "Max Users Updated on Account: $current_regnskab_name";
							$message = "User '$current_username' has updated the max users from $old_max_users_value to $new_max_users on account '$current_regnskab_name'.";
							$headers = "From: noreply@saldi.dk\r\nReply-To: noreply@saldi.dk\r\n";
						
							mail("info@saldi.dk", $subject, $message, $headers);
						
					} else {
						echo "<p style='color: gray;'>No change in max users value.</p>";
					}
				} else {
					echo "<p style='color: red;'>An unexpected error occurred.</p>";
				}
				include("../includes/online.php");
		}
		
		
		if (isset($_POST['newName']) && $_POST['newName']) {
			$newName = trim(db_escape_string($_POST['newName']));
			include("../includes/connect.php");
			if (db_fetch_array(db_select("select id from regnskab WHERE regnskab = '$newName'", __FILE__ . " linje " . __LINE__))) {
				$alert1 = findtekst('1742|Der findes allerede et regnskab med navnet', $sprog_id);
				$alert2 = findtekst('1743|Navn ikke ændret', $sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$alert1 $newName! $alert2')\">";
			} else {
				$r = db_fetch_array(db_select("select id from kundedata WHERE regnskab_id = '$db_id'", __FILE__ . " linje " . __LINE__));
				if (!$r['id']) {
					$tmp = db_escape_string($regnskab);
					db_modify("update kundedata set regnskab_id = '$db_id' WHERE regnskab='$tmp'", __FILE__ . " linje " . __LINE__);
				}
				db_modify("update regnskab set regnskab = '$newName' WHERE db='$db'", __FILE__ . " linje " . __LINE__);
			}
			include("../includes/online.php");
		} elseif (isset($_POST['updateCurrency'])) {
			$baseCurrency = $_POST['baseCurrency'];
			if ($baseCurrency) {
				$qtxt = "select id from settings where var_name='baseCurrency'";
				$r    = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($r['id'])
					$qtxt = "update settings set var_value='$baseCurrency', user_id='0' where id='$r[id]'";
				else {
					$qtxt = "insert into settings (var_name,var_value,var_description,user_id)";
					$qtxt.= " values ";
					$qtxt.= "('baseCurrency','$baseCurrency','System Base currency','0')";
				}
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		} elseif (isset($_POST['opdat_tidszone'])) {
			$timezone = $_POST['timezone'];
			if ($timezone) {
				$r = db_fetch_array(db_select("select id from settings where var_name='timezone'", __FILE__ . " linje " . __LINE__));
				if ($r['id'])
					$qtxt = "update settings set var_value='$timezone', user_id='0' where id='$r[id]'";
				else {
					$qtxt = "insert into settings (var_name,var_value,var_description,user_id)";
					$qtxt.= " values ";
					$qtxt.= "('timezone','$timezone','Tidszone. Anvendes hvis regnskabet anvender anden tidszone end serveren','0')";
				}
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				setcookie("timezone", $timezone, time() + 60 * 60 * 24 * 30, '/');
			}
		} elseif (isset($_POST['nulstil']) && $_POST['nulstil']) { #20170731
			require_once(__DIR__ . '/resetAccount.php');
			try {
				resetAccount(!empty($_POST['behold_debkred']), !empty($_POST['behold_varer']), $db_type, $db);
				$regnaar = 1;
				print tekstboks('regnskab nulstillet');
			} catch (Throwable $error) { # 20260917 en fejl må ikke ende som en hvid fejlside
				print tekstboks('Regnskabet blev ikke nulstillet: ' . $error->getMessage());
			}
		} elseif (isset($_POST['slet'])) {
			if ($_POST['slet_regnskab'] == 'on') { #20185024
				include("../includes/connect.php");
				db_modify("update regnskab set lukket='on',logintekst='slettet af $brugernavn den " . date("Ymd H.i") . "' where id = '$db_id'", __FILE__ . " linje " . __LINE__);
				db_modify("delete from online where db='$db'", __FILE__ . " linje " . __LINE__);
				include("../includes/online.php");
				print "Sletter";
			} else {
				$tekst1 = findtekst('852|Slet regnskab', $sprog_id);
				$alert  = findtekst('1744|For at slette dit regnskab skal du afmærke feltet ved', $sprog_id);
				alert("$alert $tekst1: $regnskab");
			}
		}
	} elseif ($sektion == 'tjekliste') {
		$id            = if_isset($_POST['id']);
		$tjekantal     = if_isset($_POST['tjekantal']);
		$fase          = if_isset($_POST['fase']);
		$ny_fase       = if_isset($_POST['ny_fase']);
		$ny_tjekgruppe = if_isset($_POST['ny_tjekgruppe']);
		$tjekpunkt     = if_isset($_POST['tjekpunkt']);
		$nyt_tjekpunkt = if_isset($_POST['nyt_tjekpunkt']);
		$liste_id      = if_isset($_POST['liste_id']);
		$gruppe_id     = if_isset($_POST['gruppe_id']);
		$ret           = if_isset($_POST['ret']);

		if ($ny_tjekliste = $_POST['ny_tjekliste']) {
			$r = db_fetch_array($q = db_select("select max(fase) as fase from tjekliste WHERE assign_to = 'sager'", __FILE__ . " linje " . __LINE__));
			$nf = $r['fase'] + 1;
			db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekliste','0','sager','$nf')", __FILE__ . " linje " . __LINE__);
		}
		for ($x = 1; $x <= $tjekantal; $x++) {
			if (isset($ny_fase[$x]) && $ny_fase[$x])
				$nf = $ny_fase[$x] + .1;
			if ($fase[$x] != $nf)
				db_modify("update tjekliste set fase='$nf' WHERE id = '$id[$x]'", __FILE__ . " linje " . __LINE__);
			if ($ret && $ret == $id[$x] && $tjekpunkt[$x])
				db_modify("update tjekliste set tjekpunkt='$tjekpunkt[$x]' WHERE id = '$id[$x]'", __FILE__ . " linje " . __LINE__);
			if (isset($ny_tjekgruppe[$x]) && $ny_tjekgruppe[$x]) {
				db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe[$x]','$liste_id[$x]','sager','$fase[$x]')", __FILE__ . " linje " . __LINE__);
			}
			if (isset($nyt_tjekpunkt[$x]) && $nyt_tjekpunkt[$x]) {
				db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$nyt_tjekpunkt[$x]','$gruppe_id[$x]','sager','$fase[$x]')", __FILE__ . " linje " . __LINE__);
			}
		}

		if ($ny_tjekgruppe = $_POST['ny_tjekgruppe']) {

			$qtxt = "select max(fase) from tjekliste WHERE assign_to = 'sager'";
			($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)) && isset($r['fase'])) ? $ny_fase = $r['fase'] : $ny_fase = NULL;
			if ($ny_fase || $ny_fase == '0')
				$ny_fase++;
			else
				$ny_fase = 0;
			#			db_modify("insert into tjekliste (tjekpunkt,assign_id,assign_to,fase) values ('$ny_tjekgruppe','0','sager','$ny_fase')",__FILE__ . " linje " . __LINE__);
		}
	}
} else {
	$valg    = if_isset($_GET['valg']);
	// $sektion = if_isset($_GET['sektion']);
	$sektion = if_isset($_GET, null, 'sektion');
	#	if ($sektion == 'personlige_valg') $sektion = 'userSettings';


}

print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" height=\"100%\"><tbody>";

// 20260928 Phase 4: no separate "Diverse" column; the frame (top.php) lists the group's pages.
print "<td valign=\"top\" align=\"left\"><table align=\"left\" valign=\"top\" border=\"0\" width=\"90%\"><tbody>\n";
if (!$sektion)
	print "<td><br></td>";
if ($sektion == "kontoindstillinger")
	kontoindstillinger($regnskab, $skiftnavn);
if ($sektion == "ordre_valg")
	ordre_valg();
if ($sektion == "variant_valg") variant_valg();
// if ($sektion == "shop_valg") shop_valg();
if ($sektion == "api_valg") api_valg();
if ($sektion == "stripe_valg") {
	include_once(__DIR__ . '/diverseIncludes/stripeValg.php');
	stripeValg();
}
if ($sektion == "labels") labels($valg);
if ($sektion == "pricelists") {
	include("diverseIncludes/pricelists.php");
	pricelists();
}
if ($sektion == "div_valg") div_valg(); # Kalder sys_div_valg.php
if ($sektion == "bank_integration") include('diverseIncludes/bank_integration.php');
//if ($sektion=="barcodescan") barcodescan();
if ($sektion == "massefakt") massefakt();
if ($sektion == "posOptions") {
	include("diverseIncludes/posOptions.php");
	posOptions();
}
if ($sektion == "barcodescan") {
	header("Location: ../systemdata/barcodescan.php");
	exit;
}

if ($sektion == "sprog") {
	include("diverseIncludes/language.php");
	language();
}
if ($sektion == "tjekliste")
	tjekliste();
if (strpos($sektion, "_io")) {
	kontoplan_io();
	formular_io();
	adresser_io();
	varer_io();
	variantvarer_io();
}

print "</tbody></table></td></tr>";
#print "</form>";
#print "</tbody></table></td></tr>";





?>
</tbody>
</table>
</body>

</html>
