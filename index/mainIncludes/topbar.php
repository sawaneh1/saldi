<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/mainIncludes/topbar.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh Topbar (variant A): user chip, fiscal-year switcher, who-is-online,
//                  help (guides) and notification bell shell. Data + view for index/main.php.

/**
 * Sessions in the master `online` table that belong to the caller's company and
 * were active within the last hour. Must run BEFORE includes/online.php switches
 * the connection to the company database (the `online` table lives in the master db).
 *
 * @return array<int, array{brugernavn: string, logtime: int}>
 */
function topbar_online_rows(string $sessionId): array
{
	$sessionId = db_escape_string($sessionId);
	$r = db_fetch_array(db_select("select db from online where session_id = '$sessionId' order by logtime desc limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r || empty($r['db'])) {
		return array();
	}
	$db = db_escape_string($r['db']);
	$since = (int) date('U') - 3600;
	$qtxt = "select brugernavn, max(logtime) as logtime from online where db = '$db' and logtime > '$since' and revisor is not true group by brugernavn order by brugernavn";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$rows = array();
	while ($r = db_fetch_array($q)) {
		$rows[] = array('brugernavn' => trim((string) $r['brugernavn']), 'logtime' => (int) $r['logtime']);
	}
	return $rows;
}

/**
 * Company databases may still be ISO-8859-1; the shell is a UTF-8 document.
 */
function topbar_utf8(?string $s): string
{
	global $db_encode;
	$s = (string) $s;
	if (isset($db_encode) && $db_encode != 'UTF8' && $s !== '') {
		$s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
	}
	return $s;
}

function topbar_initials(string $displayName, string $initialer, string $username): string
{
	$initialer = trim($initialer);
	if ($initialer !== '') {
		return mb_strtoupper(mb_substr($initialer, 0, 3));
	}
	$words = preg_split('/\s+/', trim($displayName));
	$words = array_values(array_filter($words, function ($w) { return $w !== ''; }));
	if (count($words) >= 2) {
		return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
	}
	$base = $displayName !== '' ? $displayName : $username;
	return mb_strtoupper(mb_substr($base, 0, 2));
}

/**
 * Avatar colour derived from the user id so a user keeps the same colour everywhere.
 *
 * @return array{bg: string, fg: string}
 */
function topbar_avatar_color(int $userId): array
{
	$palette = array(
		array('bg' => '#d9e7ff', 'fg' => '#114691'),
		array('bg' => '#dcf3e6', 'fg' => '#137a45'),
		array('bg' => '#fde9d0', 'fg' => '#a35a00'),
		array('bg' => '#ecdff8', 'fg' => '#5b2d91'),
		array('bg' => '#fde0e0', 'fg' => '#a92727'),
		array('bg' => '#d8f1f5', 'fg' => '#0e6b7c'),
		array('bg' => '#f5e6d3', 'fg' => '#7a4a12'),
		array('bg' => '#e4e8f0', 'fg' => '#2f3b52'),
	);
	return $palette[abs($userId) % count($palette)];
}

/**
 * Human-readable role until Part 2 (named roles) lands. Mirrors today's reality:
 * the "Indstillinger" bit (position 1) is what makes a user an administrator.
 */
function topbar_role_title(string $rettigheder, $revisor, int $sprogId): string
{
	if ($revisor) {
		return findtekst('2562|Revisor', $sprogId);
	}
	if (substr($rettigheder, 1, 1) === '1') {
		return findtekst('330|Administrator', $sprogId);
	}
	return findtekst('990|Bruger', $sprogId);
}

/**
 * Everything the topbar view needs, gathered in one place (company connection active).
 *
 * @param array<int, array{brugernavn: string, logtime: int}> $onlineRows From topbar_online_rows().
 * @return array{
 *   name: string, username: string, email: string, initials: string,
 *   avatar: array{bg: string, fg: string}, company: string, role: string, isAdmin: bool,
 *   fiscalYear: string, fiscalYears: array<int, array{kodenr: string, label: string, active: bool}>,
 *   onlineUsers: array<int, string>, guides: array<int, array{url: string, label: string, icon: string}>,
 *   unread: int
 * }
 */
function topbar_context(array $onlineRows, string $brugernavn, int $brugerId, string $rettigheder, $revisor, $regnaar, int $sprogId, string $regnskab): array
{
	$name = $brugernavn;
	$email = '';
	$initialer = '';
	$ansatByUser = array();

	// select * : older company databases may lack email/initialer, and a missing
	// column must not take the whole shell down.
	$q = db_select("select * from brugere", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ansatByUser[trim((string) $r['brugernavn'])] = array(
			'ansat_id' => isset($r['ansat_id']) ? (int) $r['ansat_id'] : 0,
			'email'    => isset($r['email']) ? trim((string) $r['email']) : '',
		);
	}
	$ansatte = array();
	$q = db_select("select * from ansatte", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ansatte[(int) $r['id']] = $r + array('navn' => '', 'email' => '', 'initialer' => '');
	}

	$resolveName = function (string $user) use ($ansatByUser, $ansatte): string {
		$ansatId = isset($ansatByUser[$user]) ? $ansatByUser[$user]['ansat_id'] : 0;
		if ($ansatId && isset($ansatte[$ansatId]) && trim((string) $ansatte[$ansatId]['navn']) !== '') {
			return trim((string) $ansatte[$ansatId]['navn']);
		}
		return $user;
	};

	if (!$revisor) {
		$name = $resolveName($brugernavn);
		$ansatId = isset($ansatByUser[$brugernavn]) ? $ansatByUser[$brugernavn]['ansat_id'] : 0;
		$email = isset($ansatByUser[$brugernavn]) ? $ansatByUser[$brugernavn]['email'] : '';
		if ($ansatId && isset($ansatte[$ansatId])) {
			$initialer = (string) $ansatte[$ansatId]['initialer'];
			if ($email === '') {
				$email = trim((string) $ansatte[$ansatId]['email']);
			}
		}
	}

	$company = $regnskab;
	if ($r = db_fetch_array(db_select("select firmanavn from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__))) {
		if (trim((string) $r['firmanavn']) !== '') {
			$company = trim((string) $r['firmanavn']);
		}
	}

	$fiscalYears = array();
	$fiscalYear = '';
	// Newest first: the active year is normally the latest, so it sits at the top of the list.
	$q = db_select("select kodenr, beskrivelse, box5 from grupper where art = 'RA' order by box2 desc, box1 desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$active = ((string) $r['kodenr'] === (string) $regnaar);
		if ($active) {
			$fiscalYear = (string) $r['beskrivelse'];
		}
		if ($r['box5'] === 'on' || $active) {
			$fiscalYears[] = array('kodenr' => (string) $r['kodenr'], 'label' => (string) $r['beskrivelse'], 'active' => $active);
		}
	}

	$isAdmin = !$revisor && substr($rettigheder, 1, 1) === '1';
	$onlineUsers = array();
	if ($isAdmin) {
		foreach ($onlineRows as $row) {
			if ($row['brugernavn'] !== '') {
				$onlineUsers[] = topbar_utf8($resolveName($row['brugernavn']));
			}
		}
		$onlineUsers = array_values(array_unique($onlineUsers));
	}

	return array(
		'name'        => topbar_utf8($name),
		'username'    => topbar_utf8($brugernavn),
		'email'       => topbar_utf8($email),
		'initials'    => topbar_initials(topbar_utf8($name), topbar_utf8($initialer), topbar_utf8($brugernavn)),
		'avatar'      => topbar_avatar_color($brugerId),
		'company'     => topbar_utf8($company),
		'role'        => topbar_utf8(topbar_role_title($rettigheder, $revisor, $sprogId)),
		'isAdmin'     => $isAdmin,
		'fiscalYear'  => topbar_utf8($fiscalYear),
		'fiscalYears' => array_map(function ($y) { $y['label'] = topbar_utf8($y['label']); return $y; }, $fiscalYears),
		'onlineUsers' => $onlineUsers,
		'guides'      => array(
			array('url' => '../guides/pdf/finance_guide_da.pdf',     'label' => topbar_utf8(findtekst('5235|Regnskabsguide', $sprogId)), 'icon' => 'bx-coin-stack'),
			array('url' => '../guides/pdf/scaffolding_guide_da.pdf', 'label' => topbar_utf8(findtekst('5236|Stilladsguide', $sprogId)),  'icon' => 'bx-layer'),
		),
		'unread'      => 0,
	);
}

function topbar_h(?string $s): string
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * The topbar itself: hamburger (narrow screens) · breadcrumb · help · bell · user chip.
 * Dropdown panels are siblings of the buttons so they overlay the content iframe.
 */
function topbar_render(array $ctx, int $sprogId): void
{
	$t = function (string $text) use ($sprogId): string {
		return topbar_h(topbar_utf8(findtekst($text, $sprogId)));
	};
	$avatarStyle = 'background:' . topbar_h($ctx['avatar']['bg']) . ';color:' . topbar_h($ctx['avatar']['fg']);
	?>
  <header class="topbar" id="topbar">
    <button type="button" class="topbar-menu-btn" aria-label="Menu" title="Menu" onclick="topbarMenu()"><i class='bx bx-menu'></i></button>
    <span class="topbar-crumb" id="topbar-crumb"><?= $t('2224|Oversigt') ?></span>
    <span class="topbar-spacer"></span>

    <div class="topbar-item">
      <button type="button" class="topbar-icbtn" id="topbar-help-btn" title="<?= $t('2564|Hjælp') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-help-pop" onclick="topbarToggle(event, 'topbar-help-pop')"><i class='bx bx-help-circle'></i></button>
      <div class="topbar-pop topbar-pop-help" id="topbar-help-pop" role="menu">
        <div class="topbar-pop-title"><?= $t('5234|Guides') ?><small><?= $t('5259|Åbn en guide i en ny fane') ?></small></div>
        <?php foreach ($ctx['guides'] as $g) { ?>
        <a class="topbar-pop-item" role="menuitem" href="<?= topbar_h($g['url']) ?>" target="_blank" rel="noopener" onclick="topbarCloseAll()"><i class='bx <?= topbar_h($g['icon']) ?>'></i><?= topbar_h($g['label']) ?><i class='bx bx-link-external topbar-pop-trail'></i></a>
        <?php } ?>
      </div>
    </div>

    <div class="topbar-item">
      <button type="button" class="topbar-icbtn" id="topbar-bell-btn" title="<?= $t('5232|Notifikationer') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-bell-pop" onclick="topbarToggle(event, 'topbar-bell-pop')"><i class='bx bx-bell'></i><?php if ($ctx['unread'] > 0) { ?><span class="topbar-badge"><?= (int) $ctx['unread'] ?></span><?php } ?></button>
      <div class="topbar-pop topbar-pop-bell" id="topbar-bell-pop" role="dialog" aria-label="<?= $t('5232|Notifikationer') ?>">
        <div class="topbar-pop-title"><?= $t('5232|Notifikationer') ?></div>
        <div class="topbar-empty"><i class='bx bx-bell-off'></i><span><?= $t('5233|Ingen notifikationer endnu') ?></span></div>
      </div>
    </div>

    <div class="topbar-item">
      <button type="button" class="topbar-chip" id="topbar-user-btn" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-user-pop" onclick="topbarToggle(event, 'topbar-user-pop')" title="<?= topbar_h($ctx['name']) ?> · <?= topbar_h($ctx['company']) ?>">
        <span class="topbar-avatar" style="<?= $avatarStyle ?>"><?= topbar_h($ctx['initials']) ?></span>
        <span class="topbar-who"><span class="topbar-name"><?= topbar_h($ctx['name']) ?></span><span class="topbar-company"><?= topbar_h($ctx['company']) ?></span></span>
        <i class='bx bx-chevron-down topbar-chev'></i>
      </button>
      <div class="topbar-pop topbar-pop-user" id="topbar-user-pop" role="menu">
        <div class="topbar-pop-head">
          <span class="topbar-avatar topbar-avatar-lg" style="<?= $avatarStyle ?>"><?= topbar_h($ctx['initials']) ?></span>
          <div class="topbar-pop-headtxt">
            <div class="topbar-pop-name"><?= topbar_h($ctx['name']) ?></div>
            <div class="topbar-pop-mail"><?= $ctx['email'] !== '' ? topbar_h($ctx['email']) : topbar_h($ctx['username']) ?></div>
            <span class="topbar-role"><?= topbar_h($ctx['role']) ?></span>
          </div>
        </div>
        <div class="topbar-pop-body">
          <a class="topbar-pop-item" role="menuitem" href="#" onclick="topbarCloseAll(); update_iframe('/systemdata/personalSettings.php'); return false;"><i class='bx bx-cog'></i><?= $t('5230|Personlige indstillinger') ?></a>
          <?php if (count($ctx['fiscalYears']) > 0) { ?>
          <button type="button" class="topbar-pop-item" role="menuitem" aria-expanded="false" onclick="topbarToggleSub(event, 'topbar-years')"><i class='bx bx-calendar'></i><?= $t('778|Regnskabsår') ?><small><?= topbar_h($ctx['fiscalYear']) ?> <i class='bx bx-chevron-down'></i></small></button>
          <div class="topbar-pop-sub" id="topbar-years">
            <form method="post" action="topbarAction.php" id="topbar-year-form" onsubmit="return topbarSubmitYear(this)">
              <input type="hidden" name="action" value="fiscal_year">
              <input type="hidden" name="return_hash" value="">
              <?php foreach ($ctx['fiscalYears'] as $y) { ?>
              <button type="submit" name="year" value="<?= topbar_h($y['kodenr']) ?>" class="topbar-year<?= $y['active'] ? ' active' : '' ?>"<?= $y['active'] ? ' disabled aria-current="true"' : '' ?>><i class='bx <?= $y['active'] ? 'bx-check-circle' : 'bx-calendar-event' ?>'></i><span><?= topbar_h($y['label']) ?></span><?php if ($y['active']) { ?><small><?= $t('5264|Aktivt') ?></small><?php } ?></button>
              <?php } ?>
            </form>
          </div>
          <?php } ?>
          <?php if ($ctx['isAdmin']) { ?>
          <button type="button" class="topbar-pop-item" role="menuitem" aria-expanded="false" onclick="topbarToggleSub(event, 'topbar-online')"><i class='bx bx-group'></i><?= $t('5231|Hvem er online') ?><small><?= count($ctx['onlineUsers']) ?> <i class='bx bx-chevron-down'></i></small></button>
          <div class="topbar-pop-sub" id="topbar-online">
            <?php foreach ($ctx['onlineUsers'] as $u) { ?>
            <div class="topbar-online-user"><span class="topbar-dot"></span><?= topbar_h($u) ?></div>
            <?php } ?>
          </div>
          <?php } ?>
          <div class="topbar-pop-sep"></div>
          <a class="topbar-pop-item topbar-pop-out" role="menuitem" href="#" onclick="redirect_uri('/index/logud.php'); return false;"><i class='bx bx-log-out'></i><?= $t('93|Log ud') ?></a>
        </div>
      </div>
    </div>
  </header>
	<?php
}
