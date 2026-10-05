<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/settings/components.php --- lap 5.0.0 --- 2026.09.29 ---
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
// 20260929 Sawaneh Settings redesign phase 4a (spec §7.3, §8.1): the components every generated
//                  settings form is built from, and the conversion between a posted field and
//                  the stored string. Styles live in css/unified-components.css (.st-*).
// 20261001 Sawaneh Accessibility §8.6: readable text on a user-chosen button colour, darker shade for links/outlines.
// 20261002 Sawaneh Phase 4b: type 'date' (shown dd-mm-yyyy, stored yyyy-mm-dd), decimals stored with a dot, 'range' rule.
// 20261002 Sawaneh Hand-over 2 Oct (A2, §8.0): a field is a row - label and help left, control right, amber dot when changed,
//                  dependent fields indented; actions are rows too.
// 20261004 Sawaneh G10.1: 'decimal_comma', options from departments, sales VAT groups and table names.
// 20261004 Sawaneh st_current_form_value(): a setting without a stored row shows its registry default.
// 20261004 Sawaneh G4.3: 'value_map' (form value <-> stored value, e.g. tab), rules 'required' and 'csv_url', supplier names.
// 20261004 Sawaneh G2.6: a secret stored URL-encoded ('urlencode'), 'ensure_suffix' on text fields.
// 20261003 Sawaneh G3.4 Reminders: type 'creditor' (lookup, stored as adresser id), options from the user list, a group
//                  label above a run of fields ("Rykker 1"), a help line under a card heading.
// 20261003 Sawaneh G6.3 E-mail: type 'textarea', a label suffix (the form language of a sender field), mixed select labels.
// 20261004 Sawaneh §8.13: an action shows what it touches (impact) under its help and in its dialog.
// 20261004 Sawaneh G10.6: 'color' type, a #rrggbb text with a colour swatch beside it (empty allowed).
// 20261002 Sawaneh Phase 4b batch 2 (G9): write-only 'secret' control (masked, Skift), 'info', 'link' and 'mini' rows,
//                  computed select options, rule 'setting_set', lock 'ht_keys:<var>' for keys the installation manages.

include_once(__DIR__ . '/SettingsService.php');

/**
 * Contrast ratio of two #rrggbb colours (WCAG 2.1).
 */
function st_contrast(string $a, string $b): float
{
	$lum = function (string $hex): float {
		$hex = ltrim($hex, '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$c = array();
		foreach (array(0, 2, 4) as $i) {
			$v = hexdec(substr($hex, $i, 2)) / 255;
			$c[] = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	};
	$la = $lum($a);
	$lb = $lum($b);
	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * The CSS variables of a settings page from the user's button colours (spec §8.6): the fill as chosen,
 * text on the fill that can be read (dark text when the chosen colour is too light), and a darker
 * shade of the colour for links and focus outlines on white.
 */
function st_accent_style(string $buttonColor, string $buttonTxtColor): string
{
	$norm = function (string $c, string $fallback): string {
		$c = trim($c);
		if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $c, $m)) {
			return '#' . (strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1]);
		}
		return $fallback;
	};
	$accent = $norm($buttonColor, '#114691');
	$txt = $norm($buttonTxtColor, '#ffffff');
	if (st_contrast($accent, $txt) < 4.5) {
		$txt = (st_contrast($accent, '#1c2431') >= st_contrast($accent, '#ffffff')) ? '#1c2431' : '#ffffff';
	}
	$ink = $accent;
	for ($i = 0; $i < 12 && st_contrast($ink, '#ffffff') < 4.5 && function_exists('darkenColor'); $i++) {
		$ink = darkenColor($ink, 0.15);
	}
	return '--st-accent: ' . $accent . '; --st-accent-txt: ' . $txt . '; --st-accent-ink: ' . $ink . ';';
}

function st_charset(): string
{
	global $db_encode;
	return (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8';
}

function st_h($s): string
{
	return htmlspecialchars((string) $s, ENT_QUOTES, st_charset());
}

/**
 * A text as plain text. Some rows in tekster hold HTML entities (&apos;), written for the old
 * pages that print them unescaped; here every text is escaped on output, so they are decoded first.
 */
function st_txt($id): string
{
	global $sprog_id;
	return html_entity_decode(findtekst((string) $id, (int) $sprog_id), ENT_QUOTES | ENT_HTML5, st_charset());
}

/**
 * The company name as the topbar shows it (Stamdata), the ledger name when that is empty.
 */
function st_company(): string
{
	global $regnskab;
	$r = db_fetch_array(db_select("select firmanavn from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
	return ($r && trim((string) $r['firmanavn']) !== '') ? trim((string) $r['firmanavn']) : (string) $regnskab;
}

function st_t($id): string
{
	return st_h(st_txt($id));
}

/**
 * A database timestamp on the clock the rest of Saldi shows (PHP's). The database server may run
 * in another time zone; the difference is measured once per request, in quarters of an hour.
 */
function st_local_time(string $dbTimestamp, string $format): string
{
	static $offset = null;
	if ($offset === null) {
		$r = db_fetch_array(db_select("select now() as t", __FILE__ . " linje " . __LINE__));
		$offset = $r ? (int) (round((strtotime(substr((string) $r['t'], 0, 19)) - time()) / 900) * 900) : 0;
	}
	return date($format, strtotime(substr($dbTimestamp, 0, 19)) - $offset);
}

// ---------------------------------------------------------------- lookups

function st_item_varenr(string $id): string
{
	if ((int) $id <= 0) {
		return '';
	}
	$r = db_fetch_array(db_select("select varenr from varer where id = " . (int) $id, __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['varenr'] : '';
}

/**
 * @return array{id: string, name: string}|null
 */
function st_item_by_varenr(string $varenr): ?array
{
	$r = db_fetch_array(db_select("select id, beskrivelse from varer where varenr = '" . db_escape_string($varenr) . "'", __FILE__ . " linje " . __LINE__));
	return $r ? array('id' => (string) $r['id'], 'name' => (string) $r['beskrivelse']) : null;
}

function st_creditor_kontonr(string $id): string
{
	if ((int) $id <= 0) {
		return '';
	}
	$r = db_fetch_array(db_select("select kontonr from adresser where id = " . (int) $id . " and art = 'K'", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['kontonr'] : '';
}

/**
 * @return array{id: string, name: string}|null
 */
function st_creditor_by_kontonr(string $kontonr): ?array
{
	$r = db_fetch_array(db_select("select id, firmanavn from adresser where art = 'K' and kontonr = '" . db_escape_string($kontonr) . "' order by id limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? array('id' => (string) $r['id'], 'name' => (string) $r['firmanavn']) : null;
}

function st_account_name(string $kontonr): ?string
{
	global $regnaar;
	if (!preg_match('/^[0-9]+$/', $kontonr)) {
		return null;
	}
	$r = db_fetch_array(db_select("select beskrivelse from kontoplan where regnskabsaar = '" . (int) $regnaar . "' and kontonr = '" . db_escape_string($kontonr) . "'", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['beskrivelse'] : null;
}

// ---------------------------------------------------------------- stored string <-> form value

/**
 * What the form control holds for a stored string ('1'/'0' for a toggle, the item number for an item).
 */
function st_form_value(array $def, string $raw): string
{
	if ($def['type'] === 'secret') {
		return '';
	}
	if (isset($def['value_map'])) {
		$form = array_search($raw, $def['value_map'], true);
		if ($form !== false) {
			return (string) $form;
		}
	}
	if ($def['type'] === 'bool') {
		return SettingsService::decode($def, $raw) ? '1' : '0';
	}
	if ($def['type'] === 'item' && isset($def['item_as']) && $def['item_as'] === 'id') {
		return st_item_varenr($raw);
	}
	if ($def['type'] === 'creditor' && isset($def['creditor_as']) && $def['creditor_as'] === 'id') {
		return st_creditor_kontonr($raw);
	}
	if ($def['type'] === 'date') {
		return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) ? $m[3] . '-' . $m[2] . '-' . $m[1] : $raw;
	}
	return $raw;
}

/**
 * What the form control holds for a setting as it is stored now. A setting without a stored row is shown with its
 * registry default, which is what the readers use (a switch whose default is on must not show as off).
 */
function st_current_form_value(array $def): string
{
	$key = (string) $def['key'];
	$raw = SettingsService::raw($key);
	if ($raw === '' && array_key_exists('default', $def) && $def['default'] !== '' && $def['default'] !== null && $def['default'] !== false
		&& !in_array($def['type'], array('secret', 'action', 'info', 'link', 'mini'), true) && !SettingsService::exists($key)) {
		return st_form_value($def, st_default_raw($def));
	}
	return st_form_value($def, $raw);
}

/**
 * The stored string for a posted value, or an error text id when the value is not valid.
 *
 * @param array<string, string> $posted every posted field of the form, for 'requires' rules
 * @return array{raw: string, error: int|null}
 */
function st_posted_to_raw(array $def, string $value, array $posted): array
{
	$value = trim($value);
	$error = null;
	$raw = $value;
	switch ($def['type']) {
		case 'secret':
			// The readers of some secrets expect them URL-encoded (an ftp://user:password@host address).
			if (isset($def['storage'][4]) && $def['storage'][0] === 'grupper' && $def['storage'][4] === 'urlencode') {
				$raw = urlencode($value);
			}
			break;
		case 'bool':
			$raw = SettingsService::encode($def, $value === '1');
			break;
		case 'int':
			if ($value === '') {
				$raw = !empty($def['empty_ok']) ? '' : '0';
			} elseif (!preg_match('/^-?[0-9]+$/', $value)) {
				$error = 5732;
			} else {
				$raw = (string) (int) $value;
			}
			break;
		case 'decimal':
			if ($value !== '' && !preg_match('/^-?[0-9]+([.,][0-9]+)?$/', $value)) {
				$error = 5732;
			} elseif (!empty($def['decimal_comma'])) {
				// Read back through usdecimal() by its reader, so it keeps the Danish comma.
				$raw = str_replace('.', ',', $value);
			} else {
				// Stored with a dot, as the old pages did through usdecimal().
				$raw = str_replace(',', '.', $value);
			}
			break;
		case 'date':
			if ($value === '') {
				$raw = '';
			} elseif (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4})$/', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
				$raw = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
			} else {
				$error = 6010;
			}
			break;
		case 'email':
			if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
				$error = 5733;
			}
			break;
		case 'select':
			if (!isset(st_options($def)[$value])) {
				$raw = (string) $def['default'];
			}
			break;
		case 'color':
			$raw = strtolower($value);
			if ($raw !== '' && !preg_match('/^#[0-9a-f]{6}$/', $raw)) {
				$error = 6361;
			}
			break;
		case 'text':
			if (!empty($def['ensure_suffix']) && $value !== '' && substr($value, -strlen($def['ensure_suffix'])) !== $def['ensure_suffix']) {
				$raw = $value . $def['ensure_suffix'];
			}
			if (is_array($def['validate']) && $def['validate'][0] === 'ip_list' && $value !== '') {
				foreach (explode(',', $value) as $ip) {
					$ip = trim($ip);
					if ($ip !== '*' && !filter_var($ip, FILTER_VALIDATE_IP)) {
						$error = 6060;
						break;
					}
				}
				$raw = implode(',', array_map('trim', explode(',', $value)));
			}
			break;
		case 'account':
			if ($value !== '' && st_account_name($value) === null) {
				$error = 5734;
			}
			break;
		case 'creditor':
			if ($value === '') {
				$raw = (isset($def['creditor_as']) && $def['creditor_as'] === 'id') ? '' : '';
			} else {
				$creditor = st_creditor_by_kontonr($value);
				if (!$creditor) {
					$error = 6204;
				} elseif (isset($def['creditor_as']) && $def['creditor_as'] === 'id') {
					$raw = $creditor['id'];
				}
			}
			break;
		case 'item':
			if ($value === '') {
				$raw = (isset($def['item_as']) && $def['item_as'] === 'id') ? '0' : '';
			} else {
				$item = st_item_by_varenr($value);
				if (!$item) {
					$error = 5735;
				} elseif (isset($def['item_as']) && $def['item_as'] === 'id') {
					$raw = $item['id'];
				}
			}
			break;
	}
	if ($error === null && is_array($def['validate']) && $def['validate'][0] === 'required' && $value === '') {
		$error = 6252;
	}
	if ($error === null && is_array($def['validate']) && $def['validate'][0] === 'csv_url' && $value !== '') {
		$path = (string) parse_url($value, PHP_URL_PATH);
		if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $value) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv') {
			$error = 6248;
		}
	}
	if ($error === null && isset($def['value_map'][$raw])) {
		$raw = $def['value_map'][$raw];
	}
	if ($error === null && $value !== '' && is_array($def['validate']) && $def['validate'][0] === 'range' && ((int) $value < (int) $def['validate'][1] || (int) $value > (int) $def['validate'][2])) {
		$error = 5732;
	}
	if ($error === null && is_array($def['validate']) && $def['validate'][0] === 'requires' && $def['type'] === 'bool' && $value === '1') {
		$other = isset($posted[$def['validate'][1]]) ? trim($posted[$def['validate'][1]]) : null;
		if ($other === null) {
			$otherDef = SettingsService::definition($def['validate'][1]);
			$other = $otherDef ? st_current_form_value($otherDef + array('key' => $def['validate'][1])) : '';
		}
		if ($other === '' || $other === '0') {
			$error = (int) $def['validate'][2];
		}
	}
	return array('raw' => $raw, 'error' => $error);
}

/**
 * A stored string as the reader sees it in the change history and in "Standard: ...".
 */
function st_display_value(array $def, string $raw): string
{
	switch ($def['type']) {
		case 'bool':
			return SettingsService::decode($def, $raw) ? st_txt(5715) : st_txt(5716);
		case 'select':
			$options = st_options($def);
			return isset($options[$raw]) ? html_entity_decode(st_option_label($def, $options[$raw]), ENT_QUOTES, st_charset()) : $raw;
		case 'textarea':
			$raw = trim(preg_replace('/\s+/', ' ', $raw));
			return $raw === '' ? '—' : (mb_strlen($raw) > 60 ? mb_substr($raw, 0, 57) . '…' : $raw);
		case 'secret':
			return st_txt(5713);
		case 'date':
			return $raw === '' ? '—' : st_form_value($def, $raw);
		case 'item':
			$v = (isset($def['item_as']) && $def['item_as'] === 'id') ? st_item_varenr($raw) : $raw;
			return $v === '' ? '—' : $v;
		case 'creditor':
			$v = (isset($def['creditor_as']) && $def['creditor_as'] === 'id') ? st_creditor_kontonr($raw) : $raw;
			return $v === '' ? '—' : $v;
		default:
			if ($raw === '') {
				return '—';
			}
			return $raw . st_unit($def, ' ');
	}
}

/**
 * The options of a select: as defined, or computed ('options_from') from the company's own lists.
 *
 * @return array<string, mixed> value => text id, or value => literal text when 'options_literal' is set
 */
function st_options(array $def): array
{
	if (!isset($def['options_from'])) {
		return isset($def['options']) ? $def['options'] : array();
	}
	static $cache = array();
	$from = (string) $def['options_from'];
	if (!isset($cache[$from])) {
		$cache[$from] = array('' => '');
		if ($from === 'item_groups') {
			$q = db_select("select kodenr, beskrivelse from grupper where art = 'VG' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$cache[$from][(string) $r['kodenr']] = $r['kodenr'] . ' : ' . $r['beskrivelse'];
			}
		} elseif ($from === 'departments') {
			$cache[$from] = array('0' => '');
			$q = db_select("select kodenr, beskrivelse from grupper where art = 'AFD' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$cache[$from][(string) $r['kodenr']] = (string) $r['beskrivelse'];
			}
		} elseif ($from === 'vat_sales') {
			global $regnaar;
			$cache[$from] = array('0' => '');
			$q = db_select("select kodenr, beskrivelse from grupper where art = 'SM' and fiscal_year = '" . (int) $regnaar . "' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$cache[$from][(string) $r['kodenr']] = (string) $r['beskrivelse'];
			}
		} elseif ($from === 'tables') {
			// POS/2 box7 holds the table names; a till stores the table's place in that list.
			global $regnaar;
			$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' order by (coalesce(fiscal_year, 0) = " . (int) $regnaar . ") desc, id limit 1", __FILE__ . " linje " . __LINE__));
			$names = ($r && trim((string) $r['box7']) !== '') ? explode("\t", (string) $r['box7']) : array();
			foreach ($names as $i => $name) {
				$cache[$from][(string) $i] = ($i + 1) . ' ' . trim($name);
			}
		} elseif ($from === 'creditor_names') {
			// Stored by name, as the old price-list page did (grupper PL box9).
			$q = db_select("select firmanavn from adresser where art = 'K' and (lukket is null or lukket != 'on') order by firmanavn", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$name = trim((string) $r['firmanavn']);
				if ($name !== '') {
					$cache[$from][$name] = $name;
				}
			}
		} elseif ($from === 'users') {
			$cache[$from] = array('' => 2498);
			$q = db_select("select id, brugernavn from brugere order by brugernavn", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$cache[$from][(string) $r['id']] = (string) $r['brugernavn'];
			}
		}
	}
	return $cache[$from];
}

/**
 * A label that is a text id or, for product names such as "GLS", the literal text.
 */
function st_label($label): string
{
	return is_int($label) || ctype_digit((string) $label) ? st_t($label) : st_h($label);
}

/**
 * An option's label: a text id, or the literal text ('options_literal' for all, 'options_mixed' per option).
 */
function st_option_label(array $def, $label): string
{
	if (!empty($def['options_literal'])) {
		return st_h($label);
	}
	if (!empty($def['options_mixed'])) {
		return st_label($label);
	}
	return st_t($label);
}

function st_unit(array $def, string $prefix = ''): string
{
	if (!isset($def['unit'])) {
		return '';
	}
	return $prefix . (is_int($def['unit']) ? st_txt($def['unit']) : (string) $def['unit']);
}

function st_default_raw(array $def): string
{
	if (!array_key_exists('default', $def)) {
		return '';
	}
	if ($def['type'] === 'bool') {
		return SettingsService::encode($def, (bool) $def['default']);
	}
	return (string) $def['default'];
}

// ---------------------------------------------------------------- dependencies

/**
 * Server side of 'visible_if'. $values holds the form values ('1'/'0', option values) of the
 * fields on the page; a parent that is not on the page is read from storage.
 *
 * @param array<string, string> $values
 */
function st_visible(array $def, array $values): bool
{
	$rule = $def['visible_if'];
	if (!$rule) {
		return true;
	}
	if ($rule[0] === 'module') {
		return SettingsService::hasModule((string) $rule[1]);
	}
	$parent = (string) $rule[1];
	$pdef = SettingsService::definition($parent);
	if (isset($values[$parent]) && !($pdef && $pdef['type'] === 'secret')) {
		$v = $values[$parent];
	} else {
		$v = $pdef ? st_current_form_value($pdef + array('key' => $parent)) : '';
	}
	if ($rule[0] === 'setting') {
		return ($v === '1') === (bool) $rule[2];
	}
	if ($rule[0] === 'setting_in') {
		return in_array($v, (array) $rule[2], true);
	}
	if ($rule[0] === 'setting_set') {
		// A secret never reaches the form, so the stored value decides.
		return $v !== '' || ($pdef !== null && $pdef['type'] === 'secret' && SettingsService::raw($parent) !== '');
	}
	return true;
}

/**
 * Text id of the reason a field cannot be changed, or 0.
 */
function st_locked(array $def): int
{
	if (empty($def['locked_if'])) {
		return 0;
	}
	if (strpos((string) $def['locked_if'], 'ht_keys:') === 0) {
		// Hosted installations seed this key from .ht_keys.txt at every login (includes/betweenUpdates.php),
		// so a value typed here would be overwritten; the field is read-only while the file names the key.
		if (st_ht_key(substr((string) $def['locked_if'], 8)) !== '') {
			return (int) $def['locked_text'];
		}
		return 0;
	}
	if ($def['locked_if'] === 'batch_control') {
		// Quick invoicing cannot be combined with batch control on an item group.
		if (db_fetch_array(db_select("select id from grupper where art = 'VG' and box9 = 'on'", __FILE__ . " linje " . __LINE__))) {
			return (int) $def['locked_text'];
		}
	}
	return 0;
}

/**
 * A value of the installation's .ht_keys.txt (two levels above the Saldi root), '' when the file or the key is missing.
 */
function st_ht_key(string $var): string
{
	static $keys = null;
	if ($keys === null) {
		$keys = array();
		$file = __DIR__ . '/../../../.ht_keys.txt';
		if (is_file($file)) {
			$keys = (function () use ($file) {
				include $file;
				return get_defined_vars();
			})();
		}
	}
	return isset($keys[$var]) && is_string($keys[$var]) ? trim($keys[$var]) : '';
}

// ---------------------------------------------------------------- rendering

/**
 * One field of a generated form: a row with label and help text on the left and the control on the
 * right (settings redesign §8.0, prototype_indstillinger_v4.html).
 *
 * @param array<string, mixed> $state value (form value), original, error (text id), readonly, locked (text id), visible, mine
 */
function st_render_field(array $def, array $state): void
{
	$key = $def['key'];
	$id = 'f-' . str_replace('.', '-', $key);
	$value = (string) $state['value'];
	$disabled = (!empty($state['readonly']) || !empty($state['locked']));
	$rule = $def['visible_if'];
	$classes = 'st-field st-type-' . $def['type'];
	if (!empty($state['error'])) {
		$classes .= ' st-invalid';
	}
	if (!empty($state['mine'])) {
		$classes .= ' st-mine';
	}
	if ($rule && $rule[0] !== 'module') {
		// Indented under its parent only when the parent sits in the same card.
		$parent = SettingsService::definition((string) $rule[1]);
		if ($parent && $parent['sub'] === $def['sub'] && $parent['group'] . '.' . $parent['section'] === $def['group'] . '.' . $def['section']) {
			$classes .= ' st-dep';
		}
	}
	$clientRule = ($rule && $rule[0] !== 'module') ? json_encode($rule) : '';
	$defaultForm = st_form_value($def, st_default_raw($def));
	$help = isset($def['help']) ? st_txt($def['help']) : '';
	$hasDefault = array_key_exists('default', $def) && !($def['default'] === '' || $def['default'] === null);
	if ($def['type'] === 'bool') {
		$hasDefault = array_key_exists('default', $def);
	} elseif ($def['type'] === 'select') {
		// A default whose option has no name (the empty "none" choice) is no default to show or reset to.
		$hasDefault = array_key_exists('default', $def) && (string) $def['default'] !== '' && st_display_value($def, st_default_raw($def)) !== '';
	}
	$defaultText = $hasDefault ? st_txt(5714) . ' ' . st_display_value($def, st_default_raw($def)) . '.' : '';
	$nameAttr = $disabled ? '' : ' name="f[' . st_h($key) . ']"';
	$invalid = !empty($state['error']) ? ' aria-invalid="true" aria-describedby="e-' . $id . '"' : '';
	?>
<?php if (!empty($state['group_heading'])) { ?>
<div class="st-grouphead"><?= st_h($state['group_heading']) ?></div>
<?php } ?>
<div class="<?= $classes ?>" id="<?= st_h($key) ?>" data-key="<?= st_h($key) ?>" data-default="<?= st_h($defaultForm) ?>"<?= $clientRule !== '' ? ' data-visible-if="' . st_h($clientRule) . '"' : '' ?><?= empty($state['visible']) ? ' hidden' : '' ?>>
  <div class="st-tx">
    <span class="st-labelrow">
	<?php $labelText = st_t($def['label']) . ((isset($def['label_suffix']) && empty($state['in_group'])) ? ' <span class="st-label-suffix">· ' . st_h($def['label_suffix']) . '</span>' : ''); ?>
	<?php if ($def['type'] === 'bool') { ?>
      <span class="st-label" id="l-<?= $id ?>"><?= $labelText ?></span>
	<?php } else { ?>
      <label class="st-label" for="<?= $id ?>"><?= $labelText ?></label>
	<?php } ?>
      <i class="st-chg" aria-hidden="true"></i>
      <button type="button" class="st-copy" data-copy title="<?= st_t(5741) ?>" aria-label="<?= st_t(5741) ?>"><i class='bx bx-link'></i></button>
    </span>
	<?php if ($help !== '' || $defaultText !== '') { ?>
    <span class="st-help"><?= st_h($help) ?><?= ($help !== '' && $defaultText !== '') ? ' ' : '' ?><?php if ($defaultText !== '') { ?><span class="st-default"><?= st_h($defaultText) ?></span><?php } ?><?php if ($hasDefault && !$disabled) { ?> <button type="button" class="st-reset" data-reset hidden><?= st_t(5717) ?></button><?php } ?></span>
	<?php } ?>
	<?php if (!empty($state['locked'])) { ?>
    <span class="st-locked"><i class='bx bx-lock-alt' aria-hidden="true"></i> <?= st_t($state['locked']) ?></span>
	<?php } ?>
	<?php if (!empty($state['error'])) { ?>
    <span class="st-error" id="e-<?= $id ?>"><i class='bx bx-error-circle' aria-hidden="true"></i> <?= st_t($state['error']) ?></span>
	<?php } ?>
  </div>
  <div class="st-ctl">
	<?php if ($def['type'] === 'bool') { ?>
    <button type="button" role="switch" class="st-switch" id="<?= $id ?>" aria-checked="<?= $value === '1' ? 'true' : 'false' ?>" aria-labelledby="l-<?= $id ?>"<?= $disabled ? ' disabled' : '' ?>><span></span></button>
    <input type="hidden"<?= $nameAttr ?> value="<?= st_h($value) ?>" data-control>
	<?php } elseif ($def['type'] === 'select') { ?>
    <select class="st-input st-select" id="<?= $id ?>"<?= $nameAttr ?> data-control<?= $disabled ? ' disabled' : '' ?><?= $invalid ?>>
		<?php foreach (st_options($def) as $optValue => $optLabel) { ?>
      <option value="<?= st_h($optValue) ?>"<?= ((string) $optValue === $value) ? ' selected' : '' ?>><?= st_option_label($def, $optLabel) ?></option>
		<?php } ?>
    </select>
	<?php } elseif ($def['type'] === 'account' || $def['type'] === 'item' || $def['type'] === 'creditor') {
		$resolved = '';
		if ($value !== '') {
			if ($def['type'] === 'account') {
				$resolved = (string) st_account_name($value);
			} elseif ($def['type'] === 'creditor') {
				$creditor = st_creditor_by_kontonr($value);
				$resolved = $creditor ? $creditor['name'] : '';
			} else {
				$item = st_item_by_varenr($value);
				$resolved = $item ? $item['name'] : '';
			}
		}
		?>
    <div class="st-lookup" data-lookup="<?= $def['type'] ?>">
      <span class="st-look"><input class="st-input" type="text" id="<?= $id ?>"<?= $nameAttr ?> value="<?= st_h($value) ?>" autocomplete="off" data-control role="combobox" aria-autocomplete="list" aria-expanded="false"<?= $disabled ? ' readonly' : '' ?><?= $invalid ?>><i class='bx bx-search' aria-hidden="true"></i></span>
      <span class="st-resolved"><?= $resolved !== '' ? '· ' . st_h($resolved) : '' ?></span>
      <ul class="st-lookup-list" role="listbox" hidden></ul>
    </div>
	<?php } elseif ($def['type'] === 'secret') {
		$isSet = (SettingsService::raw($key) !== '');
		$when = isset($state['set_at']) && $state['set_at'] !== '' ? st_local_time((string) $state['set_at'], 'j/n-Y') : '';
		?>
    <div class="st-secret<?= $isSet ? ' st-secret-set' : '' ?>" data-secret>
	<?php if ($isSet) { ?>
      <span class="st-secret-state"><span class="st-secret-mask" aria-hidden="true">••••••••</span><span class="st-secret-when"><?= st_t(6139) ?><?= $when !== '' ? ' ' . st_h($when) : '' ?></span></span>
		<?php if (!$disabled) { ?>
      <button type="button" class="st-tl" data-secret-change aria-controls="<?= $id ?>"><?= st_t(6137) ?></button>
		<?php } ?>
	<?php } ?>
      <input class="st-input" type="password" id="<?= $id ?>"<?= $nameAttr ?> value="" autocomplete="new-password" data-control<?= $disabled ? ' readonly' : '' ?><?= $invalid ?><?= $isSet ? ' hidden' : '' ?>>
    </div>
	<?php } elseif ($def['type'] === 'textarea') { ?>
    <textarea class="st-input st-textarea" id="<?= $id ?>"<?= $nameAttr ?> rows="6" data-control<?= $disabled ? ' readonly' : '' ?><?= $invalid ?>><?= st_h($value) ?></textarea>
	<?php } elseif ($def['type'] === 'info') { ?>
    <span class="st-info"><?= function_exists('settings_integration_info') ? settings_integration_info($def) : '' ?></span>
	<?php } elseif ($def['type'] === 'link') { ?>
    <a class="st-btn" href="<?= st_h($def['href']) ?>"<?= !empty($def['blank']) ? ' target="_blank" rel="noopener"' : '' ?>><?= st_t($def['button']) ?><?php if (!empty($def['blank'])) { ?> <i class='bx bx-link-external' aria-hidden="true"></i><?php } ?></a>
	<?php } elseif ($def['type'] === 'color') { ?>
    <span class="st-color">
      <input class="st-input st-input-short" type="text" id="<?= $id ?>"<?= $nameAttr ?> value="<?= st_h($value) ?>" placeholder="#rrggbb" maxlength="7" data-control<?= $disabled ? ' readonly' : '' ?><?= $invalid ?>>
      <input type="color" class="st-swatch" value="<?= preg_match('/^#[0-9a-f]{6}$/i', $value) ? st_h($value) : '#ffffff' ?>" data-swatch-for="<?= $id ?>" aria-label="<?= st_t(1786) ?>" tabindex="-1"<?= $disabled ? ' disabled' : '' ?>>
    </span>
	<?php } elseif ($def['type'] === 'mini') {
		if (function_exists('settings_integration_mini')) {
			settings_integration_mini($def, $disabled);
		}
	} else {
		$type = ($def['type'] === 'email') ? 'email' : 'text';
		$mode = ($def['type'] === 'int') ? ' inputmode="numeric"' : (($def['type'] === 'decimal') ? ' inputmode="decimal"' : '');
		$short = ($def['type'] === 'int' || $def['type'] === 'decimal' || $def['type'] === 'date') ? ' st-input-short' : '';
		if ($def['type'] === 'date') {
			$mode = ' inputmode="numeric" placeholder="dd-mm-' . date('Y') . '"';
		}
		?>
    <input class="st-input<?= $short ?>" type="<?= $type ?>" id="<?= $id ?>"<?= $nameAttr ?> value="<?= st_h($value) ?>"<?= $mode ?> data-control<?= $disabled ? ' readonly' : '' ?><?= $invalid ?>>
		<?php if (isset($def['unit'])) { ?><span class="st-unit"><?= st_h(st_unit($def)) ?></span><?php } ?>
	<?php } ?>
	<?php if (!in_array($def['type'], array('secret', 'info', 'link', 'mini'), true)) { ?>
    <input type="hidden" name="o[<?= st_h($key) ?>]" value="<?= st_h(isset($state['original']) ? $state['original'] : $value) ?>">
	<?php } ?>
  </div>
</div>
	<?php
}

/**
 * An action (spec P4): a row with its own button and a confirmation dialog, run on POST only.
 */
function st_render_action(array $def, bool $canRun, bool $visible): void
{
	?>
<div class="st-action" id="<?= st_h($def['key']) ?>" data-key="<?= st_h($def['key']) ?>"<?= $def['visible_if'] ? ' data-visible-if="' . st_h(json_encode($def['visible_if'])) . '"' : '' ?><?= $visible ? '' : ' hidden' ?>>
  <div class="st-tx">
    <span class="st-label"><?= st_t($def['label']) ?></span>
	<?php $impact = function_exists('settings_impact_text') ? settings_impact_text($def) : ''; ?>
	<?php if (isset($def['help'])) { ?><span class="st-help"><?= st_t($def['help']) ?></span><?php } ?>
	<?php if ($impact !== '') { ?><span class="st-impact"><?= st_h($impact) ?></span><?php } ?>
  </div>
  <div class="st-ctl">
    <button type="button" class="st-btn<?= !empty($def['danger']) ? ' st-btn-danger' : '' ?>" data-run="<?= st_h($def['key']) ?>" data-title="<?= st_t($def['confirm_title']) ?>" data-body="<?= st_t($def['confirm']) ?><?= $impact !== '' ? ' ' . st_h($impact) : '' ?>" data-verb="<?= st_t($def['label']) ?>"<?= !empty($def['blank']) ? ' data-blank="1"' : '' ?><?= $canRun ? '' : ' disabled' ?>><?= st_t($def['label']) ?><?= !empty($def['danger']) ? ' …' : '' ?></button>
  </div>
</div>
	<?php
}
