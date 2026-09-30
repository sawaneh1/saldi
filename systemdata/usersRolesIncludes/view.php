<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/usersRolesIncludes/view.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh View functions for systemdata/usersRoles.php (users, roles matrix, audit log).
// 20260928 Sawaneh Back button top-left in the theme colour, as elsewhere in the system (was Close top-right).
// 20260929 Sawaneh Roles stage 2 (§8.4): Invite user / Create user, status Invited, resend invitation.
// 20260930 Sawaneh Roles stage 2 (§7.2): audit log filters, search, pages, CSV export, readable actions.
// 20260930 Sawaneh Back goes one step up: from a user or role card to its list, from a list to the settings
//                  front page (the history fallback landed on the old VAT page);
//                  password fields on the user card are not filled in by the browser; bulk results say how
//                  many were done and why the rest were skipped; own card says why it cannot be closed.
// 20260930 Sawaneh Roles stage 2 (§8.1, §8.2): reset password, role and 2FA filters, sorting, pages of 50.
// 20260930 Sawaneh Roles stage 2 (§6.3): review of migrated roles in the list, on the card and in bulk.

/**
 * @param array<string, mixed> $vm From ur_view_model().
 */
function ur_view(array $vm): void
{
	$charset = $vm['charset'];
	$sprog = (int) $vm['sprogId'];
	$h = function ($s) use ($charset): string {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$t = function (string $text) use ($sprog, $h): string {
		return $h(findtekst($text, $sprog));
	};
	$link = function (string $query) use ($vm, $h): string {
		return $h($vm['linkPrefix'] . $query);
	};
	$roleName = function (?array $role) use ($sprog, $t): string {
		return $role ? htmlspecialchars(perm_role_name($role, $sprog), ENT_QUOTES, 'UTF-8') : $t('5554|Ingen rolle');
	};
	$flash = ($vm['msg'] === 'bulk') ? ur_bulk_flash($vm['act'], (int) $vm['done'], $vm['why'], $sprog) : ur_flash($vm['msg'], $sprog);
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('5536|Brugere & roller', $sprog), 'UTF-8', $charset)) ?>;</script>
<div class="ur-page">
  <a class="ur-back" style="<?= $h(ur_back_style()) ?>" href="<?= $h($vm['editUser'] !== null ? $vm['linkPrefix'] . 'tab=users' : ($vm['editRole'] !== null ? $vm['linkPrefix'] . 'tab=roles' : 'settings.php')) ?>"><i class='bx bx-arrow-back'></i><?= $t('5647|Tilbage') ?></a>
  <header class="ur-head">
    <div>
      <h1><i class='bx bx-group'></i><?= $t('5536|Brugere & roller') ?></h1>
      <p class="ur-sub"><?= $t('5591|Brugere uden rolle beholder deres nuværende rettigheder, indtil en rolle tildeles.') ?></p>
    </div>
  </header>

  <?php if ($flash) { ?>
  <div class="ur-flash ur-flash-<?= $h($flash['type']) ?>"><i class='bx <?= $flash['type'] === 'ok' ? 'bx-check-circle' : 'bx-error' ?>'></i><span><?= $h($flash['text']) ?></span></div>
  <?php } ?>

  <?php if (!$vm['tablesReady']) { ?>
  <div class="ur-flash ur-flash-err"><i class='bx bx-error'></i><span>Roles tables missing - log in again so includes/betweenUpdates.php can create them.</span></div>
  <?php } ?>

  <nav class="ur-tabs">
    <a class="<?= $vm['tab'] === 'users' ? 'on' : '' ?>" href="<?= $link('tab=users') ?>"><i class='bx bx-user'></i><?= $t('5549|Brugere') ?><span class="ur-count"><?= count($vm['users']) ?></span></a>
    <?php if ($vm['canRoles']) { ?><a class="<?= $vm['tab'] === 'roles' ? 'on' : '' ?>" href="<?= $link('tab=roles') ?>"><i class='bx bx-shield-quarter'></i><?= $t('5550|Roller') ?><span class="ur-count"><?= count($vm['roles']) ?></span></a><?php } ?>
    <?php if ($vm['canAudit']) { ?><a class="<?= $vm['tab'] === 'log' ? 'on' : '' ?>" href="<?= $link('tab=log') ?>"><i class='bx bx-history'></i><?= $t('5796|Audit-log') ?></a><?php } ?>
  </nav>

  <?php
	if ($vm['tab'] === 'users') {
		if ($vm['editUser'] !== null) {
			ur_view_user_card($vm, $h, $t, $link, $roleName);
		}
		ur_view_users($vm, $h, $t, $link, $roleName);
	} elseif ($vm['tab'] === 'roles') {
		if ($vm['editRole'] !== null) {
			ur_view_role_editor($vm, $h, $t, $link);
		}
		ur_view_roles($vm, $h, $t, $link, $roleName);
	} else {
		ur_view_log($vm, $h, $t, $link);
	}
	?>
</div>
<script>
(function () {
	var search = document.getElementById('ur-search');
	var statusFilter = document.getElementById('ur-status-filter');
	var roleFilter = document.getElementById('ur-role-filter');
	var tfaFilter = document.getElementById('ur-2fa-filter');
	var tbody = document.querySelector('#ur-users tbody');
	var pager = document.getElementById('ur-pager');
	var PAGE = 50, page = 0;
	function rows() { return Array.prototype.slice.call(document.querySelectorAll('#ur-users tbody tr[data-search]')); }
	// Filters first, then pages of 50 over what is left (spec 8.1).
	function applyFilters(keepPage) {
		if (!tbody) { return; }
		if (keepPage !== true) { page = 0; }
		var q = search ? search.value.toLowerCase() : '';
		var st = statusFilter ? statusFilter.value : '';
		var rl = roleFilter ? roleFilter.value : '';
		var tf = tfaFilter ? tfaFilter.value : '';
		var hits = rows().filter(function (tr) {
			tr.hidden = true;
			return tr.dataset.search.indexOf(q) !== -1 && (st === '' || tr.dataset.status === st || (st === 'review' && tr.dataset.review === '1'))
				&& (rl === '' || tr.dataset.role === rl) && (tf === '' || tr.dataset.tfa === tf);
		});
		var pages = Math.max(1, Math.ceil(hits.length / PAGE));
		if (page >= pages) { page = pages - 1; }
		hits.forEach(function (tr, i) { tr.hidden = i < page * PAGE || i >= (page + 1) * PAGE; });
		var empty = document.getElementById('ur-empty');
		if (empty) { empty.hidden = hits.length > 0; }
		if (pager) {
			pager.hidden = hits.length <= PAGE;
			var txt = document.getElementById('ur-pager-text');
			txt.textContent = txt.dataset.format.replace('%s', page * PAGE + 1).replace('%s', Math.min(hits.length, (page + 1) * PAGE)).replace('%s', hits.length);
			pager.querySelector('[data-page="-1"]').disabled = page === 0;
			pager.querySelector('[data-page="1"]').disabled = page >= pages - 1;
		}
	}
	if (pager) {
		pager.addEventListener('click', function (e) {
			var b = e.target.closest('[data-page]');
			if (b) { page += parseInt(b.dataset.page, 10); applyFilters(true); }
		});
	}
	if (statusFilter && statusFilter.dataset.initial) { statusFilter.value = statusFilter.dataset.initial; }
	[search, statusFilter, roleFilter, tfaFilter].forEach(function (el) {
		if (el) { el.addEventListener(el === search ? 'input' : 'change', applyFilters); }
	});
	// Sort on any column; a second click reverses.
	document.querySelectorAll('#ur-users th[data-sort]').forEach(function (th) {
		th.addEventListener('click', function () {
			var dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
			document.querySelectorAll('#ur-users th[data-sort]').forEach(function (o) { o.removeAttribute('aria-sort'); });
			th.setAttribute('aria-sort', dir);
			var col = th.cellIndex;
			var val = function (tr) { var td = tr.cells[col]; return (td.dataset.v !== undefined ? td.dataset.v : td.textContent).trim().toLowerCase(); };
			rows().sort(function (a, b) { return (dir === 'ascending' ? 1 : -1) * val(a).localeCompare(val(b), undefined, { numeric: true }); })
				.forEach(function (tr) { tbody.appendChild(tr); });
			applyFilters(true);
		});
	});
	applyFilters();
	// A bulk button is only active when it applies to at least one ticked user, and its
	// confirmation counts only those users.
	function applies(tr, rule) {
		var st = tr.dataset.status, self = tr.dataset.self === '1';
		if (rule === 'notself') { return !self; }
		if (rule === 'open') { return !self && st !== 'closed'; }
		if (rule === 'review') { return tr.dataset.review === '1' && !self; }
		return st === rule;
	}
	function applicable(rule) {
		var n = 0;
		document.querySelectorAll('#ur-users tbody input[name="ids[]"]:checked').forEach(function (cb) {
			if (applies(cb.closest('tr'), rule)) { n++; }
		});
		return n;
	}
	function refreshBulk() {
		document.querySelectorAll('[data-applies]').forEach(function (b) { b.disabled = applicable(b.dataset.applies) === 0; });
	}
	document.querySelectorAll('[data-bulk-confirm]').forEach(function (b) {
		b.addEventListener('click', function (e) {
			var n = b.dataset.applies ? applicable(b.dataset.applies) : document.querySelectorAll('#ur-users input[name="ids[]"]:checked').length;
			if (n === 0 || !window.confirm(b.dataset.bulkConfirm + ' (' + n + ')?')) { e.preventDefault(); }
		});
	});
	document.addEventListener('change', function (e) { if (e.target.name === 'ids[]' || e.target.id === 'ur-check-all') { refreshBulk(); } });
	refreshBulk();
	var all = document.getElementById('ur-check-all');
	if (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('#ur-users tbody tr:not([hidden]) input[name="ids[]"]').forEach(function (cb) { cb.checked = all.checked; });
			refreshBulk();
		});
	}
	// Browsers fill a saved login into the password fields of another user's card; the fields
	// stay read-only until the admin clicks into them.
	document.querySelectorAll('input[data-nofill]').forEach(function (i) {
		if (!i.disabled) { i.addEventListener('focus', function () { i.removeAttribute('readonly'); }, { once: true }); }
	});
	document.querySelectorAll('form[data-confirm]').forEach(function (f) {
		f.addEventListener('submit', function (e) {
			if (!window.confirm(f.dataset.confirm)) { e.preventDefault(); }
		});
	});
})();
</script>
	<?php
}

/**
 * Back button in the user's theme colour, as on the other system pages.
 */
function ur_back_style(): string
{
	global $buttonColor, $buttonTxtColor;
	$bg = !empty($buttonColor) ? $buttonColor : '#114691';
	$fg = !empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff';
	return 'background:' . $bg . ';color:' . $fg;
}

/**
 * @return array{type: string, text: string}|null
 */
/**
 * Result of a bulk action: how many were really changed, and why the rest were not.
 *
 * @param array<string, int> $why reason => number of users
 * @return array{type: string, text: string}
 */
function ur_bulk_flash(string $act, int $done, array $why, int $sprog): array
{
	$doneText = array(
		'close'  => '5849|%s brugere er lukket.',
		'reopen' => '5850|%s brugere er genåbnet.',
		'resend' => '5851|%s invitationer er sendt igen.',
		'role'   => '5852|%s brugere har fået ny rolle.',
		'suggest'=> '5888|%s brugere er bekræftet.',
	);
	$reasonText = array(
		'already'    => array('close' => '5854|%s var allerede lukket.', 'reopen' => '5855|%s var allerede aktive.'),
		'unchanged'  => '5856|%s havde allerede rollen.',
		'notreview'  => '5894|%s var allerede bekræftet.',
		'notinvited' => '5857|%s er ikke inviteret og fik ingen invitation.',
		'mailfailed' => '5858|%s invitationer kunne ikke sendes. Åbn brugeren for at få linket.',
		'self'       => '5846|Du kan ikke lukke din egen bruger.',
		'ownrole'    => '5761|Du kan ikke ændre din egen rolle',
		'lastadmin'  => '5760|Regnskabet skal have mindst én administrator.',
		'escalation' => '5577|Du kan ikke tildele flere rettigheder, end du selv har',
	);
	$act = isset($doneText[$act]) ? $act : 'role';
	$parts = array($done > 0 ? sprintf(findtekst($doneText[$act], $sprog), $done) : findtekst('5853|Ingen brugere blev ændret.', $sprog));
	foreach ($why as $reason => $count) {
		if (!isset($reasonText[$reason])) {
			continue;
		}
		$text = is_array($reasonText[$reason]) ? (isset($reasonText[$reason][$act]) ? $reasonText[$reason][$act] : '') : $reasonText[$reason];
		if ($text !== '') {
			$parts[] = rtrim(strpos($text, '%s') !== false ? sprintf(findtekst($text, $sprog), $count) : findtekst($text, $sprog), '.') . '.';
		}
	}
	return array('type' => $done > 0 ? 'ok' : 'err', 'text' => implode(' ', $parts));
}

function ur_flash(string $msg, int $sprog): ?array
{
	$map = array(
		'usersaved'   => array('ok',  '5572|Brugeren er gemt'),
		'userdeleted' => array('ok',  '5573|Brugeren er slettet'),
		'rolesaved'   => array('ok',  '5574|Rollen er gemt'),
		'roledeleted' => array('ok',  '5575|Rollen er slettet'),
		'assigned'    => array('ok',  '5580|Rollerne er tildelt'),
		'roleinuse'   => array('err', '5576|Rollen kan ikke slettes, mens brugere har den'),
		'escalation'  => array('err', '5577|Du kan ikke tildele flere rettigheder, end du selv har'),
		'duplicate'   => array('err', '5578|Brugernavnet findes allerede'),
		'self'        => array('err', '5579|Du kan ikke slette din egen bruger'),
		'pwmismatch'  => array('err', '5589|Adgangskoden og gentagelsen er ikke ens'),
		'pwrequired'  => array('err', '5590|Adgangskode er påkrævet for en ny bruger'),
		'name'        => array('err', '5149|Brugernavnet må højst være 80 tegn'),
		'enforce'     => array('ok',  '5599|Håndhævelsen er ændret'),
		'userclosed'  => array('ok',  '5758|Brugeren er lukket'),
		'userreopened'=> array('ok',  '5759|Brugeren er genåbnet'),
		'bulkdone'    => array('ok',  '5769|Brugerne er opdateret'),
		'rolereset'   => array('ok',  '5763|Rollen er nulstillet til standard'),
		'lastadmin'   => array('err', '5760|Regnskabet skal have mindst én administrator.'),
		'ownrole'     => array('err', '5761|Du kan ikke ændre din egen rolle'),
		'hasloggedin' => array('err', '5762|Brugeren har været logget ind og kan derfor kun lukkes, ikke slettes'),
		'norole'      => array('err', '5771|Vælg en rolle først'),
		'invited'     => array('ok',  '5782|Invitationen er sendt'),
		'invitelink'  => array('err', '5783|E-mailen kunne ikke sendes. Send dette link til brugeren.'),
		'emailrequired' => array('err', '5786|En gyldig e-mail er påkrævet for at invitere'),
		'notinvited'  => array('err', '5794|Brugeren har allerede valgt en adgangskode'),
		'noaccess'    => array('err', '5809|Du har ikke adgang til dette'),
		'already'     => array('err', '5853|Ingen brugere blev ændret.'),
		'confirmed'   => array('ok',  '5889|Rollen er bekræftet'),
		'notreview'   => array('err', '5896|Brugerens rolle er allerede bekræftet'),
		'resetsent'   => array('ok',  '5863|En midlertidig adgangskode er sendt til brugerens e-mail'),
		'noemail'     => array('err', '5864|Brugeren har ingen gyldig e-mail'),
		'mailfailed'  => array('err', '5865|E-mailen kunne ikke sendes. Tjek e-mailopsætningen under Indstillinger.'),
		'closed'      => array('err', '5866|Brugeren er lukket. Genåbn brugeren først.'),
	);
	if (!isset($map[$msg])) {
		return null;
	}
	return array('type' => $map[$msg][0], 'text' => findtekst($map[$msg][1], $sprog));
}

function ur_view_users(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	?>
  <?php if ($vm['withoutRole'] > 0) { ?>
  <div class="ur-banner">
    <i class='bx bx-info-circle'></i>
    <span><?= $h(sprintf(findtekst('5561|%s brugere har endnu ingen rolle. Deres rettigheder er overført uændret, og der vises et forslag i listen.', $vm['sprogId']), $vm['withoutRole'])) ?></span>
    <?php if ($vm['canWrite']) { ?>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>"><input type="hidden" name="action" value="apply_suggestions"><button class="ur-btn ur-btn-primary ur-btn-sm" type="submit"><i class='bx bx-check-double'></i><?= $t('5562|Anvend foreslåede roller') ?></button></form>
    <?php } ?>
  </div>
  <?php } ?>

  <?php if ($vm['reviewCount'] > 0) { ?>
  <div class="ur-banner">
    <i class='bx bx-info-circle'></i>
    <span><?= $h(sprintf(findtekst('5895|%s brugere har fået en rolle automatisk og venter på at blive bekræftet.', $vm['sprogId']), $vm['reviewCount'])) ?></span>
    <a class="ur-btn ur-btn-primary ur-btn-sm" href="<?= $link('tab=users&status=review') ?>"><i class='bx bx-list-check'></i><?= $t('5880|Gennemgå') ?></a>
  </div>
  <?php } ?>

  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-bulk">
    <input type="hidden" name="action" value="bulk_role">
    <?php
    $active = 0;
    $admins = 0;
    $invitedCount = 0;
    foreach ($vm['users'] as $u) {
    	if ($u['invited']) {
    		$invitedCount++;
    	}
    	if (!$u['closed']) {
    		$active++;
    		if ($u['role_id'] > 0 && $u['role_id'] === $vm['adminRoleId']) {
    			$admins++;
    		}
    	}
    }
    ?>
    <p class="ur-counter"><?= (int) $active ?> <?= $t('5549|Brugere') ?> · <?= (int) $admins ?> <?= $t('5768|administratorer') ?><?php if ($invitedCount > 0) { ?> · <?= (int) $invitedCount ?> <?= $t('5787|inviterede') ?><?php } ?><?php if (count($vm['users']) > $active) { ?> · <?= count($vm['users']) - $active ?> <?= $t('5559|Lukket') ?><?php } ?></p>
    <div class="ur-toolbar">
      <div class="ur-search"><i class='bx bx-search'></i><input type="search" id="ur-search" placeholder="<?= $t('5552|Søg bruger…') ?>" autocomplete="off"></div>
      <select class="ur-select ur-select-sm" id="ur-status-filter" data-initial="<?= $h($vm['statusFilter']) ?>" aria-label="<?= $t('5557|Status') ?>">
        <option value=""><?= $t('5557|Status') ?>: <?= $t('2498|Alle') ?></option>
        <option value="active"><?= $t('5558|Aktiv') ?></option>
        <option value="invited"><?= $t('5778|Inviteret') ?></option>
        <option value="closed"><?= $t('5559|Lukket') ?></option>
        <?php if ($vm['reviewCount'] > 0) { ?><option value="review"><?= $t('5882|Migreret, ikke bekræftet') ?></option><?php } ?>
      </select>
      <select class="ur-select ur-select-sm" id="ur-role-filter" aria-label="<?= $t('5553|Rolle') ?>">
        <option value=""><?= $t('5875|Alle roller') ?></option>
        <?php foreach ($vm['roles'] as $role) { ?><option value="<?= (int) $role['id'] ?>"><?= $roleName($role) ?></option><?php } ?>
        <option value="0"><?= $t('5554|Ingen rolle') ?></option>
      </select>
      <select class="ur-select ur-select-sm" id="ur-2fa-filter" aria-label="2FA">
        <option value="">2FA: <?= $t('2498|Alle') ?></option>
        <option value="1"><?= $t('5873|Med 2FA') ?></option>
        <option value="0"><?= $t('5874|Uden 2FA') ?></option>
      </select>
      <?php if ($vm['canWrite']) { ?>
      <div class="ur-bulk">
        <select class="ur-select" name="role_id">
          <option value="0"><?= $t('5560|Tildel rolle til valgte') ?></option>
          <?php foreach ($vm['roles'] as $role) { ?>
          <option value="<?= (int) $role['id'] ?>"><?= $roleName($role) ?></option>
          <?php } ?>
        </select>
        <button class="ur-btn ur-btn-ghost" type="submit" name="bulk" value="role" data-applies="notself" data-bulk-confirm="<?= $t('5560|Tildel rolle til valgte') ?>"><i class='bx bx-user-check'></i><?= $t('1091|Opdater') ?></button>
        <button class="ur-btn ur-btn-ghost" type="submit" name="bulk" value="close" data-applies="open" data-bulk-confirm="<?= $t('5764|Luk valgte') ?>"><i class='bx bx-lock-alt'></i><?= $t('5764|Luk valgte') ?></button>
        <button class="ur-btn ur-btn-ghost" type="submit" name="bulk" value="reopen" data-applies="closed" data-bulk-confirm="<?= $t('5765|Genåbn valgte') ?>"><i class='bx bx-lock-open-alt'></i><?= $t('5765|Genåbn valgte') ?></button>
        <button class="ur-btn ur-btn-ghost" type="submit" name="bulk" value="resend" data-applies="invited" data-bulk-confirm="<?= $t('5781|Send invitation igen') ?>"><i class='bx bx-envelope'></i><?= $t('5781|Send invitation igen') ?></button>
        <?php if ($vm['reviewCount'] > 0) { ?><button class="ur-btn ur-btn-ghost" type="submit" name="bulk" value="suggest" data-applies="review" data-bulk-confirm="<?= $t('5887|Anvend forslag på valgte') ?>"><i class='bx bx-check-double'></i><?= $t('5887|Anvend forslag på valgte') ?></button><?php } ?>
      </div>
      <a class="ur-btn ur-btn-ghost" href="<?= $link('tab=users&bruger=0&mode=classic') ?>"><i class='bx bx-plus'></i><?= $t('5780|Opret bruger') ?></a>
      <a class="ur-btn ur-btn-primary" href="<?= $link('tab=users&bruger=0') ?>"><i class='bx bx-envelope'></i><?= $t('5779|Inviter bruger') ?></a>
      <?php } ?>
    </div>

    <div class="ur-card ur-table-wrap">
      <table class="ur-table" id="ur-users">
        <thead>
          <tr>
            <?php if ($vm['canWrite']) { ?><th class="ur-cb"><input type="checkbox" id="ur-check-all"></th><?php } ?>
            <th class="ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>"><?= $t('5531|Navn') ?></th>
            <th class="ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>"><?= $t('225|Brugernavn') ?></th>
            <th class="ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>"><?= $t('52|E-mail') ?></th>
            <th class="ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>"><?= $t('5553|Rolle') ?></th>
            <th class="ur-center ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>">2FA</th>
            <th class="ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>"><?= $t('5556|Sidst aktiv') ?></th>
            <th class="ur-sortable" data-sort title="<?= $t('5876|Klik for at sortere') ?>"><?= $t('5557|Status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vm['users'] as $u) {
          	$searchBlob = mb_strtolower($u['navn'] . ' ' . $u['brugernavn'] . ' ' . $u['email'] . ' ' . ($u['role'] ? perm_role_name($u['role'], $vm['sprogId']) : ''));
          ?>
          <tr data-search="<?= $h($searchBlob) ?>" data-status="<?= $u['closed'] ? 'closed' : ($u['invited'] ? 'invited' : 'active') ?>"<?= $u['id'] === $vm['selfId'] ? ' data-self="1"' : '' ?> data-role="<?= (int) $u['role_id'] ?>" data-tfa="<?= $u['twofactor'] ? 1 : 0 ?>"<?= $u['review'] ? ' data-review="1"' : '' ?><?= ($vm['editUser'] && $vm['editUser']['id'] === $u['id']) ? ' class="on"' : '' ?>>
            <?php if ($vm['canWrite']) { ?><td class="ur-cb"><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>"></td><?php } ?>
            <td><a class="ur-userlink" href="<?= $link('tab=users&bruger=' . $u['id']) ?>"><span class="ur-avatar"><?= $h(mb_strtoupper(mb_substr($u['initialer'] !== '' ? $u['initialer'] : $u['navn'], 0, 2))) ?></span><?= $h($u['navn']) ?><?php if ($u['isRevisor']) { ?><span class="ur-tag"><?= $t('2562|Revisor') ?></span><?php } ?></a></td>
            <td class="ur-mut"><?= $h($u['brugernavn']) ?></td>
            <td class="ur-mut"><?= $h($u['email']) ?></td>
            <td>
              <?php if ($u['role']) { ?>
              <span class="ur-role"><?= $roleName($u['role']) ?></span>
              <?php if ($u['review'] && $u['suggestion'] && $u['suggestion']['id'] !== $u['role_id']) { ?><span class="ur-suggest"><?= $t('5555|Forslag') ?>: <?= $roleName($u['suggestion']) ?></span><?php } ?>
              <?php } else { ?>
              <span class="ur-role ur-role-none"><?= $t('5554|Ingen rolle') ?></span>
              <?php if ($u['suggestion']) { ?><span class="ur-suggest"><?= $t('5555|Forslag') ?>: <?= $roleName($u['suggestion']) ?></span><?php } ?>
              <?php } ?>
            </td>
            <td class="ur-center" data-v="<?= $u['twofactor'] ? 1 : 0 ?>"><?php if ($u['twofactor']) { ?><i class='bx bxs-check-shield ur-ok' title="2FA"></i><?php } else { ?><span class="ur-mut">–</span><?php } ?></td>
            <td class="ur-mut" data-v="<?= $h($u['lastLogin']) ?>"><?= $u['lastLogin'] !== '' ? $h(substr($u['lastLogin'], 0, 16)) : '–' ?></td>
            <td><?php if ($u['closed']) { ?><span class="ur-status ur-status-closed"><?= $t('5559|Lukket') ?></span><?php } elseif ($u['invited']) { ?><span class="ur-status ur-status-invited"><?= $t('5778|Inviteret') ?></span><?php } else { ?><span class="ur-status ur-status-ok"><?= $t('5558|Aktiv') ?></span><?php } ?><?php if ($u['review']) { ?> <span class="ur-tag ur-tag-review"><?= $t('5883|Ikke bekræftet') ?></span><?php } ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <div class="ur-empty" id="ur-empty" hidden><?= $t('5593|Ingen brugere fundet') ?></div>
    </div>
    <div class="ur-pager" id="ur-pager" hidden>
      <button class="ur-btn ur-btn-ghost ur-btn-sm" type="button" data-page="-1"><i class='bx bx-chevron-left'></i><?= $t('5870|Forrige') ?></button>
      <span class="ur-mut" id="ur-pager-text" data-format="<?= $t('5872|%s–%s af %s') ?>"></span>
      <button class="ur-btn ur-btn-ghost ur-btn-sm" type="button" data-page="1"><?= $t('5871|Næste') ?><i class='bx bx-chevron-right'></i></button>
    </div>
  </form>
	<?php
}

function ur_view_user_card(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	$u = $vm['editUser'];
	$isNew = ($u['id'] === 0);
	$inviteNew = ($isNew && $vm['newMode'] === 'invite');
	$ro = $vm['canWrite'] ? '' : ' disabled';
	?>
  <section class="ur-card ur-editor">
    <div class="ur-editor-head">
      <h2><i class='bx <?= $isNew ? 'bx-user-plus' : 'bx-user' ?>'></i><?= $isNew ? ($inviteNew ? $t('5779|Inviter bruger') : $t('5780|Opret bruger')) : $h($u['navn']) ?><?php if ($u['invited']) { ?> <span class="ur-status ur-status-invited"><?= $t('5778|Inviteret') ?></span><?php } ?></h2>
      <a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link('tab=users') ?>"><i class='bx bx-x'></i><?= $t('2172|Luk') ?></a>
    </div>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" autocomplete="off">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <?php if ($isNew) { ?><input type="hidden" name="mode" value="<?= $inviteNew ? 'invite' : 'classic' ?>"><?php } ?>
      <?php if ($u['review'] && $vm['canWrite']) { ?>
      <div class="ur-review">
        <span><?= $h(sprintf(findtekst('5884|Rollen er sat automatisk ud fra brugerens gamle rettigheder. Forslag: %s.', $vm['sprogId']), $u['suggestion'] ? perm_role_name($u['suggestion'], $vm['sprogId']) : '-')) ?></span>
        <span class="ur-review-actions">
          <?php if ($u['suggestion'] && $u['suggestion']['id'] !== $u['role_id'] && $u['id'] !== $vm['selfId']) { ?>
          <button class="ur-btn ur-btn-primary ur-btn-sm" type="submit" form="ur-confirm-use"><i class='bx bx-check'></i><?= $t('5885|Brug forslaget') ?></button>
          <?php } ?>
          <button class="ur-btn ur-btn-ghost ur-btn-sm" type="submit" form="ur-confirm-keep"><?= $h(sprintf(findtekst('5886|Behold %s', $vm['sprogId']), $u['role'] ? perm_role_name($u['role'], $vm['sprogId']) : '-')) ?></button>
        </span>
      </div>
      <?php } ?>
      <?php if ($inviteNew) { ?><p class="ur-help"><?= $t('5788|Brugeren får en e-mail med et link og vælger selv sin adgangskode.') ?></p><?php } ?>
      <?php if ($vm['inviteLink'] !== '') { ?>
      <div class="ur-invite-link">
        <b><?= $t('5783|E-mailen kunne ikke sendes. Send dette link til brugeren.') ?></b>
        <input class="ur-input" type="text" readonly value="<?= $h($vm['inviteLink']) ?>" onfocus="this.select()">
      </div>
      <?php } ?>
      <div class="ur-grid">
        <?php if ($inviteNew) { ?>
        <div class="ur-field">
          <label><?= $t('52|E-mail') ?></label>
          <input class="ur-input" type="email" name="email" id="ur-invite-email" value="" required<?= $ro ?>>
        </div>
        <?php } ?>
        <div class="ur-field">
          <label><?= $t('225|Brugernavn') ?></label>
          <input class="ur-input" type="text" name="brugernavn" id="ur-brugernavn" maxlength="80" value="<?= $h($u['brugernavn']) ?>" required<?= $ro ?>>
        </div>
        <div class="ur-field">
          <label><?= $t('5553|Rolle') ?></label>
          <?php $ownCard = (!$isNew && $u['id'] === $vm['selfId']); ?>
          <?php if ($ownCard) { ?><input type="hidden" name="role_id" value="<?= (int) $u['role_id'] ?>"><?php } ?>
          <select class="ur-select" name="role_id"<?= ($ownCard ? ' disabled' : $ro) ?>>
            <?php if ($u['role_id'] === 0) { ?><option value="0" disabled selected><?= $t('5891|Vælg en rolle') ?></option><?php } ?>
            <?php foreach ($vm['roles'] as $role) {
            	$assignable = perm_within_own(perm_levels_from_role($role['id']));
            ?>
            <option value="<?= (int) $role['id'] ?>"<?= $role['id'] === $u['role_id'] ? ' selected' : '' ?><?= $assignable ? '' : ' disabled' ?>><?= $roleName($role) ?><?= $assignable ? '' : ' 🔒' ?></option>
            <?php } ?>
          </select>
          <?php if ($u['suggestion'] && $u['role_id'] === 0) { ?><span class="ur-help"><?= $t('5555|Forslag') ?>: <?= $roleName($u['suggestion']) ?></span><?php } ?>
          <?php if ($ownCard) { ?><span class="ur-help"><?= $t('5761|Du kan ikke ændre din egen rolle') ?></span><?php } ?>
        </div>
        <?php if (!$inviteNew) { ?>
        <div class="ur-field">
          <label><?= $t('324|Adgangskode') ?></label>
          <input class="ur-input" type="password" name="kode" autocomplete="new-password" readonly data-nofill<?= $ro ?>>
          <?php if (!$isNew) { ?><span class="ur-help"><?= $t('5525|Lad felterne stå tomme for at beholde din adgangskode') ?></span><?php } ?>
        </div>
        <div class="ur-field">
          <label><?= $t('328|Gentag adgangskode') ?></label>
          <input class="ur-input" type="password" name="kode2" autocomplete="new-password" readonly data-nofill<?= $ro ?>>
        </div>
        <?php } ?>
        <div class="ur-field">
          <label><?= $t('589|Ansat') ?></label>
          <select class="ur-select" name="ansat_id"<?= $ro ?>>
            <option value="0">–</option>
            <?php foreach ($vm['employees'] as $e) { ?>
            <option value="<?= (int) $e['id'] ?>"<?= $e['id'] === $u['ansat_id'] ? ' selected' : '' ?>><?= $h(trim($e['initialer'] . ' ' . $e['navn'])) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="ur-field">
          <label><?= $t('5570|Tilladte IP-adresser') ?></label>
          <input class="ur-input" type="text" name="ip_address" maxlength="49" value="<?= $h($u['ip']) ?>"<?= $ro ?>>
          <span class="ur-help"><?= $t('1904|Angiv brugerens tilladte IP adresser') ?></span>
        </div>
        <?php if (!$inviteNew) { ?>
        <div class="ur-field">
          <label><?= $t('52|E-mail') ?></label>
          <input class="ur-input" type="email" name="email" value="<?= $h($u['email']) ?>"<?= $ro ?>>
        </div>
        <?php } ?>
        <div class="ur-field">
          <label><?= $t('37|Telefon') ?></label>
          <input class="ur-input" type="tel" name="tlf" maxlength="16" value="<?= $h($u['tlf']) ?>"<?= $ro ?>>
        </div>
        <div class="ur-field ur-field-full ur-checks">
          <label class="ur-check"><input type="checkbox" name="twofactor" value="on"<?= $u['twofactor'] ? ' checked' : '' ?><?= $ro ?>><span><b><?= $t('5515|Tofaktor-login (kode via SMS eller e-mail)') ?></b><small><?= $t('5516|Tofaktor-login kræver et telefonnummer eller en e-mail') ?></small></span></label>
          <label class="ur-check"><input type="checkbox" name="revisor" value="on"<?= $u['isRevisor'] ? ' checked' : '' ?><?= $ro ?>><span><b><?= $t('5571|Denne bruger er regnskabets revisor') ?></b><small><?= $t('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang') ?></small></span></label>
        </div>
      </div>
      <?php if ($vm['canWrite']) { ?>
      <div class="ur-actions">
        <button class="ur-btn ur-btn-primary" type="submit"><i class='bx <?= $inviteNew ? 'bx-envelope' : 'bx-save' ?>'></i><?= $inviteNew ? $t('5795|Send invitation') : $t('3|Gem') ?></button>
      </div>
      <?php } ?>
    </form>
    <?php if ($u['review'] && $vm['canWrite']) { ?>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-confirm-use"><input type="hidden" name="action" value="confirm_role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="use" value="1"></form>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-confirm-keep"><input type="hidden" name="action" value="confirm_role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="use" value="0"></form>
    <?php } ?>
    <?php if ($inviteNew) { ?>
    <script>
    (function () {
    	var mail = document.getElementById('ur-invite-email'), name = document.getElementById('ur-brugernavn'), touched = false;
    	if (!mail || !name) { return; }
    	name.addEventListener('input', function () { touched = true; });
    	mail.addEventListener('input', function () { if (!touched) { name.value = mail.value.split('@')[0].slice(0, 80); } });
    })();
    </script>
    <?php } ?>
    <?php if ($vm['canWrite'] && !$isNew && !$u['invited'] && !$u['closed'] && $u['id'] !== $vm['selfId']) { ?>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-actions" data-confirm="<?= $t('5867|Send en midlertidig adgangskode til brugerens e-mail?') ?>">
      <input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <button class="ur-btn ur-btn-ghost" type="submit"<?= $u['email'] === '' ? ' disabled title="' . $t('5864|Brugeren har ingen gyldig e-mail') . '"' : '' ?>><i class='bx bx-key'></i><?= $t('5868|Nulstil adgangskode') ?></button>
    </form>
    <?php } ?>
    <?php if ($vm['canWrite'] && $u['invited']) { ?>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-actions">
      <input type="hidden" name="action" value="resend_invite"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <button class="ur-btn ur-btn-ghost" type="submit"><i class='bx bx-envelope'></i><?= $t('5781|Send invitation igen') ?></button>
    </form>
    <?php } ?>
    <?php if ($vm['canWrite'] && !$isNew && $u['id'] === $vm['selfId']) { ?>
    <p class="ur-help ur-danger"><?= $t('5846|Du kan ikke lukke din egen bruger.') ?></p>
    <?php } ?>
    <?php if ($vm['canWrite'] && !$isNew && $u['id'] !== $vm['selfId']) { ?>
    <div class="ur-danger">
      <p class="ur-help"><?= $t('5770|Lukkede brugere kan ikke logge ind og kan genåbnes senere.') ?></p>
      <?php if ($u['closed']) { ?>
      <form method="post" action="<?= $h($vm['selfUrl']) ?>">
        <input type="hidden" name="action" value="reopen_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <button class="ur-btn ur-btn-ghost" type="submit"><i class='bx bx-lock-open-alt'></i><?= $t('5757|Genåbn bruger') ?></button>
      </form>
      <?php } else { ?>
      <form method="post" action="<?= $h($vm['selfUrl']) ?>" data-confirm="<?= $t('5766|Luk bruger?') ?> <?= $h($u['brugernavn']) ?>">
        <input type="hidden" name="action" value="close_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <button class="ur-btn ur-btn-danger" type="submit"><i class='bx bx-lock-alt'></i><?= $t('5756|Luk bruger') ?></button>
      </form>
      <?php } ?>
      <?php if (!$u['hasLoggedIn']) { ?>
      <form method="post" action="<?= $h($vm['selfUrl']) ?>" data-confirm="<?= $t('5585|Slet bruger?') ?> <?= $h($u['brugernavn']) ?>">
        <input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <button class="ur-btn ur-btn-ghost ur-txt-danger" type="submit"><i class='bx bx-trash'></i><?= $t('1099|Slet') ?></button>
      </form>
      <?php } ?>
    </div>
    <?php } ?>
  </section>
	<?php
}

function ur_view_roles(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	?>
  <div class="ur-toolbar">
    <span class="ur-spacer"></span>
    <?php if ($vm['canRolesWrite']) { ?>
    <a class="ur-btn ur-btn-primary" href="<?= $link('tab=roles&rolle=0') ?>"><i class='bx bx-plus'></i><?= $t('5565|Ny rolle') ?></a>
    <?php } ?>
  </div>
  <div class="ur-roles">
    <?php foreach ($vm['roles'] as $role) {
    	$count = isset($vm['roleCounts'][$role['id']]) ? $vm['roleCounts'][$role['id']] : 0;
    ?>
    <div class="ur-card ur-rolecard<?= ($vm['editRole'] && $vm['editRole']['id'] === $role['id']) ? ' on' : '' ?>">
      <div class="ur-rolecard-head">
        <h3><i class='bx bx-shield-quarter'></i><?= $roleName($role) ?></h3>
        <?php if ($role['system']) { ?><span class="ur-tag"><?= $t('5564|Indbygget') ?></span><?php } ?>
      </div>
      <p class="ur-mut"><?= $h($role['beskrivelse']) ?></p>
      <div class="ur-rolecard-foot">
        <span class="ur-mut"><i class='bx bx-user'></i> <?= (int) $count ?> <?= $t('5549|Brugere') ?></span>
        <span class="ur-spacer"></span>
        <a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link('tab=roles&rolle=' . $role['id']) ?>"><i class='bx bx-edit-alt'></i><?= $vm['canRolesWrite'] ? $t('5567|Rediger') : $t('5569|Rettigheder') ?></a>
        <?php if ($vm['canRolesWrite']) { ?>
        <form method="post" action="<?= $h($vm['selfUrl']) ?>"><input type="hidden" name="action" value="copy_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"><button class="ur-btn ur-btn-ghost ur-btn-sm" type="submit"><i class='bx bx-copy'></i><?= $t('5566|Kopiér') ?></button></form>
        <?php if (!$role['system']) { ?>
        <form method="post" action="<?= $h($vm['selfUrl']) ?>" data-confirm="<?= $t('5586|Slet rolle?') ?> <?= $roleName($role) ?>"><input type="hidden" name="action" value="delete_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"><button class="ur-btn ur-btn-ghost ur-btn-sm ur-txt-danger" type="submit"<?= $count > 0 ? ' disabled' : '' ?>><i class='bx bx-trash'></i><?= $t('1099|Slet') ?></button></form>
        <?php } ?>
        <?php } ?>
      </div>
    </div>
    <?php } ?>
  </div>
	<?php
}

function ur_view_role_editor(array $vm, callable $h, callable $t, callable $link): void
{
	$role = $vm['editRole'];
	$isNew = ($role['id'] === 0);
	$ro = $vm['canRolesWrite'] ? '' : ' disabled';
	$rank = perm_level_rank();
	$registry = permission_registry();
	$name = $isNew ? '' : perm_role_name($role, $vm['sprogId']);
	?>
  <section class="ur-card ur-editor">
    <div class="ur-editor-head">
      <h2><i class='bx bx-shield-quarter'></i><?= $isNew ? $t('5565|Ny rolle') : $h($name) ?></h2>
      <a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link('tab=roles') ?>"><i class='bx bx-x'></i><?= $t('2172|Luk') ?></a>
    </div>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" autocomplete="off">
      <input type="hidden" name="action" value="save_role">
      <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
      <div class="ur-grid">
        <div class="ur-field">
          <label><?= $t('5531|Navn') ?></label>
          <input class="ur-input" type="text" name="navn" maxlength="80" value="<?= $h($role['system'] ? $name : $role['navn']) ?>" required<?= ($role['system'] ? ' readonly' : $ro) ?>>
        </div>
        <div class="ur-field">
          <label><?= $t('5568|Beskrivelse') ?></label>
          <input class="ur-input" type="text" name="beskrivelse" maxlength="500" value="<?= $h($role['beskrivelse']) ?>"<?= ($role['system'] ? ' readonly' : $ro) ?>>
        </div>
      </div>

      <div class="ur-matrix">
        <?php foreach (permission_groups() as $group => $groupLabel) { ?>
        <div class="ur-matrix-group">
          <h4><?= $t($groupLabel) ?><?php if ($group === 'settings') { ?><small><?= $t('5594|Farlige funktioner (kun administratorer som standard)') ?></small><?php } ?></h4>
          <?php foreach ($registry as $key => $def) {
          	if ($def['group'] !== $group) {
          		continue;
          	}
          	$level = isset($role['levels'][$key]) ? $role['levels'][$key] : 'none';
          	$own = isset($vm['ownLevels'][$key]) ? $vm['ownLevels'][$key] : 'none';
          ?>
          <div class="ur-matrix-row">
            <span class="ur-matrix-label"><?= $t($def['label']) ?><?php if ($def['dangerous']) { ?><i class='bx bx-lock-alt' title="<?= $t('5594|Farlige funktioner (kun administratorer som standard)') ?>"></i><?php } ?></span>
            <span class="ur-seg">
              <?php foreach (array('none', 'read', 'write') as $opt) {
              	$locked = ($rank[$opt] > $rank[$own]);
              ?>
              <label class="ur-seg-opt<?= $locked ? ' locked' : '' ?>"><input type="radio" name="level[<?= $h($key) ?>]" value="<?= $opt ?>"<?= $level === $opt ? ' checked' : '' ?><?= ($locked || $ro !== '') ? ' disabled' : '' ?>><span><?= $h(perm_level_label($opt, $vm['sprogId'])) ?></span></label>
              <?php } ?>
            </span>
          </div>
          <?php } ?>
        </div>
        <?php } ?>
      </div>
      <?php if ($vm['canRolesWrite']) { ?>
      <div class="ur-actions">
        <button class="ur-btn ur-btn-primary" type="submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
      </div>
      <?php } ?>
    </form>
    <?php if ($vm['canRolesWrite'] && !$isNew && $role['system']) { ?>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-danger" data-confirm="<?= $t('5767|Nulstil rollen til standard?') ?>">
      <input type="hidden" name="action" value="reset_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
      <button class="ur-btn ur-btn-ghost" type="submit"><i class='bx bx-reset'></i><?= $t('5717|Nulstil til standard') ?></button>
    </form>
    <?php } ?>
  </section>
	<?php
}

function ur_view_log(array $vm, callable $h, callable $t, callable $link): void
{
	$mode = $vm['enforceMode'];
	$f = $vm['logFilter'];
	$types = array(
		'login'      => '5810|Login og logud',
		'session'    => '5811|Sessioner',
		'user'       => '5549|Brugere',
		'role'       => '5550|Roller',
		'permission' => '5812|Rettigheder og sikkerhed',
		'setting'    => '122|Indstillinger',
	);
	?>
  <?php if ($vm['canRoles']) { ?>
  <section class="ur-card ur-editor">
    <div class="ur-editor-head">
      <h2><i class='bx bx-lock-alt'></i><?= $t('5595|Håndhævelse') ?></h2>
    </div>
    <p class="ur-mut" style="margin:0 0 12px"><?= $t('5601|Skift til "Afvis" først, når listen over sider uden nøgle er tom, og alle "ville være afvist"-hændelser er forventede.') ?></p>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-enforce">
      <input type="hidden" name="action" value="set_enforce">
      <label class="ur-check"><input type="radio" name="mode" value="log"<?= $mode === 'log' ? ' checked' : '' ?><?= $vm['canRolesWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5596|Log kun (intet blokeres)') ?></b></span></label>
      <label class="ur-check"><input type="radio" name="mode" value="deny"<?= $mode === 'deny' ? ' checked' : '' ?><?= $vm['canRolesWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5597|Afvis (standard-afvis er slået til)') ?></b></span></label>
      <?php if ($vm['canRolesWrite']) { ?><button class="ur-btn ur-btn-primary" type="submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button><?php } ?>
    </form>
    <div class="ur-grid" style="margin-top:16px">
      <div>
        <h4 class="ur-h4"><?= $t('5598|Sider uden rettighedsnøgle set i logperioden') ?> <span class="ur-count"><?= count($vm['unguarded']) ?></span></h4>
        <?php if ($vm['unguarded']) { ?>
        <ul class="ur-list"><?php foreach ($vm['unguarded'] as $r) { ?><li><code><?= $h($r['detaljer']) ?></code><span class="ur-mut"><?= (int) $r['antal'] ?> · <?= $h(substr((string) $r['sidst'], 0, 16)) ?></span></li><?php } ?></ul>
        <?php } else { ?><p class="ur-mut">–</p><?php } ?>
      </div>
      <div>
        <h4 class="ur-h4"><?= $t('5600|Ville være afvist') ?> <span class="ur-count"><?= count($vm['wouldDeny']) ?></span></h4>
        <?php if ($vm['wouldDeny']) { ?>
        <ul class="ur-list"><?php foreach ($vm['wouldDeny'] as $r) { ?><li><code><?= $h($r['detaljer']) ?></code><span class="ur-mut"><?= (int) $r['antal'] ?> · <?= $h(substr((string) $r['sidst'], 0, 16)) ?></span></li><?php } ?></ul>
        <?php } else { ?><p class="ur-mut">–</p><?php } ?>
      </div>
    </div>
  </section>
  <?php } ?>

  <form method="get" action="usersRoles.php" class="ur-logfilter">
    <?php if (strpos($vm['linkPrefix'], 'inframe=1') !== false) { ?><input type="hidden" name="inframe" value="1"><?php } ?>
    <input type="hidden" name="tab" value="log">
    <?php if ($f['wd']) { ?><input type="hidden" name="wd" value="1"><?php } ?>
    <label><span><?= $t('5797|Fra') ?></span><input class="ur-input" type="date" name="fra" value="<?= $h($f['fra']) ?>"></label>
    <label><span><?= $t('5798|Til') ?></span><input class="ur-input" type="date" name="til" value="<?= $h($f['til']) ?>"></label>
    <select class="ur-select ur-select-sm" name="bruger" aria-label="<?= $t('225|Brugernavn') ?>">
      <option value=""><?= $t('5799|Alle brugere') ?></option>
      <?php foreach ($vm['logOptions']['users'] as $name) { ?><option value="<?= $h($name) ?>"<?= $name === $f['bruger'] ? ' selected' : '' ?>><?= $h($name) ?></option><?php } ?>
    </select>
    <select class="ur-select ur-select-sm" name="type" aria-label="<?= $t('5582|Handling') ?>"<?= $f['wd'] ? ' disabled' : '' ?>>
      <option value=""><?= $t('5800|Alle handlinger') ?></option>
      <?php foreach ($types as $key => $label) { ?><option value="<?= $h($key) ?>"<?= $key === $f['type'] ? ' selected' : '' ?>><?= $t($label) ?></option><?php } ?>
    </select>
    <?php if ($vm['logOptions']['objects']) { ?>
    <select class="ur-select ur-select-sm" name="objekt" aria-label="<?= $t('5806|Objekt') ?>">
      <option value=""><?= $t('5801|Alle objekter') ?></option>
      <?php foreach ($vm['logOptions']['objects'] as $o) { ?><option value="<?= $h($o) ?>"<?= $o === $f['objekt'] ? ' selected' : '' ?>><?= $h($o) ?></option><?php } ?>
    </select>
    <?php } ?>
    <div class="ur-search"><i class='bx bx-search'></i><input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="<?= $t('5802|Søg i brugernavn og objekt…') ?>"></div>
    <button class="ur-btn ur-btn-primary" type="submit"><i class='bx bx-filter-alt'></i><?= $t('5803|Filtrér') ?></button>
    <a class="ur-btn ur-btn-ghost" href="<?= $link('tab=log') ?>"><?= $t('5804|Nulstil filtre') ?></a>
  </form>
  <div class="ur-toolbar">
    <a class="ur-btn ur-btn-sm <?= $f['wd'] ? 'ur-btn-primary' : 'ur-btn-ghost' ?>" href="<?= $link(ur_log_query($f, array('wd' => !$f['wd'], 'type' => '', 'side' => 0))) ?>"><i class='bx bx-block'></i><?= $t('5600|Ville være afvist') ?></a>
    <span class="ur-mut ur-grow"><?= $h(sprintf(findtekst('5845|Viser %s–%s', $vm['sprogId']), $vm['log'] ? $f['side'] * UR_LOG_PAGE + 1 : 0, $f['side'] * UR_LOG_PAGE + count($vm['log']))) ?></span>
    <?php if ($vm['canExport']) { ?><a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link(ur_log_query($f, array('side' => 0)) . '&export=csv') ?>" download><i class='bx bx-download'></i><?= $t('5805|Eksportér CSV') ?></a><?php } ?>
  </div>

  <div class="ur-card ur-table-wrap">
    <table class="ur-table">
      <thead><tr><th><?= $t('5581|Tidspunkt') ?></th><th><?= $t('225|Brugernavn') ?></th><th><?= $t('5582|Handling') ?></th><th><?= $t('5806|Objekt') ?></th><th><?= $t('5583|Detaljer') ?></th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($vm['log'] as $row) {
        	$handling = (string) $row['handling'];
        	$label = ur_action_label($handling);
        	$object = ur_log_object($row, $vm['userNames']);
        	$details = (string) $row['detaljer'];
        	if (!empty($row['setting_key'])) {
        		$details = trim((string) $row['setting_key'] . ': ' . (string) ifset($row, 'old_value', '') . ' → ' . (string) ifset($row, 'new_value', '') . ' ' . $details);
        	}
        ?>
        <tr>
          <td class="ur-mut ur-nowrap"><?= $h(substr((string) $row['tidspunkt'], 0, 19)) ?></td>
          <td><?= $h($row['brugernavn']) ?></td>
          <td class="ur-nowrap"><span class="ur-action ur-action-<?= $h(preg_replace('/[^a-z0-9]+/', '-', $handling)) ?>" title="<?= $h($handling) ?>"><?= $label !== '' ? $t($label) : $h($handling) ?></span></td>
          <td class="ur-mut"><?= $h($object) ?></td>
          <td class="ur-mut ur-details"><?php if (mb_strlen($details) > 90) { ?><details><summary><?= $h(mb_substr($details, 0, 90)) ?>…</summary><?= $h($details) ?></details><?php } else { ?><?= $h($details) ?><?php } ?></td>
          <td class="ur-mut ur-nowrap"><?= $h($row['ip']) ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
    <?php if (!$vm['log']) { ?><div class="ur-empty"><?= $t('5584|Ingen hændelser endnu') ?></div><?php } ?>
  </div>
  <?php if ($f['side'] > 0 || $vm['logMore']) { ?>
  <div class="ur-pager">
    <?php if ($f['side'] > 0) { ?><a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link(ur_log_query($f, array('side' => $f['side'] - 1))) ?>"><i class='bx bx-chevron-left'></i><?= $t('5808|Nyere') ?></a><?php } ?>
    <?php if ($vm['logMore']) { ?><a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link(ur_log_query($f, array('side' => $f['side'] + 1))) ?>"><?= $t('5807|Ældre') ?><i class='bx bx-chevron-right'></i></a><?php } ?>
  </div>
  <?php } ?>
	<?php
}

/**
 * Readable name of an audit action (text id), or '' for an action without one.
 */
function ur_action_label(string $handling): string
{
	$map = array(
		'login'                 => '5813|Logget ind',
		'login.success'         => '5813|Logget ind',
		'login.failed'          => '5814|Login afvist',
		'login.2fa_failed'      => '5815|Forkert tofaktor-kode',
		'login.ip_blocked'      => '5816|IP-adresse afvist',
		'logout'                => '5817|Logget ud',
		'session.revisor_open'  => '5818|Regnskab åbnet fra admin',
		'session.forced_logout' => '5819|Bruger logget ud af en anden',
		'user.created'          => '5820|Bruger oprettet',
		'user.updated'          => '5821|Bruger ændret',
		'user.deactivated'      => '5822|Bruger lukket',
		'user.reactivated'      => '5823|Bruger genåbnet',
		'user.deleted'          => '5824|Bruger slettet',
		'user.role_changed'     => '5825|Brugerens rolle skiftet',
		'user.invited'          => '5826|Bruger inviteret',
		'user.invite_resent'    => '5827|Invitation sendt igen',
		'user.password_set'     => '5828|Adgangskode valgt',
		'user.password'         => '5829|Adgangskode ændret',
		'user.password_reset'   => '5869|Adgangskode nulstillet',
		'user.role_confirmed'   => '5890|Rolle bekræftet',
		'user.twofactor'        => '5830|Tofaktor-login ændret',
		'user.2fa_changed'      => '5830|Tofaktor-login ændret',
		'user.contact'          => '5831|Kontaktoplysninger ændret',
		'user.settings'         => '5832|Personlige indstillinger ændret',
		'role.created'          => '5833|Rolle oprettet',
		'role.updated'          => '5834|Rolle ændret',
		'role.deleted'          => '5835|Rolle slettet',
		'role.reset'            => '5836|Rolle nulstillet',
		'permission.would_deny' => '5600|Ville være afvist',
		'would-deny'            => '5600|Ville være afvist',
		'unguarded'             => '5837|Side uden rettighedsnøgle',
		'denied'                => '5838|Adgang afvist',
		'csrf'                  => '5839|Ugyldig formular (CSRF)',
		'permissions.mode'      => '5599|Håndhævelsen er ændret',
		'setting.change'        => '5840|Indstilling ændret',
		'setting.action'        => '5841|Indstillingshandling udført',
		'revisor.set'           => '5842|Revisor valgt',
		'revisor.unset'         => '5843|Revisor fjernet',
		'audit.exported'        => '5844|Audit-log eksporteret',
	);
	return isset($map[$handling]) ? $map[$handling] : '';
}

/**
 * What an entry is about: a user by name, else type and id.
 *
 * @param array<int, string> $userNames
 */
function ur_log_object(array $row, array $userNames): string
{
	$type = (string) ifset($row, 'objekt_type', '');
	$id = (string) ifset($row, 'objekt_id', '');
	if ($type === 'bruger' && isset($userNames[(int) $id])) {
		return $userNames[(int) $id];
	}
	if ($type === '' && !empty($row['setting_key'])) {
		return (string) $row['section'];
	}
	return trim($type . ' ' . $id);
}
