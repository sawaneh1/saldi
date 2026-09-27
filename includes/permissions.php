<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/permissions.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh Roles & permissions layer (spec part 2): role -> permission levels,
//                  compatibility with the legacy rettigheder string (a role is rendered
//                  into brugere.rettigheder so old checks keep working), require_permission(),
//                  nearest-role suggestion for migration, and the audit log.

include_once(__DIR__ . '/permissionRegistry.php');

// ------------------------------------------------------------------ levels

/**
 * @return array<string, int> Level name -> rank, for comparisons.
 */
function perm_level_rank(): array
{
	return array('none' => 0, 'read' => 1, 'write' => 2);
}

function perm_level_valid(string $level): string
{
	return isset(perm_level_rank()[$level]) ? $level : 'none';
}

function perm_level_label(string $level, int $sprogId): string
{
	if ($level === 'write') {
		return findtekst('5547|Fuld adgang', $sprogId);
	}
	if ($level === 'read') {
		return findtekst('2475|Kun visning', $sprogId);
	}
	return findtekst('5546|Ingen adgang', $sprogId);
}

// ------------------------------------------------------------------ schema

/**
 * True once includes/betweenUpdates.php has created the role tables in this company.
 */
function perm_tables_ready(): bool
{
	static $ready = null;
	if ($ready === null) {
		$ready = function_exists('tbl_exists') && tbl_exists('roles') && tbl_exists('role_permissions');
	}
	return $ready;
}

function audit_ready(): bool
{
	static $ready = null;
	if ($ready === null) {
		$ready = function_exists('tbl_exists') && tbl_exists('audit_log');
	}
	return $ready;
}

// ------------------------------------------------------------------ legacy <-> levels

/**
 * Levels implied by a legacy rettigheder string. Position values: '1' write, '2' read,
 * else none. Keys without a legacy position follow the Indstillinger bit (1) - that is
 * exactly what those users can do today, so nothing changes until a role is assigned.
 *
 * @return array<string, string>
 */
function perm_levels_from_legacy(string $rettigheder): array
{
	$levels = array();
	$settingsBit = substr($rettigheder, 1, 1);
	$settingsLevel = ($settingsBit === '1') ? 'write' : (($settingsBit === '2') ? 'read' : 'none');
	foreach (permission_registry() as $key => $def) {
		if (!$def['legacy']) {
			$levels[$key] = $settingsLevel;
			continue;
		}
		$best = 'none';
		foreach ($def['legacy'] as $pos) {
			$c = substr($rettigheder, $pos, 1);
			if ($c === '1') {
				$best = 'write';
			} elseif ($c === '2' && $best === 'none') {
				$best = 'read';
			}
		}
		$levels[$key] = $best;
	}
	return $levels;
}

/**
 * The 16-position legacy string a set of levels renders to.
 */
function perm_legacy_string(array $levels): string
{
	$positions = array_fill(0, 16, '0');
	foreach (permission_registry() as $key => $def) {
		$level = isset($levels[$key]) ? $levels[$key] : 'none';
		foreach ($def['legacy'] as $pos) {
			$c = ($level === 'write') ? '1' : (($level === 'read') ? '2' : '0');
			if ($c === '1' || ($c === '2' && $positions[$pos] === '0')) {
				$positions[$pos] = $c;
			}
		}
	}
	return implode('', $positions);
}

/**
 * @return array<string, string> Every registry key -> level for the role (none when unset).
 */
function perm_levels_from_role(int $roleId): array
{
	$levels = array();
	foreach (permission_registry() as $key => $def) {
		$levels[$key] = 'none';
	}
	if (!perm_tables_ready() || $roleId <= 0) {
		return $levels;
	}
	$q = db_select("select permission_key, level from role_permissions where role_id = $roleId", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (isset($levels[$r['permission_key']])) {
			$levels[$r['permission_key']] = perm_level_valid((string) $r['level']);
		}
	}
	return $levels;
}

/**
 * Effective levels for a user: the role when one is assigned, else the legacy string.
 *
 * @return array<string, string>
 */
function perm_user_levels(int $brugerId, string $rettigheder): array
{
	if (perm_tables_ready() && $brugerId > 0) {
		$r = db_fetch_array(db_select("select role_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
		if ($r && (int) $r['role_id'] > 0) {
			return perm_levels_from_role((int) $r['role_id']);
		}
	}
	return perm_levels_from_legacy($rettigheder);
}

// ------------------------------------------------------------------ enforcement mode

/**
 * Company-wide switch for phase 3 (spec 3.3 step 2):
 *  'log'  - pages without a declaration and requests that would be refused are only
 *           written to audit_log ("would have been denied"); nothing is blocked.
 *  'deny' - default-deny is on: undeclared pages are refused, read-only is real, and
 *           auditor/master-admin sessions get the read-only Revisor role.
 * Dangerous keys (registry 'dangerous') are enforced in both modes.
 */
function perm_enforcement_mode(): string
{
	static $mode = null;
	if ($mode === null) {
		$mode = 'log';
		if (function_exists('tbl_exists') && tbl_exists('settings')) {
			$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'permissions' and var_name = 'enforce'", __FILE__ . " linje " . __LINE__));
			if ($r && in_array($r['var_value'], array('log', 'deny'), true)) {
				$mode = $r['var_value'];
			}
		}
	}
	return $mode;
}

function perm_set_enforcement_mode(string $mode): void
{
	$mode = ($mode === 'deny') ? 'deny' : 'log';
	$r = db_fetch_array(db_select("select id from settings where var_grp = 'permissions' and var_name = 'enforce'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		db_modify("update settings set var_value = '$mode' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description) values ('permissions', 'enforce', '$mode', 'Permission enforcement: log or deny')", __FILE__ . " linje " . __LINE__);
	}
	audit_log('permissions.mode', $mode);
}

/**
 * Registry key a legacy $modulnr position maps to (the first key owning that position).
 */
function perm_key_for_modulnr(int $modulnr): string
{
	foreach (permission_registry() as $key => $def) {
		if (in_array($modulnr, $def['legacy'], true)) {
			return $key;
		}
	}
	return '';
}

/**
 * Central request gate, called from includes/online.php once the user is known.
 *
 * @param string|null $declaredKey   $permission_key set by the page ('any' = every logged-in user), or null
 * @param string      $declaredLevel $permission_level set by the page (read/write), default read
 * @param bool        $postIsRead    $permission_post_read: POST is a filter/search, not a write
 */
function perm_enforce_request(?string $declaredKey, string $declaredLevel, bool $postIsRead, ?int $modulnr): void
{
	$mode = perm_enforcement_mode();
	$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$page = (string) parse_url($uri, PHP_URL_PATH);

	$key = $declaredKey;
	if ($key === null && $modulnr !== null && $modulnr >= 0 && $modulnr < 16) {
		$key = perm_key_for_modulnr($modulnr);
	}
	if ($key === null || $key === '') {
		if ($mode === 'deny') {
			perm_refuse('unguarded', $page);
		}
		perm_log_once('unguarded', $page);
		return;
	}
	if ($key === 'any') {
		return;
	}
	$registry = permission_registry();
	$dangerous = isset($registry[$key]) && $registry[$key]['dangerous'];
	$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
	$need = ($declaredLevel === 'write' || ($isPost && !$postIsRead)) ? 'write' : 'read';
	if (perm_can($key, $need)) {
		return;
	}
	if ($mode === 'deny' || $dangerous) {
		perm_refuse($key . ' (' . $need . ')', $page);
	}
	perm_log_once('would-deny', $key . ' (' . $need . ') ' . $page);
}

/**
 * One audit row per page per session, so the logging period doesn't flood the table.
 */
function perm_log_once(string $handling, string $detaljer): void
{
	$slot = 'perm_' . md5($handling . '|' . $detaljer);
	if (isset($_SESSION[$slot])) {
		return;
	}
	$_SESSION[$slot] = 1;
	audit_log($handling, $detaljer);
}

function perm_refuse(string $what, string $page): void
{
	global $sprog_id;
	audit_log('denied', $what . ' ' . $page);
	// std_func.php (findtekst/tekstboks) may not be loaded yet when called from online.php.
	$txt = function_exists('findtekst')
		? findtekst('5548|Du har ikke adgang til denne funktion. Kontakt din administrator.', isset($sprog_id) ? (int) $sprog_id : 1)
		: 'Du har ikke adgang til denne funktion. Kontakt din administrator.';
	if (function_exists('tekstboks')) {
		print tekstboks($txt);
	} else {
		print "<p>" . htmlspecialchars($txt) . "</p>";
	}
	exit;
}

// ------------------------------------------------------------------ current user

/**
 * Level of the current session's user for a key. Auditor/master-admin sessions
 * (bruger_id -1) keep their rettigheder string in 'log' mode; in 'deny' mode they
 * get the read-only Revisor role (spec decision 2).
 */
function perm_level(string $key): string
{
	global $bruger_id, $rettigheder, $revisor;
	static $cache = null;
	if ($cache === null) {
		if (!empty($revisor) && perm_enforcement_mode() === 'deny') {
			$cache = perm_levels_from_role(perm_role_id_by_key('revisor'));
		} else {
			$cache = perm_user_levels((int) $bruger_id, (string) $rettigheder);
		}
	}
	return isset($cache[$key]) ? $cache[$key] : 'none';
}

function perm_role_id_by_key(string $roleKey): int
{
	foreach (perm_roles() as $role) {
		if ($role['key'] === $roleKey) {
			return $role['id'];
		}
	}
	return 0;
}

function perm_can(string $key, string $level = 'read'): bool
{
	$rank = perm_level_rank();
	return $rank[perm_level($key)] >= $rank[perm_level_valid($level)];
}

/**
 * Central gate (spec R4). Denied requests are logged, as the message has always claimed.
 */
function require_permission(string $key, string $level = 'read'): void
{
	global $sprog_id;
	if (perm_can($key, $level)) {
		return;
	}
	perm_refuse($key . ' (' . $level . ')', (string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
}

// ------------------------------------------------------------------ roles

/**
 * @return array<int, array{id: int, key: string, navn: string, beskrivelse: string, system: bool}>
 */
function perm_roles(): array
{
	$roles = array();
	if (!perm_tables_ready()) {
		return $roles;
	}
	$q = db_select("select id, role_key, navn, beskrivelse, system from roles order by system desc, navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$roles[] = array(
			'id'          => (int) $r['id'],
			'key'         => (string) $r['role_key'],
			'navn'        => (string) $r['navn'],
			'beskrivelse' => (string) $r['beskrivelse'],
			'system'      => ($r['system'] === 't' || $r['system'] === true || $r['system'] === '1' || $r['system'] === 1),
		);
	}
	return $roles;
}

/**
 * Display name of a role: built-in roles are translated via the registry, custom ones as typed.
 */
function perm_role_name(array $role, int $sprogId): string
{
	if ($role['key'] !== '') {
		$defaults = permission_default_roles();
		if (isset($defaults[$role['key']])) {
			return findtekst($defaults[$role['key']]['label'], $sprogId);
		}
	}
	return $role['navn'];
}

/**
 * Replace a role's permissions and re-render the legacy string of every user holding it.
 *
 * @param array<string, string> $levels
 */
function perm_save_role_levels(int $roleId, array $levels): void
{
	db_modify("delete from role_permissions where role_id = $roleId", __FILE__ . " linje " . __LINE__);
	foreach (permission_registry() as $key => $def) {
		$level = isset($levels[$key]) ? perm_level_valid($levels[$key]) : 'none';
		if ($level !== 'none') {
			db_modify("insert into role_permissions (role_id, permission_key, level) values ($roleId, '" . db_escape_string($key) . "', '$level')", __FILE__ . " linje " . __LINE__);
		}
	}
	perm_sync_role($roleId);
}

/**
 * Keep brugere.rettigheder equal to the role, so every legacy substr() check follows the role.
 */
function perm_sync_user(int $brugerId): void
{
	if (!perm_tables_ready()) {
		return;
	}
	$r = db_fetch_array(db_select("select role_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
	if (!$r || (int) $r['role_id'] <= 0) {
		return;
	}
	$legacy = perm_legacy_string(perm_levels_from_role((int) $r['role_id']));
	db_modify("update brugere set rettigheder = '$legacy' where id = $brugerId", __FILE__ . " linje " . __LINE__);
}

function perm_sync_role(int $roleId): void
{
	$legacy = perm_legacy_string(perm_levels_from_role($roleId));
	db_modify("update brugere set rettigheder = '$legacy' where role_id = $roleId", __FILE__ . " linje " . __LINE__);
}

/**
 * True when every level in $levels is within what the current user holds (spec R5:
 * nobody can hand out more than they have themselves).
 *
 * @param array<string, string> $levels
 */
function perm_within_own(array $levels): bool
{
	$rank = perm_level_rank();
	foreach (permission_registry() as $key => $def) {
		$wanted = isset($levels[$key]) ? perm_level_valid($levels[$key]) : 'none';
		if ($rank[$wanted] > $rank[perm_level($key)]) {
			return false;
		}
	}
	return true;
}

/**
 * Nearest built-in role for a legacy string (migration suggestion, spec 3.3 step 3).
 * Distance = number of legacy positions whose level differs; ties go to the first role.
 *
 * @return array{id: int, key: string, distance: int}|null
 */
function perm_suggest_role(string $rettigheder): ?array
{
	if (!perm_tables_ready()) {
		return null;
	}
	$target = perm_levels_from_legacy($rettigheder);
	$best = null;
	foreach (perm_roles() as $role) {
		if (!$role['system']) {
			continue;
		}
		$levels = perm_levels_from_role($role['id']);
		$distance = 0;
		foreach (permission_registry() as $key => $def) {
			if ($def['legacy'] && $levels[$key] !== $target[$key]) {
				$distance++;
			}
		}
		if ($best === null || $distance < $best['distance']) {
			$best = array('id' => $role['id'], 'key' => $role['key'], 'distance' => $distance);
		}
	}
	return $best;
}

/**
 * Seed the built-in roles once, and give users who already hold every legacy right the
 * Administrator role (an exact match; everyone else is suggested, not moved).
 * Idempotent: called from includes/betweenUpdates.php.
 */
function perm_ensure_default_roles(): void
{
	if (!perm_tables_ready()) {
		return;
	}
	$existing = array();
	foreach (perm_roles() as $role) {
		if ($role['key'] !== '') {
			$existing[$role['key']] = $role['id'];
		}
	}
	foreach (permission_default_roles() as $key => $def) {
		if (isset($existing[$key])) {
			continue;
		}
		$navn = db_escape_string(explode('|', $def['label'], 2)[1]);
		$beskrivelse = db_escape_string($def['beskrivelse']);
		db_modify("insert into roles (role_key, navn, beskrivelse, system) values ('$key', '$navn', '$beskrivelse', 't')", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from roles where role_key = '$key'", __FILE__ . " linje " . __LINE__));
		if ($r) {
			$roleId = (int) $r['id'];
			foreach ($def['levels'] as $permKey => $level) {
				db_modify("insert into role_permissions (role_id, permission_key, level) values ($roleId, '" . db_escape_string($permKey) . "', '" . perm_level_valid($level) . "')", __FILE__ . " linje " . __LINE__);
			}
			$existing[$key] = $roleId;
		}
	}
	if (isset($existing['administrator'])) {
		$adminId = (int) $existing['administrator'];
		db_modify("update brugere set role_id = $adminId where (role_id is null or role_id = 0) and rettigheder = '1111111111111111'", __FILE__ . " linje " . __LINE__);
	}
}

// ------------------------------------------------------------------ audit log

/**
 * Record who did what (spec R7). Never throws: an install without the table just skips.
 */
function audit_log(string $handling, string $detaljer = ''): void
{
	global $bruger_id, $brugernavn;
	if (!audit_ready()) {
		return;
	}
	$id = isset($bruger_id) ? (int) $bruger_id : 0;
	$navn = db_escape_string(isset($brugernavn) ? (string) $brugernavn : '');
	$ip = db_escape_string(isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '');
	$handling = db_escape_string(substr($handling, 0, 40));
	$detaljer = db_escape_string(substr($detaljer, 0, 2000));
	db_modify("insert into audit_log (bruger_id, brugernavn, handling, detaljer, ip) values ($id, '$navn', '$handling', '$detaljer', '$ip')", __FILE__ . " linje " . __LINE__);
}
