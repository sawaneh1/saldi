<?php
// ---- includes/formEditorState.php --- lap 5.0.0 --- 2026.10.05 ---
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
// 20261005 Sawaneh Settings redesign G6.1 (audit FE3): the visual form editor's print-language lock and drafts are kept
//                  in the settings table (var_grp 'formeditor'), so they are in backups and the ledger, not in files
//                  under logolib/. A file left from before is moved into the table the first time it is read.
// 20261006 Sawaneh Onboarding part 1: fe_logo_store() is the logo upload shared by the form editor and the welcome guide.

if (!function_exists('fe_state_get')):

function fe_state_get(string $name): string
{
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'formeditor' and var_name = '" . db_escape_string($name) . "' and coalesce(user_id, 0) = 0 order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['var_value'] : '';
}

function fe_state_set(string $name, string $value): void
{
	$n = db_escape_string($name);
	$v = db_escape_string($value);
	if (db_fetch_array(db_select("select id from settings where var_grp = 'formeditor' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__))) {
		db_modify("update settings set var_value = '$v' where var_grp = 'formeditor' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('formeditor', '$n', '$v', 'Visual form editor', 0)", __FILE__ . " linje " . __LINE__);
	}
}

function fe_state_delete(string $name): void
{
	db_modify("delete from settings where var_grp = 'formeditor' and var_name = '" . db_escape_string($name) . "'", __FILE__ . " linje " . __LINE__);
}

/**
 * The value stored under $name, taking over the old file $legacyFile when the table has none yet.
 */
function fe_state_get_migrating(string $name, string $legacyFile): string
{
	$v = fe_state_get($name);
	if ($v === '' && $legacyFile !== '' && @is_file($legacyFile)) {
		$v = (string) @file_get_contents($legacyFile);
		if ($v !== '') {
			fe_state_set($name, $v);
		}
		@unlink($legacyFile);
	}
	return $v;
}

/**
 * The language a form is locked to print in ('' when not locked).
 */
function fe_printlang_get($dbId, int $form): string
{
	$json = fe_state_get_migrating('printlang_' . $form, "../logolib/$dbId/fe_printlang_$form.json");
	$pl = $json !== '' ? json_decode($json, true) : null;
	return (is_array($pl) && !empty($pl['sprog'])) ? (string) $pl['sprog'] : '';
}

function fe_draft_name(int $form, string $sprog): string
{
	return 'draft_' . $form . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $sprog);
}

/**
 * Store an uploaded logo as logolib/<db_id>/fe_logo.png: png or jpg up to 5 MB, re-encoded to drop embedded scripts and
 * metadata (NR-8) and capped at 1500 px. It is placed on printed forms when a form is saved in the form editor.
 *
 * @return array{ok: bool, error: string, w: int, h: int, url: string}
 */
function fe_logo_store($dbId, string $tmp, int $size): array
{
	$fail = function (string $e) {
		return array('ok' => false, 'error' => $e, 'w' => 0, 'h' => 0, 'url' => '');
	};
	if ($tmp === '' || !is_uploaded_file($tmp)) return $fail('nofile');
	if ($size > 5 * 1024 * 1024) return $fail('toobig');
	$info = @getimagesize($tmp);
	if (!$info || !in_array($info['mime'], array('image/png', 'image/jpeg', 'image/jpg'), true)) return $fail('badtype');
	$w = (int) $info[0]; $h = (int) $info[1];
	if ($w < 1 || $h < 1) return $fail('badimg');

	$dir = "../logolib/" . (int) $dbId;
	if (!is_dir($dir)) @mkdir($dir, 0775, true);
	$png = "$dir/fe_logo.png";
	$new = "$dir/fe_logo_new.png";
	@unlink($new);
	$ok = false;
	if (function_exists('shell_exec')) {
		@shell_exec("convert " . escapeshellarg($tmp) . "[0] -strip -background none -resize '1500x1500>' " . escapeshellarg($new) . " 2>/dev/null");
		$ok = file_exists($new);
	}
	if (!$ok) $ok = @move_uploaded_file($tmp, $new);
	if (!$ok || !@rename($new, $png)) {
		@unlink($new);
		return $fail('store');
	}

	// Never write logolib/logo_<db_id>.eps: the print engine stamps that file on every form without a background PDF.
	@unlink("../logolib/logo_" . (int) $dbId . ".eps");
	$ni = @getimagesize($png);
	if ($ni) { $w = (int) $ni[0]; $h = (int) $ni[1]; }
	return array('ok' => true, 'error' => '', 'w' => $w, 'h' => $h, 'url' => "../logolib/" . (int) $dbId . "/fe_logo.png?t=" . time());
}

endif;
