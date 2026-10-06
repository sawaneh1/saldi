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
// 20261006 Sawaneh G3.5 part B: discount_matrix, the discount per customer group × item group (table rabat) by group number.
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
	if ($name === 'discount_matrix') {
		return settings_discount_matrix_read();
	}
	if ($name === 'table_count') {
		return (string) (function_exists('settings_table_count') ? settings_table_count() : 0);
	}
	if ($name === 'ledger_name') {
		// G1.4: the ledger's name lives in the master database (regnskab.regnskab).
		global $db;
		$r = db_fetch_array(db_select("select regnskab from regnskab where db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__, true));
		return $r ? (string) $r['regnskab'] : '';
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
	if ($name === 'discount_matrix') {
		settings_discount_matrix_write($raw);
		return;
	}
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
	if ($name === 'ledger_name') {
		// G1.4: as the old Kontoindstillinger page - the customer record (kundedata) is tied to the ledger id first, so it
		// keeps finding the ledger after the rename. The duplicate check is the field's validation ('ledger_name').
		global $db, $db_id, $regnskab;
		$name = db_escape_string(trim($raw));
		if ($name === '' || !isset($db_id)) {
			return;
		}
		$r = db_fetch_array(db_select("select id from kundedata where regnskab_id = '" . (int) $db_id . "'", __FILE__ . " linje " . __LINE__, true));
		if (!$r) {
			db_modify("update kundedata set regnskab_id = '" . (int) $db_id . "' where regnskab = '" . db_escape_string((string) $regnskab) . "'", __FILE__ . " linje " . __LINE__, true);
		}
		db_modify("update regnskab set regnskab = '$name' where db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__, true);
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

// ---------------------------------------------------------------- discount matrix (G3.5)

/**
 * The matrix's axes as orders read them (includes/ordrefunc.php): customers by their own discount group (DRG, adresser.
 * rabatgruppe) when any exist, else by debtor group (DG); items by item discount group (DVRG, varer.dvrg) when any exist,
 * else by item group (VG). Keys are the group numbers that the table rabat holds.
 *
 * @return array{rows: array<string, string>, cols: array<string, string>, own_rows: bool, own_cols: bool}
 */
function settings_discount_axes(): array
{
	global $regnaar;
	$y = (int) $regnaar;
	$read = function (string $sql, string $name) {
		$out = array();
		$q = db_select($sql, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$k = (string) (int) $r['kodenr'];
			if ($k !== '0' && !isset($out[$k])) {
				$out[$k] = trim((string) $r[$name]);
			}
		}
		return $out;
	};
	$rows = $read("select kodenr, box1 from grupper where art = 'DRG' order by case when fiscal_year = $y then 0 else 1 end, cast(kodenr as integer)", 'box1');
	$ownRows = (bool) $rows;
	if (!$rows) {
		$rows = $read("select kodenr, beskrivelse from grupper where art = 'DG' and fiscal_year = $y order by cast(kodenr as integer)", 'beskrivelse');
	}
	$cols = $read("select kodenr, box1 from grupper where art = 'DVRG' order by cast(kodenr as integer), id", 'box1');
	$ownCols = (bool) $cols;
	if (!$cols) {
		$cols = $read("select kodenr, beskrivelse from grupper where art = 'VG' and fiscal_year = $y order by cast(kodenr as integer)", 'beskrivelse');
	}
	uksort($rows, function ($a, $b) { return (int) $a - (int) $b; });
	uksort($cols, function ($a, $b) { return (int) $a - (int) $b; });
	return array('rows' => $rows, 'cols' => $cols, 'own_rows' => $ownRows, 'own_cols' => $ownCols);
}

/**
 * The stored form: [[customer group, type, [[item group, discount], ...]], ...] in axis order, only rows with a discount,
 * numbers as plain decimals. The page builds the same string, so an untouched matrix compares equal.
 */
function settings_discount_matrix_encode(array $rows): string
{
	return json_encode($rows);
}

function settings_discount_matrix_read(): string
{
	$axes = settings_discount_axes();
	$cells = array();
	$types = array();
	$q = db_select("select debitor, vare, rabat, rabatart from rabat order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$d = (string) (int) $r['debitor'];
		$v = (string) (int) $r['vare'];
		if (!isset($axes['rows'][$d]) || !isset($axes['cols'][$v]) || isset($cells[$d][$v])) {
			continue;
		}
		$n = (float) $r['rabat'];
		if ($n != 0) {
			$cells[$d][$v] = (string) $n;
			if (!isset($types[$d])) {
				$types[$d] = trim((string) $r['rabatart']) === 'amount' ? 'amount' : '%';
			}
		}
	}
	$out = array();
	foreach (array_keys($axes['rows']) as $d) {
		if (empty($cells[$d])) {
			continue;
		}
		$line = array();
		foreach (array_keys($axes['cols']) as $v) {
			if (isset($cells[$d][$v])) {
				$line[] = array((string) $v, $cells[$d][$v]);
			}
		}
		$out[] = array((string) $d, $types[$d], $line);
	}
	return settings_discount_matrix_encode($out);
}

/**
 * A posted matrix checked and put in the stored form, or an error text id.
 *
 * @return array{raw: string, error: int|null}
 */
function settings_discount_matrix_normalise(string $posted): array
{
	$data = json_decode($posted, true);
	if (!is_array($data)) {
		return array('raw' => '', 'error' => 5732);
	}
	$axes = settings_discount_axes();
	$byRow = array();
	foreach ($data as $line) {
		if (!is_array($line) || count($line) !== 3 || !is_array($line[2])) {
			return array('raw' => '', 'error' => 5732);
		}
		$d = (string) (int) $line[0];
		$type = $line[1] === 'amount' ? 'amount' : '%';
		if (!isset($axes['rows'][$d])) {
			continue;
		}
		foreach ($line[2] as $cell) {
			$v = (string) (int) (is_array($cell) && isset($cell[0]) ? $cell[0] : 0);
			$val = is_array($cell) && isset($cell[1]) ? str_replace(',', '.', trim((string) $cell[1])) : '';
			if (!isset($axes['cols'][$v]) || $val === '') {
				continue;
			}
			if (!is_numeric($val) || (float) $val < 0 || ($type === '%' && (float) $val > 100)) {
				return array('raw' => '', 'error' => 6876);
			}
			if ((float) $val != 0) {
				$byRow[$d]['type'] = $type;
				$byRow[$d]['cells'][$v] = (string) (float) $val;
			}
		}
	}
	$out = array();
	foreach (array_keys($axes['rows']) as $d) {
		if (empty($byRow[$d]['cells'])) {
			continue;
		}
		$line = array();
		foreach (array_keys($axes['cols']) as $v) {
			if (isset($byRow[$d]['cells'][$v])) {
				$line[] = array((string) $v, $byRow[$d]['cells'][$v]);
			}
		}
		$out[] = array((string) $d, $byRow[$d]['type'], $line);
	}
	return array('raw' => settings_discount_matrix_encode($out), 'error' => null);
}

/**
 * Write the matrix to table rabat by group number (spec G3.5, audit R6). Only cells of the groups shown are touched;
 * rows for other groups (e.g. stored by the old page by position) stay as they are.
 */
function settings_discount_matrix_write(string $raw): void
{
	$data = json_decode($raw, true);
	if (!is_array($data)) {
		return;
	}
	$axes = settings_discount_axes();
	$want = array();
	foreach ($data as $line) {
		foreach ($line[2] as $cell) {
			$want[(string) (int) $line[0]][(string) (int) $cell[0]] = array((float) $cell[1], $line[1] === 'amount' ? 'amount' : '%');
		}
	}
	$have = array();
	$q = db_select("select id, debitor, vare from rabat order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$have[(string) (int) $r['debitor']][(string) (int) $r['vare']][] = (int) $r['id'];
	}
	foreach (array_keys($axes['rows']) as $d) {
		foreach (array_keys($axes['cols']) as $v) {
			$ids = isset($have[$d][$v]) ? $have[$d][$v] : array();
			if (isset($want[$d][$v])) {
				list($n, $type) = $want[$d][$v];
				if ($ids) {
					db_modify("update rabat set rabat = '$n', rabatart = '$type' where id = " . array_shift($ids), __FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into rabat (rabat, debitorart, debitor, vareart, vare, rabatart) values ('$n', 'DG', '$d', 'VG', '$v', '$type')", __FILE__ . " linje " . __LINE__);
				}
			}
			foreach ($ids as $id) {
				db_modify("delete from rabat where id = $id", __FILE__ . " linje " . __LINE__);
			}
		}
	}
}
