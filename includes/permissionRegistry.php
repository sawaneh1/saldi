<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/permissionRegistry.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh Central registry of permission keys (spec R1), their mapping to the legacy
//                  16-position rettigheder string, and the built-in default roles (R3).
//                  Same pattern as systemdata/settingsRegistry.php: one place to maintain.
// 20260928 Sawaneh pos.kasse key (own group) so point-of-sale access is granted per role.
// 20260928 Sawaneh Settings-area keys (phase 4, spec S3), one per group of the settings front page.
// 20260928 Sawaneh Keys and groups aligned with Requirements_settings_redesign_EN.md §4 (11 groups).
// 20260930 Sawaneh Roles stage 2 (§3.1): settings.roles.manage and settings.audit.read.
// 20260930 Sawaneh Built-in role descriptions are text ids, so they follow the user's language.

/**
 * Every permission key the system knows. A key is granted at level none / read / write.
 *
 * 'legacy' lists the positions in brugere.rettigheder the key corresponds to, so a role
 * can be rendered as a legacy string (write = '1', read = '2', none = '0') and the ~450
 * existing substr($rettigheder, n, 1) checks keep working unchanged. Keys without a legacy
 * position are new (spec R5/R6) and only enforced where require_permission() is called.
 *
 * @return array<string, array{group: string, label: string, legacy: array<int, int>, dangerous: bool}>
 */
function permission_registry(): array
{
	static $registry = null;
	if ($registry !== null) {
		return $registry;
	}
	$registry = array(
		'finans.kassekladde'     => array('group' => 'finans',   'label' => '601|Kassekladde',         'legacy' => array(2),  'dangerous' => false),
		'finans.regnskab'        => array('group' => 'finans',   'label' => '322|Regnskab',            'legacy' => array(3),  'dangerous' => false),
		'finans.rapporter'       => array('group' => 'finans',   'label' => '895|Finansrapport',       'legacy' => array(4),  'dangerous' => false),

		'debitor.ordre'          => array('group' => 'debitor',  'label' => '1255|Debitorordre',       'legacy' => array(5),  'dangerous' => false),
		'debitor.konti'          => array('group' => 'debitor',  'label' => '1256|Debitorkonti',       'legacy' => array(6),  'dangerous' => false),
		'debitor.rapporter'      => array('group' => 'debitor',  'label' => '449|Debitorrapporter',    'legacy' => array(12), 'dangerous' => false),

		'kreditor.ordre'         => array('group' => 'kreditor', 'label' => '1257|Kreditorordre',      'legacy' => array(7),  'dangerous' => false),
		'kreditor.konti'         => array('group' => 'kreditor', 'label' => '1258|Kreditorkonti',      'legacy' => array(8),  'dangerous' => false),
		'kreditor.rapporter'     => array('group' => 'kreditor', 'label' => '1140|Kreditorapport',     'legacy' => array(13), 'dangerous' => false),

		'lager.varer'            => array('group' => 'lager',    'label' => '609|Varer',               'legacy' => array(9),  'dangerous' => false),
		'lager.varemodtagelse'   => array('group' => 'lager',    'label' => '182|Varemodtagelse',      'legacy' => array(10), 'dangerous' => false),
		'lager.produktion'       => array('group' => 'lager',    'label' => '1260|Produktionsordre',   'legacy' => array(14), 'dangerous' => false),
		'lager.rapporter'        => array('group' => 'lager',    'label' => '965|Varerapport',         'legacy' => array(15), 'dangerous' => false),

		'system.kontoplan'       => array('group' => 'system',   'label' => '113|Kontoplan',           'legacy' => array(0),  'dangerous' => false),
		'system.indstillinger'   => array('group' => 'system',   'label' => '122|Indstillinger',       'legacy' => array(1),  'dangerous' => false),
		'system.backup'          => array('group' => 'system',   'label' => '521|Sikkerhedskopi',      'legacy' => array(11), 'dangerous' => false),

		// No legacy position of its own: users without a role inherit it from Debitorordre (5),
		// which is what opens the cash register today ('derive').
		'pos.kasse'              => array('group' => 'pos',      'label' => '5606|Kassesystem',        'legacy' => array(),   'dangerous' => false, 'derive' => 5, 'since' => '20260928'),

		// Settings areas (settings redesign spec §4): one key per group of the settings front page.
		// Users without a role inherit them from the Indstillinger bit (1), as today.
		// 'renamed_from' moves an earlier key's role rows to the new name once (perm_seed_new_keys).
		'settings.company'       => array('group' => 'settingsarea', 'label' => '5669|Virksomhed',        'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928'),
		'settings.finance'       => array('group' => 'settingsarea', 'label' => '600|Finans',             'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928'),
		'settings.sales'         => array('group' => 'settingsarea', 'label' => '5544|Salg',              'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928'),
		'settings.purchase'      => array('group' => 'settingsarea', 'label' => '1012|Køb',               'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928', 'renamed_from' => 'settings.purchasing'),
		'settings.items'         => array('group' => 'settingsarea', 'label' => '5650|Varer & lager',     'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928', 'renamed_from' => 'settings.products'),
		'settings.documents'     => array('group' => 'settingsarea', 'label' => '5671|Dokumenter & e-mail', 'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928'),
		'settings.organisation'  => array('group' => 'settingsarea', 'label' => '5670|Organisation',      'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928', 'renamed_from' => 'settings.employees'),
		'settings.pos'           => array('group' => 'settingsarea', 'label' => '2226|Kasse',             'legacy' => array(), 'dangerous' => false, 'derive' => 1, 'since' => '20260928'),

		'settings.users.manage'  => array('group' => 'settings', 'label' => '5536|Brugere & roller',   'legacy' => array(),   'dangerous' => true),
		'settings.roles.manage'  => array('group' => 'settings', 'label' => '5550|Roller',             'legacy' => array(),   'dangerous' => true, 'since' => '20260930'),
		'settings.audit.read'    => array('group' => 'settings', 'label' => '5796|Audit-log',          'legacy' => array(),   'dangerous' => false, 'since' => '20260930'),
		'settings.integrations'  => array('group' => 'settings', 'label' => '5537|Integrationer',      'legacy' => array(),   'dangerous' => true),
		'settings.integrations.keys' => array('group' => 'settings', 'label' => '5540|API-nøgler',     'legacy' => array(),   'dangerous' => true, 'renamed_from' => 'settings.api'),
		'settings.smtp'          => array('group' => 'settings', 'label' => '5541|E-mail/SMTP',        'legacy' => array(),   'dangerous' => true),
		'settings.import_export' => array('group' => 'settings', 'label' => '5539|Import & eksport',   'legacy' => array(),   'dangerous' => true, 'renamed_from' => 'settings.importexport'),
		'system.backup.restore'  => array('group' => 'settings', 'label' => '5542|Gendan sikkerhedskopi', 'legacy' => array(), 'dangerous' => true),
	);
	return $registry;
}

/**
 * Display order and label (text id) of the areas the matrix editor groups keys by.
 *
 * @return array<string, string>
 */
function permission_groups(): array
{
	return array(
		'finans'   => '600|Finans',
		'debitor'  => '604|Debitor',
		'kreditor' => '607|Kreditorer',
		'lager'    => '608|Lager',
		'system'   => '2377|System',
		'pos'      => '5606|Kassesystem',
		'settingsarea' => '5648|Indstillingsområder',
		'settings' => '122|Indstillinger',
	);
}

/**
 * Built-in roles (spec R3). 'key' identifies the role across languages; 'levels' lists
 * the non-none permissions. Everything not listed is 'none'.
 *
 * @return array<string, array{label: string, beskrivelse: string, levels: array<string, string>}>
 */
function permission_default_roles(): array
{
	$allWrite = array();
	$legacyRead = array();
	foreach (permission_registry() as $key => $def) {
		$allWrite[$key] = 'write';
		if ($def['legacy']) {
			$legacyRead[$key] = 'read';
		}
	}
	return array(
		'administrator' => array(
			'label'       => '330|Administrator',
			'beskrivelse' => '5897|Alt, inkl. brugere, roller, integrationer og sikkerhedskopi.',
			'levels'      => $allWrite,
		),
		'bogholder' => array(
			'label'       => '5543|Bogholder',
			'beskrivelse' => '5898|Finans, kassekladde, rapporter, moms og betalinger. Ingen brugeradministration.',
			'levels'      => array(
				'finans.kassekladde' => 'write', 'finans.regnskab' => 'write', 'finans.rapporter' => 'write',
				'system.kontoplan' => 'write', 'system.indstillinger' => 'read',
				'settings.finance' => 'write', 'settings.company' => 'read', 'settings.audit.read' => 'read',
				'debitor.konti' => 'write', 'debitor.rapporter' => 'write',
				'kreditor.konti' => 'write', 'kreditor.rapporter' => 'write',
				'debitor.ordre' => 'read', 'kreditor.ordre' => 'read',
			),
		),
		'salg' => array(
			'label'       => '5544|Salg',
			'beskrivelse' => '5899|Debitorordrer, kunder og fakturering. Ingen finans eller indstillinger.',
			'levels'      => array(
				'debitor.ordre' => 'write', 'debitor.konti' => 'write', 'debitor.rapporter' => 'write',
				'lager.varer' => 'read', 'pos.kasse' => 'write',
			),
		),
		'indkoeb' => array(
			'label'       => '5545|Indkøb',
			'beskrivelse' => '5900|Kreditorordrer, leverandører og varemodtagelse.',
			'levels'      => array(
				'kreditor.ordre' => 'write', 'kreditor.konti' => 'write', 'kreditor.rapporter' => 'write',
				'lager.varemodtagelse' => 'write', 'lager.varer' => 'read',
			),
		),
		'lager' => array(
			'label'       => '608|Lager',
			'beskrivelse' => '5901|Varer, lager og produktion.',
			'levels'      => array(
				'lager.varer' => 'write', 'lager.varemodtagelse' => 'write', 'lager.produktion' => 'write', 'lager.rapporter' => 'write',
				'debitor.ordre' => 'read', 'kreditor.ordre' => 'read',
				'settings.items' => 'write', 'settings.organisation' => 'read',
			),
		),
		'kunvisning' => array(
			'label'       => '2475|Kun visning',
			'beskrivelse' => '5902|Læseadgang til alle moduler.',
			'levels'      => $legacyRead,
		),
		'revisor' => array(
			'label'       => '2562|Revisor',
			'beskrivelse' => '5903|Læseadgang til finans, kontoplan og rapporter.',
			'levels'      => array(
				'finans.kassekladde' => 'read', 'finans.regnskab' => 'read', 'finans.rapporter' => 'read',
				'system.kontoplan' => 'read', 'debitor.rapporter' => 'read', 'kreditor.rapporter' => 'read',
				'settings.audit.read' => 'read',
			),
		),
	);
}
