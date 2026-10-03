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
}
