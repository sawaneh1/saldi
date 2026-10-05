<?php
// ---- includes/settings/rows.php --- lap 5.0.0 --- 2026.10.05 ---
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
// 20261005 Sawaneh Settings redesign phase 4c (spec §7.3 / §8.2): the row editor behind a section of 'kind' rows.
//                  A table is a list of rows in grupper (one art) or in a table of its own; every cell is a column
//                  definition. Reading, usage counts, validation, saving with one audit row per changed cell,
//                  delete with usage check, the inaktiv flag and copying a fiscal year live here; the page is drawn
//                  by rowsView.php and behaves through javascript/settingsRows.js.

/**
 * The tables of a rows section with their defaults filled in.
 *
 * @return array<string, array<string, mixed>>
 */
function settings_rows_tables(array $section): array
{
	$out = array();
	foreach ($section['tables'] as $tableId => $t) {
		$t += array('sub' => $tableId, 'fiscal' => false, 'usage' => null, 'inactive' => false, 'exclude' => '', 'help' => null,
			'propagate' => array(), 'kode' => null, 'on_save' => null, 'row_name' => null);
		$st = $t['storage'];
		$t['kind'] = $st[0] === 'grupper' ? 'grupper' : 'table';
		$t['art'] = $t['kind'] === 'grupper' ? (string) $st[1] : '';
		$t['dbtable'] = $t['kind'] === 'grupper' ? 'grupper' : (string) $st[1];
		$t['code_col'] = isset($t['code_col']) ? (string) $t['code_col'] : (isset($t['columns']['kodenr']) ? 'kodenr' : key($t['columns']));
		foreach ($t['columns'] as $col => $def) {
			$t['columns'][$col] = $def + array('type' => 'text', 'required' => false, 'unique' => (isset($def['type']) && $def['type'] === 'code'), 'width' => '', 'numeric' => true, 'help' => null, 'options' => null, 'options_from' => null, 'derive' => null);
		}
		$out[$tableId] = $t;
	}
	return $out;
}

/**
 * The fiscal years a per-year table can be edited for, newest first.
 *
 * @return array<int, int>
 */
function settings_rows_years(): array
{
	static $years = null;
	if ($years === null) {
		$years = array();
		$q = db_select("select distinct regnskabsaar from kontoplan where regnskabsaar is not null order by regnskabsaar desc", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$years[] = (int) $r['regnskabsaar'];
		}
		global $regnaar;
		if (!$years) {
			$years[] = (int) $regnaar;
		}
	}
	return $years;
}

function settings_rows_where(array $t, ?int $year): string
{
	$w = $t['kind'] === 'grupper' ? "art = '" . db_escape_string($t['art']) . "'" : '1 = 1';
	if ($t['fiscal']) {
		$w .= " and fiscal_year = " . (int) $year;
	}
	if ($t['exclude'] !== '') {
		$w .= " and (" . $t['exclude'] . ")";
	}
	return $w;
}

/**
 * The rows of a table: id => array(id, cells (column => stored string), inactive, usage, usage_text).
 *
 * @return array<int, array<string, mixed>>
 */
function settings_rows_load(array $t, ?int $year): array
{
	$rows = array();
	$order = ($t['code_col'] === 'kodenr') ? "length(cast(kodenr as text)), cast(kodenr as text), id" : 'id';
	$q = db_select("select * from " . $t['dbtable'] . " where " . settings_rows_where($t, $year) . " order by " . $order, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$row = array('id' => (int) $r['id'], 'cells' => array(), 'inactive' => isset($r['inaktiv']) && ($r['inaktiv'] === 't' || $r['inaktiv'] === true || $r['inaktiv'] === '1'));
		foreach ($t['columns'] as $col => $def) {
			$row['cells'][$col] = ($def['type'] === 'derived') ? '' : (isset($r[$col]) ? (string) $r[$col] : '');
		}
		$usage = settings_rows_usage($t, $row);
		$row['usage'] = $usage['count'];
		$row['usage_text'] = $usage['text'];
		$rows[$row['id']] = $row;
	}
	return $rows;
}

/**
 * Where a row is referenced, as a count and a short text ("14 ordrer · 3 ansatte"), for the delete dialog (spec §8.2).
 *
 * @return array{count: int, text: string}
 */
function settings_rows_usage(array $t, array $row): array
{
	$parts = array();
	$total = 0;
	$code = isset($row['cells'][$t['code_col']]) ? trim((string) $row['cells'][$t['code_col']]) : '';
	$esc = db_escape_string($code);
	$isInt = ($code !== '' && ctype_digit($code));
	$add = function (string $sql, int $textId) use (&$parts, &$total) {
		$r = db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__));
		$n = $r ? (int) $r['n'] : 0;
		if ($n > 0) {
			$parts[] = sprintf(st_txt($textId), number_format($n, 0, ',', '.'));
			$total += $n;
		}
	};
	if ($code !== '') {
		switch ($t['usage']) {
			case 'department':
				if ($isInt) {
					$add("select count(*) as n from ordrer where afd = $code", 6422);
					$add("select count(*) as n from transaktioner where afd = $code", 6423);
					$add("select count(*) as n from ansatte where afd = $code", 6424);
				}
				$add("select count(*) as n from grupper where art = 'LG' and box1 = '$esc'", 6428);
				$r = db_fetch_array(db_select("select box3 from grupper where art = 'POS' and kodenr = '1' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
				if ($r && trim((string) $r['box3']) !== '') {
					$n = 0;
					foreach (explode("\t", (string) $r['box3']) as $till) {
						if (trim($till) === $code) {
							$n++;
						}
					}
					if ($n > 0) {
						$parts[] = sprintf(st_txt(6425), $n);
						$total += $n;
					}
				}
				break;
			case 'warehouse':
				if ($isInt) {
					$add("select count(*) as n from lagerstatus where lager = $code and beholdning <> 0", 6426);
					$add("select count(*) as n from ordrer where lager = $code", 6422);
					$add("select count(*) as n from batch_kob where lager = $code and antal <> 0", 6426);
				}
				break;
			case 'project':
				$add("select count(*) as n from ordrer where projekt = '$esc'", 6422);
				$add("select count(*) as n from transaktioner where projekt = '$esc'", 6423);
				break;
			case 'unit':
				$add("select count(*) as n from varer where enhed = '$esc' or enhed2 = '$esc'", 6427);
				break;
		}
	}
	return array('count' => $total, 'text' => implode(' · ', $parts));
}

/**
 * A read-only cell computed from other rows.
 */
function settings_rows_derived(string $name, array $row): string
{
	if ($name === 'department_warehouse') {
		$code = db_escape_string(trim((string) $row['cells']['kodenr']));
		if ($code === '') {
			return '';
		}
		$out = array();
		$q = db_select("select kodenr, beskrivelse from grupper where art = 'LG' and box1 = '$code' order by length(cast(kodenr as text)), cast(kodenr as text)", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[] = trim((string) $r['kodenr']) . ' ' . trim((string) $r['beskrivelse']);
		}
		return implode(', ', $out);
	}
	return '';
}

/**
 * The stored string shown in a cell.
 */
function settings_rows_form_value(array $def, string $raw): string
{
	if ($def['type'] === 'decimal') {
		return $raw === '' ? '' : str_replace('.', ',', rtrim(rtrim(number_format((float) $raw, 4, '.', ''), '0'), '.'));
	}
	if ($def['type'] === 'bool') {
		return trim($raw) !== '' ? '1' : '';
	}
	return $raw;
}

/**
 * A posted cell as the string to store, or an error text id.
 *
 * @return array{raw: string, error: int|null}
 */
function settings_rows_to_raw(array $def, string $value, ?int $year): array
{
	$value = trim($value);
	$error = null;
	$raw = $value;
	switch ($def['type']) {
		case 'bool':
			$raw = ($value === '1' || $value === 'on') ? 'on' : '';
			break;
		case 'code':
			if ($value !== '' && $def['numeric'] && !preg_match('/^[0-9]+$/', $value)) {
				$error = 6431;
			}
			break;
		case 'decimal':
			if ($value !== '') {
				$n = str_replace(array(' ', '.'), '', $value);
				$n = str_replace(',', '.', $n);
				if (!is_numeric($n)) {
					$error = 5732;
				} else {
					$raw = (string) (float) $n;
				}
			}
			break;
		case 'account':
			if ($value !== '') {
				if (!preg_match('/^[0-9]+$/', $value)) {
					$error = 5734;
				} else {
					global $regnaar;
					$y = $year !== null ? $year : (int) $regnaar;
					if (!db_fetch_array(db_select("select id from kontoplan where kontonr = '" . db_escape_string($value) . "' and regnskabsaar = $y", __FILE__ . " linje " . __LINE__))) {
						$error = 5734;
					}
				}
			}
			break;
		case 'select':
			$options = st_options($def);
			if ($value !== '' && !array_key_exists($value, $options)) {
				$error = 5719;
			}
			break;
	}
	if ($error === null && $raw === '' && $def['required']) {
		$error = 6419;
	}
	return array('raw' => $raw, 'error' => $error);
}

/**
 * Validate the posted rows of every table and, unless $dryRun, write the changes: new rows inserted, changed cells
 * updated, each with its audit row (spec §8.12). Nothing is written while any cell is wrong.
 *
 * @param array<string, array<string, mixed>> $tables
 * @return array{errors: array<string, int>, posted: array<string, array<string, array<string, string>>>, flash: array<int, array<int, string>>}
 */
function settings_rows_save(string $sectionId, array $tables, array $post, ?int $year, bool $dryRun): array
{
	$errors = array();
	$flash = array();
	$posted = (isset($post['r']) && is_array($post['r'])) ? $post['r'] : array();
	$plan = array();
	foreach ($tables as $tableId => $t) {
		if (empty($posted[$tableId]) || !is_array($posted[$tableId])) {
			continue;
		}
		$existing = settings_rows_load($t, $year);
		$codeCol = $t['code_col'];
		$codes = array();
		foreach ($existing as $id => $row) {
			$codes[mb_strtolower(trim((string) $row['cells'][$codeCol]))] = $id;
		}
		$seen = array();
		foreach ($posted[$tableId] as $rowId => $cells) {
			if (!is_array($cells)) {
				continue;
			}
			$rowId = (string) $rowId;
			$isNew = (strpos($rowId, 'n') === 0);
			if (!$isNew && !isset($existing[(int) $rowId])) {
				continue;
			}
			$current = $isNew ? null : $existing[(int) $rowId];
			$clean = array();
			$rowErrors = array();
			$empty = true;
			foreach ($t['columns'] as $col => $def) {
				if ($def['type'] === 'derived') {
					continue;
				}
				$v = isset($cells[$col]) ? (string) $cells[$col] : '';
				$conv = settings_rows_to_raw($def, $v, $year);
				$clean[$col] = $conv['raw'];
				if ($conv['raw'] !== '' && $def['type'] !== 'bool') {
					$empty = false;
				}
				if ($conv['error'] !== null) {
					$rowErrors[$col] = $conv['error'];
				}
			}
			if ($isNew && $empty) {
				continue;
			}
			// The code must be unique in the table (and in this year), and cannot change once the row is referenced.
			$code = mb_strtolower(trim((string) $clean[$codeCol]));
			if ($code !== '') {
				$owner = isset($codes[$code]) ? $codes[$code] : null;
				if (($owner !== null && (string) $owner !== $rowId) || isset($seen[$code])) {
					$rowErrors[$codeCol] = 6418;
				}
				$seen[$code] = true;
			}
			if (!$isNew && $current['usage'] > 0 && trim((string) $current['cells'][$codeCol]) !== trim((string) $clean[$codeCol])) {
				$rowErrors[$codeCol] = 6420;
			}
			foreach ($rowErrors as $col => $textId) {
				$errors[$tableId . '/' . $rowId . '/' . $col] = $textId;
			}
			if ($rowErrors) {
				continue;
			}
			if ($isNew) {
				$plan[] = array('insert', $tableId, $clean);
			} else {
				$changed = array();
				foreach ($clean as $col => $raw) {
					if ((string) $current['cells'][$col] !== $raw) {
						$changed[$col] = $raw;
					}
				}
				if ($changed) {
					$plan[] = array('update', $tableId, $current, $changed);
				}
			}
		}
	}
	if ($errors || $dryRun || !$plan) {
		return array('errors' => $errors, 'posted' => $posted, 'flash' => $flash);
	}
	global $regnaar;
	transaktion('begin');
	foreach ($plan as $step) {
		$t = $tables[$step[1]];
		$objekt = $sectionId . '.' . $step[1];
		if ($step[0] === 'insert') {
			$cols = array();
			$vals = array();
			foreach ($step[2] as $col => $raw) {
				$cols[] = $col;
				$vals[] = "'" . db_escape_string($raw) . "'";
			}
			if ($t['kind'] === 'grupper') {
				$cols[] = 'art';
				$vals[] = "'" . db_escape_string($t['art']) . "'";
				if ($t['kode'] !== null) {
					$cols[] = 'kode';
					$vals[] = "'" . db_escape_string((string) $t['kode']) . "'";
				}
				if ($t['fiscal']) {
					$cols[] = 'fiscal_year';
					$vals[] = (int) ($year !== null ? $year : $regnaar);
				}
			}
			db_modify("insert into " . $t['dbtable'] . " (" . implode(', ', $cols) . ") values (" . implode(', ', $vals) . ")", __FILE__ . " linje " . __LINE__);
			$code = (string) $step[2][$t['code_col']];
			SettingsService::auditRow($sectionId, $step[1], $objekt . '#' . $code, '', json_encode($step[2], JSON_UNESCAPED_UNICODE), 'setting.row_created');
		} else {
			$current = $step[2];
			$sets = array();
			foreach ($step[3] as $col => $raw) {
				$sets[] = $col . " = '" . db_escape_string($raw) . "'";
			}
			$where = "id = " . (int) $current['id'] . ($t['kind'] === 'grupper' ? " and art = '" . db_escape_string($t['art']) . "'" : '');
			db_modify("update " . $t['dbtable'] . " set " . implode(', ', $sets) . " where $where", __FILE__ . " linje " . __LINE__);
			$code = (string) $current['cells'][$t['code_col']];
			foreach ($step[3] as $col => $raw) {
				if (in_array($col, $t['propagate'], true) && $t['kind'] === 'grupper') {
					// Year-agnostic classifications live on every year's copy of the row (risk review R8).
					db_modify("update grupper set $col = '" . db_escape_string($raw) . "' where art = '" . db_escape_string($t['art']) . "' and kodenr = '" . db_escape_string($code) . "'", __FILE__ . " linje " . __LINE__);
				}
				SettingsService::auditRow($sectionId, $step[1] . '.' . $col, $objekt . '#' . $code, (string) $current['cells'][$col], $raw, 'setting.row_updated');
			}
		}
		if ($t['on_save'] !== null) {
			settings_rows_after_save((string) $t['on_save']);
		}
	}
	transaktion('commit');
	return array('errors' => array(), 'posted' => $posted, 'flash' => $flash);
}

/**
 * Follow-ups once a table is saved ('on_save' on the table).
 */
function settings_rows_after_save(string $hook): void
{
	if ($hook === 'warehouses_to_departments') {
		// A department's default warehouse (AFD box1, read by the order pages) follows the warehouses flagged with
		// it here; a department without a flagged warehouse keeps whatever it had.
		$q = db_select("select box1 as afd, min(cast(kodenr as text)) as lager from grupper where art = 'LG' and coalesce(box1, '') <> '' group by box1", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			db_modify("update grupper set box1 = '" . db_escape_string((string) $r['lager']) . "' where art = 'AFD' and cast(kodenr as text) = '" . db_escape_string((string) $r['afd']) . "'", __FILE__ . " linje " . __LINE__);
		}
		// A default warehouse that no longer exists is cleared.
		db_modify("update grupper set box1 = '' where art = 'AFD' and coalesce(box1, '') <> '' and box1 not in (select cast(kodenr as text) from grupper where art = 'LG')", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * Delete a row that nothing references (spec §8.2). Returns a flash entry.
 *
 * @return array{0: string, 1: string}
 */
function settings_rows_delete(string $sectionId, string $tableId, array $t, int $id, ?int $year): array
{
	$rows = settings_rows_load($t, $year);
	if (!isset($rows[$id])) {
		return array('err', st_txt(5719));
	}
	$row = $rows[$id];
	$code = trim((string) $row['cells'][$t['code_col']]);
	if ($row['usage'] > 0) {
		return array('err', sprintf(st_txt(6414), $code) . ' – ' . sprintf(st_txt(6415), $row['usage_text']));
	}
	$where = "id = $id" . ($t['kind'] === 'grupper' ? " and art = '" . db_escape_string($t['art']) . "'" : '');
	db_modify("delete from " . $t['dbtable'] . " where $where", __FILE__ . " linje " . __LINE__);
	SettingsService::auditRow($sectionId, $tableId, $sectionId . '.' . $tableId . '#' . $code, json_encode($row['cells'], JSON_UNESCAPED_UNICODE), '', 'setting.row_deleted');
	if ($t['on_save'] !== null) {
		settings_rows_after_save((string) $t['on_save']);
	}
	return array('ok', sprintf(st_txt(6430), $code !== '' ? $code : (string) $id));
}

/**
 * Mark a row inactive (soft delete) or active again. Returns a flash entry.
 *
 * @return array{0: string, 1: string}
 */
function settings_rows_set_inactive(string $sectionId, string $tableId, array $t, int $id, bool $inactive, ?int $year): array
{
	$rows = settings_rows_load($t, $year);
	if (!isset($rows[$id]) || !$t['inactive'] || $t['kind'] !== 'grupper') {
		return array('err', st_txt(5719));
	}
	$code = trim((string) $rows[$id]['cells'][$t['code_col']]);
	db_modify("update grupper set inaktiv = " . ($inactive ? 'true' : 'false') . " where id = $id and art = '" . db_escape_string($t['art']) . "'", __FILE__ . " linje " . __LINE__);
	SettingsService::auditRow($sectionId, $tableId . '.inaktiv', $sectionId . '.' . $tableId . '#' . $code, $inactive ? '' : 'on', $inactive ? 'on' : '', $inactive ? 'setting.row_deactivated' : 'setting.row_updated');
	return array('ok', sprintf(st_txt($inactive ? 6435 : 6437), $code));
}

/**
 * Copy a per-year table from another fiscal year into $year (only when $year has no rows yet).
 */
function settings_rows_copy_year(string $sectionId, string $tableId, array $t, int $fromYear, int $year): array
{
	if (!$t['fiscal'] || $t['kind'] !== 'grupper' || settings_rows_load($t, $year)) {
		return array('err', st_txt(5719));
	}
	$cols = array_keys(array_filter($t['columns'], function ($def) {
		return $def['type'] !== 'derived';
	}));
	$list = implode(', ', $cols);
	db_modify("insert into grupper ($list, art, kode, fiscal_year) select $list, art, kode, $year from grupper where " . settings_rows_where($t, $fromYear), __FILE__ . " linje " . __LINE__);
	SettingsService::auditRow($sectionId, $tableId, $sectionId . '.' . $tableId, (string) $fromYear, (string) $year, 'setting.row_created');
	return array('ok', sprintf(st_txt(6440), $fromYear));
}
