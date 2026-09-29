<?php
// ----------------systemdata/settingsDefinitions.php --- Settings registry v2 --- 2026-09-29 ----
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260929 Sawaneh Registry v2 (Requirements_settings_redesign_EN.md §7.1): the definition of each
//                  setting - key, type, scope, group/section, label and help text ids, default,
//                  storage (the EXISTING location and encoding), permission, dependencies.
//                  Section pages, save logic, search, change history and the "moved" page are
//                  generated from this file. Included by settingsRegistry.php.
//
// Storage encodings mirror the current writer (risk review R23):
//   onEmpty  'on' / ''        onOff  'on' / 'off'        oneZero  '1' / '0'        raw  as typed
// 'join' + 'index' address one part of a composite box (R7).
// 'legacy' is the old menu path as text ids; it feeds the "Tidligere: ..." search tag (§8.10).

if (!function_exists('getSettingsSections')) {
	/**
	 * Sections that are generated from the registry, in display order within their group.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function getSettingsSections(): array
	{
		return array(
			'sales.debtor_card' => array(
				'group' => 'sales', 'section' => 'debtor_card', 'number' => 'G3.2', 'label' => 5681, 'icon' => 'bx-id-card',
				'subsections' => array('card' => 5681),
				'legacy' => array(array(782, 794), array(782, 786)),
				'context' => array('debitor/debitorkort.php'),
				'keywords' => array('debitorkort', 'kundekort', 'customer card', 'debtor card', 'jobkort', 'job cards', 'debitoripad', 'kundeansvarlig'),
			),
			'sales.orders' => array(
				'group' => 'sales', 'section' => 'orders', 'number' => 'G3.3', 'label' => 5679, 'icon' => 'bx-receipt',
				'subsections' => array('prices' => 5683, 'invoicing' => 5684, 'mass' => 200, 'items' => 5685, 'window' => 5686, 'packing' => 5687),
				'legacy' => array(array(782, 786), array(782, 200)),
				'old' => array('ordre_valg' => array(782, 786), 'massefakt' => array(782, 200)),
				'context' => array('debitor/ordre.php', 'debitor/ordreliste.php'),
				'keywords' => array('ordre', 'order', 'faktura', 'invoice', 'invoicing', 'kunde', 'kunder', 'hurtigfakturering', 'massefakturering', 'følgeseddel', 'plukliste'),
			),
			'purchase.orders' => array(
				'group' => 'purchase', 'section' => 'orders', 'number' => 'G4.2', 'label' => 5682, 'icon' => 'bx-archive-in',
				'subsections' => array('posting' => 5692),
				'legacy' => array(array(782, 786)),
				'context' => array('kreditor/ordre.php'),
				'keywords' => array('købsordre', 'indkøb', 'purchase order', 'leverandør', 'supplier'),
			),
			'items.stock' => array(
				'group' => 'items', 'section' => 'stock', 'number' => 'G5.5', 'label' => 5680, 'icon' => 'bx-package',
				'subsections' => array('stock' => 5688, 'cost' => 5689, 'mail' => 5690, 'card' => 5691),
				'legacy' => array(array(782, 786), array(782, 787)),
				'context' => array('lager/varekort.php', 'lager/varer.php'),
				'keywords' => array('lager', 'stock', 'inventory', 'kostpris', 'cost price', 'fifo', 'beholdning', 'minimumsbeholdning'),
			),
		);
	}

	/**
	 * Every setting that is defined in the registry (key => definition).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function getSettingDefinitions(): array
	{
		static $defs = null;
		if ($defs !== null) {
			return $defs;
		}
		$ordre = array(782, 786);
		$vare = array(782, 787);
		$divvalg = array(782, 794);
		$mass = array(782, 200);

		$defs = array(
			// ---------------------------------------------------------------- G3.2 Debtor card
			'sales.debtor_card.mandatory_group' => array('sub' => 'card', 'type' => 'bool', 'label' => 162, 'help' => 186, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box1', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg),
			'sales.debtor_card.mandatory_responsible' => array('sub' => 'card', 'type' => 'bool', 'label' => 163, 'help' => 187, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box2', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg),
			'sales.debtor_card.job_cards' => array('sub' => 'card', 'type' => 'bool', 'label' => 168, 'help' => 194, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box7', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg,
				'keywords' => array('jobkort', 'opgaveliste', 'task list')),
			'sales.debtor_card.account_as_phone' => array('sub' => 'card', 'type' => 'bool', 'label' => 1060, 'help' => 1061, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box5', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg),
			'sales.debtor_card.debtor_ipad' => array('sub' => 'card', 'type' => 'bool', 'label' => 2369, 'help' => 2370, 'default' => false,
				'storage' => array('settings', 'ordre', 'debitoripad', 'onEmpty'), 'legacy' => $ordre),
			'sales.debtor_card.show_both_addresses' => array('sub' => 'card', 'type' => 'bool', 'label' => 5038, 'help' => 5039, 'default' => false,
				'storage' => array('settings', 'ordre', 'showBothAddrExtra', 'onEmpty'), 'legacy' => $ordre),

			// ---------------------------------------------------------------- G3.3 Orders & invoicing
			'sales.orders.vat_private' => array('sub' => 'prices', 'type' => 'bool', 'label' => 5693, 'help' => 5695, 'default' => false,
				'storage' => array('settings', 'ordre', 'vatPrivateCustomers', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('moms', 'vat', 'mva', 'private kunder')),
			'sales.orders.vat_business' => array('sub' => 'prices', 'type' => 'bool', 'label' => 5694, 'help' => 5695, 'default' => false,
				'storage' => array('settings', 'ordre', 'vatBusinessCustomers', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('moms', 'vat', 'mva', 'erhvervskunder', 'b2b')),

			'sales.orders.quick_invoice' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 165, 'help' => 190, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box4', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'locked_if' => 'batch_control', 'locked_text' => 5736,
				'keywords' => array('hurtigfakturering', 'quick invoicing', 'fast invoicing')),
			'sales.orders.post_sales_immediately' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 166, 'help' => 191, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box5', 'onEmpty', 'join' => ';', 'index' => 0, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('straksbogføring', 'immediate posting', 'bogføring')),
			'sales.orders.lock_invoice_until_paid' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 3357, 'help' => 3356, 'default' => false,
				'storage' => array('settings', 'debitor', 'lockedInvoiceButton', 'onEmpty'), 'legacy' => $ordre,
				'visible_if' => array('module', 'pos')),
			'sales.orders.percentage_invoicing' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 681, 'help' => 682, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box12', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('procentfakturering', 'udlejning', 'rental')),
			'sales.orders.percentage_surcharge' => array('sub' => 'invoicing', 'type' => 'decimal', 'label' => 683, 'help' => 684, 'default' => '', 'unit' => '%',
				'storage' => array('grupper', 'DIV', 3, 'box13', 'raw', 'join' => "\t", 'index' => 0, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'visible_if' => array('setting', 'sales.orders.percentage_invoicing', true)),
			'sales.orders.percentage_item' => array('sub' => 'invoicing', 'type' => 'item', 'item_as' => 'varenr', 'label' => 685, 'help' => 686, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box13', 'raw', 'join' => "\t", 'index' => 1, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'visible_if' => array('setting', 'sales.orders.percentage_invoicing', true), 'validate' => array('item_exists')),
			'sales.orders.bundle_price' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 742, 'help' => 743, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box14', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'validate' => array('requires', 'sales.orders.discount_item', 1875),
				'keywords' => array('samlet pris', 'bundle', 'sæt')),
			'sales.orders.bundle_item' => array('sub' => 'invoicing', 'type' => 'item', 'item_as' => 'id', 'label' => 744, 'help' => 745, 'default' => '',
				'storage' => array('grupper', 'DIV', 5, 'box8', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $ordre,
				'visible_if' => array('setting', 'sales.orders.bundle_price', true), 'validate' => array('item_exists')),

			'sales.orders.mass_invoicing' => array('sub' => 'mass', 'type' => 'bool', 'label' => 201, 'help' => 202, 'default' => false,
				'storage' => array('grupper', 'MFAKT', 1, 'box1', 'onEmpty', 'row_name' => 'Massefakturering'), 'legacy' => $mass,
				'keywords' => array('massefakturering', 'mass invoicing', 'batch invoicing')),
			'sales.orders.mass_partial_deliveries' => array('sub' => 'mass', 'type' => 'bool', 'label' => 203, 'help' => 204, 'default' => false,
				'storage' => array('grupper', 'MFAKT', 1, 'box2', 'onEmpty', 'row_name' => 'Massefakturering'), 'legacy' => $mass,
				'visible_if' => array('setting', 'sales.orders.mass_invoicing', true)),
			'sales.orders.mass_delivery_deadline' => array('sub' => 'mass', 'type' => 'int', 'label' => 205, 'help' => 206, 'default' => 0, 'unit' => 5025,
				'storage' => array('grupper', 'MFAKT', 1, 'box3', 'raw', 'row_name' => 'Massefakturering'), 'legacy' => $mass,
				'visible_if' => array('setting', 'sales.orders.mass_invoicing', true)),

			'sales.orders.discount_item' => array('sub' => 'items', 'type' => 'item', 'item_as' => 'id', 'label' => 287, 'help' => 288, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box2', 'raw', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre, 'validate' => array('item_exists'),
				'keywords' => array('rabatvare', 'discount item')),
			'sales.orders.postage_item' => array('sub' => 'items', 'type' => 'item', 'item_as' => 'varenr', 'label' => 5696, 'help' => 5697, 'default' => '',
				'storage' => array('settings', 'ordre', 'porto_varnr', 'raw'), 'legacy' => $ordre, 'validate' => array('item_exists'),
				'keywords' => array('porto', 'fragt', 'postage', 'shipping item')),
			'sales.orders.cash_account' => array('sub' => 'items', 'type' => 'account', 'label' => 687, 'help' => 688, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box7', 'raw', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre, 'validate' => array('account_exists'),
				'keywords' => array('kontantsalg', 'cash sale')),
			'sales.orders.card_account' => array('sub' => 'items', 'type' => 'account', 'label' => 689, 'help' => 690, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box10', 'raw', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre, 'validate' => array('account_exists'),
				'keywords' => array('kreditkort', 'kortsalg', 'card sale')),

			'sales.orders.internal_note' => array('sub' => 'window', 'type' => 'bool', 'label' => 1714, 'help' => 1711, 'default' => false,
				'storage' => array('settings', null, 'orderNoteEnabled', 'onEmpty'), 'legacy' => $ordre),
			'sales.orders.hide_margin' => array('sub' => 'window', 'type' => 'bool', 'label' => 5698, 'help' => 5699, 'default' => false,
				'storage' => array('settings', 'ordre', 'showDB', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('dækningsbidrag', 'db', 'contribution margin')),
			'sales.orders.hide_ratio' => array('sub' => 'window', 'type' => 'bool', 'label' => 5700, 'help' => 5701, 'default' => false,
				'storage' => array('settings', 'ordre', 'showDG', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('dækningsgrad', 'dg', 'contribution ratio')),
			'sales.orders.gs1_parsing' => array('sub' => 'window', 'type' => 'bool', 'label' => 5032, 'help' => 5033, 'default' => false,
				'storage' => array('settings', 'ordre', 'gs1_parsing', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('gs1', 'stregkode', 'barcode')),
			'sales.orders.our_ref_stock_switch' => array('sub' => 'window', 'type' => 'bool', 'label' => 5034, 'help' => 5035, 'default' => false,
				'storage' => array('settings', 'ordre', 'ourRefStockSwitch', 'onEmpty'), 'legacy' => $ordre),
			'sales.orders.out_of_stock_warning' => array('sub' => 'window', 'type' => 'bool', 'label' => 5036, 'help' => 5037, 'default' => false,
				'storage' => array('settings', 'ordre', 'stockWarningEnabled', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('udsolgt', 'out of stock')),

			'sales.orders.packing_comments' => array('sub' => 'packing', 'type' => 'bool', 'label' => 164, 'help' => 188, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box3', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('følgeseddel', 'packing slip', 'delivery note')),
			'sales.orders.packing_only_quantity' => array('sub' => 'packing', 'type' => 'bool', 'label' => 169, 'help' => 189, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box8', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre),
			'sales.orders.picklist_email' => array('sub' => 'packing', 'type' => 'email', 'label' => 5702, 'help' => 5703, 'default' => '',
				'storage' => array('settings', 'ordre', 'pluklisteEmail', 'raw'), 'legacy' => $ordre,
				'keywords' => array('plukliste', 'pick list')),

			// ---------------------------------------------------------------- G4.2 Purchase orders
			'purchase.orders.post_immediately' => array('sub' => 'posting', 'type' => 'bool', 'label' => 213, 'help' => 214, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box5', 'onEmpty', 'join' => ';', 'index' => 1, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('straksbogføring', 'immediate posting', 'købsordrer')),

			// ---------------------------------------------------------------- G5.5 Stock control & cost price
			'items.stock.fifo' => array('sub' => 'stock', 'type' => 'bool', 'label' => 314, 'help' => 313, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box6', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('fifo', 'first in first out')),
			'items.stock.allow_negative' => array('sub' => 'stock', 'type' => 'bool', 'label' => 183, 'help' => 192, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box9', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('negativt lager', 'negative stock')),
			'items.stock.low_stock_warning' => array('sub' => 'stock', 'type' => 'bool', 'label' => 714, 'help' => 680, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box11', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('lav beholdning', 'low stock')),
			'items.stock.discontinue_when_negative' => array('sub' => 'stock', 'type' => 'bool', 'label' => 1279, 'help' => 1280, 'default' => false,
				'storage' => array('settings', 'items', 'DisItemIfNeg', 'onEmpty'), 'legacy' => $vare),
			'items.stock.default_minimum' => array('sub' => 'stock', 'type' => 'int', 'label' => 2420, 'help' => 2419, 'default' => 0,
				'storage' => array('settings', 'productOptions', 'min_beholdning', 'raw'), 'legacy' => $vare,
				'keywords' => array('minimumsbeholdning', 'minimum stock', 'reorder level')),

			'items.stock.cost_method' => array('sub' => 'cost', 'type' => 'select', 'label' => 731, 'help' => 732, 'default' => '0',
				'options' => array('0' => 2527, '1' => 2528, '2' => 2529),
				'storage' => array('grupper', 'DIV', 5, 'box6', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $ordre,
				'keywords' => array('kostpris', 'gennemsnitspris', 'genanskaffelsespris', 'cost price', 'average cost')),
			'items.stock.update_cost_prices' => array('sub' => 'cost', 'type' => 'action', 'label' => 739, 'help' => 738,
				'confirm_title' => 5737, 'confirm' => 5738, 'run' => 'update_cost_prices', 'legacy' => $ordre,
				'visible_if' => array('setting_in', 'items.stock.cost_method', array('1', '2'))),

			'items.stock.status_mail' => array('sub' => 'mail', 'type' => 'email', 'label' => 2553, 'help' => 2554, 'default' => '',
				'storage' => array('settings', 'lagerstatus', 'mail', 'raw'), 'legacy' => $vare,
				'keywords' => array('lagerstatus', 'stock status')),
			'items.stock.status_frequency' => array('sub' => 'mail', 'type' => 'int', 'label' => 2555, 'help' => 2556, 'default' => '', 'unit' => 5745, 'empty_ok' => true,
				'storage' => array('settings', 'lagerstatus', 'time', 'raw'), 'legacy' => $vare),
			'items.stock.status_threshold' => array('sub' => 'mail', 'type' => 'int', 'label' => 2557, 'help' => 2558, 'default' => '', 'empty_ok' => true,
				'storage' => array('settings', 'lagerstatus', 'trigger', 'raw'), 'legacy' => $vare),

			'items.stock.vat_on_item_card' => array('sub' => 'card', 'type' => 'bool', 'label' => 1273, 'help' => 1274, 'default' => false,
				'storage' => array('settings', 'items', 'vatOnItemCard', 'onEmpty'), 'legacy' => $vare),
			'items.stock.confirm_description_change' => array('sub' => 'card', 'type' => 'bool', 'label' => 1275, 'help' => 1276, 'default' => false,
				'storage' => array('settings', 'items', 'confirmDescriptionChange', 'onEmpty'), 'legacy' => $vare),
			'items.stock.confirm_stock_change' => array('sub' => 'card', 'type' => 'bool', 'label' => 1277, 'help' => 1278, 'default' => false,
				'storage' => array('settings', 'items', 'confirmStockChange', 'onEmpty'), 'legacy' => $vare),

			// ---------------------------------------------------------------- Personal (topbar spec §4)
			'personal.orders.autocomplete' => array('group' => 'personal', 'section' => 'profile', 'sub' => 'profile', 'scope' => 'user',
				'type' => 'bool', 'label' => 5704, 'help' => 5705, 'default' => true, 'permission' => 'any',
				'storage' => array('settings', 'ordre', 'ordreAutocomplete', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('autosøgning', 'autocomplete')),
		);

		$groups = getSettingsGroups();
		foreach ($defs as $key => $def) {
			if (!isset($def['group'])) {
				$parts = explode('.', $key);
				$def['group'] = $parts[0];
				$def['section'] = $parts[1];
			}
			$def += array('scope' => 'company', 'audit' => true, 'visible_if' => null, 'validate' => null, 'keywords' => array());
			if (!isset($def['permission'])) {
				$def['permission'] = isset($groups[$def['group']]) ? $groups[$def['group']]['permission'] : 'system.indstillinger';
			}
			$defs[$key] = $def;
		}
		return $defs;
	}

	/**
	 * Appendix A of the settings redesign spec: where each old menu item lives now.
	 * 'old' is the old menu path (text ids), 'to' the new home: group, generated section
	 * (null until that section has landed) and the page that holds it today.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function getSettingsMovedMap(): array
	{
		$d = 782; // "Diverse"
		return array(
			array('old' => array(770), 'to' => array(array('finance', null, 'syssetup.php?valg=moms'))),
			array('old' => array(771), 'to' => array(array('sales', null, 'syssetup.php?valg=debitor'), array('purchase', null, 'syssetup.php?valg=debitor'))),
			array('old' => array(772), 'to' => array(array('organisation', null, 'syssetup.php?valg=afdelinger'))),
			array('old' => array(773), 'to' => array(array('organisation', null, 'syssetup.php?valg=projekter'))),
			array('old' => array(608), 'to' => array(array('items', null, 'syssetup.php?valg=lagre'))),
			array('old' => array(774), 'to' => array(array('items', null, 'syssetup.php?valg=varer'), array('sales', null, 'syssetup.php?valg=varer'))),
			array('old' => array(775), 'to' => array(array('sales', null, 'rabatgrupper.php'))),
			array('old' => array(776), 'to' => array(array('finance', null, 'valuta.php'))),
			array('old' => array(778), 'to' => array(array('company', null, 'regnskabsaar.php'))),
			array('old' => array(779), 'to' => array(array('company', null, 'stamkort.php'))),
			array('old' => array(780), 'to' => array(array('documents', null, 'formularkort.php?valg=formularer'))),
			array('old' => array(781), 'to' => array(array('items', null, 'enheder.php'))),
			array('old' => array($d, 783), 'to' => array(array('company', null, 'diverse.php?sektion=kontoindstillinger'))),
			array('old' => array($d, 784), 'to' => array(array('organisation', null, 'diverse.php?sektion=provision'))),
			array('old' => array($d, 786), 'to' => array(array('sales', 'sales.orders', null), array('sales', 'sales.debtor_card', null), array('purchase', 'purchase.orders', null), array('items', 'items.stock', null), array('personal', null, 'personalSettings.php'))),
			array('old' => array($d, 787), 'to' => array(array('items', 'items.stock', null), array('items', null, 'diverse.php?sektion=productOptions'))),
			array('old' => array($d, 788), 'to' => array(array('items', null, 'diverse.php?sektion=variant_valg'))),
			array('old' => array($d, 790), 'to' => array(array('integrations', null, 'diverse.php?sektion=api_valg'))),
			array('old' => array($d, 791), 'to' => array(array('items', null, 'diverse.php?sektion=labels'))),
			array('old' => array($d, 792), 'to' => array(array('purchase', null, 'diverse.php?sektion=pricelists'))),
			array('old' => array($d, 793), 'to' => array(array('sales', null, 'diverse.php?sektion=rykker_valg'))),
			array('old' => array($d, 794), 'to' => array(array('sales', 'sales.debtor_card', null), array('company', null, 'diverse.php?sektion=div_valg'))),
			array('old' => array($d, 796), 'to' => array(array('organisation', null, 'diverse.php?sektion=tjekliste'))),
			array('old' => array($d, 797), 'to' => array(array('finance', null, 'diverse.php?sektion=bilag'))),
			array('old' => array($d, 170), 'to' => array(array('finance', null, 'diverse.php?sektion=orediff'))),
			array('old' => array($d, 200), 'to' => array(array('sales', 'sales.orders', null))),
			array('old' => array($d, 271), 'to' => array(array('pos', null, 'diverse.php?sektion=posOptions'))),
			array('old' => array($d, 801), 'to' => array(array('personal', null, 'personalSettings.php'), array('company', null, 'diverse.php?sektion=sprog'))),
			array('old' => array($d, 802), 'to' => array(array('import_export', null, 'diverse.php?sektion=div_io'))),
		);
	}

	/**
	 * A text in every language Saldi ships (the columns of importfiler/tekster.csv), lower case,
	 * for searching: people type the name they remember, whatever language the screen is in.
	 * Read from the file, so a search never writes rows for languages nobody uses.
	 */
	function settings_text_all_languages($id): string
	{
		static $texts = null;
		if ($texts === null) {
			$texts = array();
			$fp = @fopen(__DIR__ . '/../importfiler/tekster.csv', 'r');
			if ($fp) {
				fgets($fp);
				while (($line = fgets($fp)) !== false) {
					$cols = explode("\t", rtrim($line, "\r\n"));
					if (count($cols) > 1) {
						$texts[$cols[0]] = mb_strtolower(html_entity_decode(implode(' | ', array_slice($cols, 1)), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
					}
				}
				fclose($fp);
			}
		}
		$id = (string) (int) $id;
		return isset($texts[$id]) ? $texts[$id] : '';
	}

	/**
	 * @param array<int, int> $path text ids
	 */
	function settings_legacy_all_languages(array $path): string
	{
		$out = '';
		foreach ($path as $id) {
			$out .= ' ' . settings_text_all_languages($id);
		}
		return $out;
	}

	function settings_section_url(string $sectionId, string $key = ''): string
	{
		return 'settingsSection.php?s=' . rawurlencode($sectionId) . ($key !== '' ? '#' . $key : '');
	}

	/**
	 * Old menu path as text, e.g. "Diverse → Ordrerelaterede valg".
	 *
	 * @param array<int, int> $path text ids
	 */
	function settings_legacy_text(array $path, int $sprogId): string
	{
		$parts = array();
		foreach ($path as $id) {
			$parts[] = findtekst((string) $id, $sprogId);
		}
		return implode(' → ', $parts);
	}

	/**
	 * Definitions of one section, in registry order.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function settings_section_definitions(string $sectionId): array
	{
		$out = array();
		foreach (getSettingDefinitions() as $key => $def) {
			if ($def['group'] . '.' . $def['section'] === $sectionId) {
				$out[$key] = $def + array('key' => $key);
			}
		}
		return $out;
	}
}
