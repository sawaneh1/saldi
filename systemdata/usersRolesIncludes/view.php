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
	$flash = ur_flash($vm['msg'], $sprog);
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('5536|Brugere & roller', $sprog), 'UTF-8', $charset)) ?>;</script>
<div class="ur-page">
  <header class="ur-head">
    <div>
      <h1><i class='bx bx-group'></i><?= $t('5536|Brugere & roller') ?></h1>
      <p class="ur-sub"><?= $t('5591|Brugere uden rolle beholder deres nuværende rettigheder, indtil en rolle tildeles.') ?></p>
    </div>
    <a class="ur-btn ur-btn-ghost" href="<?= $h(function_exists('nav_back_url') ? nav_back_url('syssetup.php') : 'syssetup.php') ?>"><i class='bx bx-x'></i><?= $t('2172|Luk') ?></a>
  </header>

  <?php if ($flash) { ?>
  <div class="ur-flash ur-flash-<?= $h($flash['type']) ?>"><i class='bx <?= $flash['type'] === 'ok' ? 'bx-check-circle' : 'bx-error' ?>'></i><span><?= $h($flash['text']) ?></span></div>
  <?php } ?>

  <?php if (!$vm['tablesReady']) { ?>
  <div class="ur-flash ur-flash-err"><i class='bx bx-error'></i><span>Roles tables missing - log in again so includes/betweenUpdates.php can create them.</span></div>
  <?php } ?>

  <nav class="ur-tabs">
    <a class="<?= $vm['tab'] === 'users' ? 'on' : '' ?>" href="<?= $link('tab=users') ?>"><i class='bx bx-user'></i><?= $t('5549|Brugere') ?><span class="ur-count"><?= count($vm['users']) ?></span></a>
    <a class="<?= $vm['tab'] === 'roles' ? 'on' : '' ?>" href="<?= $link('tab=roles') ?>"><i class='bx bx-shield-quarter'></i><?= $t('5550|Roller') ?><span class="ur-count"><?= count($vm['roles']) ?></span></a>
    <a class="<?= $vm['tab'] === 'log' ? 'on' : '' ?>" href="<?= $link('tab=log') ?>"><i class='bx bx-history'></i><?= $t('5551|Log') ?></a>
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
		ur_view_log($vm, $h, $t);
	}
	?>
</div>
<script>
(function () {
	var search = document.getElementById('ur-search');
	if (search) {
		search.addEventListener('input', function () {
			var q = search.value.toLowerCase();
			var shown = 0;
			document.querySelectorAll('#ur-users tbody tr[data-search]').forEach(function (tr) {
				var hit = tr.dataset.search.indexOf(q) !== -1;
				tr.hidden = !hit;
				if (hit) { shown++; }
			});
			var empty = document.getElementById('ur-empty');
			if (empty) { empty.hidden = shown > 0; }
		});
	}
	var all = document.getElementById('ur-check-all');
	if (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('#ur-users tbody tr:not([hidden]) input[name="ids[]"]').forEach(function (cb) { cb.checked = all.checked; });
		});
	}
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
 * @return array{type: string, text: string}|null
 */
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

  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-bulk">
    <input type="hidden" name="action" value="bulk_role">
    <div class="ur-toolbar">
      <div class="ur-search"><i class='bx bx-search'></i><input type="search" id="ur-search" placeholder="<?= $t('5552|Søg bruger…') ?>" autocomplete="off"></div>
      <?php if ($vm['canWrite']) { ?>
      <div class="ur-bulk">
        <select class="ur-select" name="role_id">
          <option value="0"><?= $t('5560|Tildel rolle til valgte') ?></option>
          <?php foreach ($vm['roles'] as $role) { ?>
          <option value="<?= (int) $role['id'] ?>"><?= $roleName($role) ?></option>
          <?php } ?>
        </select>
        <button class="ur-btn ur-btn-ghost" type="submit"><i class='bx bx-user-check'></i><?= $t('1091|Opdater') ?></button>
      </div>
      <a class="ur-btn ur-btn-primary" href="<?= $link('tab=users&bruger=0') ?>"><i class='bx bx-plus'></i><?= $t('333|Ny bruger') ?></a>
      <?php } ?>
    </div>

    <div class="ur-card ur-table-wrap">
      <table class="ur-table" id="ur-users">
        <thead>
          <tr>
            <?php if ($vm['canWrite']) { ?><th class="ur-cb"><input type="checkbox" id="ur-check-all"></th><?php } ?>
            <th><?= $t('5531|Navn') ?></th>
            <th><?= $t('225|Brugernavn') ?></th>
            <th><?= $t('52|E-mail') ?></th>
            <th><?= $t('5553|Rolle') ?></th>
            <th class="ur-center">2FA</th>
            <th><?= $t('5556|Sidst aktiv') ?></th>
            <th><?= $t('5557|Status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vm['users'] as $u) {
          	$searchBlob = mb_strtolower($u['navn'] . ' ' . $u['brugernavn'] . ' ' . $u['email'] . ' ' . ($u['role'] ? perm_role_name($u['role'], $vm['sprogId']) : ''));
          ?>
          <tr data-search="<?= $h($searchBlob) ?>"<?= ($vm['editUser'] && $vm['editUser']['id'] === $u['id']) ? ' class="on"' : '' ?>>
            <?php if ($vm['canWrite']) { ?><td class="ur-cb"><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>"></td><?php } ?>
            <td><a class="ur-userlink" href="<?= $link('tab=users&bruger=' . $u['id']) ?>"><span class="ur-avatar"><?= $h(mb_strtoupper(mb_substr($u['initialer'] !== '' ? $u['initialer'] : $u['navn'], 0, 2))) ?></span><?= $h($u['navn']) ?><?php if ($u['isRevisor']) { ?><span class="ur-tag"><?= $t('2562|Revisor') ?></span><?php } ?></a></td>
            <td class="ur-mut"><?= $h($u['brugernavn']) ?></td>
            <td class="ur-mut"><?= $h($u['email']) ?></td>
            <td>
              <?php if ($u['role']) { ?>
              <span class="ur-role"><?= $roleName($u['role']) ?></span>
              <?php } else { ?>
              <span class="ur-role ur-role-none"><?= $t('5554|Ingen rolle') ?></span>
              <?php if ($u['suggestion']) { ?><span class="ur-suggest"><?= $t('5555|Forslag') ?>: <?= $roleName($u['suggestion']) ?></span><?php } ?>
              <?php } ?>
            </td>
            <td class="ur-center"><?php if ($u['twofactor']) { ?><i class='bx bxs-check-shield ur-ok' title="2FA"></i><?php } else { ?><span class="ur-mut">–</span><?php } ?></td>
            <td class="ur-mut"><?= $u['lastLogin'] !== '' ? $h(substr($u['lastLogin'], 0, 16)) : '–' ?></td>
            <td><?php if ($u['closed']) { ?><span class="ur-status ur-status-closed"><?= $t('5559|Lukket') ?></span><?php } else { ?><span class="ur-status ur-status-ok"><?= $t('5558|Aktiv') ?></span><?php } ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <div class="ur-empty" id="ur-empty" hidden><?= $t('5593|Ingen brugere fundet') ?></div>
    </div>
  </form>
	<?php
}

function ur_view_user_card(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	$u = $vm['editUser'];
	$isNew = ($u['id'] === 0);
	$ro = $vm['canWrite'] ? '' : ' disabled';
	?>
  <section class="ur-card ur-editor">
    <div class="ur-editor-head">
      <h2><i class='bx <?= $isNew ? 'bx-user-plus' : 'bx-user' ?>'></i><?= $isNew ? $t('333|Ny bruger') : $h($u['navn']) ?></h2>
      <a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link('tab=users') ?>"><i class='bx bx-x'></i><?= $t('2172|Luk') ?></a>
    </div>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" autocomplete="off">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <div class="ur-grid">
        <div class="ur-field">
          <label><?= $t('225|Brugernavn') ?></label>
          <input class="ur-input" type="text" name="brugernavn" maxlength="80" value="<?= $h($u['brugernavn']) ?>" required<?= $ro ?>>
        </div>
        <div class="ur-field">
          <label><?= $t('5553|Rolle') ?></label>
          <select class="ur-select" name="role_id"<?= $ro ?>>
            <option value="0"><?= $t('5554|Ingen rolle') ?><?= ($u['rettigheder'] !== '' && $u['role_id'] === 0) ? ' (' . $t('5588|Tilpasset') . ')' : '' ?></option>
            <?php foreach ($vm['roles'] as $role) {
            	$assignable = perm_within_own(perm_levels_from_role($role['id']));
            ?>
            <option value="<?= (int) $role['id'] ?>"<?= $role['id'] === $u['role_id'] ? ' selected' : '' ?><?= $assignable ? '' : ' disabled' ?>><?= $roleName($role) ?><?= $assignable ? '' : ' 🔒' ?></option>
            <?php } ?>
          </select>
          <?php if ($u['suggestion'] && $u['role_id'] === 0) { ?><span class="ur-help"><?= $t('5555|Forslag') ?>: <?= $roleName($u['suggestion']) ?></span><?php } ?>
        </div>
        <div class="ur-field">
          <label><?= $t('324|Adgangskode') ?></label>
          <input class="ur-input" type="password" name="kode" autocomplete="new-password"<?= $ro ?>>
          <?php if (!$isNew) { ?><span class="ur-help"><?= $t('5525|Lad felterne stå tomme for at beholde din adgangskode') ?></span><?php } ?>
        </div>
        <div class="ur-field">
          <label><?= $t('328|Gentag adgangskode') ?></label>
          <input class="ur-input" type="password" name="kode2" autocomplete="new-password"<?= $ro ?>>
        </div>
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
        <div class="ur-field">
          <label><?= $t('52|E-mail') ?></label>
          <input class="ur-input" type="email" name="email" value="<?= $h($u['email']) ?>"<?= $ro ?>>
        </div>
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
        <button class="ur-btn ur-btn-primary" type="submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
      </div>
      <?php } ?>
    </form>
    <?php if ($vm['canWrite'] && !$isNew && $u['id'] !== $vm['selfId']) { ?>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-danger" data-confirm="<?= $t('5585|Slet bruger?') ?> <?= $h($u['brugernavn']) ?>">
      <input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <button class="ur-btn ur-btn-danger" type="submit"><i class='bx bx-trash'></i><?= $t('1099|Slet') ?></button>
    </form>
    <?php } ?>
  </section>
	<?php
}

function ur_view_roles(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	?>
  <div class="ur-toolbar">
    <span class="ur-spacer"></span>
    <?php if ($vm['canWrite']) { ?>
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
        <a class="ur-btn ur-btn-ghost ur-btn-sm" href="<?= $link('tab=roles&rolle=' . $role['id']) ?>"><i class='bx bx-edit-alt'></i><?= $vm['canWrite'] ? $t('5567|Rediger') : $t('5569|Rettigheder') ?></a>
        <?php if ($vm['canWrite']) { ?>
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
	$ro = $vm['canWrite'] ? '' : ' disabled';
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
          <input class="ur-input" type="text" name="navn" maxlength="80" value="<?= $h($role['navn']) ?>" required<?= $ro ?>>
        </div>
        <div class="ur-field">
          <label><?= $t('5568|Beskrivelse') ?></label>
          <input class="ur-input" type="text" name="beskrivelse" maxlength="500" value="<?= $h($role['beskrivelse']) ?>"<?= $ro ?>>
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
      <?php if ($vm['canWrite']) { ?>
      <div class="ur-actions">
        <button class="ur-btn ur-btn-primary" type="submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
      </div>
      <?php } ?>
    </form>
  </section>
	<?php
}

function ur_view_log(array $vm, callable $h, callable $t): void
{
	$mode = $vm['enforceMode'];
	?>
  <section class="ur-card ur-editor">
    <div class="ur-editor-head">
      <h2><i class='bx bx-lock-alt'></i><?= $t('5595|Håndhævelse') ?></h2>
    </div>
    <p class="ur-mut" style="margin:0 0 12px"><?= $t('5601|Skift til "Afvis" først, når listen over sider uden nøgle er tom, og alle "ville være afvist"-hændelser er forventede.') ?></p>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-enforce">
      <input type="hidden" name="action" value="set_enforce">
      <label class="ur-check"><input type="radio" name="mode" value="log"<?= $mode === 'log' ? ' checked' : '' ?><?= $vm['canWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5596|Log kun (intet blokeres)') ?></b></span></label>
      <label class="ur-check"><input type="radio" name="mode" value="deny"<?= $mode === 'deny' ? ' checked' : '' ?><?= $vm['canWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5597|Afvis (standard-afvis er slået til)') ?></b></span></label>
      <?php if ($vm['canWrite']) { ?><button class="ur-btn ur-btn-primary" type="submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button><?php } ?>
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

  <div class="ur-card ur-table-wrap">
    <p class="ur-mut ur-pad"><?= $t('5592|Kun de seneste 200 hændelser vises') ?></p>
    <table class="ur-table">
      <thead><tr><th><?= $t('5581|Tidspunkt') ?></th><th><?= $t('225|Brugernavn') ?></th><th><?= $t('5582|Handling') ?></th><th><?= $t('5583|Detaljer') ?></th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($vm['log'] as $row) { ?>
        <tr>
          <td class="ur-mut ur-nowrap"><?= $h(substr((string) $row['tidspunkt'], 0, 19)) ?></td>
          <td><?= $h($row['brugernavn']) ?></td>
          <td><span class="ur-action ur-action-<?= $h(str_replace('.', '-', (string) $row['handling'])) ?>"><?= $h($row['handling']) ?></span></td>
          <td class="ur-mut"><?= $h($row['detaljer']) ?></td>
          <td class="ur-mut ur-nowrap"><?= $h($row['ip']) ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
    <?php if (!$vm['log']) { ?><div class="ur-empty"><?= $t('5584|Ingen hændelser endnu') ?></div><?php } ?>
  </div>
	<?php
}
