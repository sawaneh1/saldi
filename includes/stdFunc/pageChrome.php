<?php
// ---- includes/stdFunc/pageChrome.php --- lap 5.0.0 --- 2026.10.05 ---
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
// 20261005 Sawaneh Topbar addendum 2026-10-05 §4: the one breadcrumb mechanism for every page in the shell.
//                  page_help() registers the page's tour, shortcuts and guide for the Assist menu (§7);
//                  page_breadcrumb() prints a script that posts saldi:breadcrumb to the shell (index/main.php),
//                  answers the shell's request after each load, and handles saldi:navigate - through
//                  window.saldiNavigate(href) when the page defines its own close logic, otherwise respecting
//                  docChange. A "came from" chip (§3.1) is added from the navigation stack when the previous page
//                  is not one of the page's own levels.

if (!function_exists('page_breadcrumb')):

/**
 * Tour, shortcut list and guide of the current page for the Assist menu (§7), e.g.
 * page_help(['tour' => '#tutorial-help', 'shortcuts' => '../doc/ledgerGuide.pdf', 'guide' => 'guides/finans/kassekladde']).
 */
function page_help(array $items): void
{
	$GLOBALS['page_help_items'] = array_intersect_key($items, array('tour' => 1, 'shortcuts' => 1, 'guide' => 1));
}

/**
 * Print the page's breadcrumb for the shell. $levels: [['label' => 'Finans'], ['label' => 'Kassekladder',
 * 'href' => '../finans/kladdeliste.php'], ['label' => 'Kassekladde 2877']]; the company name is added by the shell.
 * An href is relative to the page or starts with '/' for a path from the Saldi root. $back overrides the
 * came-from chip: ['label' => 'Ordre 1234', 'href' => '...'], or false for none.
 *
 * @param array<int, array{label: string, href?: string}> $levels
 * @param array{label: string, href: string}|false|null    $back
 */
function page_breadcrumb(array $levels, ?string $tag = null, $back = null, string $charset = 'UTF-8'): string
{
	$items = array();
	foreach ($levels as $level) {
		if (!isset($level['label']) || (string) $level['label'] === '') {
			continue;
		}
		$item = array('label' => page_chrome_text((string) $level['label'], $charset));
		if (!empty($level['href'])) {
			$item['href'] = (string) $level['href'];
		}
		$items[] = $item;
	}
	if ($back === null) {
		$back = page_came_from($items);
	}
	page_remember($items);
	$msg = array('type' => 'saldi:breadcrumb', 'items' => $items);
	if ($tag !== null && $tag !== '') {
		$msg['tag'] = page_chrome_text($tag, $charset);
	}
	if (is_array($back) && !empty($back['href'])) {
		$msg['back'] = array('label' => page_chrome_text((string) $back['label'], $charset), 'href' => (string) $back['href']);
	}
	if (!empty($GLOBALS['page_help_items'])) {
		$msg['help'] = $GLOBALS['page_help_items'];
	}
	$json = json_encode($msg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	return '<script>' . "\n" . '(function () {' . "\n"
		. "\tvar msg = $json;\n"
		. "\tfunction send() { if (window.parent && window.parent !== window) { window.parent.postMessage(msg, window.location.origin); } }\n"
		. "\twindow.saldiPageChrome = msg;\n"
		. "\tif (!window.saldiChromeBound) {\n"
		. "\t\twindow.saldiChromeBound = true;\n"
		. "\t\twindow.addEventListener('message', function (e) {\n"
		. "\t\t\tif (e.origin !== window.location.origin || !e.data || e.source !== window.parent) { return; }\n"
		. "\t\t\tif (e.data.type === 'saldi:breadcrumb-request') { send(); }\n"
		. "\t\t\tif (e.data.type === 'saldi:navigate' && typeof e.data.href === 'string') {\n"
		. "\t\t\t\twindow.parent.postMessage({ type: 'saldi:navigate-ack' }, window.location.origin);\n"
		. "\t\t\t\tif (typeof window.saldiNavigate === 'function') { window.saldiNavigate(e.data.href); return; }\n"
		. "\t\t\t\tif (window.docChange && !window.confirm(e.data.confirm || '')) { return; }\n"
		. "\t\t\t\twindow.docChange = false;\n"
		. "\t\t\t\twindow.location.href = e.data.href;\n"
		. "\t\t\t}\n"
		. "\t\t});\n"
		. "\t\t// Alt+L, the accesskey of the old Luk/Tilbage, goes back through the shell (§3.2).\n"
		. "\t\tdocument.addEventListener('keydown', function (e) {\n"
		. "\t\t\tif (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'l' || e.key === 'L')) { e.preventDefault(); window.parent.postMessage({ type: 'saldi:back' }, window.location.origin); }\n"
		. "\t\t});\n"
		. "\t}\n"
		. "\tsend();\n"
		. "})();\n</script>";
}

/**
 * A label as UTF-8 plain text (the shell inserts it with textContent).
 */
function page_chrome_text(string $label, string $charset): string
{
	if ($charset !== 'UTF-8') {
		$label = mb_convert_encoding($label, 'UTF-8', $charset);
	}
	return html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The page a link points at, as "dir/file.php" from the Saldi root (for comparing with the navigation stack).
 */
function page_chrome_path(string $href): string
{
	$path = (string) parse_url($href, PHP_URL_PATH);
	if ($path === '') {
		return '';
	}
	if ($path[0] !== '/') {
		$here = dirname((string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
		$path = $here . '/' . $path;
	}
	$parts = array();
	foreach (explode('/', $path) as $p) {
		if ($p === '' || $p === '.') {
			continue;
		}
		if ($p === '..') {
			array_pop($parts);
			continue;
		}
		$parts[] = $p;
	}
	return implode('/', array_slice($parts, -2));
}

/**
 * Remember the current page's label and parent for later "came from" chips (session, last 30 pages).
 */
function page_remember(array $items): void
{
	if (!$items || session_status() !== PHP_SESSION_ACTIVE || !isset($_SERVER['REQUEST_URI'])) {
		return;
	}
	$parent = '';
	for ($i = count($items) - 2; $i >= 0; $i--) {
		if (!empty($items[$i]['href'])) {
			$parent = page_chrome_path($items[$i]['href']);
			break;
		}
	}
	$key = page_chrome_path((string) $_SERVER['REQUEST_URI']);
	$pages = isset($_SESSION['page_chrome']) && is_array($_SESSION['page_chrome']) ? $_SESSION['page_chrome'] : array();
	unset($pages[$key]);
	$pages[$key] = array('label' => $items[count($items) - 1]['label'], 'parent' => $parent);
	$_SESSION['page_chrome'] = array_slice($pages, -30, null, true);
}

/**
 * The label a page was last shown with (its last breadcrumb level), or "Tilbage".
 */
function page_label_for(string $href): string
{
	$path = page_chrome_path($href);
	if (isset($_SESSION['page_chrome'][$path]['label'])) {
		return (string) $_SESSION['page_chrome'][$path]['label'];
	}
	return function_exists('findtekst') ? findtekst('30|Tilbage', isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1) : 'Tilbage';
}

/**
 * The came-from chip (§3.1): the previous page in the navigation stack, unless it is one of this page's own
 * levels, the page itself or a sibling with the same parent - breadcrumbs show where a page belongs, the chip
 * where the user came from.
 *
 * @return array{label: string, href: string}|false
 */
function page_came_from(array $items)
{
	if (!function_exists('nav_back_url') || !function_exists('_nav_read')) {
		return false;
	}
	$stack = _nav_read();
	if (count($stack) < 2) {
		return false;
	}
	$prev = (string) $stack[count($stack) - 2];
	$prevPath = page_chrome_path($prev);
	$here = page_chrome_path(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '');
	if ($prevPath === '' || $prevPath === $here || $prevPath === 'index/dashboard.php' || $prevPath === 'index/menu.php') {
		return false;
	}
	$parent = '';
	foreach ($items as $i => $item) {
		if (!empty($item['href'])) {
			if (page_chrome_path($item['href']) === $prevPath) {
				return false;
			}
			if ($i < count($items) - 1) {
				$parent = page_chrome_path($item['href']);
			}
		}
	}
	$known = isset($_SESSION['page_chrome'][$prevPath]) ? $_SESSION['page_chrome'][$prevPath] : null;
	if ($known && $parent !== '' && $known['parent'] === $parent) {
		return false;
	}
	$label = page_label_for($prev);
	$href = $prev;
	if (strpos($href, '/') === 0) {
		// The stack holds request URIs (/<install>/dir/page.php?...); the chip needs a path from the Saldi root.
		$href = '/' . $prevPath . (strpos($prev, '?') !== false ? substr($prev, strpos($prev, '?')) : '');
	}
	return array('label' => $label, 'href' => $href);
}

endif;
