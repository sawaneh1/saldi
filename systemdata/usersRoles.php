<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/usersRoles.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh "Brugere & roller" (spec R8): user list with search, user card with role,
//                  role list + matrix editor, bulk role assignment, migration suggestions and
//                  the audit log. Replaces brugere.php (and brugereRevisor.php). Controller here,
//                  view in usersRolesIncludes/view.php. Post/Redirect/Get throughout.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $db
 * @var string $db_encode
 * @var int    $sprog_id
 * @var mixed  $revisor
 * @var mixed  $regnaar
 */

@session_start();
$s_id = session_id();
ob_start();

$title = "Brugere & roller";
$css = "../css/usersRoles.css";
$modulnr = 1; // legacy gate (Indstillinger)
$permission_key = 'settings.users.manage';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/userFunctions.php");
include(__DIR__ . "/usersRolesIncludes/view.php");

require_permission('settings.users.manage', 'read');

$contextQuery = (!empty($_GET['inframe']) ? 'inframe=1' : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_permission('settings.users.manage', 'write');
	$redirect = ur_handle_post($_POST, (int) $bruger_id, (int) $regnaar);
	if (!empty($GLOBALS['user_sessions_to_end'])) {
		// Sessions live in the master database: back to it for this last write.
		$endSessions = $GLOBALS['user_sessions_to_end'];
		include(__DIR__ . "/../includes/connect.php");
		foreach ($endSessions as $endName) {
			db_modify("delete from online where brugernavn = '" . db_escape_string($endName) . "' and db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__);
		}
	}
	ob_end_clean();
	header('Location: usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : '') . $redirect);
	exit;
}

$vm = ur_view_model($_GET, (int) $bruger_id, (int) $sprog_id, (string) $db_encode, $contextQuery);
ur_view($vm);

// 20260929 Sawaneh Roles stage 2: close/reopen instead of delete, last-administrator and own-role rules, bulk
//                  close/reopen with one audit entry per user, reset of built-in roles; user operations
//                  moved to includes/userFunctions.php.
// 20260929 Sawaneh Roles stage 2 (§8.4): invite a user instead of choosing the password, resend, status Invited.

// ================================================================== controller

/**
 * Dispatch a POST and return the query string to redirect to.
 */
function ur_handle_post(array $post, int $selfId, int $regnaar): string
{
	$action = isset($post['action']) ? (string) $post['action'] : '';
	switch ($action) {
		case 'save_user':
			return ur_save_user($post, $selfId, $regnaar);
		case 'delete_user':
			return ur_user_result(user_delete((int) ifset($post, 'id', 0), $selfId), (int) ifset($post, 'id', 0), 'userdeleted', false);
		case 'close_user':
			return ur_user_result(user_close((int) ifset($post, 'id', 0), $selfId), (int) ifset($post, 'id', 0), 'userclosed', true);
		case 'reopen_user':
			return ur_user_result(user_reopen((int) ifset($post, 'id', 0)), (int) ifset($post, 'id', 0), 'userreopened', true);
		case 'resend_invite':
			return ur_resend_invite((int) ifset($post, 'id', 0));
		case 'bulk_role':
			return ur_bulk($post, $selfId);
		case 'reset_role':
			return ur_reset_role((int) ifset($post, 'id', 0));
		case 'apply_suggestions':
			return ur_apply_suggestions();
		case 'save_role':
			return ur_save_role($post);
		case 'delete_role':
			return ur_delete_role((int) ifset($post, 'id', 0));
		case 'copy_role':
			return ur_copy_role((int) ifset($post, 'id', 0));
		case 'set_enforce':
			perm_set_enforcement_mode((string) ifset($post, 'mode', 'log'));
			return 'tab=log&msg=enforce';
	}
	return 'tab=users';
}

/**
 * True when the current user may hand out this role (spec R5: no self-escalation).
 */
function ur_role_assignable(int $roleId): bool
{
	if ($roleId <= 0) {
		return true;
	}
	return perm_within_own(perm_levels_from_role($roleId));
}

function ur_save_user(array $post, int $selfId, int $regnaar): string
{
	$id = (int) ifset($post, 'id', 0);
	$navn = trim((string) ifset($post, 'brugernavn', ''));
	$kode = (string) ifset($post, 'kode', '');
	$kode2 = (string) ifset($post, 'kode2', '');
	$roleId = (int) ifset($post, 'role_id', 0);
	$ansatId = (int) ifset($post, 'ansat_id', 0);
	$ip = trim((string) ifset($post, 'ip_address', ''));
	$tlf = trim((string) ifset($post, 'tlf', ''));
	$email = trim((string) ifset($post, 'email', ''));
	$twofactor = !empty($post['twofactor']) ? 't' : 'f';
	$isRevisor = !empty($post['revisor']);
	$invite = ($id === 0 && ifset($post, 'mode', 'invite') !== 'classic');
	$back = 'tab=users&bruger=' . $id . ($id === 0 && !$invite ? '&mode=classic' : '');

	if ($navn === '' || mb_strlen($navn) > 80) {
		return $back . '&msg=name';
	}
	if ($kode !== '' && $kode !== $kode2) {
		return $back . '&msg=pwmismatch';
	}
	if ($invite) {
		$kode = '';
		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $back . '&msg=emailrequired';
		}
	} elseif ($id === 0 && $kode === '') {
		return $back . '&msg=pwrequired';
	}
	$navnSql = db_escape_string($navn);
	$dup = db_fetch_array(db_select("select id from brugere where brugernavn = '$navnSql' and id != $id", __FILE__ . " linje " . __LINE__));
	if ($dup) {
		return $back . '&msg=duplicate';
	}

	$current = ($id > 0) ? db_fetch_array(db_select("select role_id from brugere where id = $id", __FILE__ . " linje " . __LINE__)) : null;
	$currentRole = $current ? (int) $current['role_id'] : 0;
	if ($roleId !== $currentRole) {
		if (!ur_role_assignable($roleId)) {
			return $back . '&msg=escalation';
		}
		if ($id > 0 && $id === $selfId) {
			return $back . '&msg=ownrole';
		}
		if ($id > 0 && user_is_last_admin($id)) {
			return $back . '&msg=lastadmin';
		}
	}

	$ipSql = db_escape_string(mb_substr($ip, 0, 45));
	$tlfSql = db_escape_string(mb_substr($tlf, 0, 16));
	$emailSql = db_escape_string($email);
	$ansatSql = $ansatId > 0 ? (string) $ansatId : '0';

	$inviteToken = '';
	if ($id === 0) {
		$data = array(
			'brugernavn' => $navn, 'kode' => $kode, 'role_id' => $roleId, 'ansat_id' => $ansatId, 'ip_address' => $ip,
			'tlf' => $tlf, 'email' => $email, 'twofactor' => ($twofactor === 't'), 'regnskabsaar' => $regnaar,
		);
		if ($invite) {
			$invited = user_invite($data);
			$id = $invited['id'];
			$inviteToken = $invited['token'];
		} else {
			$id = user_create($data);
		}
	} else {
		$qtxt = "update brugere set brugernavn = '$navnSql', ansat_id = $ansatSql, ip_address = '$ipSql', tlf = '$tlfSql', twofactor = '$twofactor', email = '$emailSql'";
		if ($kode !== '') {
			$qtxt .= ", kode = '" . db_escape_string(saldikrypt($id, $kode)) . "'";
		}
		$qtxt .= " where id = $id";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		audit_log('user.updated', $navn . ($kode !== '' ? ' (ny adgangskode)' : ''), 'bruger', (string) $id);
		if ($roleId !== $currentRole) {
			user_set_role($id, $roleId, $selfId);
		}
	}
	perm_sync_user($id);

	if ($ansatId > 0) {
		$r = db_fetch_array(db_select("select afd from ansatte where id = $ansatId", __FILE__ . " linje " . __LINE__));
		if ($r) {
			update_settings_value('afd', 'brugerAfd', (int) $r['afd'], 'Department of employee', $ansatId);
		}
	}
	ur_set_revisor_user($id, $isRevisor);
	if ($inviteToken !== '') {
		return 'tab=users&bruger=' . $id . '&msg=' . ur_send_invite($id, $inviteToken);
	}
	return 'tab=users&bruger=' . $id . '&msg=usersaved';
}

/**
 * Mail the invitation. Where the server cannot send mail the link is handed to the
 * administrator instead (spec §8.4), once, through the session.
 *
 * @return string the message key for the redirect
 */
function ur_send_invite(int $id, string $token): string
{
	global $sprog_id;
	if (user_invite_mail($id, $token, (int) $sprog_id)) {
		return 'invited';
	}
	$_SESSION['ur_invite_link'] = array('id' => $id, 'link' => user_invite_link($token));
	return 'invitelink';
}

function ur_resend_invite(int $id): string
{
	$token = user_invite_resend($id);
	if ($token === '') {
		return 'tab=users&bruger=' . $id . '&msg=notinvited';
	}
	return 'tab=users&bruger=' . $id . '&msg=' . ur_send_invite($id, $token);
}

/**
 * The company's designated auditor account (settings var 'revisor'): at most one user.
 */
function ur_set_revisor_user(int $id, bool $isRevisor): void
{
	$r = db_fetch_array(db_select("select id, user_id from settings where var_name = 'revisor' and var_grp = 'system'", __FILE__ . " linje " . __LINE__));
	$currentId = $r ? (int) $r['user_id'] : 0;
	if ($isRevisor && $currentId !== $id) {
		if ($r) {
			db_modify("update settings set user_id = $id where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into settings (var_name, var_grp, var_value, var_description, user_id) values ('revisor', 'system', '', 'Designated auditor user', $id)", __FILE__ . " linje " . __LINE__);
		}
		audit_log('revisor.set', 'bruger ' . $id);
	} elseif (!$isRevisor && $currentId === $id && $r) {
		db_modify("update settings set user_id = null where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		audit_log('revisor.unset', 'bruger ' . $id);
	}
}

/**
 * Redirect target after a user operation: the success message, or the reason it was refused.
 */
function ur_user_result(string $error, int $id, string $okMsg, bool $stay): string
{
	if ($error === 'missing') {
		return 'tab=users';
	}
	if ($error !== '') {
		return 'tab=users&bruger=' . $id . '&msg=' . $error;
	}
	return 'tab=users' . ($stay ? '&bruger=' . $id : '') . '&msg=' . $okMsg;
}

/**
 * Bulk operations on the selected users (spec §8.1): assign role, close, reopen.
 * Every user gets its own audit entry; users the rules protect are skipped and counted.
 */
function ur_bulk(array $post, int $selfId): string
{
	$ids = isset($post['ids']) && is_array($post['ids']) ? array_filter(array_map('intval', $post['ids'])) : array();
	$what = isset($post['bulk']) ? (string) $post['bulk'] : 'role';
	if (!$ids) {
		return 'tab=users';
	}
	$roleId = (int) ifset($post, 'role_id', 0);
	if ($what === 'role' && ($roleId <= 0 || !ur_role_assignable($roleId))) {
		return 'tab=users&msg=' . ($roleId <= 0 ? 'norole' : 'escalation');
	}
	$skipped = 0;
	foreach ($ids as $id) {
		if ($what === 'close') {
			$error = user_close($id, $selfId);
		} elseif ($what === 'reopen') {
			$error = user_reopen($id);
		} elseif ($what === 'resend') {
			$token = user_invite_resend($id);
			$error = ($token !== '' && user_invite_mail($id, $token, (int) $GLOBALS['sprog_id'])) ? '' : 'notsent';
		} else {
			$error = user_set_role($id, $roleId, $selfId);
		}
		if ($error !== '') {
			$skipped++;
		}
	}
	return 'tab=users&msg=' . ($what === 'role' ? 'assigned' : 'bulkdone') . ($skipped > 0 ? '&skipped=' . $skipped : '');
}

/**
 * Suggest/confirm (spec 3.3 step 3): the admin has confirmed, so every user without a
 * role gets the nearest built-in role - as long as the admin may hand it out.
 */
function ur_apply_suggestions(): string
{
	$q = db_select("select id, rettigheder from brugere where role_id is null or role_id = 0", __FILE__ . " linje " . __LINE__);
	$done = array();
	while ($r = db_fetch_array($q)) {
		$suggestion = perm_suggest_role((string) $r['rettigheder']);
		if (!$suggestion || !ur_role_assignable($suggestion['id'])) {
			continue;
		}
		db_modify("update brugere set role_id = " . $suggestion['id'] . " where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		perm_sync_user((int) $r['id']);
		audit_log('user.role_changed', '- -> ' . user_role_label((int) $suggestion['id']), 'bruger', (string) $r['id'], 'migrering');
		$done[] = $r['id'];
	}
	return 'tab=users&msg=assigned';
}

/**
 * @return array<string, string> levels[key] from the matrix radios, unknown keys dropped.
 */
function ur_levels_from_post(array $post): array
{
	$levels = array();
	$posted = isset($post['level']) && is_array($post['level']) ? $post['level'] : array();
	foreach (permission_registry() as $key => $def) {
		$levels[$key] = isset($posted[$key]) ? perm_level_valid((string) $posted[$key]) : 'none';
	}
	return $levels;
}

function ur_save_role(array $post): string
{
	$id = (int) ifset($post, 'id', 0);
	$navn = trim((string) ifset($post, 'navn', ''));
	$beskrivelse = trim((string) ifset($post, 'beskrivelse', ''));
	$levels = ur_levels_from_post($post);
	if ($navn === '' || mb_strlen($navn) > 80) {
		return 'tab=roles&rolle=' . $id . '&msg=name';
	}
	if (!perm_within_own($levels)) {
		return 'tab=roles&rolle=' . $id . '&msg=escalation';
	}
	$navnSql = db_escape_string($navn);
	$beskSql = db_escape_string(mb_substr($beskrivelse, 0, 500));
	$existing = ($id > 0) ? db_fetch_array(db_select("select navn, beskrivelse, system from roles where id = $id", __FILE__ . " linje " . __LINE__)) : null;
	if ($existing && in_array($existing['system'], array('t', true, '1', 1), true)) {
		// Built-in roles keep their name and description; only the matrix can be edited (spec §3.2).
		$navn = (string) $existing['navn'];
		$navnSql = db_escape_string($navn);
		$beskSql = db_escape_string((string) $existing['beskrivelse']);
	}
	if ($id === 0) {
		db_modify("insert into roles (role_key, navn, beskrivelse, system) values (null, '$navnSql', '$beskSql', 'f')", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from roles where navn = '$navnSql' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
		$id = (int) $r['id'];
		audit_log('role.created', $navn, 'rolle', (string) $id);
	} else {
		db_modify("update roles set navn = '$navnSql', beskrivelse = '$beskSql' where id = $id", __FILE__ . " linje " . __LINE__);
		audit_log('role.updated', $navn, 'rolle', (string) $id);
	}
	perm_save_role_levels($id, $levels);
	return 'tab=roles&rolle=' . $id . '&msg=rolesaved';
}

function ur_delete_role(int $id): string
{
	if ($id <= 0) {
		return 'tab=roles';
	}
	$r = db_fetch_array(db_select("select navn, system from roles where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['system'] === 't' || $r['system'] === true || $r['system'] === '1' || $r['system'] === 1) {
		return 'tab=roles';
	}
	$inUse = db_fetch_array(db_select("select id from brugere where role_id = $id limit 1", __FILE__ . " linje " . __LINE__));
	if ($inUse) {
		return 'tab=roles&rolle=' . $id . '&msg=roleinuse';
	}
	db_modify("delete from role_permissions where role_id = $id", __FILE__ . " linje " . __LINE__);
	db_modify("delete from roles where id = $id", __FILE__ . " linje " . __LINE__);
	audit_log('role.deleted', (string) $r['navn'], 'rolle', (string) $id);
	return 'tab=roles&msg=roledeleted';
}

/**
 * "Nulstil til standard" (spec §3.2): a built-in role gets its built-in matrix back.
 */
function ur_reset_role(int $id): string
{
	$defaults = permission_default_roles();
	foreach (perm_roles() as $role) {
		if ($role['id'] !== $id || !$role['system'] || !isset($defaults[$role['key']])) {
			continue;
		}
		$levels = array();
		foreach (permission_registry() as $key => $def) {
			$levels[$key] = isset($defaults[$role['key']]['levels'][$key]) ? $defaults[$role['key']]['levels'][$key] : 'none';
		}
		if (!perm_within_own($levels)) {
			return 'tab=roles&rolle=' . $id . '&msg=escalation';
		}
		perm_save_role_levels($id, $levels);
		audit_log('role.reset', $role['navn'], 'rolle', (string) $id);
		return 'tab=roles&rolle=' . $id . '&msg=rolereset';
	}
	return 'tab=roles';
}

function ur_copy_role(int $id): string
{
	global $sprog_id;
	$source = null;
	foreach (perm_roles() as $role) {
		if ($role['id'] === $id) {
			$source = $role;
		}
	}
	if (!$source) {
		return 'tab=roles';
	}
	$navn = findtekst('5587|Kopi af', (int) $sprog_id) . ' ' . perm_role_name($source, (int) $sprog_id);
	$navnSql = db_escape_string(mb_substr($navn, 0, 80));
	$beskSql = db_escape_string($source['beskrivelse']);
	db_modify("insert into roles (role_key, navn, beskrivelse, system) values (null, '$navnSql', '$beskSql', 'f')", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select id from roles where navn = '$navnSql' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$newId = (int) $r['id'];
	perm_save_role_levels($newId, perm_levels_from_role($id));
	audit_log('role.created', $navn . ' (kopi af ' . $id . ')', 'rolle', (string) $newId);
	return 'tab=roles&rolle=' . $newId;
}

// ================================================================== view model

/**
 * Everything the view needs, loaded in one place.
 *
 * @return array<string, mixed>
 */
function ur_view_model(array $get, int $selfId, int $sprogId, string $dbEncode, string $contextQuery): array
{
	$tab = isset($get['tab']) ? (string) $get['tab'] : 'users';
	if (!in_array($tab, array('users', 'roles', 'log'), true)) {
		$tab = 'users';
	}
	$roles = perm_roles();
	$rolesById = array();
	foreach ($roles as $role) {
		$rolesById[$role['id']] = $role;
	}
	$counts = array();
	$q = db_select("select role_id, count(*) as antal from brugere group by role_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$counts[(int) $r['role_id']] = (int) $r['antal'];
	}

	$lastLogin = array();
	if (audit_ready()) {
		$q = db_select("select bruger_id, max(tidspunkt) as sidst from audit_log where handling in ('login', 'login.success') group by bruger_id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$lastLogin[(int) $r['bruger_id']] = (string) $r['sidst'];
		}
	}
	$revisorUser = 0;
	if ($r = db_fetch_array(db_select("select user_id from settings where var_name = 'revisor' and var_grp = 'system'", __FILE__ . " linje " . __LINE__))) {
		$revisorUser = (int) $r['user_id'];
	}

	$users = array();
	$withoutRole = 0;
	$q = db_select("select b.*, a.navn as ansat_navn, a.initialer, a.lukket as ansat_lukket from brugere b left join ansatte a on a.id = b.ansat_id order by lower(b.brugernavn)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$roleId = (int) $r['role_id'];
		$suggestion = null;
		if ($roleId <= 0) {
			$withoutRole++;
			$s = perm_suggest_role((string) $r['rettigheder']);
			if ($s && isset($rolesById[$s['id']])) {
				$suggestion = $rolesById[$s['id']];
			}
		}
		$users[] = array(
			'id'         => (int) $r['id'],
			'brugernavn' => (string) $r['brugernavn'],
			'navn'       => trim((string) $r['ansat_navn']) !== '' ? trim((string) $r['ansat_navn']) : (string) $r['brugernavn'],
			'initialer'  => (string) $r['initialer'],
			'email'      => (string) ifset($r, 'email', ''),
			'tlf'        => (string) ifset($r, 'tlf', ''),
			'ip'         => (string) ifset($r, 'ip_address', ''),
			'twofactor'  => in_array($r['twofactor'], array('t', true, '1', 1), true),
			'ansat_id'   => (int) $r['ansat_id'],
			'role_id'    => $roleId,
			'role'       => isset($rolesById[$roleId]) ? $rolesById[$roleId] : null,
			'suggestion' => $suggestion,
			'rettigheder'=> (string) $r['rettigheder'],
			'lastLogin'  => isset($lastLogin[(int) $r['id']]) ? $lastLogin[(int) $r['id']] : '',
			'closed'     => !user_row_active($r),
			'invited'    => user_row_invited($r),
			'hasLoggedIn'=> isset($lastLogin[(int) $r['id']]),
			'isRevisor'  => ((int) $r['id'] === $revisorUser),
		);
	}

	$employees = array();
	$q = db_select("select id, navn, initialer from ansatte where lukket is null or lukket != 'on' order by initialer, navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$employees[] = array('id' => (int) $r['id'], 'navn' => (string) $r['navn'], 'initialer' => (string) $r['initialer']);
	}

	$editUser = null;
	if (isset($get['bruger'])) {
		$wanted = (int) $get['bruger'];
		foreach ($users as $u) {
			if ($u['id'] === $wanted) {
				$editUser = $u;
			}
		}
		if ($wanted === 0) {
			$editUser = array('id' => 0, 'brugernavn' => '', 'navn' => '', 'initialer' => '', 'email' => '', 'tlf' => '', 'ip' => '',
				'twofactor' => false, 'ansat_id' => 0, 'role_id' => 0, 'role' => null, 'suggestion' => null, 'rettigheder' => '',
				'lastLogin' => '', 'closed' => false, 'invited' => false, 'hasLoggedIn' => false, 'isRevisor' => false);
		}
	}

	$inviteLink = '';
	if (isset($_SESSION['ur_invite_link'])) {
		if ($editUser && (int) $_SESSION['ur_invite_link']['id'] === $editUser['id']) {
			$inviteLink = (string) $_SESSION['ur_invite_link']['link'];
		}
		unset($_SESSION['ur_invite_link']);
	}

	$editRole = null;
	if (isset($get['rolle'])) {
		$wanted = (int) $get['rolle'];
		if ($wanted === 0) {
			$editRole = array('id' => 0, 'key' => '', 'navn' => '', 'beskrivelse' => '', 'system' => false, 'levels' => perm_levels_from_role(0));
		} elseif (isset($rolesById[$wanted])) {
			$editRole = $rolesById[$wanted];
			$editRole['levels'] = perm_levels_from_role($wanted);
		}
	}

	$log = array();
	$unguarded = array();
	$wouldDeny = array();
	if ($tab === 'log' && audit_ready()) {
		$q = db_select("select tidspunkt, brugernavn, handling, detaljer, ip from audit_log order by id desc limit 200", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$log[] = $r;
		}
		// Logging-period overview (spec 3.3 step 2): which pages still lack a key, and
		// what would have been refused, grouped so the admin can judge before switching.
		$q = db_select("select detaljer, count(*) as antal, max(tidspunkt) as sidst from audit_log where handling = 'unguarded' group by detaljer order by antal desc limit 100", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$unguarded[] = $r;
		}
		$q = db_select("select detaljer, count(*) as antal, max(tidspunkt) as sidst from audit_log where handling in ('would-deny', 'permission.would_deny') group by detaljer order by antal desc limit 100", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$wouldDeny[] = $r;
		}
	}

	return array(
		'tab'          => $tab,
		'sprogId'      => $sprogId,
		'charset'      => ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1',
		'selfUrl'      => 'usersRoles.php' . ($contextQuery !== '' ? '?' . $contextQuery : ''),
		'linkPrefix'   => 'usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : ''),
		'canWrite'     => perm_can('settings.users.manage', 'write'),
		'ownLevels'    => perm_user_levels($selfId, (string) $GLOBALS['rettigheder']),
		'selfId'       => $selfId,
		'msg'          => isset($get['msg']) ? (string) $get['msg'] : '',
		'skipped'      => isset($get['skipped']) ? (int) $get['skipped'] : 0,
		'adminRoleId'  => user_admin_role_id(),
		'users'        => $users,
		'withoutRole'  => $withoutRole,
		'roles'        => $roles,
		'roleCounts'   => $counts,
		'employees'    => $employees,
		'editUser'     => $editUser,
		'newMode'      => (isset($get['mode']) && $get['mode'] === 'classic') ? 'classic' : 'invite',
		'inviteLink'   => $inviteLink,
		'editRole'     => $editRole,
		'log'          => $log,
		'unguarded'    => $unguarded,
		'wouldDeny'    => $wouldDeny,
		'enforceMode'  => perm_enforcement_mode(),
		'tablesReady'  => perm_tables_ready(),
	);
}
