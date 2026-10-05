<!doctype html>
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/main.php --- lap 4.1.1 --- 2025.05.10 ---
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
// Copyright (c) 2024-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 17042024 MMK - Added suport for reloading page, and keeping current URI, DELETED old system that didnt work
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20260630 CDX/NTR Fixed Lager/varer from refreshing once every time we try to access it.
//                  This possibly has changes across everything, but I have tested it, and it has also fixed rendering prematurely.
// 20260716 MJ      Tilfoejede Momsperioder-link i Finans-sidebar.
// 20260730 NTR - Added translation to momsperioder.
// 20260730 MJ Fjernede Momsperioder-link fra Finans-sidebaren; linket er nu en knap i regnskabsaar.php
// 20260902 CL/LH Indlejrede chaty_V2 support-chatbot (wuweiworkai.com/chaty-v2) i skallen
// 20260904 Sawaneh WP-1.6: update_iframe() tags iframe navigations with inframe=1 (context flag for hosted pages)
// 20260907 CDX/LH Fjernede gammel widget-loader, saa SALDI Assist kun indlaeses en gang
// 20260907 CDX/LH Preserve iframe navigation while merging the current shell integration.
// 20260907 CDX/LH Enable the saved-record bridge when the installation opts in.
// 20260910 Sawaneh JOB-128: hash sync raced its setTimeout(0) guard, so a page rendered on a POST
//                 response (kreditor split view, bare ordre.php URL) got reloaded from the hash as
//                 ordre.php?inframe=1 = empty new order. Track the shell-written hash explicitly and
//                 ignore the inframe flag when deciding whether the iframe already shows the target.
// 20260914 CDX/LH Removed the Guides sidebar entry and its popup.
// 20260916 Sawaneh Permanent topbar (variant A): user chip with personal settings, fiscal-year
//                  switch, who-is-online and log out (moved from the sidebar), help (guides) and
//                  notification bell shell. Markup/data in mainIncludes/topbar.php, css/topbar.css.
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20260922 Sawaneh Topbar spec 2026-09-17 step 1a: global cluster (language, Assist, PoS, chip with
//                  dashboard hide/edit + print), no breadcrumb, sidebar Kontakt/Print removed,
//                  Guides and Kassesystem entries added, widget's Assist entry hidden.
// 20260927 Sawaneh Step 1b: cluster placement top/sidebar (mount point + topbarApplyPlacement).
// 20260928 Sawaneh Phase 4: System → Settings opens systemdata/settings.php, shown per settings-group access.
// 20260928 Sawaneh After the page-change confirm, clear the iframe's docChange so its beforeunload does not ask twice.
// 20260930 Sawaneh check_permissions() moved to includes/std_func.php (roles spec §4.4).
// 20260930 Sawaneh Dashboard items in the user menu hidden, not greyed out, away from the dashboard (Adam).
// 20261002 Sawaneh Hand-over 2 Oct: sidebar "System" replaced by one entry "Indstillinger", Kontoplan under Finans (also for
//                  users with only that right), breadcrumb in the topbar on settings pages (settings redesign §8.0, decision 16).
// 20261004 Sawaneh topbarSetGear(): the gear in the sub-bar opens the settings section that governs the page in the frame (§8.11).
@session_start();
$s_id = session_id();

$css = "../css/sidebar_style.css?v=23";

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $db
 * @var string $version
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $rettigheder
 * @var mixed  $revisor
 * @var mixed  $regnaar
 * @var int    $sprog_id
 * @var string $regnskab
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */

include("../includes/connect.php");
include("../includes/license_func.php");
include(__DIR__ . "/mainIncludes/topbar.php");
// Must run while the master connection is active (the `online` table lives there).
$topbarOnlineRows = topbar_online_rows($s_id);
$permission_key = 'any';
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/stdFunc/dkDecimal.php");
$topbar = topbar_context($topbarOnlineRows, (string) $brugernavn, (int) $bruger_id, (string) $rettigheder, $revisor, $regnaar, (int) $sprog_id, (string) $regnskab);
include_once(__DIR__ . "/../systemdata/settingsRegistry.php");
$settingsGroups = settings_accessible_groups();


if (substr($brugernavn, 0, 11) == "debitoripad") {
  header('Location: ../debitoripad/await.php');
}

function brightenColor($color, $amount = 0.2) {
    // Remove # if present
    $color = ltrim($color, '#');
    
    // Convert hex to RGB
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    // Brighten each component
    $r = min(255, $r + ($amount * (255 - $r)));
    $g = min(255, $g + ($amount * (255 - $g)));
    $b = min(255, $b + ($amount * (255 - $b)));
    
    // Convert back to hex
    return '#' . sprintf('%02x%02x%02x', round($r), round($g), round($b));
}

?>

<script>
  // Simple cookie-based refresh listener
  function checkRefreshCookie() {
      const cookies = document.cookie.split(';');
      for (let cookie of cookies) {
          const [name, value] = cookie.trim().split('=');
          if (name === 'refresh_opener' && value === 'true') {
              // Clear the cookie and reload
              document.cookie = 'refresh_opener=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
              location.reload();
              return;
          }
      }
  }

  // Check every 1000ms for the cookie
  setInterval(checkRefreshCookie, 1000);
</script>
<style>
  .showMenu{
    background: <?php echo $buttonColor; ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .nav-links{
    background-color: <?php echo $buttonColor; ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }
  .sidebar .nav-links li:hover, .sidebar :not(.closed) .nav-links li.showMenu, .sidebar ul.nav-links li.active {
    background: <?php echo brightenColor($buttonColor, 0.2); ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .sidebar{
    background-color: <?php echo $buttonColor; ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .link_name{
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .sidebar a, .sidebar p{
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .sidebar .bx{
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .logo-img {
    width: 120px;
    height: 40px; /* ← REQUIRED */

    background-color: <?php echo $buttonTxtColor; ?> !important;

    -webkit-mask: url("../img/sidebar_logo.png") no-repeat center;
    mask: url("../img/sidebar_logo.png") no-repeat center;
    -webkit-mask-size: contain;
    mask-size: contain;
  }

  .sidebar:not(.closed) .nav-links li .sub-menu li a::before{
    background: <?php echo $buttonTxtColor; ?> !important;
  }
</style>

<meta charset="utf-8">
<title>Sidebar</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="icon" href="../img/saldiLogo.png">
<link href='../css/sidebar_style.css?v=24' rel='stylesheet'>
<link href='../css/topbar.css?v=13' rel='stylesheet'>
<meta name="viewport" content="width=device-width, initial-scale=0.8">

<div class="modalbg" onclick="
    document.getElementsByClassName('sidebar')[0].style.width=''; 
    document.getElementsByClassName('modalbg')[0].style.display='none'; 
  "></div>
<div class="sidebar">

  <div class="logo wide">
    <div class="logo-img"></div>
    <i id="icon-open" class="bx bxs-arrow-from-right"></i>
  </div>

  <div class="logo small" onclick="
      document.getElementsByClassName('sidebar')[0].style.width=''; 
      document.getElementsByClassName('modalbg')[0].style.display='none'; 
    ">
    <img class="logo-img" src="../img/sidebar_logo.png">
    <i id="icon-open" class='bx bxs-arrow-from-right'></i>
  </div>

  <ul class="nav-links top-links" style='margin-top: 1em; background: <?php echo $buttonColor; ?> !important; color: <?php echo $buttonTxtColor; ?> !important;'>
    <!-- Finans -->
    <li class="active">
      <a href="#" id="dashboard" style="background: <?php echo $buttonColor; ?> !important; color: <?php echo $buttonTxtColor; ?> !important;" onclick='clear_sidebar(); this.parentElement.classList.add("active"); update_iframe("/index/dashboard.php")'>
        <i class='bx bxs-dashboard'></i>
        <span class="link_name"><?php print findtekst('2224|Oversigt', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='clear_sidebar(); update_iframe("/index/dashboard.php")'><?php print findtekst('2224|Oversigt', $sprog_id); ?></a></li>
      </ul>
    </li>

    <li style="display: <?php if (check_permissions(array(0, 2, 3, 4))) {
                          echo 'block';
                        } else {
                          echo 'none';
                        } ?>">
      <div class="icon_link" id="finans">
        <a href="#">
          <i class='bx bx-coin-stack'></i>
          <span class="link_name"><?php print findtekst('600|Finans', $sprog_id); ?></span>
        </a>
        <i class='bx bxs-chevron-down arrow'> </i>
      </div>
      <ul class="sub-menu">
        <li><span class="link_name"><?php print findtekst('600|Finans', $sprog_id); ?></span></li>
        <?php
        if (check_permissions(array(2))) {
          echo '<li><a href="#" id="kladdeliste" onclick=\'update_iframe("/finans/kladdeliste.php")\'>' . findtekst('601|Kassekladde', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(3))) {
          echo '<li><a href="#" id="regnskab" onclick=\'update_iframe("/finans/regnskab.php")\'>' . findtekst('602|Regnskab', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(4))) {
          echo '<li><a href="#" id="rapport" onclick=\'update_iframe("/finans/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
        }
        // 20261002 Settings redesign decision 16: the chart of accounts is a daily tool and sits under Finans.
        if (check_permissions(array(0))) {
          echo '<li><a href="#" id="kontoplan" onclick=\'update_iframe("/systemdata/kontoplan.php")\'>' . findtekst('612|Kontoplan', $sprog_id) . '</a></li>';
        }
        ?>
      </ul>
    </li>

    <!-- Debitor -->
    <li style="display: <?php if (check_permissions(array(5, 6, 12))) {
                          echo 'block';
                        } else {
                          echo 'none';
                        } ?>">
      <div class="icon_link" id="debitor">
        <a href="#">
          <i class='bx bx-group'></i>
          <span class="link_name"><?php print findtekst('604|Debitor', $sprog_id); ?></span>
        </a>
        <i class='bx bxs-chevron-down arrow'> </i>
      </div>
      <ul class="sub-menu">
        <li><span class="link_name"><?php print findtekst('604|Debitor', $sprog_id); ?></span></li>
        <?php
        if (check_permissions(array(5))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/ordreliste.php?menu_entry=1&reset_context=1&valg=ordrer")\'>' . findtekst('605|Ordre', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(6))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/debitor.php")\'>' . findtekst('606|Konti', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(12))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(6))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/crmkalender.php")\'>CRM</a></li>';
        }
        ?>
      </ul>
    </li>
    <!-- Booking -->
    <?php
    if (is_feature_licensed('booking')) {
    ?>
      <li style="display: <?php if (check_permissions(array(6))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link">
          <a href="#">
            <i class='bx bx-calendar'></i>
            <span class="link_name"><?php print findtekst('1116|Booking', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst('1116|Booking', $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(6))) {
            echo '<li><a href="#" onclick=\'update_iframe("/rental/index.php?vare")\'>' . findtekst('2137|Udlejningsoversigt', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/index.php")\'>' . findtekst('2138|Daglig oversigt', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/settings.php")\'>' . findtekst('122|Indstillinger', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/daysoff.php")\'>' . findtekst('2140|Lukkedage', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/items.php")\'>' . findtekst('2141|Udlejningsvare', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/remote.php")\'>' . findtekst('2143|Ekstern booking', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/lookupcust.php")\'>' . findtekst('2142|Søg kundehistorik', $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      <?php } ?>
      <!-- Kreditor -->
      <?php if (is_feature_licensed('kreditor')) { ?>
      <li style="display: <?php if (check_permissions(array(7, 8, 13))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="kreditor">
          <a href="#">
            <i class='bx bx-archive-out'></i>
            <span class="link_name"><?php print findtekst('607|Kreditorer', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst('607|Kreditorer', $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(7))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/ordreliste.php")\'>' . findtekst('605|Ordre', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(8))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/kreditor.php")\'>' . findtekst('606|Konti', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(13))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>
      <?php } ?>

      <!-- Lager -->
      <?php if (is_feature_licensed('lager')) { ?>
      <li style="display: <?php if (check_permissions(array(9, 10, 15))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="lager">
          <a href="#">
            <i class='bx bx-package'></i>
            <span class="link_name"><?php print findtekst('608|Lager', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst('608|Lager', $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(9))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/varer.php")\'>' . findtekst('609|Varer', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(10))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/modtageliste.php")\'>' . findtekst('610|Varemodtagelse', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(15))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>
      <?php } ?>

      <!-- Indstillinger (settings redesign decision 16): one entry that opens the settings front page. Kontoplan is
           under Finans, POS-menuer under Indstillinger → Kasse, Sikkerhedskopi under Indstillinger → Import & eksport. -->
      <?php if ($settingsGroups) { ?>
      <li>
        <a href="#" id="indstillinger" onclick='clear_sidebar(); this.parentElement.classList.add("active"); update_iframe("/systemdata/settings.php")'>
          <i class='bx bx-cog'></i>
          <span class="link_name"><?php print findtekst('122|Indstillinger', $sprog_id); ?></span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="" href="#" onclick='clear_sidebar(); update_iframe("/systemdata/settings.php")'><?php print findtekst('122|Indstillinger', $sprog_id); ?></a></li>
        </ul>
      </li>
      <?php } ?>
  </ul>

  <ul class="nav-links">
    <?php if ($topbar['posUrl'] !== '' || $topbar['sagerUrl'] !== '') {
      $shortcutUrl = $topbar['posUrl'] !== '' ? $topbar['posUrl'] : $topbar['sagerUrl'];
      $shortcutLabel = $topbar['posUrl'] !== '' ? findtekst('5606|Kassesystem', $sprog_id) : findtekst('5607|Sagsstyring', $sprog_id);
    ?>
    <li>
      <a href="<?php print htmlspecialchars($shortcutUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_top">
        <i class='bx bx-store-alt'></i>
        <span class="link_name"><?php print $shortcutLabel; ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="<?php print htmlspecialchars($shortcutUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_top"><?php print $shortcutLabel; ?></a></li>
      </ul>
    </li>
    <?php } ?>
    <li>
      <a href="#" onclick="document.getElementById('guideOverlay').classList.add('active'); return false;">
        <i class='bx bx-book-open'></i>
        <span class="link_name"><?php print findtekst('5504|Guides', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick="document.getElementById('guideOverlay').classList.add('active'); return false;"><?php print findtekst('5504|Guides', $sprog_id); ?></a></li>
      </ul>
    </li>
  </ul>

  <div id="cluster-sidebar-mount"></div>
  <div id="desc-line">
    <p title="DB nummer <?php print $db; ?>">Saldi version <?php print $version; ?></p>
  </div>
</div>

<div class="guide-overlay" id="guideOverlay" onclick="if (event.target === this) { this.classList.remove('active'); }">
  <div class="guide-modal">
    <div class="guide-modal-head"><span><i class='bx bx-book-open'></i> <?php print findtekst('5504|Guides', $sprog_id); ?></span><button type="button" onclick="document.getElementById('guideOverlay').classList.remove('active')">&times;</button></div>
    <div class="topbar-pop-body">
      <a class="topbar-pop-item" href="../guides/pdf/finance_guide_da.pdf" target="_blank" rel="noopener"><i class='bx bx-coin-stack'></i><?php print findtekst('5505|Regnskabsguide', $sprog_id); ?><i class='bx bx-link-external topbar-pop-trail'></i></a>
      <a class="topbar-pop-item" href="../guides/pdf/scaffolding_guide_da.pdf" target="_blank" rel="noopener"><i class='bx bx-layer'></i><?php print findtekst('5506|Stilladsguide', $sprog_id); ?><i class='bx bx-link-external topbar-pop-trail'></i></a>
    </div>
  </div>
</div>

<section class="home-section">
  <?php topbar_render($topbar, (int) $sprog_id); ?>

  <div class="home-content">
    <iframe
      onLoad="
      document.title = 'Saldi - ' + this.contentWindow.document.title;
      topbarSetDashState(this.contentWindow.location.pathname);
      topbarSetCrumb(this.contentWindow);
      topbarSetGear(this.contentWindow);
      console.log('Locaiton', this.contentWindow.document.location.href);
      trigger_iframe_load();
      stopLoading();
      content_finished_loading(this);"
      id="iframe_a" src="-"
      name="iframe_a"
      title="Site"
      class="content-iframe"></iframe>
  </div>
  <div id="loadingBar">
    <div></div>
  </div>
</section>

<script>
  // ---- topbar (mainIncludes/topbar.php) ----
  function topbarCloseAll() {
    document.querySelectorAll('.topbar-pop.open').forEach((el) => el.classList.remove('open'));
    document.querySelectorAll('.topbar [aria-expanded="true"]').forEach((el) => el.setAttribute('aria-expanded', 'false'));
  }

  function topbarToggle(event, popId) {
    event.stopPropagation();
    const pop = document.getElementById(popId);
    const wasOpen = pop.classList.contains('open');
    topbarCloseAll();
    if (!wasOpen) {
      pop.classList.add('open');
      event.currentTarget.setAttribute('aria-expanded', 'true');
    }
  }

  function topbarToggleSub(event, subId) {
    event.stopPropagation();
    const sub = document.getElementById(subId);
    const open = sub.classList.toggle('open');
    event.currentTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      const active = sub.querySelector('.active');
      if (active) {
        active.scrollIntoView({ block: 'nearest' });
      }
    }
  }

  // Hamburger: collapse/expand the sidebar on desktop, open it as an overlay on phones.
  function topbarMenu() {
    topbarCloseAll();
    if (window.innerWidth <= 780) {
      document.getElementsByClassName('sidebar')[0].setAttribute('style', 'width: 210px !important; height: ' + (window.screen.availHeight + 1) + 'px');
      document.getElementsByClassName('modalbg')[0].style.display = 'block';
      return;
    }
    document.querySelector('.logo.wide').click();
  }

  // Carry the current in-app location through a topbar action (year, language) so
  // the user lands back on the same page (topbarAction.php redirects to main.php#<path>).
  function topbarSubmitReturn(form) {
    form.querySelector('input[name="return_hash"]').value = window.location.hash.replace(/^#/, '');
    return true;
  }

  // Dashboard items in the chip (Skjul/Rediger oversigt) only act on the dashboard itself.
  // Settings pages declare their trail in window.saldiBreadcrumb; every other page leaves the left side empty.
  function topbarSetCrumb(win) {
    const nav = document.getElementById('topbar-crumb');
    if (!nav) return;
    let trail = null;
    try { trail = win && Array.isArray(win.saldiBreadcrumb) ? win.saldiBreadcrumb : null; } catch (e) { trail = null; }
    nav.textContent = '';
    const entry = document.getElementById('indstillinger');
    if (entry) {
      if (trail && trail.length) { clear_sidebar(); entry.parentElement.classList.add('active'); }
      else { entry.parentElement.classList.remove('active'); }
    }
    if (!trail || !trail.length) { nav.hidden = true; return; }
    const items = [{ label: nav.dataset.company || '', url: '/index/dashboard.php' }].concat(trail);
    items.forEach((item, i) => {
      if (!item || !item.label) return;
      if (nav.childNodes.length) {
        const sep = document.createElement('i');
        sep.textContent = '/';
        sep.setAttribute('aria-hidden', 'true');
        nav.appendChild(sep);
      }
      const last = (i === items.length - 1) || !item.url;
      const el = document.createElement(last ? 'b' : 'a');
      el.textContent = item.label;
      if (last) {
        el.setAttribute('aria-current', 'page');
      } else {
        el.href = '#';
        el.addEventListener('click', (e) => { e.preventDefault(); update_iframe(item.url); });
      }
      nav.appendChild(el);
    });
    nav.hidden = false;
  }
  // Gear in the sub-bar (settings redesign §8.11): shown when a settings section names the page in the frame
  // (data-map from the registry, already filtered by the user's permissions); opens it with a way back.
  function topbarSetGear(win) {
    const gear = document.getElementById('topbar-gear');
    if (!gear) return;
    let map = {};
    try { map = JSON.parse(gear.dataset.map || '{}'); } catch (e) { map = {}; }
    const root = window.location.pathname.replace(/\/index\/main\.php$/, '');
    let page = '', search = '';
    try { page = win.location.pathname; search = win.location.search || ''; } catch (e) { page = ''; }
    if (page.indexOf(root + '/') === 0) { page = page.slice(root.length + 1); }
    const links = map[page];
    if (!links || !links.length) { gear.hidden = true; return; }
    gear.hidden = false;
    gear.title = links[0].label;
    gear.onclick = (e) => { e.preventDefault(); update_iframe(links[0].url + '&back=' + encodeURIComponent('/' + page + search)); };
  }
  function topbarSetDashState(path) {
    const onDash = /\/index\/dashboard\.php$/.test(path || '');
    document.querySelectorAll('.topbar-dash').forEach((el) => { el.hidden = !onDash; });
  }
  function topbarDashHide() {
    const iframe = document.querySelector('.content-iframe');
    const hidden = document.querySelector('.topbar-dash [class*="bx-show"]') !== null;
    topbarCloseAll();
    iframe.src = (location + '').split('/').splice(0, 4).join('/') + '/index/dashboard.php?inframe=1&hidden=' + (hidden ? '0' : '1');
    setTimeout(() => location.reload(), 600);
  }
  function topbarDashEdit() {
    const iframe = document.querySelector('.content-iframe');
    topbarCloseAll();
    try {
      const popup = iframe.contentWindow.document.getElementById('settingpopup');
      if (popup) { popup.style.display = 'block'; }
    } catch (e) { /* cross-document access blocked */ }
  }
  function topbarPrint() {
    topbarCloseAll();
    window.frames['iframe_a'].focus();
    window.frames['iframe_a'].print();
  }

  // Global cluster placement (spec 2.3): the same DOM node is mounted either in the
  // top bar or at the bottom of the sidebar; no page reload, the iframe is untouched.
  function topbarApplyPlacement(placement) {
    const cluster = document.getElementById('topbar-cluster');
    const header = document.getElementById('topbar');
    const mount = document.getElementById('cluster-sidebar-mount');
    const move = document.getElementById('topbar-move');
    topbarCloseAll();
    if (placement === 'sidebar') {
      mount.appendChild(cluster);
      document.documentElement.classList.add('cluster-sidebar');
      move.querySelector('span').textContent = move.dataset.toTop;
      move.title = move.dataset.toTop;
    } else {
      header.appendChild(cluster);
      document.documentElement.classList.remove('cluster-sidebar');
      move.querySelector('span').textContent = move.dataset.toSidebar;
      move.title = move.dataset.toSidebar;
    }
    header.dataset.placement = placement;
  }
  function topbarMovePlacement() {
    const next = document.getElementById('topbar').dataset.placement === 'sidebar' ? 'top' : 'sidebar';
    topbarApplyPlacement(next);
    const body = new URLSearchParams({ action: 'placement', placement: next });
    fetch('topbarAction.php', { method: 'POST', body: body, credentials: 'same-origin' }).catch(() => {});
  }
  if (document.getElementById('topbar').dataset.placement === 'sidebar') {
    topbarApplyPlacement('sidebar');
  }

  // Notification center (spec §3): polled from the shell every 60 s, rendered client-side.
  const topbarNotifIcons = { news: 'bx-news', warning: 'bx-error', suggestion: 'bx-bulb', system: 'bx-plug' };
  function topbarNotifLoad() {
    fetch('notifications.php', { credentials: 'same-origin' })
      .then((r) => r.ok ? r.json() : null)
      .then((data) => { if (data) { topbarNotifRender(data); } })
      .catch(() => {});
  }
  function topbarNotifRender(data) {
    const list = document.getElementById('topbar-notif-list');
    const btn = document.getElementById('topbar-bell-btn');
    const pop = document.getElementById('topbar-bell-pop');
    if (!list || !btn) { return; }
    let badge = btn.querySelector('.topbar-badge');
    if (data.unread > 0) {
      if (!badge) { badge = document.createElement('span'); badge.className = 'topbar-badge'; btn.appendChild(badge); }
      badge.textContent = data.unread > 99 ? '99+' : String(data.unread);
    } else if (badge) {
      badge.remove();
    }
    document.getElementById('topbar-notif-all').hidden = data.unread === 0;
    list.textContent = '';
    if (!data.items.length) {
      const empty = document.createElement('div');
      empty.className = 'topbar-empty';
      empty.innerHTML = "<i class='bx bx-bell-off'></i>";
      const span = document.createElement('span'); span.textContent = pop.dataset.empty; empty.appendChild(span);
      list.appendChild(empty);
      return;
    }
    data.items.forEach((n) => {
      const item = document.createElement('div');
      item.className = 'topbar-notif' + (n.unread ? ' unread' : '');
      item.addEventListener('click', () => topbarNotifRead(n.id, n.link));
      const ic = document.createElement('div');
      ic.className = 'topbar-notif-ic topbar-notif-' + n.type;
      ic.innerHTML = "<i class='bx " + (topbarNotifIcons[n.type] || 'bx-bell') + "'></i>";
      const txt = document.createElement('div');
      const t = document.createElement('div'); t.className = 'topbar-notif-t'; t.textContent = n.title;
      const b = document.createElement('div'); b.className = 'topbar-notif-b'; b.textContent = n.body;
      const a = document.createElement('div'); a.className = 'topbar-notif-time'; a.textContent = n.ago;
      txt.appendChild(t); if (n.body) { txt.appendChild(b); } txt.appendChild(a);
      item.appendChild(ic); item.appendChild(txt);
      list.appendChild(item);
    });
  }
  function topbarNotifRead(id, link) {
    const body = new URLSearchParams({ action: 'read', id: String(id) });
    fetch('notifications.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(() => topbarNotifLoad())
      .catch(() => {});
    if (link) {
      topbarCloseAll();
      update_iframe(link);
    }
  }
  topbarNotifLoad();
  setInterval(topbarNotifLoad, 60000);

  // SALDI Assist: the widget (chaty-v2) exposes window.SALDI_CHAT and mounts its own
  // launcher as a <li> in the lower sidebar menu. The cluster button opens the chat
  // directly (spec 2.1); the injected entry is collapsed, not removed, so the widget's
  // layout observer keeps working.
  function topbarOpenAssist() {
    topbarCloseAll();
    if (window.SALDI_CHAT && typeof window.SALDI_CHAT.open === 'function') {
      window.SALDI_CHAT.open();
      return;
    }
    const launcher = document.getElementById('saldi-chat-launcher');
    if (launcher) { launcher.click(); }
  }
  function topbarHideAssistEntry() {
    const launcher = document.getElementById('saldi-chat-launcher');
    const li = launcher ? launcher.closest('li') : null;
    if (li && !li.classList.contains('topbar-assist-hidden')) { li.classList.add('topbar-assist-hidden'); }
  }
  topbarHideAssistEntry();
  new MutationObserver(topbarHideAssistEntry).observe(document.querySelector('.sidebar'), { childList: true, subtree: true });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.topbar-pop, .topbar-item')) {
      topbarCloseAll();
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      topbarCloseAll();
    }
  });
  // A click inside the content iframe never reaches this document, but it does
  // move focus into the frame - close the panels on that instead.
  window.addEventListener('blur', topbarCloseAll);

  function setCookie(cname, cvalue, exdays) {
    console.log(cname, cvalue);
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue;
  }

  function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  }

  let arrow = document.querySelectorAll(".icon_link");

  for (var i = 0; i < arrow.length; i++) {
    arrow[i].addEventListener("click", (e) => {
      let arrowParent = e.target.parentElement;
      console.log(e);
      arrowParent.classList.toggle("showMenu");
    });
  }

  let sidebar = document.querySelector(".sidebar");
  let sidebarBtn = document.querySelector(".logo.wide");
  sidebarBtn.addEventListener("click", () => {
    sidebar.classList.toggle("closed");
    document.cookie = `isSidebarOpen=${sidebar.classList.contains("closed")}`
  });

  console.log(getCookie("isSidebarOpen"));
  if (getCookie("isSidebarOpen") === "true") {
    sidebar.classList.toggle("closed");
  }

  const get_iframe_path = () => {
    const iframe = document.querySelector(".content-iframe")
    if (!iframe || !iframe.contentWindow) return "";

    try {
      const url = new URL(iframe.contentWindow.location.href);
      return url.pathname + url.search;
    } catch (e) {
      return "";
    }
  }

  // Compare shell paths without the inframe flag: a page reached by an in-frame
  // redirect (ordre.php?id=X) has no inframe=1 yet still is the requested page.
  const strip_inframe = (path) => {
    try {
      const url = new URL(path, location.origin);
      url.searchParams.delete('inframe');
      return url.pathname + url.search;
    } catch (e) {
      return path;
    }
  }

  const update_iframe = (uri) => {
    const iframe = document.querySelector(".content-iframe")
    const baseUrl = (location + "").split("/").splice(0, 4).join("/");
    const targetUrl = baseUrl + (uri.startsWith("/") ? uri : "/" + uri);
    const parsedTargetUrl = new URL(targetUrl);
    // Context flag for the loaded page: it runs inside the shell's iframe, so
    // window.close()-based flows (luk.php) can't work and back targets must stay
    // in-frame. Set centrally here instead of on every menu link.
    if (!parsedTargetUrl.searchParams.has('inframe')) {
      parsedTargetUrl.searchParams.set('inframe', '1');
    }
    const targetPath = parsedTargetUrl.pathname + parsedTargetUrl.search;

    if (strip_inframe(get_iframe_path()) === strip_inframe(targetPath)) {
      return;
    }

    if (iframe.contentWindow?.docChange) {
      if (!window.confirm("Er du sikker på du gerne vil ændre side? Dine ændringer vil ikke blive gemt")) {
        return;
      }
      // Already confirmed: stop the page's own beforeunload dialog from asking again.
      iframe.contentWindow.docChange = false;
    }

    iframe.src = parsedTargetUrl.href
  }

  const redirect_uri = (uri) => {
    window.location = (location + "").split("/").splice(0, 4).join("/") + uri
  }

  // Check for page reloads and manage inital load of iframe
  update_iframe(window.location.hash == "" ? "/index/dashboard.php" : window.location.hash.replace("#", ""));

  // Hash the shell wrote itself from an iframe load. hashchange is dispatched
  // asynchronously, so a timer-based flag could reset before the event arrived
  // and the shell would then reload the iframe from the hash - fatal for pages
  // rendered straight on a POST response (e.g. kreditor split view), whose URL
  // carries no id and reloads as an empty form.
  let shellWrittenHash = null;
  addEventListener("hashchange", (event) => {
    const newHash = event.newURL.split("#")[1];
    if (shellWrittenHash !== null && newHash === shellWrittenHash) {
      shellWrittenHash = null;
      return;
    }
    if (newHash && newHash !== "/") {
      update_iframe(newHash);
    }
  });

  function trigger_iframe_load() {
    const iframe = document.querySelector(".content-iframe");
    const path = "/" + iframe.contentWindow.document.location.href.split("/").slice(4).join("/");

    if (window.location.hash !== "#" + path) {
      shellWrittenHash = path;
      window.location.hash = path;
    }

    setCookie('last-sidebar-location', path, 1);
  }

  document.addEventListener('DOMContentLoaded', function() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a");
    for (let i = 0; i < refs.length; i++) {
      refs[i].addEventListener('click', function() {
        clear_sidebar();
        this.classList.toggle('active');
      });
    }
  });

  function clear_sidebar() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a, ul.nav-links li");
    for (let i = 0; i < refs.length; i++) {
      refs[i].classList.remove('active');
    }
  }

  function startLoading() {
    var loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'block'; // Show the loading bar
  }

  function stopLoading() {
    var loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'none'; // Hide the loading bar
  }

  var content_finished_loading = function(iframe) {
    // inject the start loading handler when content finished loading
    iframe.contentWindow.onbeforeunload = startLoading;
  };

</script>

<style>
  /* The loading bar container */
  #loadingBar {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background-color: #f3f3f3;
    z-index: 9999;
    display: none;
    /* Initially hidden */
    overflow: hidden;
  }

  /* The loading bar itself, with cool back-and-forth animation */
  #loadingBar div {
    position: fixed;
    height: 100%;
    width: 0;
    background-color: #4caf50;
    animation: loadingAnimation 2s infinite ease-in-out;
  }

  /* Keyframes for back-and-forth animation */
  @keyframes loadingAnimation {
    0% {
      width: 0;
      left: 0;
    }

    50% {
      width: 100%;
      left: 0;
    }

    100% {
      width: 0;
      left: 100%;
    }
  }
</style>

<?php
/* SALDI Assist (support-chatbot). Loaderen hentes fra chatbottens server; token-
   endpointet ligger i includes/saldi_assist_token.php. SALDI_ASSIST_WIDGET_URL
   kan saettes i webserverens miljoe til en test-instans; standard er produktion. */
$assistWidgetUrl = getenv('SALDI_ASSIST_WIDGET_URL') ?: 'https://wuweiworkai.com/chaty-v2/widget.js';
$assistVersion = isset($version) ? (string)$version : '';
?>
<script src="../javascript/saldi-assist-navigate.js"></script>
<script>
  // update_iframe er en const i sidens script; goer den tilgaengelig for
  // navigate-hook'en, saa "Gaa dertil" gaar gennem SALDIs egen navigation
  // (inkl. advarslen om ugemte aendringer).
  if (typeof update_iframe === 'function') { window.update_iframe = update_iframe; }
</script>
<script src="<?= htmlspecialchars($assistWidgetUrl, ENT_QUOTES, 'UTF-8') ?>" data-widget-id="saldi" data-brand="SALDI" data-lang="da" data-app-version="<?= htmlspecialchars($assistVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script>window.SaldiAssist = { appVersion: <?= json_encode($assistVersion) ?>, correlationId: <?= json_encode($assist_correlation_id ?? null) ?>, errorCategory: <?= json_encode($assist_error_category ?? null) ?>, getContextToken: function (sessionHash) { return fetch('../includes/saldi_assist_token.php?embed_session=' + encodeURIComponent(sessionHash), {credentials:'same-origin'}).then(function (r) { return r.ok ? r.json() : null }).then(function (j) { return j && j.token ? j.token : null }) }, navigate: window.SaldiAssistNavigate };</script>
<?php if (getenv('SALDI_ASSIST_RECORDS_ENABLED') === '1') { ?>
<script src="../javascript/saldi-assist-records.js"></script>
<script>
  window.SaldiAssist.getRecordContext = window.SaldiAssistRecords.getRecordContext;
  window.SaldiAssist.highlightRows = window.SaldiAssistRecords.highlightRows;
</script>
<?php } ?>
</html>
