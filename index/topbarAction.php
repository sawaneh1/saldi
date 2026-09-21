<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/topbarAction.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh Controller for topbar actions (fiscal-year switch from the user chip).
//                  POST only, redirects back to the shell (Post/Redirect/Get).

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $db
 * @var mixed  $revisor
 */

@session_start();
$s_id = session_id();
ob_start();

$title = 'topbarAction';
$css = '';
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");

$request = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : array();
$action = isset($request['action']) ? (string) $request['action'] : '';
$returnHash = isset($request['return_hash']) ? (string) $request['return_hash'] : '';

// Activating a fiscal year = the same two writes systemdata/brugerdata.php has always
// done: the user's default in `brugere` (next login) and the session row in the
// master `online` table (what every page reads as $regnaar). connect.php must be
// re-included at top level so the master connection is the one the db_* helpers see.
// Auditor / master-admin sessions (bruger_id -1) have no brugere row but still
// switch year for their session, as they always could via brugerdata.php.
if ($action === 'fiscal_year') {
	$year = topbar_open_fiscal_year(isset($request['year']) ? (string) $request['year'] : '');
	if ($year !== '') {
		if (!$revisor && (int) $bruger_id > 0) {
			db_modify("update brugere set regnskabsaar = '$year' where id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__);
		}
		include(__DIR__ . "/../includes/connect.php");
		db_modify("update online set regnskabsaar = '$year' where session_id = '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
	}
}

ob_end_clean();
header('Location: main.php' . topbar_return_hash($returnHash));
exit;

/**
 * The requested year, if it is one of the company's open fiscal years; else ''.
 */
function topbar_open_fiscal_year(string $year): string
{
	$year = trim($year);
	if ($year === '' || !preg_match('/^[0-9]+$/', $year)) {
		return '';
	}
	$qtxt = "select kodenr from grupper where art = 'RA' and kodenr = '$year' and box5 = 'on'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		return '';
	}
	return $year;
}

/**
 * Only an in-app path (as the shell writes it to location.hash) may be echoed back.
 */
function topbar_return_hash(string $hash): string
{
	$hash = trim($hash);
	if ($hash === '' || $hash[0] !== '/' || strpos($hash, '//') === 0 || preg_match('/[\s<>"\'\\\\]/', $hash)) {
		return '';
	}
	return '#' . $hash;
}
