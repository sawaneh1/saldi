<?php
// ---- includes/settings/rowHooks.php --- lap 5.0.0 --- 2026.10.05 ---
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
// 20261005 Sawaneh Settings 4d G1.2 fiscal years and G2.3 currencies on the row editor: derived cells, row actions,
//                  usage, the year creation shared with the onboarding guide (onboarding spec step 3 and §153: one
//                  function, no copy of regnskabskort.php), and exchange-rate changes that show the postings they
//                  make and need a confirmation before they are booked (audit V9).
// 20261006 Sawaneh 4c G5.3 variants: type options for the values filter, value list per type, usage from variant items,
//                  a type's values deleted with it.
// 20261006 Sawaneh Onboarding part 1: settings_fiscal_year_set_first() gives the first year a new period while nothing
//                  is posted; the date checks are shared with the year creation.

// ---------------------------------------------------------------- fiscal years (grupper art RA)

/**
 * First and last day of a fiscal year row.
 *
 * @return array{0: string, 1: string}
 */
function settings_fy_dates(array $raw): array
{
	$sm = max(1, min(12, (int) $raw['box1']));
	$em = max(1, min(12, (int) $raw['box3']));
	$start = sprintf('%04d-%02d-01', (int) $raw['box2'], $sm);
	$end = date('Y-m-t', mktime(0, 0, 0, $em, 1, (int) $raw['box4']));
	return array($start, $end);
}

function settings_fy_deleted(array $raw): bool
{
	return isset($raw['box10']) && trim((string) $raw['box10']) !== '';
}

function settings_fy_count(string $sql): int
{
	$r = db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['n'] : 0;
}

/**
 * What keeps a fiscal year from being deleted as an empty year (spec G1.2, audit F3): bookings in its period, being
 * somebody's active year, or a later year existing (years are numbered in sequence).
 *
 * @return array<int, array{0: int, 1: string}>
 */
function settings_fiscal_year_usage(array $row): array
{
	global $regnaar;
	$raw = $row['raw'];
	if (settings_fy_deleted($raw)) {
		return array();
	}
	$k = (int) $raw['kodenr'];
	list($s, $e) = settings_fy_dates($raw);
	$out = array();
	$n = settings_fy_count("select count(*) as n from transaktioner where transdate >= '$s' and transdate <= '$e'");
	$out[] = array($n, sprintf(st_txt(6423), $n));
	$n = settings_fy_count("select count(*) as n from kassekladde where transdate >= '$s' and transdate <= '$e'");
	$out[] = array($n, sprintf(st_txt(6566), $n));
	$n = settings_fy_count("select count(*) as n from ordrer where (fakturadate >= '$s' and fakturadate <= '$e') or (fakturadate is null and ordredate >= '$s' and ordredate <= '$e')");
	$out[] = array($n, sprintf(st_txt(6422), $n));
	$n = settings_fy_count("select count(*) as n from openpost where transdate >= '$s' and transdate <= '$e'");
	$out[] = array($n, sprintf(st_txt(6568), $n));
	$n = settings_fy_count("select count(*) as n from brugere where cast(regnskabsaar as text) = '$k'");
	$out[] = array($n, sprintf(st_txt(6567), $n));
	if ($k === (int) $regnaar) {
		$out[] = array(1, st_txt(6569));
	}
	if (settings_fy_count("select count(*) as n from grupper where art = 'RA' and cast(kodenr as integer) > $k")) {
		$out[] = array(1, st_txt(6570));
	}
	return $out;
}

/**
 * Why the oldest year may not be deleted with its data yet ('' when it may): five years after its end, oldest first,
 * closed for posting, nobody's active year (old regnskabsaar.php rules plus audit F15).
 */
function settings_fy_archive_refusal(array $raw): string
{
	global $regnaar;
	if (settings_fy_deleted($raw)) {
		return st_txt(5719);
	}
	$k = (int) $raw['kodenr'];
	list(, $e) = settings_fy_dates($raw);
	if (strtotime($e . ' +5 years') >= time()) {
		return st_txt(6577);
	}
	if (settings_fy_count("select count(*) as n from grupper where art = 'RA' and cast(kodenr as integer) < $k and coalesce(box10, '') = ''")) {
		return st_txt(6578);
	}
	if (trim((string) $raw['box5']) === 'on') {
		return st_txt(6554);
	}
	if ($k === (int) $regnaar) {
		return st_txt(6569);
	}
	$n = settings_fy_count("select count(*) as n from brugere where cast(regnskabsaar as text) = '$k'");
	if ($n) {
		return sprintf(st_txt(6567), $n);
	}
	return '';
}

/**
 * Make $year the active fiscal year: for this user (default for the next login and the session row in the master
 * `online` table, as the top bar's year switch does), or for every user of the company.
 */
function settings_fy_switch_year(int $year, bool $everybody): void
{
	global $bruger_id, $revisor, $s_id, $db, $db_id, $brugernavn;
	if ($everybody) {
		db_modify("update brugere set regnskabsaar = '$year'", __FILE__ . " linje " . __LINE__);
		db_modify("update online set regnskabsaar = '$year' where db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__, true);
		return;
	}
	if (empty($revisor) && (int) $bruger_id > 0) {
		db_modify("update brugere set regnskabsaar = '$year' where id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__);
	}
	db_modify("update online set regnskabsaar = '$year' where session_id = '" . db_escape_string((string) $s_id) . "'", __FILE__ . " linje " . __LINE__, true);
	if (!empty($revisor) && isset($db_id)) {
		db_modify("update revisor set regnskabsaar = '$year' where brugernavn = '" . db_escape_string((string) $brugernavn) . "' and db_id = '" . (int) $db_id . "'", __FILE__ . " linje " . __LINE__, true);
	}
}

/**
 * The suggestion for the next fiscal year: right after the latest one, twelve months long; or the calendar year when
 * there is none yet.
 *
 * @return array{kodenr: int, start_month: int, start_year: int, end_month: int, end_year: int, first: bool}
 */
function settings_fiscal_year_suggestion(): array
{
	$r = db_fetch_array(db_select("select * from grupper where art = 'RA' order by cast(kodenr as integer) desc limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		$y = (int) date('Y');
		return array('kodenr' => 1, 'start_month' => 1, 'start_year' => $y, 'end_month' => 12, 'end_year' => $y, 'first' => true);
	}
	$em = (int) $r['box3'];
	$ey = (int) $r['box4'];
	$sm = $em === 12 ? 1 : $em + 1;
	$sy = $em === 12 ? $ey + 1 : $ey;
	return array('kodenr' => (int) $r['kodenr'] + 1, 'start_month' => $sm, 'start_year' => $sy, 'end_month' => $em, 'end_year' => $ey + 1, 'first' => false);
}

/**
 * What is wrong with a fiscal-year period: [text id, args], or null when it is valid.
 *
 * @return array{0: int, 1: array<int, mixed>}|null
 */
function settings_fy_period_error(int $startMonth, int $startYear, int $endMonth, int $endYear): ?array
{
	$lo = (int) date('Y') - 20;
	$hi = (int) date('Y') + 10;
	if ($startMonth < 1 || $startMonth > 12 || $endMonth < 1 || $endMonth > 12) {
		return array(6562, array());
	}
	if ($startYear < $lo || $startYear > $hi || $endYear < $lo || $endYear > $hi) {
		return array(6563, array($lo, $hi));
	}
	if ($endYear * 100 + $endMonth <= $startYear * 100 + $startMonth) {
		return array(6561, array());
	}
	return null;
}

/**
 * The first fiscal year's period while nothing is posted yet (shared with the onboarding guide): the ledger's only year
 * gets the new start and end, or the first year is created when there is none.
 *
 * @return array{error: int|null, error_args: array<int, mixed>, kodenr: int, id: int}
 */
function settings_fiscal_year_set_first(int $startMonth, int $startYear, int $endMonth, int $endYear): array
{
	$err = settings_fy_period_error($startMonth, $startYear, $endMonth, $endYear);
	if ($err) {
		return array('error' => $err[0], 'error_args' => $err[1], 'kodenr' => 0, 'id' => 0);
	}
	$q = db_select("select * from grupper where art = 'RA' order by cast(kodenr as integer)", __FILE__ . " linje " . __LINE__);
	$years = array();
	while ($r = db_fetch_array($q)) {
		$years[] = $r;
	}
	if (!$years) {
		return settings_fiscal_year_create($startMonth, $startYear, $endMonth, $endYear, '', true);
	}
	if (count($years) > 1 || settings_fy_count("select count(*) as n from transaktioner")) {
		return array('error' => 6746, 'error_args' => array(), 'kodenr' => 0, 'id' => 0);
	}
	$r = $years[0];
	$old = json_encode(array('start' => sprintf('%02d-%04d', (int) $r['box1'], (int) $r['box2']), 'slut' => sprintf('%02d-%04d', (int) $r['box3'], (int) $r['box4'])));
	$new = json_encode(array('start' => sprintf('%02d-%04d', $startMonth, $startYear), 'slut' => sprintf('%02d-%04d', $endMonth, $endYear)));
	if ($old !== $new) {
		$oldName = (string) $r['box2'] . ((string) $r['box2'] !== (string) $r['box4'] ? '/' . $r['box4'] : '');
		$name = trim((string) $r['beskrivelse']) === '' || trim((string) $r['beskrivelse']) === $oldName
			? (string) $startYear . ($startYear !== $endYear ? '/' . $endYear : '') : (string) $r['beskrivelse'];
		db_modify("update grupper set beskrivelse = '" . db_escape_string($name) . "', box1 = '" . sprintf('%02d', $startMonth) . "', box2 = '$startYear', box3 = '"
			. sprintf('%02d', $endMonth) . "', box4 = '$endYear' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		SettingsService::auditRow('company.fiscal_years', 'years', 'company.fiscal_years.years#' . (int) $r['kodenr'], $old, $new, 'setting.row_updated');
	}
	return array('error' => null, 'error_args' => array(), 'kodenr' => (int) $r['kodenr'], 'id' => (int) $r['id']);
}

/**
 * Create a fiscal year (spec G1.2; shared with the onboarding guide). After the first year the start is fixed to the
 * month after the latest year, the year-dependent groups and the chart of accounts are copied from it, and balance
 * accounts open with last year's balance until the opening balance is reviewed. The first year also gets the voucher
 * defaults the old first-year page saved (RB).
 *
 * @return array{error: int|null, error_args: array<int, mixed>, kodenr: int, id: int}
 */
function settings_fiscal_year_create(int $startMonth, int $startYear, int $endMonth, int $endYear, string $description, bool $postingAllowed): array
{
	$fail = function (int $textId, array $args = array()) {
		return array('error' => $textId, 'error_args' => $args, 'kodenr' => 0, 'id' => 0);
	};
	$sug = settings_fiscal_year_suggestion();
	if (!$sug['first']) {
		$startMonth = $sug['start_month'];
		$startYear = $sug['start_year'];
	}
	$err = settings_fy_period_error($startMonth, $startYear, $endMonth, $endYear);
	if ($err) {
		return $fail($err[0], $err[1]);
	}
	$description = trim($description);
	if ($description === '') {
		$description = (string) $startYear . ($startYear !== $endYear ? '/' . $endYear : '');
	}
	$kodenr = (int) $sug['kodenr'];
	$prev = $kodenr - 1;
	if ($prev > 0 && function_exists('genberegn')) {
		genberegn($prev);
	}
	transaktion('begin');
	db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('" . db_escape_string($description) . "', '$kodenr', '', 'RA', '"
		. sprintf('%02d', $startMonth) . "', '$startYear', '" . sprintf('%02d', $endMonth) . "', '$endYear', '" . ($postingAllowed ? 'on' : '') . "')", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select id from grupper where art = 'RA' and cast(kodenr as integer) = $kodenr order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$id = $r ? (int) $r['id'] : 0;
	if ($prev > 0) {
		// Portable copies (no temporary table, audit F6), every column the table has so newer ones are not lost.
		if (!settings_fy_count("select count(*) as n from grupper where fiscal_year = $kodenr and art != 'RA'")) {
			$cols = settings_fy_columns('grupper', array('id', 'fiscal_year'));
			db_modify("insert into grupper (" . implode(', ', $cols) . ", fiscal_year) select " . implode(', ', $cols) . ", $kodenr from grupper where fiscal_year = $prev and art != 'RA' order by id", __FILE__ . " linje " . __LINE__);
		}
		if (!settings_fy_count("select count(*) as n from kontoplan where regnskabsaar = $kodenr")) {
			$cols = settings_fy_columns('kontoplan', array('id', 'regnskabsaar', 'primo', 'saldo'));
			db_modify("insert into kontoplan (" . implode(', ', $cols) . ", regnskabsaar, primo) select " . implode(', ', $cols) . ", $kodenr, case when kontotype = 'S' then coalesce(saldo, 0) else 0 end from kontoplan where regnskabsaar = $prev order by kontonr", __FILE__ . " linje " . __LINE__);
		}
	} elseif (!settings_fy_count("select count(*) as n from grupper where art = 'RB'")) {
		db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('Regnskabsbilag', '1', '1', 'RB', '1', '1', '', 'on', 'on')", __FILE__ . " linje " . __LINE__);
	}
	SettingsService::auditRow('company.fiscal_years', 'years', 'company.fiscal_years.years#' . $kodenr, '', json_encode(array('beskrivelse' => $description, 'start' => sprintf('%02d-%04d', $startMonth, $startYear), 'slut' => sprintf('%02d-%04d', $endMonth, $endYear), 'box5' => $postingAllowed ? 'on' : ''), JSON_UNESCAPED_UNICODE), 'setting.row_created');
	transaktion('commit');
	return array('error' => null, 'error_args' => array(), 'kodenr' => $kodenr, 'id' => $id);
}

/**
 * The columns of a table except some (for copying rows).
 *
 * @return array<int, string>
 */
function settings_fy_columns(string $table, array $except): array
{
	$cols = array();
	$q = db_select("select column_name from information_schema.columns where table_name = '" . db_escape_string($table) . "' order by ordinal_position", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r['column_name'], $except, true)) {
			$cols[] = $r['column_name'];
		}
	}
	return $cols;
}

/**
 * The create card under the year list (and the only content for a ledger without years).
 */
function settings_fiscal_year_card(array $c, string $tableId): void
{
	$sug = settings_fiscal_year_suggestion();
	$old = isset($_SESSION['settings_create']) && is_array($_SESSION['settings_create']) ? $_SESSION['settings_create'] : array();
	unset($_SESSION['settings_create']);
	$v = function (string $k, $default) use ($old) {
		return isset($old[$k]) ? (string) $old[$k] : (string) $default;
	};
	$f = 'st-create-' . $tableId;
	$months = array();
	for ($m = 1; $m <= 12; $m++) {
		$months[$m] = sprintf('%02d', $m);
	}
	?>
        <div class="st-rhead" id="create">
          <h2><?= st_t(508) ?></h2>
          <p class="st-rhelp"><?= st_t($sug['first'] ? 6560 : 6558) ?></p>
        </div>
        <div class="st-card st-create">
          <div class="st-create-grid">
            <label><span><?= st_t(914) ?></span><input class="st-input" form="<?= $f ?>" name="beskrivelse" maxlength="60" value="<?= st_h($v('beskrivelse', '')) ?>" placeholder="<?= st_h($sug['start_year'] . ($sug['start_year'] !== $sug['end_year'] ? '/' . $sug['end_year'] : '')) ?>"></label>
            <label><span><?= st_t(6559) ?></span>
			<?php if ($sug['first']) { ?>
              <span class="st-create-pair"><select class="st-input st-input-short" form="<?= $f ?>" name="start_month" aria-label="<?= st_t(1217) ?>"><?php foreach ($months as $m => $lab) { ?><option value="<?= $m ?>"<?= (int) $v('start_month', $sug['start_month']) === $m ? ' selected' : '' ?>><?= $lab ?></option><?php } ?></select>
              <input class="st-input st-input-short" form="<?= $f ?>" name="start_year" inputmode="numeric" maxlength="4" value="<?= st_h($v('start_year', $sug['start_year'])) ?>" aria-label="<?= st_t(1218) ?>"></span>
			<?php } else { ?>
              <span class="st-create-fixed"><?= sprintf('%02d', $sug['start_month']) ?>-<?= (int) $sug['start_year'] ?></span>
			<?php } ?>
            </label>
            <label><span><?= st_t(1216) ?></span>
              <span class="st-create-pair"><select class="st-input st-input-short" form="<?= $f ?>" name="end_month" aria-label="<?= st_t(1217) ?>"><?php foreach ($months as $m => $lab) { ?><option value="<?= $m ?>"<?= (int) $v('end_month', $sug['end_month']) === $m ? ' selected' : '' ?>><?= $lab ?></option><?php } ?></select>
              <input class="st-input st-input-short" form="<?= $f ?>" name="end_year" inputmode="numeric" maxlength="4" value="<?= st_h($v('end_year', $sug['end_year'])) ?>" aria-label="<?= st_t(1218) ?>"></span>
            </label>
            <label class="st-create-check"><input type="checkbox" form="<?= $f ?>" name="posting" value="1"<?= $v('posting', '1') === '1' ? ' checked' : '' ?>> <?= st_t(6550) ?></label>
          </div>
		<?php if ($c['canWrite']) { ?>
          <div class="st-rfoot"><button type="submit" class="st-btn st-btn-primary" form="<?= $f ?>"><i class='bx bx-plus' aria-hidden="true"></i><?= st_t(508) ?></button></div>
		<?php } ?>
        </div>
	<?php
}

// ---------------------------------------------------------------- currencies (grupper art VK, rates in valuta)

/**
 * ISO 4217 codes offered for a new currency (the list of the old valutakort.php).
 *
 * @return array<int, string>
 */
function settings_iso_currencies(): array
{
	return array('AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BOV', 'BRL', 'BSD', 'BTN', 'BWP', 'BYR', 'BZD',
		'CAD', 'CDF', 'CHE', 'CHF', 'CHW', 'CLF', 'CLP', 'CNY', 'COP', 'COU', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL',
		'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD',
		'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MXV', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK',
		'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SDG', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'SYP',
		'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'VND', 'VUV', 'XAF', 'XBT', 'XCD', 'XOF', 'XPF', 'XUA', 'YER', 'ZAR', 'ZMW', 'ZWL');
}

function settings_base_currency(): string
{
	return !empty($GLOBALS['baseCurrency']) ? strtoupper(trim((string) $GLOBALS['baseCurrency'])) : 'DKK';
}

/**
 * The currencies to pick the rates of: kodenr => "USD – Amerikanske dollar".
 *
 * @return array<int, string>
 */
function settings_currency_options(): array
{
	$out = array();
	$q = db_select("select kodenr, box1, beskrivelse from grupper where art = 'VK' order by box1", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['kodenr']] = trim((string) $r['box1']) . ' – ' . trim((string) $r['beskrivelse']);
	}
	return $out;
}

function settings_money(float $n): string
{
	return number_format($n, 2, ',', '.');
}

/**
 * The rate the accounts of a currency were last converted with, as the old valutakort.php took it: the rate being
 * changed, else the newest rate.
 */
function settings_currency_old_rate(int $gruppe, ?array $current): float
{
	if ($current !== null && isset($current['cells']['kurs'])) {
		return (float) $current['cells']['kurs'];
	}
	$r = db_fetch_array(db_select("select kurs from valuta where gruppe = $gruppe order by valdate desc, id desc limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (float) $r['kurs'] : 0.0;
}

/**
 * What a new or changed rate books (old valutakort.php, audit V9): the exchange difference on every balance of the
 * currency's accounts against the currency's difference account, and the open items of customers and suppliers in
 * groups whose collective account is in the currency.
 *
 * @return array{lines: array<int, string>, postings: array<int, array<string, mixed>>, openpost: array<int, array<string, mixed>>, text: string}
 */
function settings_currency_rate_effect(int $gruppe, string $date, float $new, float $old): array
{
	global $regnaar;
	$out = array('lines' => array(), 'postings' => array(), 'openpost' => array(), 'text' => '');
	if ($old <= 0 || $new <= 0 || abs($new - $old) < 0.0000001) {
		return $out;
	}
	$vk = db_fetch_array(db_select("select box1, box3 from grupper where art = 'VK' and cast(kodenr as integer) = $gruppe", __FILE__ . " linje " . __LINE__));
	if (!$vk) {
		return $out;
	}
	$code = trim((string) $vk['box1']);
	$diffAccount = trim((string) $vk['box3']);
	$out['text'] = 'Kursændring ' . $code . ' fra ' . settings_money($old) . ' til ' . settings_money($new);
	$q = db_select("select id, kontonr, saldo from kontoplan where valuta = $gruppe and regnskabsaar = " . (int) $regnaar . " order by kontonr", __FILE__ . " linje " . __LINE__);
	$accounts = array();
	while ($r = db_fetch_array($q)) {
		$accounts[] = $r;
		$saldo = (float) $r['saldo'];
		$diff = round(($saldo * 100 / $old) * $new / 100 - $saldo, 3);
		if (abs($diff) >= 0.001) {
			$out['postings'][] = array('kontonr' => (string) $r['kontonr'], 'diff' => $diff);
			$out['lines'][] = sprintf(st_txt(6598), $r['kontonr'], settings_money($diff), $diffAccount);
		}
	}
	foreach ($accounts as $a) {
		$q = db_select("select art, kodenr from grupper where art in ('DG', 'KG') and box2 = '" . db_escape_string((string) $a['kontonr']) . "'", __FILE__ . " linje " . __LINE__);
		while ($g = db_fetch_array($q)) {
			// A debtor group's customers and a creditor group's suppliers (the old page looked at suppliers only).
			$art = $g['art'] === 'DG' ? 'D' : 'K';
			$q2 = db_select("select id, kontonr from adresser where art = '$art' and cast(gruppe as text) = '" . db_escape_string((string) $g['kodenr']) . "'", __FILE__ . " linje " . __LINE__);
			while ($adr = db_fetch_array($q2)) {
				$sum = 0.0;
				$q3 = db_select("select amount, valutakurs from openpost where udlignet = '0' and konto_id = " . (int) $adr['id'], __FILE__ . " linje " . __LINE__);
				while ($op = db_fetch_array($q3)) {
					$rate = (float) $op['valutakurs'];
					$sum += $rate > 0 ? (float) $op['amount'] * 100 / $rate : (float) $op['amount'];
				}
				$diff = round(($sum * $old / 100) * $new / 100 - $sum, 3);
				if (abs($diff) >= 0.001) {
					$out['openpost'][] = array('konto_id' => (int) $adr['id'], 'kontonr' => (string) $adr['kontonr'], 'diff' => $diff);
				}
			}
		}
	}
	if ($out['openpost']) {
		$out['lines'][] = sprintf(st_txt(6603), count($out['openpost']));
	}
	return $out;
}

function settings_currency_book(array $effect, string $date): void
{
	global $regnaar;
	if (!$effect['postings'] && !$effect['openpost']) {
		return;
	}
	$text = db_escape_string($effect['text']);
	$today = date('Y-m-d');
	$now = date('H:i');
	$cur = isset($effect['gruppe']) ? (int) $effect['gruppe'] : 0;
	$vk = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and cast(kodenr as integer) = $cur", __FILE__ . " linje " . __LINE__));
	$diffAccount = $vk ? db_escape_string(trim((string) $vk['box3'])) : '';
	foreach ($effect['postings'] as $p) {
		$amount = abs((float) $p['diff']);
		$accountSide = $p['diff'] > 0 ? 'debet' : 'kredit';
		$diffSide = $p['diff'] > 0 ? 'kredit' : 'debet';
		$cols = "(kontonr, bilag, transdate, logdate, logtime, beskrivelse, %s, faktura, kladde_id, afd, ansat, projekt, valuta, valutakurs, ordre_id, moms)";
		$vals = "'%s', '0', '$date', '$today', '$now', '$text', '$amount', '0', '0', '0', '0', '', '-1', '100', '0', '0'";
		db_modify("insert into transaktioner " . sprintf($cols, $diffSide) . " values (" . sprintf($vals, $diffAccount) . ")", __FILE__ . " linje " . __LINE__);
		db_modify("insert into transaktioner " . sprintf($cols, $accountSide) . " values (" . sprintf($vals, db_escape_string($p['kontonr'])) . ")", __FILE__ . " linje " . __LINE__);
		db_modify("update kontoplan set valutakurs = '" . (float) $effect['new'] . "' where kontonr = '" . db_escape_string($p['kontonr']) . "' and regnskabsaar = " . (int) $regnaar, __FILE__ . " linje " . __LINE__);
	}
	foreach ($effect['openpost'] as $p) {
		db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs, udlign_id, udlign_date) values ("
			. (int) $p['konto_id'] . ", '" . db_escape_string($p['kontonr']) . "', '" . (float) $p['diff'] . "', '$text', '1', '$today', '0', '0', '-', '0', '0', '$today')", __FILE__ . " linje " . __LINE__);
	}
	$GLOBALS['settings_currency_booked'] = true;
}

/**
 * The effect of one planned rate row.
 */
function settings_currency_step_effect(array $t, array $step): array
{
	$cells = $step[0] === 'insert' ? $step[2] : array_merge($step[2]['cells'], $step[3]);
	$gruppe = (int) $t['filter']['value'];
	$new = (float) $cells['kurs'];
	$old = settings_currency_old_rate($gruppe, $step[0] === 'insert' ? null : $step[2]);
	$effect = settings_currency_rate_effect($gruppe, (string) $cells['valdate'], $new, $old);
	$effect['gruppe'] = $gruppe;
	$effect['new'] = $new;
	return $effect + array('date' => (string) $cells['valdate']);
}

// ---------------------------------------------------------------- the hooks rows.php / rowsView.php call

function settings_rows_hook_locked(string $hook, array $row): bool
{
	return $hook === 'fiscal_year_deleted' && settings_fy_deleted($row['raw']);
}

function settings_rows_derived_extra(string $name, array $row): ?string
{
	global $regnaar;
	$raw = isset($row['raw']) ? $row['raw'] : array();
	switch ($name) {
		case 'variant_values':
			$names = array();
			$q = db_select("select beskrivelse from variant_typer where variant_id = " . (int) $row['id'] . " order by beskrivelse, id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$names[] = trim((string) $r['beskrivelse']);
			}
			return count($names) > 4 ? implode(', ', array_slice($names, 0, 4)) . ' ' . sprintf(st_txt(6842), count($names) - 4) : implode(', ', $names);
		case 'fy_period':
			list($s, $e) = settings_fy_dates($raw);
			return date('j/n-Y', strtotime($s)) . ' – ' . date('j/n-Y', strtotime($e));
		case 'fy_status':
			if (settings_fy_deleted($raw)) {
				$b = trim((string) $raw['box10']);
				return st_txt(6552) . (ctype_digit($b) && (int) $b > 1 ? ' ' . date('d-m-Y', (int) $b) : '');
			}
			if ((int) $raw['kodenr'] === (int) $regnaar) {
				return st_txt(6553);
			}
			return trim((string) $raw['box5']) === 'on' ? st_txt(6554) : st_txt(387);
		case 'currency_rate_now':
			$k = isset($raw['kodenr']) ? (int) $raw['kodenr'] : 0;
			$r = $k ? db_fetch_array(db_select("select kurs, valdate from valuta where gruppe = $k and valdate <= '" . date('Y-m-d') . "' order by valdate desc, id desc limit 1", __FILE__ . " linje " . __LINE__)) : null;
			return $r ? settings_money((float) $r['kurs']) . ' (' . date('d-m-Y', strtotime((string) $r['valdate'])) . ')' : '–';
	}
	return null;
}

function settings_rows_forbidden(string $rule, string $raw): bool
{
	if ($rule === 'default_background') {
		return strcasecmp(trim($raw), 'Dansk') === 0;
	}
	return $rule === 'base_currency' && strtoupper(trim($raw)) === settings_base_currency();
}

/**
 * @return array<string, int> column => error text id
 */
function settings_rows_row_check(string $hook, array $t, array $clean, ?array $current): array
{
	global $regnaar;
	if ($hook !== 'currency_rate') {
		return array();
	}
	$gruppe = (int) $t['filter']['value'];
	if ((float) $clean['kurs'] <= 0) {
		return array('kurs' => 6604);
	}
	$date = db_escape_string((string) $clean['valdate']);
	// No rate change dated before bookings in the currency or on its accounts (old valutakort.php).
	if (settings_fy_count("select count(*) as n from transaktioner where cast(valuta as text) = '$gruppe' and transdate >= '$date'")
		|| settings_fy_count("select count(*) as n from transaktioner where transdate >= '$date' and cast(kontonr as text) in (select cast(kontonr as text) from kontoplan where valuta = $gruppe and regnskabsaar = " . (int) $regnaar . ")")) {
		return array('valdate' => 6595);
	}
	$effect = settings_currency_step_effect($t, $current === null ? array('insert', '', $clean) : array('update', '', $current, $clean));
	if ($effect['postings']) {
		$vk = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and cast(kodenr as integer) = $gruppe", __FILE__ . " linje " . __LINE__));
		if (!$vk || trim((string) $vk['box3']) === '') {
			return array('kurs' => 6596);
		}
	}
	return array();
}

/**
 * @return array<int, string>
 */
function settings_rows_confirm_lines(string $hook, array $t, array $step): array
{
	if ($hook !== 'currency_rate') {
		return array();
	}
	return settings_currency_step_effect($t, $step)['lines'];
}

function settings_rows_before_row(string $hook, array $t, array $step): void
{
	if ($hook === 'background_create' && $step[0] === 'insert') {
		// G6.2: a new background gets a copy of every form line of its template, as the old formularkort.php did.
		$name = trim((string) $step[2]['box1']);
		$template = isset($step[2]['template']) && trim((string) $step[2]['template']) !== '' ? trim((string) $step[2]['template']) : 'Dansk';
		$nameEsc = db_escape_string($name);
		if ($name === '' || settings_fy_count("select count(*) as n from formularer where lower(sprog) = lower('$nameEsc')")) {
			return;
		}
		$cols = settings_fy_columns('formularer', array('id', 'sprog'));
		$r = db_fetch_array(db_select("select coalesce(max(id), 0) as m from formularer", __FILE__ . " linje " . __LINE__));
		$next = (int) $r['m'] + 1;
		$q = db_select("select id from formularer where sprog = '" . db_escape_string($template) . "' order by id", __FILE__ . " linje " . __LINE__);
		$ids = array();
		while ($row = db_fetch_array($q)) {
			$ids[] = (int) $row['id'];
		}
		foreach ($ids as $id) {
			db_modify("insert into formularer (id, sprog, " . implode(', ', $cols) . ") select $next, '$nameEsc', " . implode(', ', $cols) . " from formularer where id = $id", __FILE__ . " linje " . __LINE__);
			$next++;
		}
		return;
	}
	if ($hook === 'currency_rate') {
		$effect = settings_currency_step_effect($t, $step);
		settings_currency_book($effect, $effect['date']);
	}
}

function settings_rows_on_delete(string $hook, array $t, array $row): void
{
	if ($hook === 'background_delete') {
		$name = trim((string) $row['cells']['box1']);
		if ($name !== '' && strcasecmp($name, 'Dansk') !== 0) {
			db_modify("delete from formularer where sprog = '" . db_escape_string($name) . "'", __FILE__ . " linje " . __LINE__);
		}
		return;
	}
	if ($hook === 'variant_type_values') {
		db_modify("delete from variant_typer where variant_id = " . (int) $row['id'], __FILE__ . " linje " . __LINE__);
		return;
	}
	if ($hook === 'fiscal_year_empty') {
		$k = (int) $row['raw']['kodenr'];
		db_modify("delete from kontoplan where regnskabsaar = $k", __FILE__ . " linje " . __LINE__);
		db_modify("delete from grupper where fiscal_year = $k and art != 'RA'", __FILE__ . " linje " . __LINE__);
	}
}

function settings_rows_after_save_extra(string $hook): bool
{
	global $regnaar;
	if ($hook !== 'currency_rates') {
		return false;
	}
	// The newest rate per currency is also kept on the currency row (box2), as the old page did.
	$q = db_select("select kodenr from grupper where art = 'VK'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$k = (int) $r['kodenr'];
		$rate = db_fetch_array(db_select("select kurs from valuta where gruppe = $k order by valdate desc, id desc limit 1", __FILE__ . " linje " . __LINE__));
		db_modify("update grupper set box2 = '" . ($rate ? settings_money((float) $rate['kurs']) : '') . "' where art = 'VK' and cast(kodenr as integer) = $k", __FILE__ . " linje " . __LINE__);
	}
	if (!empty($GLOBALS['settings_currency_booked'])) {
		include_once(__DIR__ . '/../genberegn.php');
		if (function_exists('genberegn')) {
			genberegn((int) $regnaar);
		}
		$GLOBALS['settings_currency_booked'] = false;
	}
	return true;
}

function settings_rows_action_visible(string $name, array $row): bool
{
	global $regnaar;
	$raw = $row['raw'];
	$k = isset($raw['kodenr']) ? (int) $raw['kodenr'] : 0;
	switch ($name) {
		case 'fy_activate':
			return !settings_fy_deleted($raw) && $k !== (int) $regnaar && trim((string) $raw['box5']) === 'on';
		case 'fy_activate_all':
			return $k === (int) $regnaar && settings_fy_count("select count(*) as n from brugere where coalesce(cast(regnskabsaar as text), '') != '$k'") > 0;
		case 'fy_opening':
			return !settings_fy_deleted($raw);
		case 'fy_archive':
			return settings_fy_archive_refusal($raw) === '';
	}
	return false;
}

/**
 * Run a row action. Returns a flash entry and, for the opening balance, a page to go to.
 *
 * @return array{flash: array<int, string>, redirect: string}
 */
function settings_rows_row_action(string $sectionId, string $tableId, array $t, array $row, string $name): array
{
	$raw = $row['raw'];
	$k = (int) $raw['kodenr'];
	$out = array('flash' => array('err', st_txt(5719)), 'redirect' => '');
	if (!settings_rows_action_visible($name, $row)) {
		return $out;
	}
	$objekt = $sectionId . '.' . $tableId . '#' . $k;
	if ($name === 'fy_activate' || $name === 'fy_activate_all') {
		settings_fy_switch_year($k, $name === 'fy_activate_all');
		SettingsService::auditRow($sectionId, $tableId . '.' . $name, $objekt, '', (string) $k, 'setting.action');
		$out['flash'] = array('ok', sprintf(st_txt($name === 'fy_activate_all' ? 6574 : 6575), trim((string) $raw['beskrivelse'])));
		$out['reload_shell'] = true;
	} elseif ($name === 'fy_archive') {
		include_once(__DIR__ . '/../../systemdata/fiscalYearInc/deleteFiscalYear.php');
		$res = deleteFinancialYear($k);
		if ($res === '') {
			SettingsService::auditRow($sectionId, $tableId . '.' . $name, $objekt, json_encode(array('beskrivelse' => $raw['beskrivelse'], 'periode' => settings_fy_dates($raw)), JSON_UNESCAPED_UNICODE), '', 'setting.row_deleted');
			$out['flash'] = array('ok', sprintf(st_txt(6573), trim((string) $raw['beskrivelse'])));
		} else {
			$out['flash'] = array('err', sprintf(st_txt(6576), $res));
		}
	}
	return $out;
}

function settings_rows_create_card(string $hook, array $c, string $tableId, array $t): void
{
	if ($hook === 'fiscal_year') {
		settings_fiscal_year_card($c, $tableId);
	}
}

/**
 * The create form of a table (rendered after the main form; the card's inputs point at it with form="...").
 */
function settings_rows_create_forms(array $c): void
{
	foreach ($c['tables'] as $tableId => $t) {
		if ($t['create'] !== null) { ?>
  <form method="post" action="<?= st_h($c['selfUrl']) ?>" id="st-create-<?= st_h($tableId) ?>" hidden><input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>"><input type="hidden" name="action" value="row_create"><input type="hidden" name="table" value="<?= st_h($tableId) ?>"></form>
		<?php }
	}
}

/**
 * Handle a create form. Returns a flash entry and where to go next.
 *
 * @return array{flash: array<int, string>, redirect: string, year: int}
 */
function settings_rows_create_submit(string $hook, array $post): array
{
	if ($hook !== 'fiscal_year') {
		return array('flash' => array('err', st_txt(5719)), 'redirect' => '', 'year' => 0);
	}
	include_once(__DIR__ . '/../genberegn.php');
	$sug = settings_fiscal_year_suggestion();
	$in = array(
		'beskrivelse' => isset($post['beskrivelse']) ? (string) $post['beskrivelse'] : '',
		'start_month' => isset($post['start_month']) ? (int) $post['start_month'] : $sug['start_month'],
		'start_year' => isset($post['start_year']) ? (int) $post['start_year'] : $sug['start_year'],
		'end_month' => isset($post['end_month']) ? (int) $post['end_month'] : 0,
		'end_year' => isset($post['end_year']) ? (int) $post['end_year'] : 0,
		'posting' => !empty($post['posting']) ? '1' : '0',
	);
	$res = settings_fiscal_year_create($in['start_month'], $in['start_year'], $in['end_month'], $in['end_year'], mb_substr($in['beskrivelse'], 0, 60), $in['posting'] === '1');
	if ($res['error'] !== null) {
		$_SESSION['settings_create'] = $in;
		return array('flash' => array('err', vsprintf(st_txt($res['error']), $res['error_args'])), 'redirect' => '', 'year' => 0);
	}
	settings_fy_switch_year($res['kodenr'], false);
	return array('flash' => array('ok', sprintf(st_txt(6564), $res['kodenr'])), 'redirect' => 'regnskabskort.php?id=' . $res['id'], 'year' => $res['kodenr']);
}

/**
 * The choices of a table's parent filter.
 *
 * @return array<int, string>
 */
function settings_rows_filter_options(string $name): array
{
	if ($name === 'variant_types') {
		$out = array();
		$q = db_select("select id, beskrivelse from varianter order by beskrivelse, id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[(int) $r['id']] = (string) $r['beskrivelse'];
		}
		return $out;
	}
	return $name === 'currencies' ? settings_currency_options() : array();
}

function settings_rows_help_text(array $t): string
{
	if (isset($t['help_args']) && $t['help_args'] === 'base_currency') {
		return sprintf(st_txt($t['help']), settings_base_currency());
	}
	return st_txt($t['help']);
}

// ---------------------------------------------------------------- variants (G5.3)

/**
 * The value ids of a variant type (varianter.id -> variant_typer.variant_id).
 *
 * @return array<int, int>
 */
function settings_variant_value_ids(int $typeId): array
{
	$ids = array();
	$q = db_select("select id from variant_typer where variant_id = $typeId", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ids[] = (int) $r['id'];
	}
	return $ids;
}

/**
 * Variant items (variant_varer) using any of these values. variant_type holds one value id, or several joined by tabs
 * (written by the product card), so it is read as text and split here.
 */
function settings_variant_item_count(array $valueIds): int
{
	$valueIds = array_values(array_filter(array_map('intval', $valueIds)));
	if (!$valueIds) {
		return 0;
	}
	$like = array();
	foreach ($valueIds as $id) {
		$like[] = "cast(variant_type as text) like '%$id%'";
	}
	$n = 0;
	$q = db_select("select variant_type from variant_varer where " . implode(' or ', $like), __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (array_intersect(array_map('intval', preg_split('/\t+/', trim((string) $r['variant_type']))), $valueIds)) {
			$n++;
		}
	}
	return $n;
}
