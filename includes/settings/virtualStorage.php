<?php
// ---- includes/settings/virtualStorage.php --- lap 5.0.0 --- 2026.10.04 ---
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
// 20261004 Sawaneh G10.5: table_count (POS/2 box7 names) and floor_plan_count (table_pages rows).
// 20261004 Sawaneh G10.1: seed_post_each_sale, the starting value of the per-till postEachSale list.
// 20261004 Sawaneh Settings redesign: a setting whose value is derived from several stored fields (storage
//                  'virtual'). Read and written here, audited like any other setting by SettingsService.

/**
 * The value of a virtual setting.
 */
function settings_virtual_get(string $name): string
{
	if ($name === 'seed_post_each_sale') {
		// G10.1: without a postEachSale list every till follows POS/1 box9 (includes/ordrefunc.php).
		$on = function_exists('settings_post_each_sale_default') && settings_post_each_sale_default();
		$tills = function_exists('settings_till_count') ? settings_till_count() : 0;
		return $tills > 0 ? implode("\t", array_fill(0, $tills, $on ? 'on' : '')) : '';
	}
	if ($name === 'table_count') {
		return (string) (function_exists('settings_table_count') ? settings_table_count() : 0);
	}
	if ($name === 'floor_plan_count') {
		return (string) count(function_exists('settings_floor_plans') ? settings_floor_plans() : array());
	}
	if ($name === 'document_storage') {
		// G2.6: internal storage is box6 'on'; own FTP is box6 empty with a server; otherwise none.
		$r = db_fetch_array(db_select("select box1, box2, box6 from grupper where art = 'bilag' order by id limit 1", __FILE__ . " linje " . __LINE__));
		if (!$r) {
			return '';
		}
		if ($r['box6'] === 'on') {
			return 'internFTP';
		}
		return (trim((string) $r['box1']) !== '' && trim((string) $r['box2']) !== '') ? 'externFTP' : '';
	}
	return '';
}

/**
 * Store a virtual setting. Same writes as the old Bilagshåndtering page (systemdata/diverse.php sektion=bilag);
 * its update of regnskab.bilag in the master database is left out, nothing reads that column.
 */
function settings_virtual_set(string $name, string $raw): void
{
	if ($name === 'table_count') {
		// G10.5: new tables are named "Bord n" as the old page did; the list is written to every fiscal year (R2).
		$n = max(0, (int) $raw);
		$row = function_exists('settings_pos_row') ? settings_pos_row(2) : null;
		$names = ($row && trim((string) $row['box7']) !== '') ? explode("\t", (string) $row['box7']) : array();
		$names = array_slice($names, 0, $n);
		for ($i = count($names); $i < $n; $i++) {
			$names[] = 'Bord ' . ($i + 1);
		}
		if ($row) {
			db_modify("update grupper set box7 = '" . db_escape_string(implode("\t", $names)) . "' where art = 'POS' and kodenr = '2'", __FILE__ . " linje " . __LINE__);
		} elseif ($n > 0) {
			global $regnaar;
			db_modify("insert into grupper (beskrivelse, kodenr, art, kode, fiscal_year, box7) values ('Pos valg', '2', 'POS', '', " . (int) $regnaar . ", '" . db_escape_string(implode("\t", $names)) . "')", __FILE__ . " linje " . __LINE__);
		}
		return;
	}
	if ($name === 'floor_plan_count') {
		// G10.5: same as the old page - rows are added without a name, and the last ones go when the number is lowered.
		$n = max(0, (int) $raw);
		$plans = function_exists('settings_floor_plans') ? settings_floor_plans() : array();
		$have = count($plans);
		for ($i = $have; $i < $n; $i++) {
			db_modify("insert into table_pages (id, name) values ((select coalesce(max(id), 0) + 1 from table_pages), '')", __FILE__ . " linje " . __LINE__);
		}
		if ($n < $have) {
			db_modify("delete from table_pages where id in (select id from table_pages order by id desc limit " . ($have - $n) . ")", __FILE__ . " linje " . __LINE__);
		}
		return;
	}
	if ($name === 'document_storage') {
		if (!db_fetch_array(db_select("select id from grupper where art = 'bilag'", __FILE__ . " linje " . __LINE__))) {
			db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5, box6, box7) values ('Bilag og dokumenter', '1', 'bilag', '', '', '', '', '', '', '')", __FILE__ . " linje " . __LINE__);
		}
		if ($raw === 'internFTP') {
			db_modify("update grupper set box6 = 'on' where art = 'bilag'", __FILE__ . " linje " . __LINE__);
		} elseif ($raw === 'externFTP') {
			db_modify("update grupper set box6 = '' where art = 'bilag'", __FILE__ . " linje " . __LINE__);
		} else {
			// None: the old page cleared the server details too.
			db_modify("update grupper set box1 = '', box2 = '', box3 = '', box6 = '' where art = 'bilag'", __FILE__ . " linje " . __LINE__);
		}
	}
}
