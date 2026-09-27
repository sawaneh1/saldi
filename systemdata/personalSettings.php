<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/personalSettings.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh Consolidated personal settings page (language, colours, popup/expiry
//                  choices, contact details, 2FA, password). Replaces the "Personlige valg"
//                  section in diverse.php and the self-service page brugerdata.php.
//                  Controller (POST -> save -> redirect) above, view below.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $db
 * @var string $db_encode
 * @var int    $sprog_id
 * @var mixed  $revisor
 * @var string $regnskab
 * @var mixed  $header
 */

@session_start();
$s_id = session_id();
ob_start();

$title = "Personlige indstillinger";
$css = "../css/personalSettings.css";
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");

$contextQuery = personal_settings_context_query($_GET, $_POST);
$selfUrl = 'personalSettings.php' . ($contextQuery !== '' ? '?' . $contextQuery : '');

// An auditor / master-admin session has bruger_id -1 and no row in brugere, but its
// colours, popup choice and language are still stored per session user (grupper USET
// kodenr -1, settings user_id -1) - exactly as the old "Personlige valg" did.
$isRevisor = (bool) $revisor;
$canEdit = ((int) $bruger_id !== 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
	$result = personal_settings_save($_POST, (int) $bruger_id, (string) $brugernavn, (string) $db, $isRevisor);
	if (!empty($result['language'])) {
		// The session row lives in the master database: back to it for the last write.
		include(__DIR__ . "/../includes/connect.php");
		db_modify("update online set language_id = '" . (int) $result['language'] . "' where session_id = '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
		unset($result['language']);
	}
	ob_end_clean();
	header('Location: ' . $selfUrl . ($contextQuery !== '' ? '&' : '?') . http_build_query($result));
	exit;
}

$flash = personal_settings_flash($_GET, (int) $sprog_id);
$data = $canEdit ? personal_settings_load((int) $bruger_id, $isRevisor, (string) $brugernavn) : null;
personal_settings_view($data, $flash, $selfUrl, (int) $sprog_id, (string) $db_encode);

// ---------------------------------------------------------------- controller helpers

/**
 * Carry the shell/popup context flags across the redirect so the page keeps
 * behaving as an in-frame or popup page after saving.
 */
function personal_settings_context_query(array $get, array $post): string
{
	$parts = array();
	if (!empty($get['inframe']) || !empty($post['inframe'])) {
		$parts[] = 'inframe=1';
	}
	if (!empty($get['popup']) || !empty($post['popup_window'])) {
		$parts[] = 'popup=1';
	}
	return implode('&', $parts);
}

/**
 * Six hex digits without '#', or the default when the input is not a colour.
 */
function personal_settings_hex($value, string $default): string
{
	$value = strtolower(ltrim(trim((string) $value), '#'));
	return (strlen($value) === 6 && ctype_xdigit($value)) ? $value : $default;
}

/**
 * Persist everything the form posted. Writes go to the same places the old pages
 * wrote (grupper USET, settings colors/lager, brugere), so nothing else changes.
 *
 * @return array{saved: int, language?: int, pw?: string, tfa?: string} Query parameters for the redirect.
 */
function personal_settings_save(array $post, int $brugerId, string $brugernavn, string $db, bool $isRevisor): array
{
	$out = array('saved' => 1);
	$reloadShell = false;
	$current = personal_settings_load($brugerId, $isRevisor, $brugernavn);

	// Language: on the user (next login) and, by the caller, on the session row.
	$lang = isset($post['language_id']) ? (int) $post['language_id'] : 0;
	if ($lang >= 1 && $lang <= 3 && $lang !== $current['language']) {
		if (!$isRevisor) {
			db_modify("update brugere set language_id = $lang where id = $brugerId", __FILE__ . " linje " . __LINE__);
		}
		setcookie('languageId', (string) $lang, time() + (10 * 365 * 24 * 60 * 60), '/');
		$out['language'] = $lang;
		$reloadShell = true;
	}

	// Colours + popup preference.
	$colors = array(
		'bgcolor'        => personal_settings_hex(isset($post['bgcolor']) ? $post['bgcolor'] : '', 'eeeef0'),
		'fgcolor'        => personal_settings_hex(isset($post['fgcolor']) ? $post['fgcolor'] : '', 'eeeef0'),
		'buttonColor'    => personal_settings_hex(isset($post['buttonColor']) ? $post['buttonColor'] : '', '114691'),
		'buttonTxtColor' => personal_settings_hex(isset($post['buttonTxtColor']) ? $post['buttonTxtColor'] : '', 'ffffff'),
	);
	$popup = !empty($post['popup']) ? 'on' : '';
	if ($colors !== $current['colors'] || ($popup !== '') !== $current['popup']) {
		$reloadShell = true;
	}
	$descriptions = array(
		'bgcolor'        => 'Background color for user settings',
		'fgcolor'        => 'Nuance color for user settings',
		'buttonColor'    => 'Background color for user settings',
		'buttonTxtColor' => 'Button color for user settings',
	);
	foreach ($colors as $name => $value) {
		update_settings_value($name, 'colors', $value, $descriptions[$name], $brugerId);
	}
	$r = db_fetch_array(db_select("select id from grupper where art = 'USET' and kodenr = '$brugerId'", __FILE__ . " linje " . __LINE__));
	if ($r && $r['id']) {
		$qtxt = "update grupper set box2 = '$popup', box4 = '#$colors[bgcolor]', box5 = '#$colors[fgcolor]' where id = " . (int) $r['id'];
	} else {
		$jsvars = "statusbar=0,menubar=0,titlebar=0,toolbar=0,scrollbars=1,resizable=1,dependent=1";
		$qtxt = "insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) values ";
		$qtxt .= "('Personlige valg', '$brugerId', 'USET', '$jsvars', '$popup', 'S', '#$colors[bgcolor]', '#$colors[fgcolor]')";
	}
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);

	// Expiry warning (per user, lager group) - carried over from brugerdata.php.
	if (isset($post['due_date_warning_days']) && $post['due_date_warning_days'] !== '') {
		update_settings_value('due_date_warning_days', 'lager', max(1, intval($post['due_date_warning_days'])), 'Days before expiry to warn', $brugerId);
	}

	// Account row (contact details, 2FA, password) exists only for the company's own users.
	if (!$isRevisor) {
		personal_settings_save_account($post, $brugerId, $brugernavn, $db, $out);
	}

	if ($reloadShell) {
		// index/main.php polls this cookie and reloads itself, so sidebar colours and texts follow.
		setcookie('refresh_opener', 'true', time() + 30, '/');
	}
	return $out;
}

/**
 * Contact details, two-factor login (needs a phone number or an email to send the
 * code to) and password change - the parts that live on the user's brugere row.
 *
 * @param array<string, mixed> $out Redirect parameters; 'tfa' / 'pw' are added here.
 */
function personal_settings_save_account(array $post, int $brugerId, string $brugernavn, string $db, array &$out): void
{
	$email = db_escape_string(trim(isset($post['email']) ? (string) $post['email'] : ''));
	$tlf = db_escape_string(trim(isset($post['tlf']) ? (string) $post['tlf'] : ''));
	$twofactor = !empty($post['twofactor']);
	if ($twofactor && $email === '' && $tlf === '') {
		$twofactor = false;
		$out['tfa'] = 'missing';
	}
	db_modify("update brugere set email = '$email', tlf = '$tlf', twofactor = '" . ($twofactor ? 't' : 'f') . "' where id = $brugerId", __FILE__ . " linje " . __LINE__);

	// Password: only when the user typed something in the password fields.
	$old = isset($post['glkode']) ? (string) $post['glkode'] : '';
	$new1 = isset($post['nykode1']) ? (string) $post['nykode1'] : '';
	$new2 = isset($post['nykode2']) ? (string) $post['nykode2'] : '';
	if ($old === '' && $new1 === '' && $new2 === '') {
		return;
	}
	if ($brugernavn === 'test' && $db === 'test') {
		$out['pw'] = 'demo';
	} elseif ($new1 === '' || $new1 !== $new2) {
		$out['pw'] = 'mismatch';
	} else {
		$r = db_fetch_array(db_select("select kode from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
		$stored = isset($r['kode']) ? (string) $r['kode'] : '';
		if ($stored !== '' && ($stored === md5($old) || $stored === saldikrypt($brugerId, $old))) {
			$hash = db_escape_string(saldikrypt($brugerId, $new1));
			db_modify("update brugere set kode = '$hash' where id = $brugerId", __FILE__ . " linje " . __LINE__);
			$out['pw'] = 'changed';
		} else {
			$out['pw'] = 'wrong';
		}
	}
}

/**
 * Current values for the form.
 *
 * @return array{
 *   name: string, username: string, email: string, tlf: string, twofactor: bool, language: int,
 *   languages: array<int, string>, colors: array{bgcolor: string, fgcolor: string, buttonColor: string, buttonTxtColor: string},
 *   popup: bool, warnDays: int
 * }
 */
function personal_settings_load(int $brugerId, bool $isRevisor, string $brugernavn): array
{
	global $sprog_id;

	$u = array();
	if (!$isRevisor) {
		// select * : keep working on company databases that predate the email/tlf/twofactor columns.
		$u = db_fetch_array(db_select("select * from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
	}
	$u = ($u ?: array()) + array('brugernavn' => $brugernavn, 'email' => '', 'tlf' => '', 'twofactor' => 'f', 'language_id' => 1, 'ansat_id' => 0);
	$name = trim((string) $u['brugernavn']);
	$email = trim((string) $u['email']);
	if ((int) $u['ansat_id'] > 0) {
		$a = db_fetch_array(db_select("select navn, email from ansatte where id = " . (int) $u['ansat_id'], __FILE__ . " linje " . __LINE__));
		if ($a && trim((string) $a['navn']) !== '') {
			$name = trim((string) $a['navn']);
		}
		if ($a && $email === '') {
			$email = trim((string) $a['email']);
		}
	}
	$twofactor = ($u['twofactor'] === 't' || $u['twofactor'] === true || $u['twofactor'] === '1' || $u['twofactor'] === 1);

	$uset = db_fetch_array(db_select("select box2, box4, box5 from grupper where art = 'USET' and kodenr = '$brugerId'", __FILE__ . " linje " . __LINE__));
	$colors = array(
		'bgcolor'        => personal_settings_hex(get_settings_value('bgcolor', 'colors', $uset ? $uset['box4'] : '', $brugerId), 'eeeef0'),
		'fgcolor'        => personal_settings_hex(get_settings_value('fgcolor', 'colors', $uset ? $uset['box5'] : '', $brugerId), 'eeeef0'),
		'buttonColor'    => personal_settings_hex(get_settings_value('buttonColor', 'colors', '', $brugerId), '114691'),
		'buttonTxtColor' => personal_settings_hex(get_settings_value('buttonTxtColor', 'colors', '', $brugerId), 'ffffff'),
	);

	$languages = array();
	$fp = @fopen(__DIR__ . "/../importfiler/tekster.csv", "r");
	if ($fp) {
		$header = explode("\t", trim((string) fgets($fp)));
		fclose($fp);
		for ($i = 1; $i < count($header) && $i <= 3; $i++) {
			$languages[$i] = trim($header[$i]);
		}
	}
	if (!$languages) {
		$languages = array(1 => 'Dansk', 2 => 'English', 3 => 'Norsk');
	}

	return array(
		'name'      => $name,
		'username'  => trim((string) $u['brugernavn']),
		'email'     => $email,
		'tlf'       => trim((string) $u['tlf']),
		'twofactor' => $twofactor,
		'language'  => max(1, (int) $sprog_id ?: (int) $u['language_id']),
		'languages' => $languages,
		'revisor'   => $isRevisor,
		'colors'    => $colors,
		'popup'     => ($uset && trim((string) $uset['box2']) !== ''),
		'warnDays'  => get_due_date_warning_days($brugerId),
	);
}

/**
 * Messages for the banner row, from the redirect's query parameters.
 *
 * @return array<int, array{type: string, text: string}>
 */
function personal_settings_flash(array $get, int $sprogId): array
{
	$flash = array();
	$pw = isset($get['pw']) ? (string) $get['pw'] : '';
	if ($pw === 'changed') {
		$flash[] = array('type' => 'ok', 'text' => findtekst('5518|Adgangskoden er ændret', $sprogId));
	} elseif ($pw === 'wrong') {
		$flash[] = array('type' => 'err', 'text' => findtekst('5519|Den nuværende adgangskode er forkert', $sprogId));
	} elseif ($pw === 'mismatch') {
		$flash[] = array('type' => 'err', 'text' => findtekst('5520|De to nye adgangskoder er ikke ens', $sprogId));
	} elseif ($pw === 'demo') {
		$flash[] = array('type' => 'warn', 'text' => findtekst('5528|Adgangskoden kan ikke ændres i demoversionen', $sprogId));
	}
	if (isset($get['tfa']) && $get['tfa'] === 'missing') {
		$flash[] = array('type' => 'warn', 'text' => findtekst('5516|Tofaktor-login kræver et telefonnummer eller en e-mail', $sprogId));
	}
	if (!empty($get['saved'])) {
		$flash[] = array('type' => 'ok', 'text' => findtekst('5517|Dine indstillinger er gemt', $sprogId));
	}
	return $flash;
}

// ---------------------------------------------------------------- view

function personal_settings_view(?array $d, array $flash, string $selfUrl, int $sprogId, string $dbEncode): void
{
	$charset = ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1';
	$h = function ($s) use ($charset): string {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$t = function (string $text) use ($sprogId, $h): string {
		return $h(findtekst($text, $sprogId));
	};
	// Inside the shell the page is reached from the user chip, not from a workflow, and
	// the nav stack may hold the dashboard's last AJAX request (weekly_graph_data.php),
	// so Close goes to the dashboard there. Popup/legacy contexts keep the back helper.
	if (strpos($selfUrl, 'inframe=1') !== false) {
		$backUrl = '../index/dashboard.php';
	} else {
		$backUrl = function_exists('nav_back_url') ? nav_back_url() : '../index/dashboard.php';
	}
	$flashIcons = array('ok' => 'bx-check-circle', 'warn' => 'bx-error', 'err' => 'bx-x-circle');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('5500|Personlige indstillinger', $sprogId), 'UTF-8', $charset)) ?>;</script>
<div class="ps-page">
  <header class="ps-head">
    <div>
      <h1><i class='bx bx-cog'></i><?= $t('5500|Personlige indstillinger') ?></h1>
      <p class="ps-sub"><?= $t('5522|Gælder kun for dig, ikke for andre brugere i regnskabet') ?></p>
    </div>
    <a class="ps-btn ps-btn-ghost" href="<?= $h($backUrl) ?>"><i class='bx bx-x'></i><?= $t('2172|Luk') ?></a>
  </header>

  <?php foreach ($flash as $f) { ?>
  <div class="ps-flash ps-flash-<?= $h($f['type']) ?>"><i class='bx <?= $flashIcons[$f['type']] ?>'></i><span><?= $h($f['text']) ?></span></div>
  <?php } ?>

  <?php if ($d === null) { ?>
  <div class="ps-notice"><i class='bx bx-info-circle'></i> <?= $t('5533|Personlige indstillinger findes kun for regnskabets egne brugere. Du er logget ind som revisor/administrator udefra.') ?></div>
</div>
	<?php return; } ?>

  <form method="post" action="<?= $h($selfUrl) ?>" id="ps-form" autocomplete="off">
    <?php if (strpos($selfUrl, 'popup=1') !== false) { ?><input type="hidden" name="popup_window" value="1"><?php } ?>

    <section class="ps-card">
      <h2><i class='bx bx-user'></i><?= $t('5521|Konto') ?></h2>
      <div class="ps-grid">
        <div class="ps-field">
          <label><?= $t('5531|Navn') ?></label>
          <input class="ps-input" type="text" value="<?= $h($d['name']) ?>" readonly>
        </div>
        <div class="ps-field">
          <label><?= $t('5530|Brugernavn') ?></label>
          <input class="ps-input" type="text" value="<?= $h($d['username']) ?>" readonly>
        </div>
        <?php if (!$d['revisor']) { ?>
        <div class="ps-field">
          <label for="ps-email"><?= $t('52|E-mail') ?></label>
          <input class="ps-input" type="email" id="ps-email" name="email" value="<?= $h($d['email']) ?>">
          <span class="ps-help"><?= $t('5523|Bruges til tofaktor-koder') ?></span>
        </div>
        <div class="ps-field">
          <label for="ps-tlf"><?= $t('37|Telefon') ?></label>
          <input class="ps-input" type="tel" id="ps-tlf" name="tlf" value="<?= $h($d['tlf']) ?>">
          <span class="ps-help"><?= $t('5523|Bruges til tofaktor-koder') ?></span>
        </div>
        <?php } else { ?>
        <div class="ps-field ps-field-full">
          <span class="ps-help"><?= $t('5535|Kontaktoplysninger, tofaktor-login og adgangskode hører til regnskabets egne brugere og kan ikke ændres her, da du er logget ind som revisor/administrator udefra.') ?></span>
        </div>
        <?php } ?>
        <div class="ps-field">
          <label for="ps-language"><?= $t('801|Sprog') ?></label>
          <select class="ps-select" id="ps-language" name="language_id">
            <?php foreach ($d['languages'] as $id => $label) { ?>
            <option value="<?= (int) $id ?>"<?= ($id === $d['language']) ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
    </section>

    <section class="ps-card">
      <h2><i class='bx bx-palette'></i><?= $t('5508|Udseende') ?></h2>
      <p class="ps-card-help"><?= $t('5526|Farverne bruges i menuen og på knapper') ?></p>
      <div class="ps-grid">
        <?php
        $colorFields = array(
        	array('name' => 'buttonColor',    'label' => '5509|Knapfarve',            'help' => ''),
        	array('name' => 'buttonTxtColor', 'label' => '5510|Tekstfarve på knapper', 'help' => ''),
        	array('name' => 'bgcolor',        'label' => '317|Baggrundsfarve',        'help' => ''),
        	array('name' => 'fgcolor',        'label' => '415|Fremhævning',           'help' => '416|Fremhæver eksempelvis ordrer med den angivne farvenuance'),
        );
        foreach ($colorFields as $cf) {
        	$val = $d['colors'][$cf['name']];
        ?>
        <div class="ps-field">
          <label for="ps-<?= $cf['name'] ?>-text"><?= $t($cf['label']) ?></label>
          <div class="ps-color">
            <input type="color" id="ps-<?= $cf['name'] ?>-color" value="#<?= $h($val) ?>" data-target="ps-<?= $cf['name'] ?>-text">
            <span class="ps-hash">#</span>
            <input class="ps-input" type="text" id="ps-<?= $cf['name'] ?>-text" name="<?= $cf['name'] ?>" value="<?= $h($val) ?>" maxlength="7" pattern="#?[0-9a-fA-F]{6}" data-color="ps-<?= $cf['name'] ?>-color">
          </div>
          <?php if ($cf['help'] !== '') { ?><span class="ps-help"><?= $t($cf['help']) ?></span><?php } ?>
        </div>
        <?php } ?>
        <div class="ps-field ps-field-full">
          <div class="ps-preview">
            <span class="ps-preview-label"><?= $t('5527|Eksempel') ?></span>
            <span class="ps-preview-menu" id="ps-preview-menu"><i class='bx bx-coin-stack'></i><?= $t('600|Finans') ?></span>
            <button type="button" class="ps-preview-btn" id="ps-preview-btn"><?= $t('3|Gem') ?></button>
          </div>
        </div>
      </div>
    </section>

    <section class="ps-card">
      <h2><i class='bx bx-window-alt'></i><?= $t('5524|Vinduer og advarsler') ?></h2>
      <div class="ps-grid">
        <div class="ps-field ps-field-full">
          <label class="ps-check">
            <input type="checkbox" name="popup" value="on"<?= $d['popup'] ? ' checked' : '' ?>>
            <span class="ps-check-txt"><b><?= $t('208|Anvend popup-vinduer') ?></b><span><?= $t('207|Hvis du afmærker dette felt vil SALDI virke i pop op-vinduer') ?></span></span>
          </label>
        </div>
        <div class="ps-field">
          <label for="ps-warn"><?= $t('5006|Advar om udløb (dage før)') ?></label>
          <input class="ps-input ps-input-short" type="number" min="1" id="ps-warn" name="due_date_warning_days" value="<?= (int) $d['warnDays'] ?>">
        </div>
      </div>
    </section>

    <?php if (!$d['revisor']) { ?>
    <section class="ps-card">
      <h2><i class='bx bx-shield-quarter'></i><?= $t('5511|Sikkerhed') ?></h2>
      <div class="ps-grid">
        <div class="ps-field ps-field-full">
          <label class="ps-check">
            <input type="checkbox" name="twofactor" value="on" id="ps-twofactor"<?= $d['twofactor'] ? ' checked' : '' ?>>
            <span class="ps-check-txt"><b><?= $t('5515|Tofaktor-login (kode via SMS eller e-mail)') ?></b><span><?= $t('5516|Tofaktor-login kræver et telefonnummer eller en e-mail') ?></span></span>
          </label>
        </div>
        <div class="ps-field ps-field-full">
          <span class="ps-help"><?= $t('5525|Lad felterne stå tomme for at beholde din adgangskode') ?></span>
        </div>
        <div class="ps-field">
          <label for="ps-glkode"><?= $t('5512|Nuværende adgangskode') ?></label>
          <input class="ps-input" type="password" id="ps-glkode" name="glkode" autocomplete="current-password">
        </div>
        <div class="ps-field"></div>
        <div class="ps-field">
          <label for="ps-nykode1"><?= $t('5513|Ny adgangskode') ?></label>
          <input class="ps-input" type="password" id="ps-nykode1" name="nykode1" autocomplete="new-password">
        </div>
        <div class="ps-field">
          <label for="ps-nykode2"><?= $t('5514|Bekræft ny adgangskode') ?></label>
          <input class="ps-input" type="password" id="ps-nykode2" name="nykode2" autocomplete="new-password">
        </div>
      </div>
    </section>
    <?php } ?>

    <footer class="ps-save">
      <span class="ps-dirty" id="ps-dirty"><i class='bx bx-edit-alt'></i> <?= $t('5532|Du har ændringer, der ikke er gemt') ?></span>
      <a class="ps-btn ps-btn-ghost" href="<?= $h($backUrl) ?>"><?= $t('2172|Luk') ?></a>
      <button class="ps-btn ps-btn-primary" type="submit" id="ps-submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
    </footer>
  </form>
</div>
<script>
(function () {
	var form = document.getElementById('ps-form');
	var dirty = document.getElementById('ps-dirty');

	function hex(id) {
		var v = (document.getElementById(id).value || '').replace('#', '').toLowerCase();
		return /^[0-9a-f]{6}$/.test(v) ? '#' + v : null;
	}
	function preview() {
		var btn = document.getElementById('ps-preview-btn');
		var menu = document.getElementById('ps-preview-menu');
		var bc = hex('ps-buttonColor-text') || '#114691';
		var tc = hex('ps-buttonTxtColor-text') || '#ffffff';
		btn.style.background = bc; btn.style.color = tc;
		menu.style.background = bc; menu.style.color = tc;
		var bg = hex('ps-bgcolor-text');
		document.querySelector('.ps-preview').style.background = bg || 'transparent';
	}
	// Dirty = the form differs from what was loaded, so browser autofill or a
	// restored field cannot flag unsaved changes that the user never made.
	var initial = snapshot();
	function snapshot() {
		return Array.prototype.map.call(form.elements, function (el) {
			if (!el.name) { return ''; }
			return el.name + '=' + (el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value);
		}).join('&');
	}
	function markDirty() {
		var changed = snapshot() !== initial;
		window.docChange = changed;
		dirty.classList.toggle('on', changed);
	}

	Array.prototype.forEach.call(document.querySelectorAll('input[type="color"][data-target]'), function (picker) {
		picker.addEventListener('input', function () {
			document.getElementById(picker.dataset.target).value = picker.value.substring(1);
			markDirty(); preview();
		});
	});
	Array.prototype.forEach.call(document.querySelectorAll('input[data-color]'), function (text) {
		text.addEventListener('input', function () {
			var v = text.value.replace('#', '');
			if (/^[0-9a-fA-F]{6}$/.test(v)) { document.getElementById(text.dataset.color).value = '#' + v.toLowerCase(); }
			preview();
		});
		text.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
	});
	form.addEventListener('input', markDirty);
	form.addEventListener('change', markDirty);
	form.addEventListener('submit', function () {
		window.docChange = false;
		var b = document.getElementById('ps-submit');
		b.disabled = true;
	});
	preview();
})();
</script>
	<?php
}
