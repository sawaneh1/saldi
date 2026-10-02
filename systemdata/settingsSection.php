<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/settingsSection.php --- lap 5.0.0 --- 2026.09.29 ---
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
// 20260929 Sawaneh Settings redesign phase 4a: a settings section generated from the registry
//                  (spec §7.3, §8). Controller: POST with CSRF -> validation -> conflict check ->
//                  transaction -> audit -> redirect. View below. No hand-written form.
// 20261002 Sawaneh Phase 4b: actions run through includes/settings/actions.php, sections gated by a module, on_save follow-ups.
// 20261002 Sawaneh Hand-over 2 Oct (A2, §8.0): a heading above each card, plain tab list, history as its own column, save bar
//                  only while something is unsaved, no Back button or in-page trail (the shell's topbar has the breadcrumb).

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $sprog_id
 * @var int    $bruger_id
 * @var string $db_encode
 * @var string $regnskab
 */

@session_start();
$s_id = session_id();
ob_start();

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$title = "Indstillinger";
$css = "../css/unified-components.css?v=20261002";
$modulnr = 1;
$permission_key = 'system.indstillinger';
$permission_post_read = false;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");
include_once(__DIR__ . "/../includes/settings/actions.php");

$sections = getSettingsSections();
$sectionId = isset($_GET['s']) ? (string) $_GET['s'] : '';
if (!isset($sections[$sectionId])) {
	ob_end_clean();
	header('Location: settings.php');
	exit;
}
$section = $sections[$sectionId];
if (!empty($section['module']) && !settings_has_module($section['module'])) {
	ob_end_clean();
	header('Location: settings.php?err=module');
	exit;
}
$groups = getSettingsGroups();
$permission = $groups[$section['group']]['permission'];
require_permission($permission, 'read');
$canWrite = perm_can($permission, 'write');

$defs = settings_section_definitions($sectionId);
SettingsService::preload(array_keys($defs));
$selfUrl = 'settingsSection.php?s=' . rawurlencode($sectionId);

$state = array('errors' => array(), 'posted' => null, 'conflict' => null, 'flash' => array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])) {
		audit_log('csrf', 'settingsSection.php?s=' . $sectionId);
		ob_end_clean();
		header('Location: ' . $selfUrl . '&err=csrf');
		exit;
	}
	require_permission($permission, 'write');
	$action = isset($_POST['action']) ? (string) $_POST['action'] : 'save';

	if ($action === 'revert') {
		$entry = SettingsService::historyEntry(isset($_POST['entry']) ? (int) $_POST['entry'] : 0);
		if ($entry && $entry['section'] === $sectionId && isset($defs[$entry['setting_key']]) && $defs[$entry['setting_key']]['type'] !== 'secret') {
			SettingsService::saveRaw($entry['setting_key'], (string) $entry['old_value']);
		}
		ob_end_clean();
		header('Location: ' . $selfUrl . '&reverted=1#' . ($entry ? rawurlencode((string) $entry['setting_key']) : ''));
		exit;
	}

	if ($action === 'run') {
		$key = isset($_POST['key']) ? (string) $_POST['key'] : '';
		$target = $selfUrl;
		if (isset($defs[$key]) && $defs[$key]['type'] === 'action' && st_visible($defs[$key], array())) {
			audit_log('setting.action', $key);
			$target = settings_run_action($defs[$key], $selfUrl);
		}
		ob_end_clean();
		header('Location: ' . $target);
		exit;
	}

	$state = settings_section_save($sectionId, $defs, $_POST);
	if (!$state['errors'] && !$state['conflict']) {
		ob_end_clean();
		header('Location: ' . $selfUrl . '&saved=' . date('Hi'));
		exit;
	}
}

settings_section_view($sectionId, $section, $defs, $state, $canWrite, $csrfToken, $selfUrl);

// ---------------------------------------------------------------- controller

/**
 * Validate everything first; save only when nothing is wrong and nobody else saved in between.
 *
 * @param array<string, array<string, mixed>> $defs
 * @return array{errors: array<string, int>, posted: array<string, string>, conflict: array<string, mixed>|null, flash: array<int, mixed>}
 */
function settings_section_save(string $sectionId, array $defs, array $post): array
{
	$posted = array();
	$originals = array();
	foreach ((isset($post['f']) && is_array($post['f'])) ? $post['f'] : array() as $key => $value) {
		if (isset($defs[$key]) && is_string($value)) {
			$posted[$key] = $value;
		}
	}
	foreach ((isset($post['o']) && is_array($post['o'])) ? $post['o'] : array() as $key => $value) {
		if (isset($defs[$key]) && is_string($value)) {
			$originals[$key] = $value;
		}
	}

	// Only fields that were rendered, may be changed and are visible take part (P6, R17).
	$errors = array();
	$toSave = array();
	foreach ($defs as $key => $def) {
		if ($def['type'] === 'action' || !isset($posted[$key]) || st_locked($def) || !st_visible($def, $posted)) {
			continue;
		}
		$res = st_posted_to_raw($def, $posted[$key], $posted);
		$current = SettingsService::raw($key);
		$unchanged = (st_form_value($def, $current) === trim($posted[$key]));
		if ($res['error'] !== null) {
			// A value that was already stored and is left untouched is not this user's error.
			if (!$unchanged || (is_array($def['validate']) && $def['validate'][0] === 'requires')) {
				$errors[$key] = $res['error'];
			}
			continue;
		}
		if (!$unchanged) {
			$toSave[$key] = $res['raw'];
		}
	}

	$conflict = null;
	$version = isset($post['version']) ? (int) $post['version'] : 0;
	$currentVersion = SettingsService::version($sectionId);
	if ($currentVersion > $version) {
		$latest = SettingsService::history($sectionId, 1);
		$mine = array();
		foreach ($posted as $key => $value) {
			if (isset($originals[$key]) && trim($value) !== trim($originals[$key])) {
				$mine[$key] = $value;
			}
		}
		$conflict = array('by' => $latest ? (string) $latest[0]['brugernavn'] : '', 'at' => $latest ? (string) $latest[0]['tidspunkt'] : '', 'mine' => $mine);
	}

	if (!$errors && !$conflict && $toSave) {
		transaktion('begin');
		foreach ($toSave as $key => $raw) {
			SettingsService::saveRaw($key, $raw);
		}
		transaktion('commit');
		foreach ($toSave as $key => $raw) {
			if (!empty($defs[$key]['on_save'])) {
				settings_after_save($defs[$key], $raw);
			}
		}
	}
	return array('errors' => $errors, 'posted' => $posted, 'conflict' => $conflict, 'flash' => array());
}

// ---------------------------------------------------------------- view

/**
 * @param array<string, mixed>                 $section
 * @param array<string, array<string, mixed>> $defs
 * @param array<string, mixed>                 $state
 */
function settings_section_view(string $sectionId, array $section, array $defs, array $state, bool $canWrite, string $csrfToken, string $selfUrl): void
{
	global $sprog_id, $regnskab, $buttonColor, $buttonTxtColor;
	$sprogId = (int) $sprog_id;
	$charset = st_charset();
	$groups = getSettingsGroups();
	$group = $groups[$section['group']];
	$accent = !empty($buttonColor) ? $buttonColor : '#114691';
	$accentTxt = !empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff';

	// Form values: stored values, or after a refused save what the user typed.
	$values = array();
	$originals = array();
	foreach ($defs as $key => $def) {
		if ($def['type'] === 'action') {
			continue;
		}
		$stored = st_form_value($def, SettingsService::raw($key));
		$values[$key] = $stored;
		$originals[$key] = $stored;
		if ($state['conflict']) {
			if (isset($state['conflict']['mine'][$key])) {
				$values[$key] = $state['conflict']['mine'][$key];
			}
		} elseif ($state['errors'] && isset($state['posted'][$key])) {
			$values[$key] = $state['posted'][$key];
		}
	}

	$flash = array();
	if (isset($_GET['err']) && $_GET['err'] === 'csrf') {
		$flash[] = array('err', st_txt(5754));
	}
	if ($state['errors']) {
		$flash[] = array('err', st_txt(5731));
	}
	if ($state['conflict']) {
		$when = $state['conflict']['at'] !== '' ? st_local_time($state['conflict']['at'], 'H:i') : '';
		$flash[] = array('warn', $state['conflict']['by'] . ' ' . st_txt(5728) . ' ' . $when . '. ' . st_txt(5744));
	}
	if (!empty($_GET['reverted'])) {
		$flash[] = array('ok', st_txt(5752));
	}
	if (isset($_GET['converted'])) {
		$flash[] = array('ok', sprintf(st_txt(6013), (int) $_GET['converted']));
	}
	if (!$canWrite) {
		$flash[] = array('info', st_txt(5755));
	}
	if (isset($_GET['saved']) && preg_match('/^[0-9]{4}$/', (string) $_GET['saved'])) {
		$flash[] = array('ok', st_txt(5709) . ' ' . substr($_GET['saved'], 0, 2) . ':' . substr($_GET['saved'], 2));
	}
	$moved = isset($_GET['moved']) ? (string) $_GET['moved'] : '';
	$movedText = '';
	if ($moved !== '' && isset($section['old'][$moved])) {
		$movedText = st_txt(5720) . ' ' . settings_legacy_text($section['old'][$moved], $sprogId) . '. ' . st_txt(5721);
	}

	$tabs = settings_section_tabs($section['group'], $sprogId);
	$history = SettingsService::history($sectionId, 20);
	$version = SettingsService::version($sectionId);
	$config = array(
		'unsavedN' => st_txt(6043), 'unsaved1' => st_txt(6044),
		'copied' => st_txt(5718), 'notFound' => st_txt(5719), 'cancel' => st_txt(5), 'lookupUrl' => 'settingsLookup.php',
		'restoreTitle' => st_txt(5747), 'restoreBody' => st_txt(5748), 'restoreVerb' => st_txt(5711),
	);
	foreach ($config as $k => $v) {
		$config[$k] = mb_convert_encoding($v, 'UTF-8', $charset);
	}
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt($section['label']), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb((string) $section['group'], st_txt($section['label']), $sprogId), $charset) ?>
<div class="st-page" style="<?= st_h(st_accent_style((string) $accent, (string) $accentTxt)) ?>">
  <a class="st-skip" href="#st-form"><?= st_t(5751) ?></a>
  <section class="st-phead">
    <div>
      <h1><?= st_t($section['label']) ?></h1>
      <p class="st-lead"><?= st_t(5706) ?> <?= st_h(st_company()) ?>.</p>
    </div>
  </section>

	<?php if ($movedText !== '' || $flash) { ?>
  <div class="st-notes">
		<?php if ($movedText !== '') { ?>
    <div class="st-toast" role="status"><i class="st-dotw st-dot-acc" aria-hidden="true"></i><span><?= st_h($movedText) ?> <a href="settingsMoved.php"><?= st_t(5722) ?></a></span><button type="button" class="st-toast-close" aria-label="<?= st_t(2172) ?>" data-dismiss><i class='bx bx-x'></i></button></div>
		<?php } ?>
		<?php foreach ($flash as $f) { ?>
    <div class="st-flash st-flash-<?= $f[0] ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><?php if ($f[0] === 'info') { ?><i class='bx bx-lock-alt' aria-hidden="true"></i><?php } else { ?><i class="st-dotw st-dot-<?= $f[0] ?>" aria-hidden="true"></i><?php } ?><span><?= st_h($f[1]) ?></span></div>
		<?php } ?>
  </div>
	<?php } ?>

  <div class="st-layout">
    <nav class="st-tabs" aria-label="<?= st_h(html_entity_decode(findtekst($group['label'], $sprogId), ENT_QUOTES | ENT_HTML5, $charset)) ?>">
      <select class="st-tabs-select" aria-label="<?= st_h(html_entity_decode(findtekst($group['label'], $sprogId), ENT_QUOTES | ENT_HTML5, $charset)) ?>" onchange="if (this.value) { window.location = this.value; }">
			<?php foreach ($tabs as $tab) { ?>
        <option value="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' selected' : '' ?>><?= st_h($tab['label']) ?></option>
			<?php } ?>
      </select>
      <ul>
			<?php foreach ($tabs as $tab) { ?>
        <li><a href="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' class="on" aria-current="page"' : '' ?>><span><?= st_h($tab['label']) ?></span><?= $tab['id'] === $sectionId ? '<i class="st-dot" id="st-dot" hidden></i>' : '' ?></a></li>
			<?php } ?>
      </ul>
    </nav>

    <form class="st-form" id="st-form" method="post" action="<?= st_h($selfUrl) ?>" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="version" value="<?= (int) $version ?>">

		<?php if ($state['errors']) { ?>
      <ul class="st-errorlist" aria-label="<?= st_t(5731) ?>">
			<?php foreach ($state['errors'] as $key => $textId) { ?>
        <li><a href="#<?= st_h($key) ?>"><?= st_t($defs[$key]['label']) ?>: <?= st_t($textId) ?></a></li>
			<?php } ?>
      </ul>
		<?php } ?>

		<?php
		$actions = array();
		$danger = array();
		foreach ($section['subsections'] as $sub => $subLabel) {
			$fields = array();
			foreach ($defs as $key => $def) {
				if ($def['sub'] !== $sub) {
					continue;
				}
				if ($def['type'] === 'action') {
					if (!empty($def['danger'])) {
						$danger[$key] = $def;
					} else {
						$actions[$key] = $def;
					}
					continue;
				}
				if ($def['visible_if'] && $def['visible_if'][0] === 'module' && !st_visible($def, $values)) {
					continue;
				}
				$fields[$key] = $def;
			}
			if (!$fields) {
				continue;
			}
			?>
      <section class="st-sect" id="sub-<?= st_h($sub) ?>">
        <h2><?= st_t($subLabel) ?></h2>
        <div class="st-card">
			<?php foreach ($fields as $key => $def) {
				st_render_field($def, array(
					'value'    => $values[$key],
					'original' => $originals[$key],
					'error'    => isset($state['errors'][$key]) ? $state['errors'][$key] : null,
					'readonly' => !$canWrite,
					'locked'   => st_locked($def),
					'visible'  => st_visible($def, $values),
					'mine'     => ($state['conflict'] && isset($state['conflict']['mine'][$key])),
				));
			} ?>
        </div>
      </section>
		<?php } ?>

		<?php if ($actions && $canWrite) { ?>
      <section class="st-sect" id="sub-actions">
        <h2><?= st_t(3285) ?></h2>
        <p><?= st_t(6047) ?></p>
        <div class="st-card">
			<?php foreach ($actions as $def) {
				st_render_action($def, $canWrite, st_visible($def, $values));
			} ?>
        </div>
      </section>
		<?php } ?>
		<?php if ($danger && $canWrite) { ?>
      <section class="st-sect st-sect-danger" id="sub-danger">
        <h2><?= st_t(6048) ?></h2>
        <div class="st-card st-card-danger">
			<?php foreach ($danger as $def) {
				st_render_action($def, $canWrite, st_visible($def, $values));
			} ?>
        </div>
      </section>
		<?php } ?>

		<?php if ($canWrite) { ?>
      <footer class="st-savebar" id="st-savebar" hidden>
        <div class="st-savebar-in">
          <span class="st-status"><i class="st-dotw st-dot-warn" aria-hidden="true"></i><span id="st-status" role="status"></span></span>
          <span class="st-savebar-btns">
            <kbd aria-hidden="true">Ctrl S</kbd>
            <button type="button" class="st-btn st-btn-quiet" id="st-undo" disabled><?= st_t(159) ?></button>
            <button type="submit" class="st-btn st-btn-primary" id="st-save"><?= st_t(6045) ?></button>
          </span>
        </div>
      </footer>
		<?php } ?>
    </form>

    <aside class="st-hist" aria-labelledby="st-hist-title">
      <h2 id="st-hist-title"><?= st_t(5710) ?></h2>
      <div class="st-card">
			<?php
			$shown = 0;
			foreach ($history as $row) {
				$key = (string) $row['setting_key'];
				if (!isset($defs[$key])) {
					continue;
				}
				$shown++;
				$def = $defs[$key];
				$secret = ($def['type'] === 'secret');
				$oldText = st_display_value($def, (string) $row['old_value']);
				?>
        <div class="st-h">
          <a class="st-h-field" href="#<?= st_h($key) ?>"><?= st_t($def['label']) ?></a>
          <span class="st-h-ch"><?php if ($secret) { ?><?= st_t(5713) ?><?php } else { ?><s><?= st_h($oldText) ?></s> → <?= st_h(st_display_value($def, (string) $row['new_value'])) ?><?php } ?></span>
          <span class="st-h-m"><span><?= st_h($row['brugernavn']) ?> · <?= st_h(st_local_time((string) $row['tidspunkt'], 'j/n H:i')) ?></span><?php if (!$secret && $canWrite && !st_locked($def)) { ?><button type="button" class="st-tl" data-restore="<?= (int) $row['id'] ?>" data-value="<?= st_h($oldText) ?>"><?= st_t(5711) ?></button><?php } ?></span>
        </div>
			<?php } ?>
			<?php if (!$shown) { ?>
        <p class="st-h-empty"><?= st_t(5712) ?></p>
			<?php } ?>
			<?php if (function_exists('perm_can') && perm_can('settings.audit.read', 'read')) { ?>
        <div class="st-h-foot"><a class="st-tl" href="usersRoles.php?tab=log"><?= st_t(6046) ?></a></div>
			<?php } ?>
      </div>
    </aside>
  </div>

  <div class="st-backdrop" id="st-backdrop" hidden></div>
  <div class="st-dialog" id="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-dialog-title" hidden>
    <h3 id="st-dialog-title"></h3>
    <p id="st-dialog-body"></p>
    <form method="post" action="<?= st_h($selfUrl) ?>" id="st-dialog-form">
      <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
      <input type="hidden" name="action" value="">
      <input type="hidden" name="key" value="">
      <input type="hidden" name="entry" value="">
      <div class="st-dialog-btns">
        <button type="button" class="st-btn st-btn-quiet" id="st-dialog-cancel"><?= st_t(5) ?></button>
        <button type="submit" class="st-btn st-btn-primary" id="st-dialog-ok"></button>
      </div>
    </form>
  </div>
  <div class="st-snack" id="st-snack" role="status" hidden></div>
</div>
<script>window.SALDI_SETTINGS = <?= json_encode($config) ?>;</script>
<script src="../javascript/settingsSection.js?v=4"></script>
	<?php
}

/**
 * The sections of a group: generated ones first as registered, then the pages that have not
 * landed yet, so nothing of the group is out of reach from the tab list.
 *
 * @return array<int, array{id: string, label: string, url: string}>
 */
function settings_section_tabs(string $group, int $sprogId): array
{
	$tabs = array();
	foreach (getSettingsRegistry() as $entry) {
		if (!isset($entry['group']) || $entry['group'] !== $group || !settings_entry_available($entry)) {
			continue;
		}
		$tabs[] = array(
			'id'    => isset($entry['section']) ? (string) $entry['section'] : (string) $entry['key'],
			'label' => settings_entry_label($entry, $sprogId),
			'url'   => (string) $entry['url'],
		);
	}
	return $tabs;
}
