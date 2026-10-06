<?php
// ---- index/onboarding.php --- lap 5.0.0 --- 2026.10.06 ---
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
// 20261006 Sawaneh Onboarding part 1 (Requirements_onboarding_welcome_EN.md): the welcome guide, one page with ?step=,
//                  shown in the shell overlay (index/main.php). Næste saves through the existing logic (company fields
//                  through the settings definitions of Firmaoplysninger, the logo through fe_logo_store(), the colour as
//                  a personal setting, the fiscal year through settings_fiscal_year_set_first()) and marks the step done.

@session_start();
$s_id = session_id();
ob_start();

$title = "Saldi";
$header = 'nix';
$bg = 'nix';
$permission_key = 'any';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once("../systemdata/settingsRegistry.php");
include_once("../includes/settings/SettingsService.php");
include_once("../includes/settings/components.php");
include_once("../includes/settings/actions.php");
include_once("../includes/settings/rowHooks.php");
include_once("../includes/formEditorState.php");
include_once("../includes/onboarding.php");

$h = function ($s) {
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$tx = function ($t) use ($sprog_id) {
	return is_int($t) ? st_txt($t) : findtekst($t, $sprog_id);
};

if (!onb_can_run()) {
	ob_end_clean();
	print "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='font-family:system-ui,sans-serif;padding:2em'>"
		. $h($tx('6756|Opsætningen er ikke færdig – bed administrator om at gøre den færdig.')) . "</body></html>";
	exit;
}

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$def = onb_steps_def();
$step = isset($_REQUEST['step']) ? (string) $_REQUEST['step'] : '';
if (!isset($def[$step]) || !$def[$step][3]) {
	$step = onb_next_step();
	if ($step === '') {
		$step = 'profile';
	}
}

$companyFields = array(
	'cvrnr' => 'company.data.cvr', 'firmanavn' => 'company.data.name', 'addr1' => 'company.data.address1', 'addr2' => 'company.data.address2',
	'postnr' => 'company.data.zip', 'bynavn' => 'company.data.city', 'tlf' => 'company.data.phone', 'email' => 'company.data.email',
);
$systems = array('e-conomic' => 'e-conomic', 'uniconta' => 'Uniconta', 'dinero' => 'Dinero', 'billy' => 'Billy', 'spreadsheet' => '6772|Regneark', 'other' => '6773|Andet');

/**
 * The fiscal-year step is read-only once anything is posted or a second year exists (spec step 3).
 */
function onb_fiscal_locked(): bool
{
	return settings_fy_count("select count(*) as n from grupper where art = 'RA'") > 1 || settings_fy_count("select count(*) as n from transaktioner") > 0;
}

function onb_first_year(): array
{
	$r = db_fetch_array(db_select("select * from grupper where art = 'RA' order by cast(kodenr as integer) limit 1", __FILE__ . " linje " . __LINE__));
	$y = (int) date('Y');
	if (!$r) {
		return array(sprintf('%04d-01', $y), sprintf('%04d-12', $y));
	}
	return array(sprintf('%04d-%02d', (int) $r['box2'], (int) $r['box1']), sprintf('%04d-%02d', (int) $r['box4'], (int) $r['box3']));
}

function onb_company_web(): string
{
	$r = db_fetch_array(db_select("select web from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['web'] : '';
}

$errors = array();
$posted = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])) {
		if (function_exists('audit_log')) {
			audit_log('csrf', 'onboarding.php');
		}
		ob_end_clean();
		header('Location: onboarding.php?step=' . rawurlencode($step));
		exit;
	}
	$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
	if ($action === 'hide') {
		onb_set('onboarding_state', 'hidden');
		ob_end_clean();
		header('Location: dashboard.php');
		exit;
	}
	if (onb_get('onboarding_state') === 'new') {
		onb_set('onboarding_state', 'started');
	}
	foreach ($_POST as $k => $v) {
		if (is_string($v)) {
			$posted[$k] = trim($v);
		}
	}

	if ($action === 'next' && $step === 'profile') {
		$source = isset($posted['source']) && $posted['source'] === 'switch' ? 'switch' : 'new';
		$system = isset($posted['system'], $systems[$posted['system']]) ? $posted['system'] : '';
		onb_set('onboarding_source', $source === 'new' ? 'new' : ($system !== '' ? $system : 'switch'));
		onb_set('onboarding_role', isset($posted['role']) && $posted['role'] === 'accountant' ? 'accountant' : 'owner');
	}

	if ($action === 'next' && $step === 'company') {
		$defs = settings_section_definitions('company.data');
		$toSave = array();
		foreach ($companyFields as $name => $key) {
			if (!isset($posted[$name], $defs[$key]) || st_field_access($defs[$key]) !== 'write') {
				continue;
			}
			if ($key === 'company.data.name' && $posted[$name] === '') {
				$errors[$name] = $tx('6767|Firmanavn skal udfyldes');
				continue;
			}
			$res = st_posted_to_raw($defs[$key], $posted[$name], $posted);
			if ($res['error'] !== null) {
				$errors[$name] = st_txt($res['error']);
			} elseif ($res['raw'] !== SettingsService::raw($key)) {
				$toSave[$key] = $res['raw'];
			}
		}
		$web = isset($posted['web']) ? $posted['web'] : '';
		if (mb_strlen($web) > 60) {
			$errors['web'] = st_txt(6511);
		}
		$logoError = '';
		if (!$errors && isset($_FILES['logo']) && (int) $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
			$res = fe_logo_store($db_id, (string) $_FILES['logo']['tmp_name'], (int) $_FILES['logo']['size']);
			if (!$res['ok']) {
				$errors['logo'] = $res['error'] === 'store' ? $tx('6776|Logoet kunne ikke gemmes. Prøv igen.') : $tx('6765|Logoet skal være en png- eller jpg-fil på højst 5 MB.');
			}
		}
		if (!$errors) {
			transaktion('begin');
			foreach ($toSave as $key => $raw) {
				SettingsService::saveRaw($key, $raw);
			}
			if ($web !== onb_company_web()) {
				db_modify("update adresser set web = '" . db_escape_string($web) . "' where art = 'S'", __FILE__ . " linje " . __LINE__);
			}
			transaktion('commit');
			foreach ($toSave as $key => $raw) {
				if (!empty($defs[$key]['on_save'])) {
					settings_after_save($defs[$key], $raw);
				}
			}
			$colour = isset($posted['colour']) ? $posted['colour'] : '';
			if ($colour === 'custom') {
				$colour = isset($posted['colour_custom']) ? $posted['colour_custom'] : '';
			}
			$colour = strtolower(ltrim($colour, '#'));
			if ($colour !== '' && $colour !== onb_colour_get((int) $bruger_id) && onb_colour_set((int) $bruger_id, $colour)) {
				$_SESSION['onb_colour_changed'] = 1;
			}
		}
	}

	if ($action === 'next' && $step === 'fiscal') {
		if (!onb_fiscal_locked()) {
			$s = isset($posted['fy_start']) ? $posted['fy_start'] : '';
			$e = isset($posted['fy_end']) ? $posted['fy_end'] : '';
			if (!preg_match('/^(\d{4})-(\d{2})$/', $s, $ms) || !preg_match('/^(\d{4})-(\d{2})$/', $e, $me)) {
				$errors['fy'] = $tx('6766|Ugyldig dato');
			} else {
				$res = settings_fiscal_year_set_first((int) $ms[2], (int) $ms[1], (int) $me[2], (int) $me[1]);
				if ($res['error'] !== null) {
					$errors['fy'] = $res['error_args'] ? vsprintf(st_txt($res['error']), $res['error_args']) : st_txt($res['error']);
				}
			}
		}
		if (!$errors) {
			onb_vat_set(!isset($posted['vat_reg']) || $posted['vat_reg'] !== 'off', isset($posted['vat_period']) ? $posted['vat_period'] : '');
		}
	}

	if (($action === 'next' || $action === 'skip') && !$errors) {
		onb_mark($step, $action === 'next' ? 'done' : 'skipped');
		$nb = onb_neighbours($step);
		ob_end_clean();
		header('Location: onboarding.php?' . ($nb[1] !== '' ? 'step=' . rawurlencode($nb[1]) : 'closed=1'));
		exit;
	}
}

$closed = isset($_GET['closed']);
$colourChanged = !empty($_SESSION['onb_colour_changed']);
if ($closed) {
	unset($_SESSION['onb_colour_changed']);
}

$steps = onb_steps();
$userColour = onb_colour_get((int) $bruger_id);
$logoUrl = onb_logo_url($db_id);
$companyName = SettingsService::raw('company.data.name');
$railKeys = array();
foreach ($def as $k => $d) {
	if ($k !== 'welcome' && $k !== 'done') {
		$railKeys[] = $k;
	}
}
$nb = onb_neighbours($step);
$val = function (string $name, string $current) use ($posted) {
	return isset($posted[$name]) ? $posted[$name] : $current;
};
$err = function (string $name) use ($errors, $h) {
	return isset($errors[$name]) ? "<div class='onb-errors'>" . $h($errors[$name]) . "</div>" : '';
};
$icon = array(
	'new' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
	'switch' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h13l-3-3M20 17H7l3 3"/></svg>',
	'owner' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>',
	'accountant' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
	'wave' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v6a3 3 0 0 1-3 3H9a5 5 0 0 1-5-5V9a1.5 1.5 0 0 1 3 0v4"/><path d="M7 13V5a1.5 1.5 0 0 1 3 0v7"/><path d="M10 12V4a1.5 1.5 0 0 1 3 0v8"/><path d="M13 12V6a1.5 1.5 0 0 1 3 0v6"/><path d="M16 12v-1a1.5 1.5 0 0 1 3 0v1"/></svg>',
	'check' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>',
	'x' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
);

ob_end_clean();
?>
<!DOCTYPE html>
<html class="onb-page" lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php print $h($tx('6748|Kom godt i gang')); ?></title>
<link rel="stylesheet" href="../css/onboarding.css?v=1">
<script src="../javascript/jquery-1.8.0.min.js"></script>
</head>
<body>
<script>
function onbClose() {
	var reload = <?php print $colourChanged ? 'true' : 'false'; ?>;
	if (window.parent && window.parent !== window && window.parent.saldiOnboardingClose) {
		window.parent.saldiOnboardingClose(reload);
	} else {
		location.href = 'dashboard.php';
	}
}
function onbGo(url) {
	if (window.parent && window.parent !== window && window.parent.saldiOnboardingGo) {
		window.parent.saldiOnboardingGo(url);
	} else {
		location.href = url;
	}
}
</script>
<?php if ($closed) { ?>
<script>onbClose();</script>
</body></html>
<?php exit; } ?>
<div class="onb" style="--brand:#<?php print $h($userColour); ?>">
<form class="onb-modal" method="post" action="onboarding.php" enctype="multipart/form-data" id="onbForm">
	<input type="hidden" name="csrf_token" value="<?php print $h($csrfToken); ?>">
	<input type="hidden" name="step" value="<?php print $h($step); ?>">
	<aside class="onb-rail">
		<div class="onb-rail-logo"><?php
			if ($logoUrl !== '') {
				print "<img src='" . $h($logoUrl) . "' alt='' id='railLogo'>";
			}
			print "<span>" . $h($companyName !== '' ? $companyName : 'SALDI') . "</span><small>" . $h($tx('6699|Opsætning')) . "</small>";
		?></div>
		<?php
		foreach ($railKeys as $i => $k) {
			$st = isset($steps[$k]) ? $steps[$k] : '';
			$cls = $k === $step ? 'on' : ($st === 'done' ? 'done' : ($st === 'skipped' ? 'skip' : ''));
			$mark = ($st === 'done' && $k !== $step) ? $icon['check'] : (string) ($i + 1);
			$label = "<i>$mark</i>" . $h($tx($def[$k][0]));
			if ($def[$k][3]) {
				print "<a class='onb-rs $cls' href='onboarding.php?step=" . $h($k) . "'>$label</a>";
			} else {
				print "<span class='onb-rs $cls' title='" . $h($tx('6755|Kommer snart')) . "'>$label</span>";
			}
		}
		?>
		<div class="onb-rail-foot"><?php print $h($tx('6700|Du kan altid lukke og fortsætte senere. Intet går tabt.')); ?></div>
	</aside>
	<div class="onb-pane">
		<div class="onb-head">
			<span class="n"><?php
				$pos = array_search($step, $railKeys, true);
				if ($pos !== false) {
					print $h(sprintf($tx('6701|Trin %s af %s'), $pos + 1, count($railKeys)));
				}
			?></span>
			<button type="button" class="onb-x" title="<?php print $h($tx('6702|Luk og fortsæt senere')); ?>" onclick="onbClose()"><?php print $icon['x']; ?></button>
		</div>
		<div class="onb-body">
<?php
if ($step === 'welcome') {
	print "<div class='hero'><div class='mark'>{$icon['wave']}</div>";
	print "<h2>" . $h($tx('6707|Hej, og velkommen til Saldi')) . "</h2>";
	print "<p>" . $h($tx('6708|Lad os få dig godt og trygt i gang, så du får en god oplevelse. Det tager ca. 10 minutter, og du kan til enhver tid lukke og fortsætte senere.')) . "</p>";
	print "<div class='chips'><span class='chip'>🏢 " . $h($tx('6709|Dit firma og logo')) . "</span><span class='chip'>📅 " . $h($tx('6691|Regnskabsår og moms')) . "</span>"
		. "<span class='chip'>🧾 " . $h($tx('6693|Din faktura')) . "</span><span class='chip'>👥 " . $h($tx('6710|Dine kolleger')) . "</span></div></div>";
}

if ($step === 'profile') {
	$src = onb_get('onboarding_source');
	$source = $val('source', ($src === '' || $src === 'new') ? 'new' : 'switch');
	$system = $val('system', isset($systems[$src]) ? $src : '');
	$role = $val('role', onb_get('onboarding_role') === 'accountant' ? 'accountant' : 'owner');
	print "<h2>" . $h($tx('6687|Hvem er du?')) . "</h2><p class='lead'>" . $h($tx('6711|To hurtige spørgsmål, så guiden kan tilpasse sig dig.')) . "</p>";
	print "<label class='l'>" . $h($tx('6712|Din situation')) . "</label><div class='cards'>";
	foreach (array('new' => array('6713|Ny virksomhed', '6714|Vi starter fra en ren tavle med Saldis standardopsætning.'),
		'switch' => array('6715|Skifter fra et andet system', '6716|Vi hjælper med nummerserier og, i næste del, import af dine data.')) as $k => $t) {
		print "<label class='card'><input type='radio' name='source' value='$k'" . ($source === $k ? ' checked' : '') . " onchange='onbSource()'><div class='ic'>{$icon[$k]}</div><div><b>" . $h($tx($t[0])) . "</b><span>" . $h($tx($t[1])) . "</span></div></label>";
	}
	print "</div><div class='f' id='systemBox' style='max-width:340px" . ($source === 'switch' ? '' : ';display:none') . "'><label class='l'>" . $h($tx('6717|Hvilket system kommer du fra?')) . "</label><select name='system'><option value=''>–</option>";
	foreach ($systems as $k => $t) {
		print "<option value='" . $h($k) . "'" . ($system === $k ? ' selected' : '') . ">" . $h(strpos($t, '|') ? $tx($t) : $t) . "</option>";
	}
	print "</select></div>";
	print "<label class='l'>" . $h($tx('6718|Din rolle')) . "</label><div class='cards'>";
	foreach (array('owner' => array('6719|Jeg driver virksomheden', '6720|Vi foreslår at invitere din bogholder senere.'),
		'accountant' => array('6721|Jeg er bogholder/revisor for virksomheden', '6722|Vi foreslår at invitere ejeren senere.')) as $k => $t) {
		print "<label class='card'><input type='radio' name='role' value='$k'" . ($role === $k ? ' checked' : '') . "><div class='ic'>{$icon[$k]}</div><div><b>" . $h($tx($t[0])) . "</b><span>" . $h($tx($t[1])) . "</span></div></label>";
	}
	print "</div>";
}

if ($step === 'company') {
	$defs = settings_section_definitions('company.data');
	print "<h2>" . $h($tx('6723|Din virksomhed og dit udtryk')) . "</h2><p class='lead'>" . $h($tx('6724|Slå dit CVR-nummer op, så udfylder vi det meste. Du kan rette alt.')) . "</p>";
	print "<div class='g2'><div>";
	$field = function (string $name, string $type = 'text') use ($companyFields, $defs, $val, $err, $h) {
		$key = $companyFields[$name];
		$d = $defs[$key];
		$ro = st_field_access($d) !== 'write' ? ' readonly' : '';
		$max = !empty($d['maxlength']) ? " maxlength='" . (int) $d['maxlength'] . "'" : '';
		return "<div class='f'><label class='l'>" . $h(st_txt($d['label'])) . "</label><input type='$type' name='$name' value='" . $h($val($name, SettingsService::raw($key))) . "'$max$ro>" . $err($name) . "</div>";
	};
	$cvr = $val('cvrnr', SettingsService::raw('company.data.cvr'));
	print "<div class='f'><label class='l'>" . $h(st_txt($defs['company.data.cvr']['label'])) . "</label><div style='display:flex;gap:8px'><input type='text' name='cvrnr' value='" . $h($cvr) . "' maxlength='15' placeholder='12345678'>"
		. "<button type='button' onclick='onbCvr()'>" . $h($tx('6725|Slå op')) . "</button></div><div class='hint'>" . $h($tx('6726|Henter navn og adresse fra CVR-registret.')) . "</div>" . $err('cvrnr') . "</div>";
	print $field('firmanavn') . $field('addr1') . $field('addr2');
	print "<div class='g2' style='gap:10px'>" . $field('postnr') . $field('bynavn') . "</div>";
	print "<div class='g2' style='gap:10px'>" . $field('tlf') . $field('email', 'email') . "</div>";
	print "<div class='f'><label class='l'>" . $h($tx('367|Hjemmeside')) . "</label><input type='text' name='web' maxlength='60' value='" . $h($val('web', onb_company_web())) . "'>" . $err('web') . "</div>";
	print "</div><div>";
	print "<div class='f'><label class='l'>Logo</label><label class='logo-drop'><img id='logoPrev' src='" . $h($logoUrl) . "' alt=''" . ($logoUrl === '' ? " style='display:none'" : '') . ">"
		. $h($tx('6727|Træk dit logo hertil eller klik for at vælge')) . "<br><span style='font-size:11px;opacity:.7'>" . $h($tx('6728|png eller jpg')) . "</span>"
		. "<input type='file' name='logo' accept='image/png,image/jpeg' style='display:none' onchange='onbLogo(this)'></label>" . $err('logo') . "</div>";
	$colour = $val('colour', $userColour);
	$preset = in_array($colour, onb_colours(), true);
	print "<div class='f'><label class='l'>" . $h($tx('6729|Farve')) . "</label><div class='swatches'>";
	foreach (onb_colours() as $c) {
		print "<label class='sw' style='background:#$c'><input type='radio' name='colour' value='$c'" . ($colour === $c ? ' checked' : '') . " onchange='onbBrand(\"#$c\")'></label>";
	}
	print "<label style='margin:0 0 0 4px;font-weight:500;color:var(--onb-mut);cursor:pointer'><input type='radio' name='colour' value='custom' id='colourCustom' style='display:none'" . ($preset ? '' : ' checked') . ">"
		. $h($tx('6730|Egen farve')) . " <input type='color' name='colour_custom' value='#" . $h(preg_match('/^[0-9a-f]{6}$/', ltrim($colour, '#')) ? ltrim($colour, '#') : $userColour) . "' oninput='document.getElementById(\"colourCustom\").checked=true;onbBrand(this.value)' style='width:26px;height:26px;border:none;background:none;vertical-align:middle;cursor:pointer;padding:0'></label>";
	print "</div><div class='hint'>" . $h($tx('6731|Farven bruges i menuen og på knapperne. Du ser det med det samme.')) . "</div></div>";
	print "<div class='note'>" . $h($tx('6732|Logo og farve kan altid ændres under Indstillinger.')) . "</div>";
	print "</div></div>";
}

if ($step === 'fiscal') {
	$locked = onb_fiscal_locked();
	$fy = onb_first_year();
	$vat = onb_vat_get();
	$vatReg = $val('vat_reg', $vat['registered'] ? 'on' : 'off');
	$vatPeriod = $val('vat_period', $vat['period']);
	print "<h2>" . $h($tx('6691|Regnskabsår og moms')) . "</h2><p class='lead'>" . $h($tx('6733|De to ting, der er besværlige at ændre, når du først har bogført. Tag dem nu, hvis du kan.')) . "</p>";
	print "<div class='g2'><div>";
	if ($locked) {
		print "<div class='note'>" . $h($tx('6746|Regnskabet har allerede et regnskabsår med posteringer. Det ændres under Regnskabsår.')) . "<br><br>"
			. "<button type='button' onclick=\"onbGo('../systemdata/settingsSection.php?s=company.fiscal_years')\">" . $h($tx('6747|Åbn Regnskabsår')) . " &rarr;</button></div>";
	} else {
		print "<div class='g2' style='gap:10px'><div class='f'><label class='l'>" . $h($tx('6734|Regnskabsåret starter')) . "</label><input type='month' name='fy_start' id='fyStart' value='" . $h($val('fy_start', $fy[0])) . "' onchange='onbFy()'></div>"
			. "<div class='f'><label class='l'>" . $h($tx('6735|Regnskabsåret slutter')) . "</label><input type='month' name='fy_end' id='fyEnd' value='" . $h($val('fy_end', $fy[1])) . "' onchange='this.dataset.touched=1'></div></div>" . $err('fy');
	}
	print "<div class='f'><label class='l'>" . $h($tx('6736|Er virksomheden momsregistreret?')) . "</label><div class='seg'>"
		. "<label><input type='radio' name='vat_reg' value='on'" . ($vatReg !== 'off' ? ' checked' : '') . " onchange='onbVat()'>" . $h($tx('6737|Ja')) . "</label>"
		. "<label><input type='radio' name='vat_reg' value='off'" . ($vatReg === 'off' ? ' checked' : '') . " onchange='onbVat()'>" . $h($tx('6738|Nej')) . "</label></div></div>";
	print "<div class='f' id='vatBox'" . ($vatReg === 'off' ? " style='display:none'" : '') . "><label class='l'>" . $h($tx('6739|Momsperiode')) . "</label><select name='vat_period'>";
	foreach (array('M' => '6740|Månedlig', 'K' => '6741|Kvartalsvis', 'H' => '6742|Halvårlig') as $k => $t) {
		print "<option value='$k'" . ($vatPeriod === $k ? ' selected' : '') . ">" . $h($tx($t)) . "</option>";
	}
	print "</select><div class='hint'>" . $h($tx('6743|Står på din registreringsbekræftelse fra Skattestyrelsen.')) . "</div></div>";
	print "</div><div><div class='note warn' style='margin-top:22px'><b>" . $h($tx('6744|Hvorfor nu?')) . "</b><br>"
		. $h($tx('6745|Regnskabsår og momsperiode styrer, hvordan alle posteringer grupperes. Springer du over, markerer tjeklisten trinnet som Vigtigt.')) . "</div></div></div>";
}
?>
		</div>
		<div class="onb-foot">
			<input type="hidden" name="action" id="onbAction" value="next">
<?php
if ($step === 'welcome') {
	print "<span class='sp'></span><button type='button' class='ghost' onclick='onbClose()'>" . $h($tx('6702|Luk og fortsæt senere')) . "</button>"
		. "<button type='submit' class='pri'>" . $h($tx('6703|Kom i gang')) . " &rarr;</button>";
} else {
	$back = $nb[0] !== '' && $nb[0] !== 'welcome' ? "onclick=\"location.href='onboarding.php?step=" . $h($nb[0]) . "'\"" : 'disabled';
	print "<button type='button' class='ghost' $back>&larr; " . $h($tx('6704|Tilbage')) . "</button><span class='sp'></span>"
		. "<button type='submit' class='ghost' formnovalidate onclick=\"document.getElementById('onbAction').value='skip'\">" . $h($tx('6705|Spring over')) . "</button>"
		. "<button type='submit' class='pri' onclick=\"document.getElementById('onbAction').value='next'\">" . $h($tx('6706|Næste')) . " &rarr;</button>";
}
?>
		</div>
	</div>
</form>
</div>
<script>
function onbBrand(c) { document.querySelector('.onb').style.setProperty('--brand', c); }
function onbSource() {
	var sw = document.querySelector('input[name=source][value=switch]');
	document.getElementById('systemBox').style.display = sw && sw.checked ? '' : 'none';
}
function onbVat() {
	var off = document.querySelector('input[name=vat_reg][value=off]');
	document.getElementById('vatBox').style.display = off && off.checked ? 'none' : '';
}
function onbFy() {
	var s = document.getElementById('fyStart'), e = document.getElementById('fyEnd');
	var m = /^(\d{4})-(\d{2})$/.exec(s.value);
	if (!m || e.dataset.touched) return;
	var y = parseInt(m[1], 10), mo = parseInt(m[2], 10) - 1;
	if (mo === 0) { mo = 12; } else { y += 1; }
	e.value = y + '-' + (mo < 10 ? '0' : '') + mo;
}
function onbLogo(input) {
	if (!input.files || !input.files[0]) return;
	var r = new FileReader();
	r.onload = function (ev) {
		var img = document.getElementById('logoPrev');
		img.src = ev.target.result; img.style.display = '';
		var rail = document.getElementById('railLogo');
		if (rail) rail.src = ev.target.result;
	};
	r.readAsDataURL(input.files[0]);
}
<?php if ($step === 'company') { ?>
var cvrLookupProxy = '../sager/cvrLookupProxy.php';
var cvrAutoFelter = ['cvrnr'];
var cvrTekster = <?php print json_encode(array(
	'fejl' => findtekst('3374|CVR-opslaget kunne ikke gennemføres. Udfyld felterne manuelt.', $sprog_id),
	'QUOTA_EXCEEDED' => findtekst('3375|Kvoten for CVR-opslag er opbrugt.', $sprog_id),
	'NOT_FOUND' => findtekst('3376|CVR-nummeret blev ikke fundet.', $sprog_id),
	'INVALID_VAT' => findtekst('3377|CVR-nummeret er ikke gyldigt.', $sprog_id),
	'soeger' => findtekst('3378|Søger...', $sprog_id),
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
function onbCvr() {
	var f = $('[name=cvrnr]');
	var v = (f.val() || '').replace(/\D/g, '');
	if (v.length !== 8) { cvrFejl(f, 'INVALID_VAT'); return; }
	f.val(v);
	cvrSidste = null;
	cvrOpslag(f, v, 'vat');
}
<?php } ?>
</script>
<?php if ($step === 'company') { ?>
<script src="../javascript/cvrapiopslag.js"></script>
<?php } ?>
</body>
</html>
