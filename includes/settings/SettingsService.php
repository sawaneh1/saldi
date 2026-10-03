<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/settings/SettingsService.php --- lap 5.0.0 --- 2026.09.29 ---
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
// 20260929 Sawaneh Settings redesign phase 4a (spec §7.2): one service that reads and writes a
//                  setting through its registry definition. The registry points at the EXISTING
//                  storage (grupper box / settings row), so every current reader keeps working.
// 20261002 Sawaneh Phase 4b batch 2: lastChanged() for the 'sat <date>' note next to a write-only secret.
// 20261002 Sawaneh Settings changes in audit_log carry objekt_type 'indstilling' and the key as objekt_id (settings redesign §11.2).

include_once(__DIR__ . '/../../systemdata/settingsRegistry.php');

class SettingsService
{
	/** @var array<string, array<string, mixed>|false> grupper rows, keyed "art|kodenr" */
	private static $grupper = array();
	/** @var array<string, array<int, array<string, mixed>>> settings rows, keyed "var_grp|var_name" */
	private static $settings = array();
	/** @var bool|null */
	private static $auditColumns = null;
	private static $auditObjekt = false;

	// ------------------------------------------------------------ definitions

	public static function definition(string $key): ?array
	{
		$all = getSettingDefinitions();
		if (!isset($all[$key])) {
			return null;
		}
		return $all[$key] + array('key' => $key);
	}

	/**
	 * Storage of a definition as named parts. Registry form (spec §7.1):
	 *   ['grupper', art, kodenr, box, encoding]   or   ['settings', var_grp, var_name, encoding]
	 * plus optional 'join' (separator of a composite box, R7) and 'index' (part of it).
	 *
	 * @return array<string, mixed>
	 */
	public static function storage(array $def): array
	{
		$s = $def['storage'];
		$out = array('join' => isset($s['join']) ? $s['join'] : null, 'index' => isset($s['index']) ? (int) $s['index'] : 0);
		if ($s[0] === 'grupper') {
			return $out + array('table' => 'grupper', 'art' => (string) $s[1], 'kodenr' => (string) $s[2], 'box' => (string) $s[3], 'encoding' => isset($s[4]) ? $s[4] : 'raw');
		}
		return $out + array('table' => 'settings', 'var_grp' => $s[1], 'var_name' => (string) $s[2], 'encoding' => isset($s[3]) ? $s[3] : 'raw');
	}

	// ------------------------------------------------------------ reading

	/**
	 * Load the storage rows of several keys with one query per storage table.
	 *
	 * @param array<int, string> $keys
	 */
	public static function preload(array $keys): void
	{
		$gr = array();
		$names = array();
		foreach ($keys as $key) {
			$def = self::definition($key);
			if (!$def || empty($def['storage'])) {
				continue;
			}
			$st = self::storage($def);
			if ($st['table'] === 'grupper') {
				$gr[$st['art'] . '|' . $st['kodenr']] = "(art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "')";
			} else {
				$names[$st['var_name']] = "'" . db_escape_string($st['var_name']) . "'";
			}
		}
		$gr = array_diff_key($gr, self::$grupper);
		if ($gr) {
			foreach (array_keys($gr) as $k) {
				self::$grupper[$k] = false;
			}
			$q = db_select("select * from grupper where " . implode(' or ', $gr) . " order by id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$k = $r['art'] . '|' . $r['kodenr'];
				if (self::$grupper[$k] === false) {
					self::$grupper[$k] = $r;
				}
			}
		}
		if ($names) {
			$q = db_select("select id, var_grp, var_name, var_value, user_id, pos_id from settings where var_name in (" . implode(',', $names) . ") order by id", __FILE__ . " linje " . __LINE__);
			$loaded = array();
			while ($r = db_fetch_array($q)) {
				$loaded[(string) $r['var_name']][] = $r;
			}
			foreach (array_keys($names) as $name) {
				self::$settings[$name] = isset($loaded[$name]) ? $loaded[$name] : array();
			}
		}
	}

	/**
	 * The stored string of a setting, exactly as the current writer left it ('' when unset).
	 */
	public static function raw(string $key, $scopeId = null): string
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return '';
		}
		$st = self::storage($def);
		if ($st['table'] === 'grupper') {
			$ck = $st['art'] . '|' . $st['kodenr'];
			if (!array_key_exists($ck, self::$grupper)) {
				self::preload(array($key));
			}
			$row = self::$grupper[$ck];
			$value = ($row && isset($row[$st['box']])) ? (string) $row[$st['box']] : '';
		} else {
			if (!array_key_exists($st['var_name'], self::$settings)) {
				self::preload(array($key));
			}
			$value = '';
			foreach (self::$settings[$st['var_name']] as $r) {
				if (self::rowInScope($r, $def, $st, $scopeId)) {
					$value = (string) $r['var_value'];
					break;
				}
			}
		}
		if ($st['join'] !== null) {
			$parts = self::splitComposite($value, $st['join']);
			$value = isset($parts[$st['index']]) ? $parts[$st['index']] : '';
		}
		return $value;
	}

	/**
	 * Value of a setting as a PHP type (bool / int / string); the registry default when unset.
	 *
	 * @return mixed
	 */
	public static function get(string $key, $scopeId = null)
	{
		$def = self::definition($key);
		if (!$def) {
			return null;
		}
		$raw = self::raw($key, $scopeId);
		if ($raw === '' && !self::exists($key, $scopeId)) {
			return isset($def['default']) ? $def['default'] : null;
		}
		return self::decode($def, $raw);
	}

	/**
	 * True when a storage row for the key exists (an empty stored value still counts).
	 */
	public static function exists(string $key, $scopeId = null): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		$st = self::storage($def);
		self::raw($key, $scopeId);
		if ($st['table'] === 'grupper') {
			return (bool) self::$grupper[$st['art'] . '|' . $st['kodenr']];
		}
		foreach (self::$settings[$st['var_name']] as $r) {
			if (self::rowInScope($r, $def, $st, $scopeId)) {
				return true;
			}
		}
		return false;
	}

	/** @return mixed */
	public static function decode(array $def, string $raw)
	{
		$st = self::storage($def);
		switch ($def['type']) {
			case 'bool':
				if ($st['encoding'] === 'oneZero') {
					return $raw === '1';
				}
				return trim($raw) === 'on';
			case 'int':
				return (int) $raw;
			default:
				return $raw;
		}
	}

	public static function encode(array $def, $value): string
	{
		$st = self::storage($def);
		switch ($def['type']) {
			case 'bool':
				$on = ($value === true || $value === 1 || $value === '1' || $value === 'on');
				if ($st['encoding'] === 'oneZero') {
					return $on ? '1' : '0';
				}
				if ($st['encoding'] === 'onOff') {
					return $on ? 'on' : 'off';
				}
				return $on ? 'on' : '';
			case 'int':
				return (string) (int) $value;
			default:
				return (string) $value;
		}
	}

	// ------------------------------------------------------------ writing

	/**
	 * Save a typed value. Returns true when the stored value changed.
	 */
	public static function save(string $key, $value, $scopeId = null): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		return self::saveRaw($key, self::encode($def, $value), $scopeId);
	}

	/**
	 * Write a stored string as is (also used by "Gendan" in the change history).
	 */
	public static function saveRaw(string $key, string $raw, $scopeId = null): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		$scope = isset($def['scope']) ? $def['scope'] : 'company';
		if ($scope === 'user' && (int) $scopeId <= 0) {
			return false;
		}
		if ($scope === 'company') {
			$scopeId = null;
		}
		$old = self::raw($key, $scopeId);
		$had = self::exists($key, $scopeId);
		if ($had && $old === $raw) {
			return false;
		}
		if (!$had) {
			// No row yet: the readers use their default, so only a value that differs from it is stored.
			$default = array_key_exists('default', $def) ? ($def['type'] === 'bool' ? self::encode($def, (bool) $def['default']) : (string) $def['default']) : '';
			if ($raw === $default || ($raw === '0' && $default === '')) {
				return false;
			}
		}
		$st = self::storage($def);
		$esc = db_escape_string($raw);
		if ($st['table'] === 'grupper') {
			$ck = $st['art'] . '|' . $st['kodenr'];
			$row = self::$grupper[$ck];
			$stored = $esc;
			if ($st['join'] !== null) {
				$parts = self::splitComposite($row ? (string) $row[$st['box']] : '', $st['join']);
				$parts[$st['index']] = $raw;
				for ($i = 0; $i <= max(array_keys($parts)); $i++) {
					if (!isset($parts[$i])) {
						$parts[$i] = '';
					}
				}
				ksort($parts);
				$stored = db_escape_string(implode($st['join'], $parts));
			}
			$where = "art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "'";
			if ($row) {
				// Every row of the art/kodenr pair: a ledger may hold duplicates (risk review R9).
				db_modify("update grupper set " . $st['box'] . " = '$stored' where $where", __FILE__ . " linje " . __LINE__);
			} else {
				$name = db_escape_string(isset($def['storage']['row_name']) ? (string) $def['storage']['row_name'] : 'Indstillinger');
				db_modify("insert into grupper (beskrivelse, kodenr, art, " . $st['box'] . ") values ('$name', '" . db_escape_string($st['kodenr']) . "', '" . db_escape_string($st['art']) . "', '$stored')", __FILE__ . " linje " . __LINE__);
			}
			unset(self::$grupper[$ck]);
		} else {
			$where = "var_name = '" . db_escape_string($st['var_name']) . "'";
			if ($st['var_grp'] !== null) {
				$where .= " and var_grp = '" . db_escape_string((string) $st['var_grp']) . "'";
			}
			if ($scope === 'user') {
				$where .= " and user_id = " . (int) $scopeId;
			} elseif ($scope === 'pos') {
				$where .= " and pos_id = " . (int) $scopeId;
			} else {
				$where .= " and (user_id is null or user_id = 0)";
			}
			if ($had) {
				db_modify("update settings set var_value = '$esc' where $where", __FILE__ . " linje " . __LINE__);
			} else {
				// Company scope is stored with user_id 0 (risk review R10).
				$userId = ($scope === 'user') ? (int) $scopeId : 0;
				$cols = "var_name, var_grp, var_value, var_description, user_id";
				$vals = "'" . db_escape_string($st['var_name']) . "', '" . db_escape_string((string) $st['var_grp']) . "', '$esc', '" . db_escape_string($key) . "', $userId";
				if ($scope === 'pos') {
					$cols .= ", pos_id";
					$vals .= ", " . (int) $scopeId;
				}
				db_modify("insert into settings ($cols) values ($vals)", __FILE__ . " linje " . __LINE__);
			}
			unset(self::$settings[$st['var_name']]);
		}
		if (!isset($def['audit']) || $def['audit']) {
			self::audit($def, $old, $raw);
		}
		return true;
	}

	// ------------------------------------------------------------ modules

	/**
	 * Licence flag of an optional module (decision 9): settings system/<module>_licence,
	 * and while that key is unset, what the installation did before.
	 */
	public static function hasModule(string $module): bool
	{
		static $cache = array();
		if (isset($cache[$module])) {
			return $cache[$module];
		}
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'system' and var_name = '" . db_escape_string($module) . "_licence'", __FILE__ . " linje " . __LINE__));
		if ($r) {
			return $cache[$module] = ((string) $r['var_value'] === 'on');
		}
		if ($module === 'pos') {
			return $cache[$module] = file_exists(__DIR__ . '/../../debitor/pos_ordre.php');
		}
		return $cache[$module] = false;
	}

	// ------------------------------------------------------------ change history

	private static function auditColumns(): bool
	{
		if (self::$auditColumns === null) {
			self::$auditColumns = (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'setting_key'", __FILE__ . " linje " . __LINE__));
			self::$auditObjekt = self::$auditColumns && (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'objekt_type'", __FILE__ . " linje " . __LINE__));
		}
		return self::$auditColumns;
	}

	private static function audit(array $def, string $old, string $new): void
	{
		global $bruger_id, $brugernavn;
		$secret = ($def['type'] === 'secret');
		$section = $def['group'] . '.' . $def['section'];
		if (!self::auditColumns()) {
			if (function_exists('audit_log')) {
				audit_log('setting.change', $def['key'] . ($secret ? '' : ': ' . $old . ' -> ' . $new));
			}
			return;
		}
		$ip = db_escape_string(isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '');
		// objekt_type 'indstilling' is what the roles spec's audit log groups settings changes by (settings redesign §11.2).
		$objekt = self::$auditObjekt ? ", objekt_type, objekt_id, kilde" : "";
		$objektValues = self::$auditObjekt ? ", 'indstilling', '" . db_escape_string(substr($def['key'], 0, 60)) . "', 'ui'" : "";
		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, detaljer, ip, setting_key, section, old_value, new_value$objekt) values (";
		$qtxt .= (int) $bruger_id . ", '" . db_escape_string((string) $brugernavn) . "', 'setting.change', '" . db_escape_string($def['key']) . "', '$ip', ";
		$qtxt .= "'" . db_escape_string($def['key']) . "', '" . db_escape_string($section) . "', ";
		$qtxt .= "'" . db_escape_string($secret ? '' : $old) . "', '" . db_escape_string($secret ? '' : $new) . "'$objektValues)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}

	/**
	 * Audit version of a section: the id of its newest change (0 when none). The form carries
	 * the version it was rendered from, so a save on top of someone else's save is detected.
	 */
	public static function version(string $section): int
	{
		if (!self::auditColumns()) {
			return 0;
		}
		$r = db_fetch_array(db_select("select max(id) as id from audit_log where section = '" . db_escape_string($section) . "'", __FILE__ . " linje " . __LINE__));
		return $r ? (int) $r['id'] : 0;
	}

	/**
	 * @return array<int, array<string, mixed>> newest first
	 */
	public static function history(string $section, int $limit = 20, int $afterId = 0): array
	{
		$rows = array();
		if (!self::auditColumns()) {
			return $rows;
		}
		$qtxt = "select id, bruger_id, brugernavn, tidspunkt, setting_key, old_value, new_value from audit_log ";
		$qtxt .= "where section = '" . db_escape_string($section) . "' and handling = 'setting.change' and id > " . (int) $afterId . " order by id desc limit " . (int) $limit;
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$rows[] = $r;
		}
		return $rows;
	}

	/**
	 * When each setting of a section was last changed through the registry (key => timestamp), for the
	 * "sat 12/9" note next to a secret.
	 *
	 * @return array<string, string>
	 */
	public static function lastChanged(string $section): array
	{
		if (!self::auditColumns()) {
			return array();
		}
		$out = array();
		$q = db_select("select setting_key, max(tidspunkt) as t from audit_log where section = '" . db_escape_string($section) . "' group by setting_key", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[(string) $r['setting_key']] = (string) $r['t'];
		}
		return $out;
	}

	public static function historyEntry(int $id): ?array
	{
		if (!self::auditColumns()) {
			return null;
		}
		$r = db_fetch_array(db_select("select id, brugernavn, tidspunkt, setting_key, section, old_value, new_value from audit_log where id = " . (int) $id . " and handling = 'setting.change'", __FILE__ . " linje " . __LINE__));
		return $r ?: null;
	}

	// ------------------------------------------------------------ helpers

	/**
	 * @return array<int, string>
	 */
	private static function splitComposite(string $value, string $sep): array
	{
		if ($value === '') {
			return array();
		}
		if (strpos($value, $sep) === false) {
			// A box written before it became composite holds one value meant for every part.
			return array(0 => $value, 1 => $value);
		}
		return explode($sep, $value);
	}

	private static function rowInScope(array $row, array $def, array $st, $scopeId): bool
	{
		if ($st['var_grp'] !== null && (string) $row['var_grp'] !== (string) $st['var_grp']) {
			return false;
		}
		$scope = isset($def['scope']) ? $def['scope'] : 'company';
		if ($scope === 'user') {
			return (int) $row['user_id'] === (int) $scopeId && (int) $scopeId > 0;
		}
		if ($scope === 'pos') {
			return (int) $row['pos_id'] === (int) $scopeId;
		}
		return (int) $row['user_id'] === 0;
	}

	public static function reset(): void
	{
		self::$grupper = array();
		self::$settings = array();
	}
}

if (!function_exists('settings_registry_note')) {
	/**
	 * Compatibility layer (spec §7.2): get_settings_value() reports every (var_grp, var_name) it is
	 * asked for; pairs without a registry definition are written once per session to
	 * temp/<db>/settings_not_in_registry.log, so the inventory becomes complete over time.
	 */
	function settings_registry_note($varGrp, $varName): void
	{
		global $db;
		static $known = null;
		if ($known === null) {
			$known = array();
			foreach (getSettingDefinitions() as $def) {
				if (!empty($def['storage']) && $def['storage'][0] === 'settings') {
					$known[(string) $def['storage'][1] . '|' . (string) $def['storage'][2]] = true;
					$known['|' . (string) $def['storage'][2]] = true;
				}
			}
		}
		$pair = (string) $varGrp . '|' . (string) $varName;
		if (isset($known[$pair]) || isset($_SESSION['settings_noted'][$pair]) || empty($db)) {
			return;
		}
		$_SESSION['settings_noted'][$pair] = 1;
		$dir = __DIR__ . '/../../temp/' . $db;
		if (is_dir($dir)) {
			@file_put_contents($dir . '/settings_not_in_registry.log', date('Y-m-d H:i:s') . "\t" . $pair . "\n", FILE_APPEND);
		}
	}
}

if (!function_exists('setting')) {
	/** @return mixed */
	function setting(string $key, $scopeId = null)
	{
		return SettingsService::get($key, $scopeId);
	}
	function setting_save(string $key, $value, $scopeId = null): bool
	{
		return SettingsService::save($key, $value, $scopeId);
	}
	function has_module(string $module): bool
	{
		return SettingsService::hasModule($module);
	}
}
