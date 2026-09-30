<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/userFunctions.php --- lap 5.0.0 --- 2026.09.29 ---
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
// 20260929 Sawaneh Roles stage 2 (Requirements_roles_stage2_EN.md §8, §8.2, §8.5): the user
//                  operations of "Brugere & roller" in one place, so the page, onboarding and
//                  the API share them: create, close instead of delete, reopen, the
//                  last-administrator rule.
// 20260929 Sawaneh Invitation flow (§8.4): user_invite, resend, welcome mail, first password.
// 20260930 Sawaneh Close, reopen and role change report 'already'/'unchanged' instead of success when nothing changes.

include_once(__DIR__ . '/permissions.php');

/**
 * brugere.status: true/null = active, false = closed.
 */
function user_row_active(array $row): bool
{
	if (!array_key_exists('status', $row)) {
		return true;
	}
	return !in_array($row['status'], array('f', false, '0', 0), true);
}

function user_admin_role_id(): int
{
	return perm_role_id_by_key('administrator');
}

/**
 * Active users holding the Administrator role, not counting $exceptId.
 */
function user_active_admins(int $exceptId = 0): int
{
	$roleId = user_admin_role_id();
	if ($roleId <= 0) {
		return 0;
	}
	$n = 0;
	$q = db_select("select * from brugere where role_id = $roleId and id != " . (int) $exceptId, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (user_row_active($r)) {
			$n++;
		}
	}
	return $n;
}

/**
 * True when this user is the only active administrator left (spec §8.5): such a user can
 * not be closed, deleted or given another role.
 */
function user_is_last_admin(int $id): bool
{
	$roleId = user_admin_role_id();
	if ($roleId <= 0 || $id <= 0) {
		return false;
	}
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r || (int) $r['role_id'] !== $roleId || !user_row_active($r)) {
		return false;
	}
	return user_active_admins($id) === 0;
}

/**
 * True when the user has ever logged in: such a user is part of the audit trail and can
 * only be closed, never deleted (spec §8.2).
 */
function user_has_logged_in(int $id): bool
{
	if (!audit_ready()) {
		return true;
	}
	return (bool) db_fetch_array(db_select("select id from audit_log where bruger_id = $id and handling in ('login', 'login.success') limit 1", __FILE__ . " linje " . __LINE__));
}

/**
 * Create a user. The password is hashed with the user's id, so row and hash are written in
 * one transaction and brugere.kode never holds anything but the hash (acceptance 12).
 *
 * @param array<string, mixed> $data brugernavn, kode, role_id, ansat_id, ip_address, tlf, email, twofactor, regnskabsaar
 */
function user_create(array $data, string $kilde = 'ui'): int
{
	$navnSql = db_escape_string((string) $data['brugernavn']);
	$roleId = isset($data['role_id']) ? (int) $data['role_id'] : 0;
	$ansatId = isset($data['ansat_id']) ? (int) $data['ansat_id'] : 0;
	$regnaar = !empty($data['regnskabsaar']) ? (int) $data['regnskabsaar'] : 1;
	$twofactor = !empty($data['twofactor']) ? 't' : 'f';

	transaktion('begin');
	$qtxt = "insert into brugere (brugernavn, kode, rettigheder, regnskabsaar, ansat_id, ip_address, tlf, twofactor, email, role_id, status) values (";
	$qtxt .= "'$navnSql', '', '0000000000000000', '$regnaar', $ansatId, ";
	$qtxt .= "'" . db_escape_string(mb_substr((string) (isset($data['ip_address']) ? $data['ip_address'] : ''), 0, 45)) . "', ";
	$qtxt .= "'" . db_escape_string(mb_substr((string) (isset($data['tlf']) ? $data['tlf'] : ''), 0, 16)) . "', ";
	$qtxt .= "'$twofactor', '" . db_escape_string((string) (isset($data['email']) ? $data['email'] : '')) . "', ";
	$qtxt .= ($roleId > 0 ? (string) $roleId : 'null') . ", 't')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select id from brugere where brugernavn = '$navnSql' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$id = (int) $r['id'];
	if ((string) $data['kode'] !== '') {
		db_modify("update brugere set kode = '" . db_escape_string(saldikrypt($id, (string) $data['kode'])) . "' where id = $id", __FILE__ . " linje " . __LINE__);
	}
	transaktion('commit');

	perm_sync_user($id);
	audit_log('user.created', (string) $data['brugernavn'] . ' (rolle ' . $roleId . ')', 'bruger', (string) $id, $kilde);
	return $id;
}

/**
 * Close a user: no login, sessions ended, linked employee closed as before. The row stays,
 * so the audit trail keeps its originator.
 *
 * @return string '' on success, else the reason: 'self', 'lastadmin', 'missing', 'already'
 */
function user_close(int $id, int $selfId, string $kilde = 'ui'): string
{
	if ($id === $selfId) {
		return 'self';
	}
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return 'missing';
	}
	if (!user_row_active($r)) {
		return 'already';
	}
	if (user_is_last_admin($id)) {
		return 'lastadmin';
	}
	db_modify("update brugere set status = 'f' where id = $id", __FILE__ . " linje " . __LINE__);
	if ((int) $r['ansat_id'] > 0) {
		db_modify("update ansatte set lukket = 'on', slutdate = '" . date('Y-m-d') . "' where id = " . (int) $r['ansat_id'], __FILE__ . " linje " . __LINE__);
	}
	$GLOBALS['user_sessions_to_end'][] = (string) $r['brugernavn'];
	audit_log('user.deactivated', (string) $r['brugernavn'], 'bruger', (string) $id, $kilde);
	return '';
}

/**
 * @return string '' on success, else 'missing' or 'already' (the user is not closed)
 */
function user_reopen(int $id, string $kilde = 'ui'): string
{
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return 'missing';
	}
	if (user_row_active($r)) {
		return 'already';
	}
	db_modify("update brugere set status = 't' where id = $id", __FILE__ . " linje " . __LINE__);
	if ((int) $r['ansat_id'] > 0) {
		db_modify("update ansatte set lukket = '', slutdate = null where id = " . (int) $r['ansat_id'], __FILE__ . " linje " . __LINE__);
	}
	audit_log('user.reactivated', (string) $r['brugernavn'], 'bruger', (string) $id, $kilde);
	return '';
}

/**
 * Delete a user that has never logged in. Everyone else is closed instead.
 *
 * @return string '' on success, else 'self', 'lastadmin', 'missing', 'hasloggedin'
 */
function user_delete(int $id, int $selfId, string $kilde = 'ui'): string
{
	if ($id === $selfId) {
		return 'self';
	}
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return 'missing';
	}
	if (user_is_last_admin($id)) {
		return 'lastadmin';
	}
	if (user_has_logged_in($id)) {
		return 'hasloggedin';
	}
	db_modify("delete from brugere where id = $id", __FILE__ . " linje " . __LINE__);
	audit_log('user.deleted', (string) $r['brugernavn'], 'bruger', (string) $id, $kilde);
	return '';
}

/**
 * Give a user another role. One audit entry per user (acceptance 9).
 *
 * @return string '' on success, else 'unchanged', 'ownrole', 'lastadmin', 'escalation', 'missing'
 */
function user_set_role(int $id, int $roleId, int $selfId, string $kilde = 'ui'): string
{
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return 'missing';
	}
	$current = (int) $r['role_id'];
	if ($current === $roleId) {
		return 'unchanged';
	}
	if ($id === $selfId) {
		return 'ownrole';
	}
	if ($roleId > 0 && !perm_within_own(perm_levels_from_role($roleId))) {
		return 'escalation';
	}
	if (user_is_last_admin($id)) {
		return 'lastadmin';
	}
	db_modify("update brugere set role_id = " . ($roleId > 0 ? $roleId : 'null') . " where id = $id", __FILE__ . " linje " . __LINE__);
	perm_sync_user($id);
	audit_log('user.role_changed', (string) $r['brugernavn'] . ': ' . user_role_label($current) . ' -> ' . user_role_label($roleId), 'bruger', (string) $id, $kilde);
	return '';
}

function user_role_label(int $roleId): string
{
	if ($roleId <= 0) {
		return '-';
	}
	foreach (perm_roles() as $role) {
		if ($role['id'] === $roleId) {
			return $role['navn'] . ' (' . $roleId . ')';
		}
	}
	return (string) $roleId;
}

// ------------------------------------------------------------------ invitation (spec §8.4)

/**
 * True for a user that was invited and has not chosen a password yet.
 */
function user_row_invited(array $row): bool
{
	return (string) ifset($row, 'kode', '') === '' && strpos((string) ifset($row, 'tmp_kode', ''), 'invite|') === 0 && user_row_active($row);
}

/**
 * Issue a new invitation token, valid for 72 hours. Only its hash is stored.
 *
 * @return string the token for the link, "<regnskab id>-<code>"
 */
function user_invite_token(int $id): string
{
	global $db_id;
	include_once(__DIR__ . '/tmpCode.php');
	$code = bin2hex(random_bytes(24));
	$stored = tmp_code_make('invite', time() + 72 * 3600, hash('sha256', $code));
	db_modify("update brugere set tmp_kode = '" . db_escape_string($stored) . "' where id = $id", __FILE__ . " linje " . __LINE__);
	return (int) $db_id . '-' . $code;
}

/**
 * Create a user without a password and issue the invitation.
 *
 * @param array<string, mixed> $data as user_create(), without kode
 * @return array{id: int, token: string}
 */
function user_invite(array $data, string $kilde = 'ui'): array
{
	$data['kode'] = '';
	$id = user_create($data, $kilde);
	$token = user_invite_token($id);
	audit_log('user.invited', (string) ifset($data, 'email', ''), 'bruger', (string) $id, $kilde);
	return array('id' => $id, 'token' => $token);
}

/**
 * New token for a user that is still invited.
 *
 * @return string the token, or '' when the user is not in status invited
 */
function user_invite_resend(int $id, string $kilde = 'ui'): string
{
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r || !user_row_invited($r)) {
		return '';
	}
	$token = user_invite_token($id);
	audit_log('user.invite_resent', (string) ifset($r, 'email', ''), 'bruger', (string) $id, $kilde);
	return $token;
}

function user_invite_link(string $token): string
{
	$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	$host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) $_SERVER['HTTP_HOST']) : 'localhost';
	$root = rtrim(str_replace('\\', '/', dirname(dirname(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/index/x'))), '/');
	return ($https ? 'https' : 'http') . '://' . $host . $root . '/index/login.php?invite=' . rawurlencode($token);
}

/**
 * Send the welcome mail with the invitation link through the company's mail setup.
 *
 * @return bool false when the mail could not be sent: the caller then shows the link instead
 */
function user_invite_mail(int $id, string $token, int $sprogId): bool
{
	global $brugernavn, $charset;
	$r = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
	$to = $r ? trim((string) ifset($r, 'email', '')) : '';
	if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
		return false;
	}
	$autoload = '';
	foreach (array(__DIR__ . '/../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php') as $candidate) {
		if (file_exists($candidate)) {
			$autoload = $candidate;
			break;
		}
	}
	if ($autoload === '') {
		return false;
	}
	require_once $autoload;

	$firm = db_fetch_array(db_select("select * from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
	$firmName = $firm ? (string) $firm['firmanavn'] : 'Saldi';
	$firmMail = $firm ? trim((string) $firm['email']) : '';
	$enc = (isset($charset) && $charset) ? (string) $charset : 'UTF-8';
	$h = function ($s) use ($enc) {
		return htmlspecialchars((string) $s, ENT_QUOTES, $enc);
	};
	$link = user_invite_link($token);
	$body = '<p>' . sprintf($h(findtekst('5774|%s har inviteret dig til regnskabet %s i Saldi.', $sprogId)), '<b>' . $h($brugernavn) . '</b>', '<b>' . $h($firmName) . '</b>') . '</p>';
	$body .= '<p>' . $h(findtekst('225|Brugernavn', $sprogId)) . ': <b>' . $h($r['brugernavn']) . '</b><br>';
	$body .= $h(findtekst('5553|Rolle', $sprogId)) . ': <b>' . $h(user_role_name((int) $r['role_id'])) . '</b></p>';
	$body .= '<p><a href="' . $h($link) . '">' . $h(findtekst('5775|Vælg din adgangskode', $sprogId)) . '</a><br>' . $h($link) . '</p>';
	$body .= '<p>' . $h(findtekst('5776|Linket gælder i 72 timer.', $sprogId)) . '</p>';
	if (in_array($r['twofactor'], array('t', true, '1', 1), true)) {
		$body .= '<p>' . $h(findtekst('5777|Tofaktor-login er slået til: du får en kode på SMS eller e-mail, hver gang du logger ind.', $sprogId)) . '</p>';
	}

	try {
		$mail = new PHPMailer\PHPMailer\PHPMailer();
		$mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
		$mail->IsSMTP();
		$mail->CharSet = $enc;
		$mail->Host = ($firm && $firm['felt_1']) ? (string) $firm['felt_1'] : 'localhost';
		if ($firm && $firm['felt_2']) {
			$mail->SMTPAuth = true;
			$mail->Username = (string) $firm['felt_2'];
			$mail->Password = (string) $firm['felt_3'];
			if (!empty($firm['felt_4'])) {
				$mail->SMTPSecure = strtolower((string) $firm['felt_4']);
			}
		} else {
			$mail->SMTPAuth = false;
		}
		$mail->Timeout = 10;
		$mail->From = 'kan_ikke_besvares@saldi.dk';
		$mail->FromName = $firmName;
		if ($firmMail !== '' && filter_var($firmMail, FILTER_VALIDATE_EMAIL)) {
			$mail->AddReplyTo($firmMail, $firmName);
		}
		$mail->AddAddress($to);
		$mail->IsHTML(true);
		$mail->Subject = sprintf(findtekst('5773|Invitation til %s i Saldi', $sprogId), $firmName);
		$mail->Body = $body;
		$mail->AltBody = html_entity_decode(strip_tags(str_replace(array('<br>', '</p>'), "\n", $body)), ENT_QUOTES, $enc);
		return (bool) $mail->Send();
	} catch (\Throwable $e) {
		return false;
	}
}

function user_role_name(int $roleId): string
{
	foreach (perm_roles() as $role) {
		if ($role['id'] === $roleId) {
			return (string) $role['navn'];
		}
	}
	return '-';
}

/**
 * The invited user chooses a password: hash stored, token cleared.
 */
function user_set_first_password(int $id, string $password, string $kilde = 'ui'): void
{
	db_modify("update brugere set kode = '" . db_escape_string(saldikrypt($id, $password)) . "', tmp_kode = null where id = $id", __FILE__ . " linje " . __LINE__);
	audit_log('user.password_set', '', 'bruger', (string) $id, $kilde);
}
