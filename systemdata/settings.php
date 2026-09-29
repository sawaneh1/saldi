<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/settings.php --- lap 5.0.0 --- 2026.09.28 ---
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
// 20260928 Sawaneh Settings front page (parent spec S1/S3/S4): search on top and one card per
//                  settings group the user may open, generated from systemdata/settingsRegistry.php.
// 20260929 Sawaneh Phase 4a (settings redesign spec §8.5, §8.10, §8.13, §8.14): search finds single settings and
//                  old menu names, transition banner, "Hvor er...?" link, PoS licence card, shortcuts.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $sprog_id
 * @var string $db_encode
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */

@session_start();
$s_id = session_id();

$title = "Indstillinger";
$css = "../css/settingsHub.css";
$modulnr = 1;
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['csrf_token'], $_POST['dismiss']) && hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']) && (int) $bruger_id > 0) {
		update_settings_value('transition_banner', 'settings_ui', 'dismissed', 'Settings transition banner dismissed', (int) $bruger_id);
	}
	header('Location: settings.php');
	exit;
}
$showBanner = ((int) $bruger_id > 0) && get_settings_value('transition_banner', 'settings_ui', '', (int) $bruger_id) !== 'dismissed';
$posLocked = (function_exists('perm_can') && perm_can('settings.pos', 'read') && !settings_has_module('pos'));

settings_hub_view(settings_accessible_groups(), (int) $sprog_id, (string) $db_encode, $showBanner, $posLocked, (string) $_SESSION['csrf_token']);

/**
 * @param array<string, array{def: array<string, string>, entries: array<int, array<string, mixed>>}> $groups
 */
function settings_hub_view(array $groups, int $sprogId, string $dbEncode, bool $showBanner, bool $posLocked, string $csrfToken): void
{
	global $buttonColor, $buttonTxtColor;
	$charset = ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1';
	$h = function ($s) use ($charset): string {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$t = function (string $text) use ($sprogId, $h): string {
		return $h(findtekst($text, $sprogId));
	};
	$backUrl = function_exists('nav_back_url') ? nav_back_url('../index/dashboard.php') : '../index/dashboard.php';
	$sections = getSettingsSections();
	$backStyle = 'background:' . (!empty($buttonColor) ? $buttonColor : '#114691') . ';color:' . (!empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" type="text/css" href="../css/unified-components.css">
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('122|Indstillinger', $sprogId), 'UTF-8', $charset)) ?>;</script>
<div class="sh-page st-page" style="padding-bottom:60px">
  <a class="sh-back" style="<?= $h($backStyle) ?>" href="<?= $h($backUrl) ?>"><i class='bx bx-arrow-back'></i><?= $t('5647|Tilbage') ?></a>
  <header class="sh-head">
    <h1><i class='bx bx-cog'></i><?= $t('122|Indstillinger') ?></h1>
    <div class="sh-search">
      <i class='bx bx-search'></i>
      <input type="search" id="sh-search" placeholder="<?= $t('5663|Søg i indstillinger…') ?>" autocomplete="off" aria-controls="sh-results">
      <button type="button" class="st-btn st-btn-ghost sh-keys" id="sh-keys" title="<?= $t('5739|Genveje') ?>" aria-label="<?= $t('5739|Genveje') ?>">?</button>
    </div>
  </header>

  <?php if ($showBanner) { ?>
  <form method="post" action="settings.php" class="st-toast" role="status">
    <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
    <input type="hidden" name="dismiss" value="1">
    <i class='bx bx-transfer-alt'></i>
    <span><?= $t('5724|Indstillingerne har fået ny struktur — det hele er her stadig. Søg, eller se hvor tingene er flyttet hen.') ?> <a href="settingsMoved.php"><?= $t('5725|Hvor er…?') ?></a></span>
    <button type="submit" class="st-toast-close" aria-label="<?= $t('5746|Skjul') ?>" title="<?= $t('5746|Skjul') ?>"><i class='bx bx-x'></i></button>
  </form>
  <?php } ?>

  <section class="sh-results" id="sh-results" aria-live="polite" hidden></section>

  <?php if (!$groups) { ?>
  <div class="sh-empty"><i class='bx bx-lock-alt'></i><?= $t('5665|Du har ikke adgang til nogen indstillinger. Kontakt en administrator.') ?></div>
  <?php } ?>

  <div class="sh-grid" id="sh-grid">
    <?php foreach ($groups as $group => $g) { ?>
    <section class="sh-card" data-group="<?= $h($group) ?>" data-title="<?= $h(settings_text_all_languages((int) $g['def']['label'])) ?>">
      <div class="sh-card-head">
        <span class="sh-icon"><i class='bx <?= $h($g['def']['icon']) ?>'></i></span>
        <div>
          <h2><?= $t($g['def']['label']) ?></h2>
          <p><?= $t($g['def']['description']) ?></p>
        </div>
      </div>
      <ul class="sh-links">
        <?php foreach ($g['entries'] as $entry) {
        	$label = settings_entry_label($entry, $sprogId);
        	$search = $label . ' ' . implode(' ', isset($entry['keywords']) ? $entry['keywords'] : array());
        	// The name in every language, and for a generated section its old menu names.
        	if (isset($entry['textId'])) {
        		$search .= ' ' . settings_text_all_languages($entry['textId']);
        	}
        	foreach (array('labelDa', 'labelEn', 'labelNo') as $other) {
        		if (!empty($entry[$other])) {
        			$search .= ' ' . $entry[$other];
        		}
        	}
        	if (isset($entry['section'], $sections[$entry['section']])) {
        		foreach ($sections[$entry['section']]['legacy'] as $path) {
        			$search .= ' ' . settings_legacy_all_languages($path);
        		}
        	}
        	$search = mb_strtolower(html_entity_decode($search, ENT_QUOTES | ENT_HTML5, $charset));
        ?>
        <li data-search="<?= $h($search) ?>"><a href="<?= $h($entry['url']) ?>"><?= $h($label) ?><i class='bx bx-chevron-right'></i></a></li>
        <?php } ?>
      </ul>
    </section>
    <?php } ?>
    <?php if ($posLocked) { ?>
    <section class="sh-card sh-card-locked" aria-disabled="true">
      <div class="sh-card-head">
        <span class="sh-icon"><i class='bx bx-store-alt'></i></span>
        <div>
          <h2><?= $t('2226|Kasse') ?></h2>
          <p><i class='bx bx-lock-alt'></i> <?= $t('5740|Kræver kasselicens — kontakt Saldi') ?></p>
        </div>
      </div>
    </section>
    <?php } ?>
  </div>
  <div class="sh-empty" id="sh-nomatch" hidden><i class='bx bx-search-alt'></i><?= $t('5664|Ingen indstillinger matcher søgningen') ?></div>
  <p class="sh-foot"><a href="settingsMoved.php"><i class='bx bx-transfer-alt'></i><?= $t('5725|Hvor er…?') ?></a></p>

  <div class="st-backdrop" id="sh-backdrop" hidden></div>
  <div class="st-dialog" id="sh-dialog" role="dialog" aria-modal="true" aria-labelledby="sh-dialog-title" hidden>
    <h2 id="sh-dialog-title"><?= $t('5739|Genveje') ?></h2>
    <table class="sh-keytable">
      <tr><td><kbd>/</kbd></td><td><?= $t('913|Søg') ?></td></tr>
      <tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td><?= $t('3|Gem') ?></td></tr>
      <tr><td><kbd>Esc</kbd></td><td><?= $t('2172|Luk') ?></td></tr>
      <tr><td><kbd>Alt</kbd> + <kbd>←</kbd></td><td><?= $t('5647|Tilbage') ?></td></tr>
    </table>
    <div class="st-dialog-btns"><button type="button" class="st-btn st-btn-primary" id="sh-dialog-close"><?= $t('2172|Luk') ?></button></div>
  </div>
</div>
<script>
(function () {
	var input = document.getElementById('sh-search');
	// Focus without letting the browser scroll the surrounding shell (autofocus does).
	if (input) { try { input.focus({ preventScroll: true }); } catch (e) {} }
	if (!input) { return; }
	input.addEventListener('input', function () {
		var q = input.value.trim().toLowerCase();
		var any = false;
		document.querySelectorAll('.sh-card').forEach(function (card) {
			var hits = 0;
			var titleHit = q !== '' && ((card.dataset.title || '') + ' ' + card.querySelector('h2').textContent.toLowerCase()).indexOf(q) !== -1;
			card.querySelectorAll('li[data-search]').forEach(function (li) {
				var hit = q === '' || titleHit || li.dataset.search.indexOf(q) !== -1;
				li.hidden = !hit;
				if (hit) { hits++; }
			});
			card.hidden = hits === 0;
			if (hits > 0) { any = true; }
		});
		document.getElementById('sh-nomatch').hidden = any || fieldHits > 0;
		lookup(q);
	});

	// Single settings from the registry (spec §8.5): max 8, grouped, "Vis alle" expands.
	var results = document.getElementById('sh-results');
	var showAllText = <?= json_encode(mb_convert_encoding(findtekst('5743|Vis alle', $sprogId), 'UTF-8', $charset)) ?>;
	var fieldHits = 0, timer = null, selected = -1;
	function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
	function render(data, all) {
		var rows = data.fields || [];
		fieldHits = rows.length;
		selected = -1;
		if (!rows.length) { results.hidden = true; results.innerHTML = ''; return; }
		var shown = all ? rows : rows.slice(0, 8), html = '', group = null;
		shown.forEach(function (r) {
			var g = r.group + (r.section ? ' → ' + r.section : '');
			if (g !== group) { if (group !== null) { html += '</ul>'; } html += '<h3>' + esc(g) + '</h3><ul>'; group = g; }
			html += '<li><a href="' + esc(r.url) + '">' + esc(r.label);
			if (r.personal) { html += ' <span class="st-tag st-tag-personal">' + esc(data.personalLabel) + '</span>'; }
			if (r.legacy) { html += ' <span class="st-tag">' + esc(data.legacyLabel + ' ' + r.legacy) + '</span>'; }
			html += '</a></li>';
		});
		html += '</ul>';
		if (!all && rows.length > 8) { html += '<button type="button" class="st-btn st-btn-ghost" id="sh-all">' + esc(showAllText) + ' (' + rows.length + ')</button>'; }
		results.innerHTML = html;
		results.hidden = false;
		document.getElementById('sh-nomatch').hidden = true;
		var more = document.getElementById('sh-all');
		if (more) { more.addEventListener('click', function () { render(data, true); }); }
	}
	function lookup(q) {
		window.clearTimeout(timer);
		if (q.length < 2) { fieldHits = 0; results.hidden = true; results.innerHTML = ''; return; }
		timer = window.setTimeout(function () {
			fetch('settingsSearch.php?search=' + encodeURIComponent(q), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) { if (input.value.trim().toLowerCase() === q) { render(data, false); } })
				.catch(function () {});
		}, 150);
	}
	input.addEventListener('keydown', function (e) {
		var links = results.querySelectorAll('a');
		if (e.key === 'Escape') { input.value = ''; input.dispatchEvent(new Event('input')); return; }
		if (!links.length) { return; }
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			selected = (selected + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
			links.forEach(function (a, i) { a.classList.toggle('on', i === selected); });
		} else if (e.key === 'Enter' && selected >= 0) {
			window.location = links[selected].getAttribute('href');
		}
	});

	var dialog = document.getElementById('sh-dialog'), backdrop = document.getElementById('sh-backdrop'), keys = document.getElementById('sh-keys');
	function toggleDialog(open) {
		dialog.hidden = !open;
		backdrop.hidden = !open;
		(open ? document.getElementById('sh-dialog-close') : keys).focus();
	}
	keys.addEventListener('click', function () { toggleDialog(true); });
	backdrop.addEventListener('click', function () { toggleDialog(false); });
	document.getElementById('sh-dialog-close').addEventListener('click', function () { toggleDialog(false); });
	document.addEventListener('keydown', function (e) {
		var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
		if (e.key === '/' && !typing) { e.preventDefault(); input.focus(); }
		if (e.key === 'Escape' && !dialog.hidden) { toggleDialog(false); }
	});
})();
</script>
	<?php
}
