<?php
// ---- includes/onboarding.php --- lap 5.0.0 --- 2026.10.06 ---
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
// 20261006 Sawaneh Onboarding part 1 (Requirements_onboarding_welcome_EN.md §4, §8): the guide's state per company in the
//                  settings table - onboarding_state (new/started/completed/hidden), onboarding_steps (JSON step => done/
//                  skipped), onboarding_source and onboarding_role. A company without a state counts as completed, so
//                  existing customers never see the pop-up without an upgrade step. The dashboard card is rendered here.

if (!function_exists('onb_get')):

function onb_get(string $name): string
{
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'onboarding' and var_name = '" . db_escape_string($name) . "' and coalesce(user_id, 0) = 0 order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['var_value'] : '';
}

function onb_set(string $name, string $value): void
{
	$n = db_escape_string($name);
	$v = db_escape_string($value);
	if (db_fetch_array(db_select("select id from settings where var_grp = 'onboarding' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__))) {
		db_modify("update settings set var_value = '$v' where var_grp = 'onboarding' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('onboarding', '$n', '$v', 'Onboarding guide', 0)", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * The guide's steps in order: key => [title text, description text, important, built]. Steps not built yet are shown
 * as "Kommer snart" and are not counted as next.
 *
 * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
 */
function onb_steps_def(): array
{
	return array(
		'welcome' => array('6685|Velkommen', '6686|Kort intro', false, true),
		'profile' => array('6687|Hvem er du?', '6688|Ny virksomhed eller skifter du system?', false, true),
		'company' => array('6689|Virksomhed og udtryk', '6690|CVR, firmaoplysninger, logo og farve', false, true),
		'fiscal'  => array('6691|Regnskabsår og moms', '6692|Startdato og momsperiode', true, true),
		'invoice' => array('6693|Din faktura', '6694|Bank, betalingsbetingelser og forhåndsvisning', false, false),
		'users'   => array('6695|Inviter kolleger', '6696|Bogholder, ejer eller medarbejdere', false, false),
		'done'    => array('6697|Færdig', '6698|Opsummering', false, false),
	);
}

function onb_state(): string
{
	$s = onb_get('onboarding_state');
	return $s === '' ? 'completed' : $s;
}

/**
 * @return array<string, string> step => done|skipped
 */
function onb_steps(): array
{
	$j = json_decode(onb_get('onboarding_steps'), true);
	return is_array($j) ? $j : array();
}

function onb_mark(string $step, string $status): void
{
	$steps = onb_steps();
	if ($status === 'skipped' && isset($steps[$step]) && $steps[$step] === 'done') {
		return;
	}
	$steps[$step] = $status;
	onb_set('onboarding_steps', json_encode($steps));
	$all = true;
	foreach (array_keys(onb_steps_def()) as $k) {
		if (!isset($steps[$k])) {
			$all = false;
		}
	}
	if ($all && onb_state() !== 'hidden') {
		onb_set('onboarding_state', 'completed');
	}
}

/**
 * Who may run the guide: the Indstillinger permission (§8).
 */
function onb_can_run(): bool
{
	return function_exists('perm_can') ? perm_can('settings.company', 'write') : true;
}

/**
 * Steps counted on the card ("4 af 7"): done ones.
 */
function onb_done_count(): int
{
	$n = 0;
	foreach (onb_steps() as $status) {
		if ($status === 'done') {
			$n++;
		}
	}
	return $n;
}

/**
 * The first built step that is not done (the one "Fortsæt" opens), or '' when there is none.
 */
function onb_next_step(): string
{
	$steps = onb_steps();
	foreach (onb_steps_def() as $k => $d) {
		if ($d[3] && (!isset($steps[$k]) || $steps[$k] !== 'done')) {
			return $k;
		}
	}
	return '';
}

/**
 * The step before and after $step among the built ones ('' at the ends).
 *
 * @return array{0: string, 1: string}
 */
function onb_neighbours(string $step): array
{
	$keys = array();
	foreach (onb_steps_def() as $k => $d) {
		if ($d[3]) {
			$keys[] = $k;
		}
	}
	$i = array_search($step, $keys, true);
	if ($i === false) {
		return array('', '');
	}
	return array($i > 0 ? $keys[$i - 1] : '', isset($keys[$i + 1]) ? $keys[$i + 1] : '');
}

/**
 * VAT registration and period chosen in step 3. Nothing in Saldi stores them today (finans/moms_periode.php only opens
 * and closes months), so they are kept in settings var_grp 'vat' until a VAT setting takes them over.
 *
 * @return array{registered: bool, period: string}
 */
function onb_vat_get(): array
{
	$reg = get_settings_value('vat_registered', 'vat', '', 0);
	$per = get_settings_value('vat_period', 'vat', '', 0);
	return array('registered' => $reg !== 'off', 'period' => in_array($per, array('M', 'K', 'H'), true) ? $per : 'K');
}

function onb_vat_set(bool $registered, string $period): void
{
	update_settings_value('vat_registered', 'vat', $registered ? 'on' : 'off', 'VAT registered (onboarding step 3)', 0);
	if ($registered && in_array($period, array('M', 'K', 'H'), true)) {
		update_settings_value('vat_period', 'vat', $period, 'VAT period M/K/H (onboarding step 3)', 0);
	}
}

/**
 * The colour themes offered in step 2 (prototype), the first one being Saldi's default.
 *
 * @return array<int, string>
 */
function onb_colours(): array
{
	return array('114691', '1d9e57', '7c3aed', 'e0442c', '0e7c86', 'b45309', '1c2431', 'd63384');
}

/**
 * The user's menu colour (personal settings, var_grp 'colors'), without '#'.
 */
function onb_colour_get(int $userId): string
{
	$v = strtolower(ltrim((string) get_settings_value('buttonColor', 'colors', '114691', $userId), '#'));
	return preg_match('/^[0-9a-f]{6}$/', $v) ? $v : '114691';
}

/**
 * Store the colour personally, as the personal settings page does (spec A2: no ledger colour yet).
 */
function onb_colour_set(int $userId, string $hex): bool
{
	$hex = strtolower(ltrim(trim($hex), '#'));
	if ($userId <= 0 || !preg_match('/^[0-9a-f]{6}$/', $hex)) {
		return false;
	}
	update_settings_value('buttonColor', 'colors', $hex, 'Background color for user settings', $userId);
	return true;
}

function onb_logo_url($dbId): string
{
	$f = "../logolib/" . (int) $dbId . "/fe_logo.png";
	return file_exists($f) ? $f . "?t=" . filemtime($f) : '';
}

/**
 * The "Kom godt i gang" card at the top of the dashboard (spec §7): shown while the guide is new or started, and as
 * "Opsætning færdig" once every step is set, until it is hidden. A ledger without a state (existing customers) gets no card.
 */
function onb_render_card(int $userId, int $sprogId): void
{
	$state = onb_get('onboarding_state');
	$steps = onb_steps();
	if ($state === '' || $state === 'hidden' || ($state === 'completed' && !$steps)) {
		return;
	}
	$canRun = onb_can_run();
	$h = function ($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	};
	$tx = function ($t) use ($sprogId) {
		return findtekst($t, $sprogId);
	};
	$def = onb_steps_def();
	$total = count($def);
	$done = onb_done_count();
	$next = onb_next_step();
	if (!isset($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	print "<link rel='stylesheet' href='../css/onboarding.css?v=1'>";
	print "<div class='onb-card' style='--brand:#" . $h(onb_colour_get($userId)) . "'>";
	print "<div class='onb-card-hero'><div><h3>" . $h($state === 'completed' ? $tx('6775|Opsætning færdig') : $tx('6748|Kom godt i gang')) . "</h3>";
	if (!$canRun) {
		print "<p>" . $h($tx('6756|Opsætningen er ikke færdig – bed administrator om at gøre den færdig.')) . "</p>";
	} elseif ($state === 'completed') {
		print "<p>" . $h($tx('6757|Alt er sat op. Godt gået!')) . "</p>";
	} else {
		print "<p>" . $h($tx('6749|Et par korte trin, så Saldi ser ud som dit firma og er klar til din første faktura.')) . "</p>";
	}
	$dash = round($done / max(1, $total) * 239);
	print "<div class='onb-ring'><svg width='92' height='92'><circle class='bg' cx='46' cy='46' r='38'/><circle class='fg' cx='46' cy='46' r='38' stroke-dasharray='$dash 239'/></svg><b>$done/$total</b></div></div>";
	if ($canRun) {
		print "<div class='cta'>";
		if ($next !== '') {
			$label = $done === 0 ? $tx('6751|Start opsætning') : sprintf($tx('6752|Fortsæt: %s'), $tx($def[$next][0]));
			print "<button type='button' class='light' onclick=\"onbOpen('" . $h($next) . "')\">" . $h($label) . " &rarr;</button>";
		} else {
			print "<button type='button' class='light' onclick=\"onbOpen('profile')\">" . $h($tx('6758|Gennemse opsætning')) . "</button>";
		}
		print "</div>";
	}
	print "</div><div class='onb-card-body'><div class='onb-card-head'><span>" . $h(sprintf($tx('6750|%s af %s trin færdige'), $done, $total)) . "</span>";
	if ($canRun) {
		print "<form method='post' action='onboarding.php' style='margin:0'><input type='hidden' name='csrf_token' value='" . $h($_SESSION['csrf_token']) . "'>"
			. "<input type='hidden' name='action' value='hide'><button type='submit' class='ghost'>" . $h($state === 'completed' ? $tx('6774|Skjul') : $tx('6753|Skjul tjekliste')) . "</button></form>";
	}
	print "</div><div class='onb-grid'>";
	$check = "<svg width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><path d='M5 13l4 4L19 7'/></svg>";
	foreach ($def as $k => $d) {
		$st = isset($steps[$k]) ? $steps[$k] : '';
		$cls = $st === 'done' ? 'done' : (($st === 'skipped' && $d[2]) ? 'imp' : '');
		$icon = $cls === 'done' ? $check : ($cls === 'imp' ? '!' : '');
		$tag = $cls === 'imp' ? "<span class='onb-tag imp'>" . $h($tx('6754|Vigtigt')) . "</span>" : (!$d[3] ? "<span class='onb-tag'>" . $h($tx('6755|Kommer snart')) . "</span>" : '');
		$open = ($canRun && $d[3]) ? " onclick=\"onbOpen('" . $h($k) . "')\"" : '';
		print "<button type='button' class='onb-step" . (($canRun && $d[3]) ? '' : ' soon') . "'$open><span class='onb-st $cls'>$icon</span><span class='t'><b>" . $h($tx($d[0])) . "</b><span>" . $h($tx($d[1])) . "</span></span>$tag</button>";
	}
	$part2 = array(array('6760|Kontoplan', '6768|Behold standard, vælg skabelon eller importér'), array('6761|Åbningsbalance', '6769|Saldobalance og åbne poster'),
		array('6762|Debitorer og kreditorer', '6770|Importér kunder og leverandører'), array('6763|Varer og lager', '6771|Importér varer og beholdning'));
	foreach ($part2 as $p) {
		print "<div class='onb-step soon'><span class='onb-st'></span><span class='t'><b>" . $h($tx($p[0])) . "</b><span>" . $h($tx($p[1])) . "</span></span><span class='onb-tag'>" . $h($tx('6759|Del 2')) . "</span></div>";
	}
	print "</div></div></div>";
	print "<script>function onbOpen(s){ if (window.parent && window.parent !== window && window.parent.saldiOnboardingOpen) { window.parent.saldiOnboardingOpen(s); } else { location.href = 'onboarding.php?step=' + encodeURIComponent(s); } }</script>";
}

endif;
