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

settings_hub_view(settings_accessible_groups(), (int) $sprog_id, (string) $db_encode);

/**
 * @param array<string, array{def: array<string, string>, entries: array<int, array<string, mixed>>}> $groups
 */
function settings_hub_view(array $groups, int $sprogId, string $dbEncode): void
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
	$backStyle = 'background:' . (!empty($buttonColor) ? $buttonColor : '#114691') . ';color:' . (!empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('122|Indstillinger', $sprogId), 'UTF-8', $charset)) ?>;</script>
<div class="sh-page">
  <a class="sh-back" style="<?= $h($backStyle) ?>" href="<?= $h($backUrl) ?>"><i class='bx bx-arrow-back'></i><?= $t('5647|Tilbage') ?></a>
  <header class="sh-head">
    <h1><i class='bx bx-cog'></i><?= $t('122|Indstillinger') ?></h1>
    <div class="sh-search">
      <i class='bx bx-search'></i>
      <input type="search" id="sh-search" placeholder="<?= $t('5663|Søg i indstillinger…') ?>" autocomplete="off">
    </div>
  </header>

  <?php if (!$groups) { ?>
  <div class="sh-empty"><i class='bx bx-lock-alt'></i><?= $t('5665|Du har ikke adgang til nogen indstillinger. Kontakt en administrator.') ?></div>
  <?php } ?>

  <div class="sh-grid" id="sh-grid">
    <?php foreach ($groups as $group => $g) { ?>
    <section class="sh-card" data-group="<?= $h($group) ?>">
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
        	$search = mb_strtolower($label . ' ' . implode(' ', isset($entry['keywords']) ? $entry['keywords'] : array()));
        ?>
        <li data-search="<?= $h($search) ?>"><a href="<?= $h($entry['url']) ?>"><?= $h($label) ?><i class='bx bx-chevron-right'></i></a></li>
        <?php } ?>
      </ul>
    </section>
    <?php } ?>
  </div>
  <div class="sh-empty" id="sh-nomatch" hidden><i class='bx bx-search-alt'></i><?= $t('5664|Ingen indstillinger matcher søgningen') ?></div>
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
			var titleHit = q !== '' && card.querySelector('h2').textContent.toLowerCase().indexOf(q) !== -1;
			card.querySelectorAll('li[data-search]').forEach(function (li) {
				var hit = q === '' || titleHit || li.dataset.search.indexOf(q) !== -1;
				li.hidden = !hit;
				if (hit) { hits++; }
			});
			card.hidden = hits === 0;
			if (hits > 0) { any = true; }
		});
		document.getElementById('sh-nomatch').hidden = any;
	});
})();
</script>
	<?php
}
