<?php
// --- systemdata/top.php --- ver 4.1.1 --- 2025-04-14 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20181102 PHR Oprydning, udefinerede variabler.
// 20210223 Loe Replaced the text values on the table data with dynamic data with findtekst().
// 20220103 PHR Checks for error in text id 778 - can be removed in 2023
// 20250414 LOE Barcode button added for app
// 20260710 SZ Added Settings search box to sidebar (settingsSearch.php/.js/.css)
// 20260928 Sawaneh Phase 4: menu column generated from settingsRegistry.php (current group, access-filtered),
//                  Back goes to settings.php, unsaved-changes guard for the settings pages.

$small=NULL;
if (!isset($css)) $css=NULL;
if (!isset($rightoptxt)) $rightoptxt=NULL;

if ($css) $font="";
else $small="<small>";

if ($popup) $returside="../includes/luk.php";
else $returside="settings.php";

if (findtekst(778,$sprog_id) == 'Regnskabså') {
	$qtxt = "update tekster set tekst = '' where tekst_id = '778'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

include("../includes/topline_settings.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1

print "<td width=\"170px\"><a href=\"$returside\" accesskey=\"L\">
       <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('5647|Tilbage', $sprog_id)."</button></a></td>

       <td align='center' style='$topStyle'>".findtekst(613, $sprog_id)."<br></td>

       <td width=\"170px\" style='$topStyle'><br></td></tr>
       </tbody></table></td></tr>"; # <- tabel 1.1.1

print "</tr></tbody></table></td></tr>
  <tr><td id='sidebar-base'  width=\"125px\" align=\"left\" valign=\"top\">";
print "<table align=\"left\" border=\"0\" cellspacing=\"2\" cellpadding=\"2\"width=\"170px\"><tbody>"; #tabel 1.1.2

$searchPlaceholder = ($sprog_id == 2) ? 'Search settings...' : (($sprog_id == 3) ? 'Søk i innstillinger...' : 'Søg i indstillinger...');
$noResultsText = ($sprog_id == 2) ? 'No results' : (($sprog_id == 3) ? 'Ingen resultater' : 'Ingen resultater');
$matchHintText = ($sprog_id == 2) ? 'Found via' : (($sprog_id == 3) ? 'Funnet via' : 'Fundet via');
print "<script>
if (typeof window.saldiTranslations === 'undefined') {
	window.saldiLanguage = " . (int)$sprog_id . ";
	window.saldiTranslations = { settingsNoResults: " . json_encode($noResultsText) . ", settingsMatchHint: " . json_encode($matchHintText) . " };
}
</script>";
print "<link rel=\"stylesheet\" href=\"../css/settingsSearch.css\">";
print "<script src=\"../javascript/settingsSearch.js\" defer></script>";
print "<tr><td width=\"170px\"><div class=\"settings-search-wrapper\"><input type=\"text\" class=\"settings-search-input\" autocomplete=\"off\" placeholder=\"" . htmlspecialchars($searchPlaceholder) . "\"></div></td></tr>";

print "<tr><td width=\"170px\"><br></td></tr>";

// 20260928 Phase 4: the column lists the pages of the group the current page belongs to
// (settingsRegistry.php), filtered by the user's access; without a match, the groups.
include_once(__DIR__ . "/settingsRegistry.php");
$settingsGroups = settings_accessible_groups();
$currentEntries = settings_entries_for_request(basename($_SERVER['PHP_SELF']), $_GET);
$currentGroup = '';
foreach ($currentEntries as $currentEntry) {
	if (isset($settingsGroups[$currentEntry['group']])) {
		$currentGroup = $currentEntry['group'];
		break;
	}
}
$currentKeys = array_map(function ($e) { return $e['key']; }, $currentEntries);
$activeStyle = "$buttonStyle; filter:brightness(1.25); font-weight:bold";
if ($currentGroup !== '') {
	print "<tr><td style='padding:6px 2px 2px;font-weight:bold;font-size:12px;text-transform:uppercase;letter-spacing:.4px'>".findtekst($settingsGroups[$currentGroup]['def']['label'], $sprog_id)."</td></tr>";
	foreach ($settingsGroups[$currentGroup]['entries'] as $menuEntry) {
		$style = in_array($menuEntry['key'], $currentKeys, true) ? $activeStyle : $buttonStyle;
		print "<tr><td><a href=\"".htmlspecialchars($menuEntry['url'], ENT_QUOTES)."\"><button style='$style; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".settings_entry_label($menuEntry, $sprog_id)."</button></a></td></tr>\n";
	}
} else {
	foreach ($settingsGroups as $menuGroup) {
		print "<tr><td><a href=\"".htmlspecialchars($menuGroup['entries'][0]['url'], ENT_QUOTES)."\"><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst($menuGroup['def']['label'], $sprog_id)."</button></a></td></tr>\n";
	}
}
print "<tr><td><br></td></tr>";
print "<tr><td><a href=\"settings.php\"><button style='$buttonStyle; width:100%; opacity:.85' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('5666|Alle indstillinger', $sprog_id)."</button></a></td></tr>";

// Unsaved changes (spec S4): warn before leaving a settings page with edited, unsubmitted forms.
$unsavedText = json_encode(findtekst('5667|Du har ændringer, der ikke er gemt. Vil du forlade siden?', $sprog_id));
print "<script>
(function () {
	var dirty = false, submitting = false;
	document.addEventListener('input', function (e) { if (e.target.form) { dirty = true; window.docChange = true; } }, true);
	document.addEventListener('change', function (e) { if (e.target.form) { dirty = true; window.docChange = true; } }, true);
	document.addEventListener('submit', function () { submitting = true; window.docChange = false; }, true);
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || !dirty || submitting || a.target === '_blank' || a.getAttribute('href').charAt(0) === '#') { return; }
		if (!window.confirm($unsavedText)) { e.preventDefault(); e.stopPropagation(); }
		else { dirty = false; window.docChange = false; }
	}, true);
})();
</script>";
print "</tbody></table>";# <-tabel 1.1.2
print "</td><td align=\"center\" valign=\"top\" height=\"99%\"><br>";

?>
