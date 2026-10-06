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
// 20261006 Sawaneh Breadcrumb on every page (Adam 2026-10-06): online.php prints page_auto_breadcrumb() for each page from
//                  the central map in pageRoutes.php (folder module + page title when a page is not in it); a page's own
//                  page_breadcrumb() replaces it. The shell is always answered with the latest message, and a click
//                  leaves through the page's old luk.php link when it has one, so record locks are still released.

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
		. "\twindow.saldiPageChrome = $json;\n"
		. "\tfunction send() { if (window.parent && window.parent !== window) { window.parent.postMessage(window.saldiPageChrome, window.location.origin); } }\n"
		. "\tif (!window.saldiChromeBound) {\n"
		. "\t\twindow.saldiChromeBound = true;\n"
		. "\t\t// The page's old Luk/Tilbage through includes/luk.php releases the record it locked: leave the same way.\n"
		. "\t\tvar viaLuk = function (href) {\n"
		. "\t\t\tvar c = document.querySelector('a[accesskey=\"l\"], a[accesskey=\"L\"]');\n"
		. "\t\t\tif (!c || !/(^|\\/)luk\\.php/.test(c.getAttribute('href') || '')) { return href; }\n"
		. "\t\t\ttry { var u = new URL(c.href), t = new URL(href); u.searchParams.delete('popup'); u.searchParams.set('returside', t.pathname + t.search); return u.href; } catch (x) { return href; }\n"
		. "\t\t};\n"
		. "\t\twindow.addEventListener('message', function (e) {\n"
		. "\t\t\tif (e.origin !== window.location.origin || !e.data || e.source !== window.parent) { return; }\n"
		. "\t\t\tif (e.data.type === 'saldi:breadcrumb-request') { send(); }\n"
		. "\t\t\tif (e.data.type === 'saldi:navigate' && typeof e.data.href === 'string') {\n"
		. "\t\t\t\twindow.parent.postMessage({ type: 'saldi:navigate-ack' }, window.location.origin);\n"
		. "\t\t\t\tif (typeof window.saldiNavigate === 'function') { window.saldiNavigate(e.data.href); return; }\n"
		. "\t\t\t\tif (window.docChange && !window.confirm(e.data.confirm || '')) { return; }\n"
		. "\t\t\t\twindow.docChange = false;\n"
		. "\t\t\t\twindow.location.href = viaLuk(e.data.href);\n"
		. "\t\t\t}\n"
		. "\t\t});\n"
		. "\t\t// Alt+L goes back through the shell (§3.2); a page that still has its old Luk/Tilbage keeps that accesskey.\n"
		. "\t\tdocument.addEventListener('keydown', function (e) {\n"
		. "\t\t\tif (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'l' || e.key === 'L') && !document.querySelector('[accesskey=\"l\"], [accesskey=\"L\"]')) { e.preventDefault(); window.parent.postMessage({ type: 'saldi:back' }, window.location.origin); }\n"
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

/**
 * The central page map (pageRoutes.php): 'modules' key => [l, href], 'pages' 'dir/file.php' => [m, l, p].
 *
 * @return array{modules: array<string, array<string, string>>, pages: array<string, array<string, mixed>>}
 */
function page_routes(): array
{
	static $routes = null;
	if ($routes === null) {
		$f = __DIR__ . '/pageRoutes.php';
		$routes = is_file($f) ? (array) include $f : array();
		$routes += array('modules' => array(), 'pages' => array(), 'dirs' => array());
	}
	return $routes;
}

/**
 * The running page as 'dir/file.php' from the Saldi root.
 */
function page_route_key(): string
{
	$root = realpath(__DIR__ . '/../..');
	$file = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string) $_SERVER['SCRIPT_FILENAME']) : false;
	if ($root === false || $file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0) {
		return '';
	}
	return str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1));
}

/**
 * True when a request's query has every parameter of a map variant ('' = present with any value).
 */
function page_route_query_matches(array $want, array $have): bool
{
	foreach ($want as $k => $v) {
		if (!isset($have[$k]) || !is_string($have[$k]) || ($v !== '' && $have[$k] !== $v)) {
			return false;
		}
	}
	return true;
}

/**
 * The link for a parent level: the user's own visit from the navigation stack when there is one (it keeps the ids
 * and list filters), else the map's path.
 */
function page_route_visited(string $parent): string
{
	$path = ltrim((string) parse_url($parent, PHP_URL_PATH), '/');
	$want = array();
	parse_str((string) parse_url($parent, PHP_URL_QUERY), $want);
	$stack = function_exists('_nav_read') ? _nav_read() : array();
	for ($i = count($stack) - 1; $i >= 0; $i--) {
		$url = (string) $stack[$i];
		if (page_chrome_path($url) !== implode('/', array_slice(explode('/', $path), -2))) {
			continue;
		}
		$have = array();
		parse_str((string) parse_url($url, PHP_URL_QUERY), $have);
		$ok = page_route_query_matches($want, $have);
		if ($ok && !$want) {
			// The plain page, not one of its variants.
			foreach (array_keys(page_routes()['pages']) as $key) {
				if (strpos($key, $path . '?') === 0) {
					$variant = array();
					parse_str(substr($key, strlen($path) + 1), $variant);
					if (page_route_query_matches($variant, $have)) {
						$ok = false;
						break;
					}
				}
			}
		}
		if ($ok) {
			$q = (string) parse_url($url, PHP_URL_QUERY);
			return '/' . $path . ($q !== '' ? '?' . $q : '');
		}
	}
	return $parent;
}

/**
 * The breadcrumb every page gets from online.php (Adam 2026-10-06: breadcrumbs on every page, replacing the old back
 * buttons): module, the page's parents and the page from the central map; a page not in the map shows its folder's
 * module and its title. Pages that call page_breadcrumb() themselves replace it.
 */
function page_auto_breadcrumb(string $title, int $sprogId, string $charset): string
{
	$key = page_route_key();
	$routes = page_routes();
	if ($key === '' || in_array($key, array('index/main.php', 'index/dashboard.php', 'index/index.php', 'index/login.php', 'index/menu.php', 'index/onboarding.php', 'systemdata/settingsSection.php', 'systemdata/settings.php'), true)) {
		return '';
	}
	$tx = function (string $l) use ($sprogId) {
		return trim((strpos($l, '|') === 0) ? substr($l, 1) : findtekst($l, $sprogId));
	};
	// A page's own entry, or the variant whose query ('?funktion=vis_sag', '?vare') matches the request's.
	$find = function (string $path) use ($routes) {
		$k = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
		$q = array();
		parse_str((string) parse_url($path, PHP_URL_QUERY), $q);
		foreach ($routes['pages'] as $key => $entry) {
			if (strpos($key, $k . '?') !== 0) {
				continue;
			}
			$want = array();
			parse_str(substr($key, strlen($k) + 1), $want);
			if (page_route_query_matches($want, $q)) {
				return array($key, $entry);
			}
		}
		return isset($routes['pages'][$k]) ? array($k, $routes['pages'][$k]) : null;
	};
	$here = $find('/' . $key . '?' . http_build_query(array_filter($_GET, 'is_string')));
	$levels = array();
	if ($here) {
		$levels[] = array('label' => $tx((string) $here[1]['l']));
		$parent = isset($here[1]['p']) ? (string) $here[1]['p'] : '';
		$seen = array($here[0] => true);
		while ($parent !== '' && count($levels) < 6) {
			$p = $find($parent);
			if (!$p || isset($seen[$p[0]])) {
				break;
			}
			$seen[$p[0]] = true;
			$href = page_route_visited($parent);
			array_unshift($levels, array('label' => $tx((string) $p[1]['l'])) + ($href !== '' ? array('href' => $href) : array()));
			$parent = isset($p[1]['p']) ? (string) $p[1]['p'] : '';
		}
		$module = (string) $here[1]['m'];
	} else {
		$label = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
		if ($label === '') {
			return '';
		}
		$levels[] = array('label' => function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1) : ucfirst($label));
		$dir = strpos($key, '/') !== false ? substr($key, 0, strpos($key, '/')) : '';
		$module = isset($routes['dirs'][$dir]) ? (string) $routes['dirs'][$dir] : '';
	}
	if ($module !== '' && isset($routes['modules'][$module])) {
		$m = $routes['modules'][$module];
		$landing = isset($m['href']) ? (string) $m['href'] : '';
		$first = isset($levels[0]['href']) ? ltrim((string) parse_url($levels[0]['href'], PHP_URL_PATH), '/') : $key;
		$isLanding = $landing !== '' && ltrim((string) parse_url($landing, PHP_URL_PATH), '/') === $first;
		array_unshift($levels, array('label' => $tx((string) $m['l'])) + (($landing !== '' && !$isLanding) ? array('href' => $landing) : array()));
	}
	return page_breadcrumb($levels, null, null, $charset);
}

endif;
