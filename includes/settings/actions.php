<?php
// ---- includes/settings/actions.php --- lap 5.0.0 --- 2026.10.02 ---
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261002 Sawaneh Settings redesign phase 4b: the actions a generated section can run (spec P4), each after a
//                  confirmation on the page. Called from systemdata/settingsSection.php.
// 20261004 Sawaneh G4.3: price lists - add, use (one active list), test the file (fetched only here, with a timeout), delete.
// 20261004 Sawaneh G2.6: FTP connection test for document storage.
// 20261002 Sawaneh Phase 4b batch 2 (G9): new API key, shop sync, MobilePay webhook and QR codes (includes/settings/integrations.php).

include_once(__DIR__ . '/integrations.php');

/**
 * Items created for mySale where the cost price was used as the commission percentage get a real
 * commission rate and cost price. Moved from systemdata/diverse.php (productOptions), without its sleep(10).
 *
 * @return int items changed
 */
function settings_convert_commission_items(): int
{
	$n = 0;
	$qtxt = "select id, varenr, kostpris, retail_price, provision from varer where (varenr like 'kb%' or varenr like 'kn%') ";
	$qtxt .= "and ((retail_price > 0 and retail_price < 100) or (kostpris > 0 and kostpris < 1)) order by varenr";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['provision']) {
			continue;
		}
		$id = (int) $r['id'];
		if ($r['retail_price'] && $r['retail_price'] < 100) {
			$provision = afrund($r['retail_price'], 0) * 1;
			$kostpris = 1 - $provision / 100;
			$qtxt = "update varer set provision = '$provision', kostpris = '$kostpris' where id = $id";
		} elseif ($r['kostpris'] >= 0.5 && $r['kostpris'] < 1) {
			$provision = 100 - ($r['kostpris'] * 100);
			$qtxt = "update varer set provision = '$provision' where id = $id";
		} else {
			$provision = $r['kostpris'] * 100;
			$kostpris = 1 - $r['kostpris'];
			$qtxt = "update varer set provision = '$provision', kostpris = '$kostpris' where id = $id";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$n++;
	}
	return $n;
}

function settings_pricelist_name(int $id): string
{
	$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__));
	return $r ? trim((string) $r['beskrivelse']) : '';
}

/**
 * Fetch a price file and report its size and columns (spec G4.3: never on render, only on request, with a timeout).
 *
 * @return array{0: string, 1: string} flash kind and text
 */
function settings_pricelist_test(int $id): array
{
	$r = db_fetch_array(db_select("select box2, box10, box11 from grupper where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__));
	$url = $r ? trim((string) $r['box2']) : '';
	if ($url === '' || !preg_match('#^https?://#i', $url)) {
		return array('err', st_txt(6247));
	}
	$context = stream_context_create(array('http' => array('timeout' => 10), 'https' => array('timeout' => 10)));
	$data = @file_get_contents($url, false, $context, 0, 20 * 1024 * 1024);
	if ($data === false || trim($data) === '') {
		return array('err', st_txt(6247));
	}
	if ($r['box11'] !== 'utf-8' && !mb_check_encoding($data, 'UTF-8')) {
		$data = mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
	}
	$lines = preg_split('/\r\n|\n|\r/', trim($data));
	$delimiter = ((string) $r['box10'] !== '') ? (string) $r['box10'] : ';';
	$columns = array_map('trim', str_getcsv($lines[0], $delimiter));
	$shown = implode(', ', array_slice($columns, 0, 8)) . (count($columns) > 8 ? ' …' : '');
	return array('ok', sprintf(st_txt(6246), count($lines) - 1, $shown));
}

/**
 * G2.6: log in to the company's own FTP server with the stored details, create the two folders and write and
 * read a test file. Same steps as testftp() on the old Bilagshåndtering page, with PHP's ftp functions.
 */
function settings_ftp_test(): bool
{
	global $db;
	$r = db_fetch_array(db_select("select box1, box2, box3, box4, box5 from grupper where art = 'bilag' order by id limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r || trim((string) $r['box1']) === '' || !function_exists('ftp_connect')) {
		return false;
	}
	$target = rtrim(trim((string) $r['box1']), '/');
	$parts = parse_url((strpos($target, '://') === false ? 'ftp://' : '') . $target);
	$host = isset($parts['host']) ? $parts['host'] : '';
	$port = isset($parts['port']) ? (int) $parts['port'] : 21;
	$path = isset($parts['path']) ? trim($parts['path'], '/') : '';
	$folderVouchers = trim((string) $r['box4']) !== '' ? trim((string) $r['box4']) : 'bilag';
	$folderDocuments = trim((string) $r['box5']) !== '' ? trim((string) $r['box5']) : 'dokumenter';
	$ok = false;
	$conn = $host !== '' ? @ftp_connect($host, $port, 10) : false;
	if ($conn && @ftp_login($conn, (string) $r['box2'], urldecode((string) $r['box3']))) {
		@ftp_pasv($conn, true);
		if ($path !== '') {
			@ftp_chdir($conn, $path);
		}
		@ftp_mkdir($conn, $folderVouchers);
		@ftp_mkdir($conn, $folderDocuments);
		if (@ftp_chdir($conn, $folderVouchers)) {
			$local = tempnam(sys_get_temp_dir(), 'saldi_ftp');
			file_put_contents($local, "testfil fra saldi $db\n");
			if (@ftp_put($conn, 'saldi_testfil.txt', $local, FTP_ASCII)) {
				$ok = @ftp_get($conn, $local, 'saldi_testfil.txt', FTP_ASCII);
				@ftp_delete($conn, 'saldi_testfil.txt');
			}
			@unlink($local);
		}
	}
	if ($conn) {
		ftp_close($conn);
	}
	return $ok;
}

/**
 * Run the action named in a definition. Returns the page to go to next, with its message.
 */
function settings_run_action(array $def, string $selfUrl): string
{
	switch ($def['run']) {
		case 'update_cost_prices':
			return '../includes/opdat_kostpriser.php?metode=' . (int) SettingsService::raw('items.stock.cost_method');
		case 'convert_commission_items':
			return $selfUrl . '&converted=' . settings_convert_commission_items();
		case 'generate_api_key':
			// Shown once on the next page view, then forgotten (P8): never in the address bar or the audit log.
			$_SESSION['settings_newkey'] = settings_generate_api_key();
			return $selfUrl . '&newkey=1';
		case 'shop_sync_new':
			return 'diverse.php?sektion=api_valg&varesync=1';
		case 'shop_sync_update':
			return 'diverse.php?sektion=api_valg&varesync=2';
		case 'mobilepay_webhook':
			return 'sys_div_func_includes/setup_mobilepay_webhook.php';
		case 'ftp_test':
			return $selfUrl . '&ftp=' . (settings_ftp_test() ? 'ok' : 'fail');
		case 'pricelist_create':
			$name = db_escape_string(st_txt(6222));
			db_modify("insert into grupper (beskrivelse, kodenr, art, box2, box10, box11) values ('$name', '0', 'PL', '', ';', 'utf-8')", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select max(id) as id from grupper where art = 'PL'", __FILE__ . " linje " . __LINE__));
			return 'settingsSection.php?s=purchase.pricelists&item=pl_' . (int) $r['id'];
		case 'pricelist_use':
			$id = (int) $def['scope_id'];
			// Same writes as "Use" on the old page: this list active (and offered in the order lookup), no other list active.
			db_modify("update grupper set box12 = '' where art = 'PL' and id <> $id and box12 = 'Yes'", __FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box12 = 'Yes', box4 = 'on' where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__);
			$_SESSION['settings_flash'] = array('ok', st_txt(6233) . ': ' . settings_pricelist_name($id));
			return $selfUrl;
		case 'pricelist_test':
			$_SESSION['settings_flash'] = settings_pricelist_test((int) $def['scope_id']);
			return $selfUrl;
		case 'pricelist_delete':
			$id = (int) $def['scope_id'];
			$name = settings_pricelist_name($id);
			db_modify("delete from grupper where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__);
			if (function_exists('audit_log')) {
				audit_log('setting.row_deleted', $name, 'indstilling', 'purchase.pricelists#' . $id);
			}
			$_SESSION['settings_flash'] = array('ok', st_txt(6249) . ': ' . $name);
			return 'settingsSection.php?s=purchase.pricelists';
		case 'mobilepay_qr':
			$res = settings_mobilepay_create_qr();
			if ($res['error'] !== '') {
				$_SESSION['settings_error'] = $res['error'];
			}
			return $selfUrl . '&qr=' . (int) $res['created'];
	}
	return $selfUrl;
}

/**
 * Follow-ups after a value is stored ('on_save' in a definition).
 */
function settings_after_save(array $def, string $raw): void
{
	if ($def['on_save'] === 'ensure_emballage_schema' && $raw === 'on') {
		include_once(__DIR__ . '/../emballage_schema.php');
		ensure_emballage_schema();
	}
	if ($def['on_save'] === 'pricelist_group_name') {
		// The old page kept the item group's name next to its number (box8); debitor/_varerInsert.php reads the name.
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'VG' and kodenr = '" . db_escape_string($raw) . "' order by fiscal_year desc limit 1", __FILE__ . " linje " . __LINE__));
		db_modify("update grupper set box8 = '" . db_escape_string($r ? (string) $r['beskrivelse'] : '') . "' where art = 'PL' and id = " . (int) $def['scope_id'], __FILE__ . " linje " . __LINE__);
	}
	if ($def['on_save'] === 'smtp_changed' && function_exists('audit_log')) {
		// The roles spec names this event (settings redesign §11.2); the value itself is in the setting's own audit row.
		audit_log('integration.smtp_changed', $def['key']);
	}
}
