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
// 20260930 Sawaneh Field links use ?field= so they work through the shell (spec §8.11).
// 20261002 Sawaneh Phase 4b batch 1: G2.5 cash journal & payments, G3.6 mySale, G5.6 consignment, G5.7 packaging,
//                  G6.4 print, G7.4 commission; 'module' gates a section, 'on_save' names a follow-up, type 'date'.
// 20261004 Sawaneh G4.3 Price lists: a list section whose items come from the database ('items_from'), 'per' => 'pricelist'
//                  fields on storage 'grupper_row', 'value_map', rules 'required' and 'csv_url', a page-level 'add_action'.
// 20261004 Sawaneh G2.6 Document storage: storage 'virtual', encoding 'urlencode' for a secret, 'ensure_suffix'.
// 20261003 Sawaneh G3.4 Reminders: storage 'formularer', type 'creditor', 'options_from' users, 'group_label' and 'sub_help'.
// 20261003 Sawaneh G6.3 E-mail: storage 'adresser', 'per' => 'language' fields expanded per form language (scope 'group'),
//                  type 'textarea', a section-level 'permission'.
// 20261002 Sawaneh Phase 4b batch 2: G9 Integrations as a list section ('kind' list, 'items', 'sub_module'); types secret,
//                  info, link and mini; 'options_from', 'options_literal', 'locked_if' ht_keys:, rule 'setting_set'.
// 20261002 Sawaneh Merge of master: its new batchExpiryEnabled setting (was on the removed Varerelaterede valg page) is
//                  items.stock.batch_expiry.
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
				'old' => array('productOptions' => array(782, 787)),
				'context' => array('lager/varekort.php', 'lager/varer.php'),
				'keywords' => array('lager', 'stock', 'inventory', 'kostpris', 'cost price', 'fifo', 'beholdning', 'minimumsbeholdning'),
			),
			// ---- phase 4b
			'finance.cash_journal' => array(
				'group' => 'finance', 'section' => 'cash_journal', 'number' => 'G2.5', 'label' => 5983, 'icon' => 'bx-book',
				'subsections' => array('journal' => 601, 'payments' => 532, 'due' => 2732, 'rounding' => 170),
				'legacy' => array(array(782, 794), array(782, 170), array(2732)),
				'old' => array('div_valg' => array(782, 794), 'orediff' => array(782, 170), 'betalinger' => array(2732)),
				'context' => array('finans/kassekladde.php', 'debitor/betalingsliste.php', 'kreditor/betalingsliste.php'),
				'keywords' => array('kassekladde', 'cash journal', 'betalingsliste', 'payment list', 'betalingsfrist', 'payment days', 'øredifferencer', 'rounding', 'bilagsnummer', 'voucher'),
			),
			'purchase.pricelists' => array(
				'group' => 'purchase', 'section' => 'pricelists', 'number' => 'G4.3', 'label' => 6219, 'icon' => 'bx-spreadsheet', 'kind' => 'list',
				'lead' => 6220, 'items_from' => 'pricelists', 'add_action' => 'purchase.pricelists.create', 'empty_text' => 6250,
				'subsections' => array('lists' => 6219),
				'legacy' => array(array(782, 792)),
				'old' => array('pricelists' => array(782, 792)),
				'context' => array('debitor/ordre.php', 'debitor/_varerInsert.php'),
				'keywords' => array('prisliste', 'prislister', 'price list', 'price lists', 'leverandør', 'supplier', 'csv', 'prisfil', 'price file'),
			),
			'finance.document_storage' => array(
				'group' => 'finance', 'section' => 'document_storage', 'number' => 'G2.6', 'label' => 6205, 'icon' => 'bx-folder',
				'subsections' => array('storage' => 6206, 'ftp' => 1343, 'viewer' => 6207),
				'sub_help' => array('ftp' => 1340),
				'legacy' => array(array(782, 797)),
				'old' => array('bilag' => array(782, 797)),
				'context' => array('includes/bilag.php', 'includes/vis_bilag.php', 'finans/kassekladde.php'),
				'keywords' => array('bilag', 'bilagsopbevaring', 'bilagshåndtering', 'document storage', 'documents', 'ftp', 'scanning', 'scannede bilag', 'bilagspulje', 'google docs'),
			),
			'sales.mysale' => array(
				'group' => 'sales', 'section' => 'mysale', 'number' => 'G3.6', 'label' => 5986, 'icon' => 'bx-store',
				'subsections' => array('mysale' => 5986, 'labels' => 5992),
				'legacy' => array(array(782, 794)),
				'old' => array('div_valg' => array(782, 794)),
				'context' => array('mysale/showMySale.php', 'debitor/debitor_kommission.php'),
				'keywords' => array('mit salg', 'mysale', 'my sales', 'loppemarked', 'kommission', 'provisionskunder', 'labels'),
			),
			'items.consignment' => array(
				'group' => 'items', 'section' => 'consignment', 'number' => 'G5.6', 'label' => 5975, 'icon' => 'bx-purchase-tag', 'module' => 'pos',
				'subsections' => array('consignment' => 5975, 'accounts' => 117, 'settlement' => 2051),
				'legacy' => array(array(782, 787)),
				'old' => array('productOptions' => array(782, 787)),
				'context' => array('lager/varekort.php', 'debitor/kasseoptaelling.php'),
				'keywords' => array('kommission', 'kommissionsvarer', 'consignment', 'commission', 'brugte varer', 'used items', 'afregning', 'settlement'),
			),
			'items.packaging' => array(
				'group' => 'items', 'section' => 'packaging', 'number' => 'G5.7', 'label' => 5976, 'icon' => 'bx-box',
				'subsections' => array('packaging' => 5976),
				'legacy' => array(array(782, 787)),
				'old' => array('productOptions' => array(782, 787)),
				'context' => array('lager/emballage.php', 'lager/varekort.php'),
				'keywords' => array('emballage', 'packaging', 'producentansvar', 'producer responsibility', 'emballageafgift'),
			),
			'documents.print' => array(
				'group' => 'documents', 'section' => 'print', 'number' => 'G6.4', 'label' => 5993, 'icon' => 'bx-printer',
				'subsections' => array('print' => 5993),
				'legacy' => array(array(782, 794)),
				'old' => array('div_valg' => array(782, 794)),
				'context' => array('includes/udskriv.php'),
				'keywords' => array('udskrift', 'print', 'printer', 'lokal printer', 'local printer', 'html', 'postscript', 'formulargenerering', 'form generation'),
			),
			'organisation.commission' => array(
				'group' => 'organisation', 'section' => 'commission', 'number' => 'G7.4', 'label' => 657, 'icon' => 'bx-line-chart',
				'subsections' => array('basis' => 1263, 'card' => 566),
				'legacy' => array(array(782, 784), array(782, 787)),
				'old' => array('provision' => array(782, 784)),
				'context' => array('finans/provisionsrapport.php', 'lager/varekort.php'),
				'keywords' => array('provision', 'commission', 'provisionsrapport', 'commission report', 'kundeansvarlig', 'referenceperson', 'skæringsdato', 'cut-off'),
			),
			'sales.reminders' => array(
				'group' => 'sales', 'section' => 'reminders', 'number' => 'G3.4', 'label' => 6190, 'icon' => 'bx-bell',
				'subsections' => array('responsible' => 6186, 'deadlines' => 6187, 'collection' => 6188, 'fees' => 6189),
				'sub_help' => array('deadlines' => 6202),
				'legacy' => array(array(782, 793), array(780)),
				'old' => array('rykker_valg' => array(782, 793)),
				'context' => array('debitor/rykker.php', 'debitor/ny_rykker.php'),
				'keywords' => array('rykker', 'rykkere', 'reminder', 'reminders', 'påmindelse', 'inkasso', 'collection', 'rykkergebyr', 'rente', 'interest', 'betalingsbetingelser', 'payment terms'),
			),
			'documents.email' => array(
				'group' => 'documents', 'section' => 'email', 'number' => 'G6.3', 'label' => 6166, 'icon' => 'bx-envelope', 'permission' => 'settings.email',
				'subsections' => array('server' => 6167, 'sender' => 6168, 'texts' => 6169),
				'legacy' => array(array(782, 783)),
				'old' => array('smtp' => array(782, 783), 'email_settings' => array(573, 6166), 'mailTxt' => array(606, 6169)),
				'context' => array('includes/formFuncIncludes/sendMail.php', 'debitor/mail_modtagere.php'),
				'keywords' => array('e-mail', 'email', 'mail', 'smtp', 'afsender', 'sender', 'mailtekst', 'mail text', 'emne', 'subject', 'mit salg', 'mysale', 'afregning', 'kryptering', 'ssl', 'tls'),
			),
			// ---- phase 4b batch 2: a list of integrations, each with a drawer (hand-over 2 Oct, mock-ups 07/08)
			'integrations.connections' => array(
				'group' => 'integrations', 'section' => 'connections', 'number' => 'G9', 'label' => 5537, 'icon' => 'bx-plug', 'kind' => 'list',
				'lead' => 6126,
				'subsections' => array('api' => 6055, 'shipping' => 6056, 'einvoice' => 6057, 'payments' => 6058),
				'sub_module' => array('payments' => 'pos'),
				'items' => array(
					'rest_api'  => array('sub' => 'api',      'abbr' => 'API', 'label' => 'REST API',        'desc' => 6059),
					'webshop'   => array('sub' => 'api',      'abbr' => 'WS',  'label' => 6068,              'desc' => 6069),
					'quickpay'  => array('sub' => 'api',      'abbr' => 'QP',  'label' => 'QuickPay',        'desc' => 6073),
					'gls'       => array('sub' => 'shipping', 'abbr' => 'GLS', 'label' => 'GLS',             'desc' => 6079),
					'dfm'       => array('sub' => 'shipping', 'abbr' => 'DFM', 'label' => 'Danske Fragtmænd', 'desc' => 6080),
					'easyubl'   => array('sub' => 'einvoice', 'abbr' => 'EAN', 'label' => 6084,              'desc' => 6085),
					'app'       => array('sub' => 'einvoice', 'abbr' => 'APP', 'label' => 6091,              'desc' => 6092),
					'mobilepay' => array('sub' => 'payments', 'abbr' => 'MP',  'label' => 'MobilePay',       'desc' => 6096),
					'flatpay'   => array('sub' => 'payments', 'abbr' => 'FP',  'label' => 'Flatpay',         'desc' => 6112),
					'vibrant'   => array('sub' => 'payments', 'abbr' => 'VB',  'label' => 'Vibrant',         'desc' => 6117),
					'bank'      => array('sub' => 'payments', 'abbr' => 'BK',  'label' => 6141,              'desc' => 6142, 'soon' => true),
				),
				'legacy' => array(array(782, 790), array(782, 794)),
				'old' => array('api_valg' => array(782, 790), 'div_valg' => array(782, 794)),
				'context' => array('debitor/ordre.php', 'api/rest_api.php'),
				'keywords' => array('integration', 'integrationer', 'api', 'rest api', 'api-nøgle', 'api key', 'webshop', 'shop', 'quickpay', 'gls', 'dfm', 'danske fragtmænd', 'fragt', 'shipping', 'nemhandel', 'easyubl', 'e-faktura', 'oioubl', 'app', 'qr', 'barcode', 'mobilepay', 'vipps', 'webhook', 'flatpay', 'vibrant', 'kortterminal', 'betalingsudbyder', 'payment provider'),
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
		$prov = array(782, 784);
		$ore = array(782, 170);
		$api = array(782, 790);
		$konto = array(782, 783);
		$rykker = array(782, 793);
		$bilag = array(782, 797);
		$prisliste = array(782, 792);

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
			// From master (batch expiry, #457): was a checkbox on the old Varerelaterede valg page.
			'items.stock.batch_expiry' => array('sub' => 'card', 'type' => 'bool', 'label' => 6051, 'help' => 6052, 'default' => false,
				'storage' => array('settings', 'items', 'batchExpiryEnabled', 'onOff'), 'legacy' => $vare,
				'keywords' => array('batch', 'batch management', 'batch control', 'expiry date', 'due date', 'shelf life', 'fefo', 'batchstyring', 'udløbsdato', 'holdbarhed', 'batchkontrol')),

			// ---------------------------------------------------------------- G2.5 Cash journal & payments
			'finance.cash_journal.different_dates_same_voucher' => array('sub' => 'journal', 'type' => 'bool', 'label' => 708, 'help' => 709, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box4', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg,
				'keywords' => array('bilagsnummer', 'voucher number', 'dato', 'date')),
			'finance.cash_journal.payment_lists' => array('sub' => 'payments', 'type' => 'select', 'label' => 184, 'help' => 185, 'default' => '',
				'options' => array('' => 2541, 'B' => 1266, 'D' => 5985, 'K' => 607),
				'storage' => array('grupper', 'DIV', 2, 'box10', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $divvalg,
				'keywords' => array('betalingslister', 'payment lists', 'erh', 'bank')),
			'finance.cash_journal.payment_days' => array('sub' => 'due', 'type' => 'int', 'label' => 2733, 'help' => 2734, 'default' => '', 'empty_ok' => true, 'unit' => 5025,
				'storage' => array('settings', 'payment_list', 'paymentDays', 'raw'), 'legacy' => array(2732),
				'keywords' => array('betalingsfrist', 'betalingsdage', 'payment days', 'forfald', 'due date')),
			'finance.cash_journal.rounding_max' => array('sub' => 'rounding', 'type' => 'decimal', 'label' => 172, 'help' => 171, 'default' => '',
				'storage' => array('grupper', 'OreDif', 1, 'box1', 'raw', 'row_name' => 'Oredifferencer'), 'legacy' => $ore,
				'keywords' => array('øredifference', 'rounding', 'afrunding')),
			'finance.cash_journal.rounding_account' => array('sub' => 'rounding', 'type' => 'account', 'label' => 174, 'help' => 173, 'default' => '',
				'storage' => array('grupper', 'OreDif', 1, 'box2', 'raw', 'row_name' => 'Oredifferencer'), 'legacy' => $ore, 'validate' => array('account_exists'),
				'keywords' => array('øredifferencekonto', 'rounding account')),

			// ---------------------------------------------------------------- G3.6 mySale
			'sales.mysale.enabled' => array('sub' => 'mysale', 'type' => 'bool', 'label' => 768, 'help' => 767, 'default' => false,
				'storage' => array('settings', 'debitor', 'mySale', 'onEmpty'), 'legacy' => $divvalg,
				'keywords' => array('mit salg', 'mysale', 'my sales')),
			'sales.mysale.show_times' => array('sub' => 'mysale', 'type' => 'bool', 'label' => 5990, 'help' => 5991, 'default' => false,
				'storage' => array('settings', 'mysale', 'showMysaleTimes', 'oneZero'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'sales.mysale.enabled', true)),
			'sales.mysale.disable_customer_labels' => array('sub' => 'labels', 'type' => 'bool', 'label' => 5987, 'help' => 5988, 'default' => false,
				'storage' => array('settings', 'debitor', 'mySaleLabel', 'onEmpty'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'sales.mysale.enabled', true)),
			'sales.mysale.label_max_length' => array('sub' => 'labels', 'type' => 'int', 'label' => 5989, 'help' => 2450, 'default' => 22,
				'storage' => array('settings', 'mysale', 'labelsize', 'raw'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'sales.mysale.enabled', true)),

			// ---------------------------------------------------------------- G5.6 Consignment items (PoS)
			'items.consignment.enabled' => array('sub' => 'consignment', 'type' => 'bool', 'label' => 1281, 'help' => 1282, 'default' => false,
				'storage' => array('settings', 'items', 'useCommission', 'onEmpty'), 'legacy' => $vare,
				'keywords' => array('kommission', 'consignment', 'commission')),
			'items.consignment.default_rate' => array('sub' => 'consignment', 'type' => 'decimal', 'label' => 1283, 'help' => 1284, 'default' => '', 'unit' => '%',
				'storage' => array('settings', 'items', 'defaultCommission', 'raw'), 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.include_vat' => array('sub' => 'consignment', 'type' => 'bool', 'label' => 2544, 'help' => 2545, 'default' => false,
				'storage' => array('settings', 'items', 'commissionInclVat', 'onEmpty'), 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.income_account_new' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1286, 'help' => 1287, 'default' => '',
				'storage' => array('settings', 'items', 'commissionAccountNew', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.settlement_account_new' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1289, 'help' => 1290, 'default' => '',
				'storage' => array('settings', 'items', 'customerCommissionAccountNew', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.own_account_new' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1291, 'help' => 1292, 'default' => '',
				'storage' => array('settings', 'items', 'ownCommissionAccountNew', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.income_account_used' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1293, 'help' => 1294, 'default' => '',
				'storage' => array('settings', 'items', 'commissionAccountUsed', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.settlement_account_used' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1295, 'help' => 1296, 'default' => '',
				'storage' => array('settings', 'items', 'customerCommissionAccountUsed', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.own_account_used' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1297, 'help' => 1298, 'default' => '',
				'storage' => array('settings', 'items', 'ownCommissionAccountUsed', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.settlement_from' => array('sub' => 'settlement', 'type' => 'date', 'label' => 1306, 'help' => 1307, 'default' => '2021-01-01',
				'storage' => array('settings', 'items', 'commissionFromDate', 'raw'), 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.convert_existing' => array('sub' => 'settlement', 'type' => 'action', 'label' => 1299, 'help' => 6005,
				'confirm_title' => 1299, 'confirm' => 6006, 'run' => 'convert_commission_items', 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),

			// ---------------------------------------------------------------- G5.7 Packaging
			'items.packaging.enabled' => array('sub' => 'packaging', 'type' => 'bool', 'label' => 5995, 'help' => 5996, 'default' => false,
				'storage' => array('settings', 'items', 'packagingModuleEnabled', 'onEmpty'), 'legacy' => $vare, 'on_save' => 'ensure_emballage_schema',
				'keywords' => array('emballage', 'packaging', 'producentansvar')),

			// ---------------------------------------------------------------- G6.4 Print
			'documents.print.local_printer' => array('sub' => 'print', 'type' => 'bool', 'label' => 763, 'help' => 5994, 'default' => false,
				'storage' => array('grupper', 'PV', 1, 'box1', 'onEmpty', 'row_name' => 'Udskrift'), 'legacy' => $divvalg,
				'keywords' => array('lokal printer', 'local printer', 'port 9100')),
			'documents.print.html_forms' => array('sub' => 'print', 'type' => 'bool', 'label' => 818, 'help' => 817, 'default' => false,
				'storage' => array('grupper', 'PV', 1, 'box3', 'onEmpty', 'row_name' => 'Udskrift'), 'legacy' => $divvalg,
				'keywords' => array('html', 'css', 'postscript', 'formulargenerering')),

			// ---------------------------------------------------------------- G7.4 Commission
			'organisation.commission.basis' => array('sub' => 'basis', 'type' => 'select', 'label' => 1269, 'help' => 6002, 'default' => 'fak',
				'options' => array('fak' => 1264, 'bet' => 1265),
				'storage' => array('grupper', 'DIV', 1, 'box4', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov),
			'organisation.commission.person_source' => array('sub' => 'basis', 'type' => 'select', 'label' => 1268, 'help' => 6003, 'default' => 'smart',
				'options' => array('smart' => 1266, 'ref' => 6001, 'kua' => 386),
				'storage' => array('grupper', 'DIV', 1, 'box1', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov),
			'organisation.commission.cost_source' => array('sub' => 'basis', 'type' => 'select', 'label' => 1270, 'help' => 6004, 'default' => 'batch',
				'options' => array('batch' => 1271, 'kort' => 566),
				'storage' => array('grupper', 'DIV', 1, 'box2', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov),
			'organisation.commission.cutoff_day' => array('sub' => 'basis', 'type' => 'int', 'label' => 1272, 'help' => 1724, 'default' => '', 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 1, 'box3', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov,
				'validate' => array('range', 1, 28)),
			'organisation.commission.default_rate' => array('sub' => 'card', 'type' => 'decimal', 'label' => 5997, 'help' => 5998, 'default' => '', 'unit' => '%',
				'storage' => array('settings', 'items', 'defaultProvision', 'raw'), 'legacy' => $vare),
			'organisation.commission.show_on_item_card' => array('sub' => 'card', 'type' => 'bool', 'label' => 5999, 'help' => 6000, 'default' => false,
				'storage' => array('settings', 'items', 'showProvision', 'onEmpty'), 'legacy' => $vare),

			// ---------------------------------------------------------------- G4.3 Supplier price lists (one drawer per grupper PL row)
			'purchase.pricelists.create' => array('sub' => 'lists', 'type' => 'action', 'label' => 6221, 'help' => 6220,
				'confirm_title' => 6221, 'confirm' => 6220, 'run' => 'pricelist_create', 'audit' => false),
			'purchase.pricelists.description' => array('sub' => 'lists', 'type' => 'text', 'label' => 914, 'per' => 'pricelist', 'validate' => array('required'),
				'storage' => array('grupper_row', 'PL', 'beskrivelse'), 'legacy' => $prisliste, 'keywords' => array('prisliste', 'beskrivelse', 'description')),
			'purchase.pricelists.url' => array('sub' => 'lists', 'type' => 'text', 'label' => 6223, 'help' => 6224, 'per' => 'pricelist', 'validate' => array('csv_url'),
				'storage' => array('grupper_row', 'PL', 'box2'), 'legacy' => $prisliste, 'keywords' => array('prisfil', 'url', 'csv', 'price file')),
			'purchase.pricelists.delimiter' => array('sub' => 'lists', 'type' => 'select', 'label' => 6225, 'per' => 'pricelist', 'default' => ';',
				'options' => array(';' => 6227, ',' => 6226, 'tab' => 6228), 'value_map' => array('tab' => "\t"),
				'storage' => array('grupper_row', 'PL', 'box10'), 'legacy' => $prisliste, 'keywords' => array('skilletegn', 'delimiter', 'separator')),
			'purchase.pricelists.encoding' => array('sub' => 'lists', 'type' => 'select', 'label' => 6229, 'per' => 'pricelist', 'default' => 'utf-8',
				'options' => array('utf-8' => 'UTF-8', 'iso-8859' => 'ISO-8859-1'), 'options_literal' => true,
				'storage' => array('grupper_row', 'PL', 'box11'), 'legacy' => $prisliste, 'keywords' => array('tegnsæt', 'encoding', 'charset')),
			'purchase.pricelists.item_group' => array('sub' => 'lists', 'type' => 'select', 'label' => 6230, 'help' => 6231, 'per' => 'pricelist', 'default' => '',
				'options_from' => 'item_groups', 'options_literal' => true, 'on_save' => 'pricelist_group_name',
				'storage' => array('grupper_row', 'PL', 'kodenr'), 'legacy' => $prisliste, 'keywords' => array('varegruppe', 'item group')),
			'purchase.pricelists.supplier' => array('sub' => 'lists', 'type' => 'select', 'label' => 6232, 'per' => 'pricelist', 'default' => '',
				'options_from' => 'creditor_names', 'options_literal' => true,
				'storage' => array('grupper_row', 'PL', 'box9'), 'legacy' => $prisliste, 'keywords' => array('leverandør', 'supplier', 'kreditor')),
			'purchase.pricelists.use' => array('sub' => 'lists', 'type' => 'action', 'label' => 6235, 'help' => 6236, 'per' => 'pricelist',
				'confirm_title' => 6237, 'confirm' => 6238, 'run' => 'pricelist_use', 'legacy' => $prisliste),
			'purchase.pricelists.test' => array('sub' => 'lists', 'type' => 'action', 'label' => 6251, 'help' => 6239, 'per' => 'pricelist',
				'confirm_title' => 6240, 'confirm' => 6241, 'run' => 'pricelist_test', 'legacy' => $prisliste),
			'purchase.pricelists.delete' => array('sub' => 'lists', 'type' => 'action', 'label' => 6242, 'help' => 6243, 'per' => 'pricelist', 'danger' => true,
				'confirm_title' => 6244, 'confirm' => 6245, 'run' => 'pricelist_delete', 'legacy' => $prisliste),

			// ---------------------------------------------------------------- G2.6 Document storage
			'finance.document_storage.type' => array('sub' => 'storage', 'type' => 'select', 'label' => 1341, 'help' => 6214, 'default' => '',
				'options' => array('internFTP' => 1342, 'externFTP' => 1343, '' => 1344),
				'storage' => array('virtual', 'document_storage'), 'legacy' => $bilag,
				'keywords' => array('opbevaring', 'storage', 'intern opbevaring', 'internal storage', 'ftp', 'ingen opbevaring')),
			'finance.document_storage.mail_address' => array('sub' => 'storage', 'type' => 'info', 'label' => 6216, 'help' => 6217, 'info' => 'document_mail', 'audit' => false,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('internFTP')),
				'keywords' => array('bilag mail', 'scan to mail', 'bilag_')),
			'finance.document_storage.ftp_server' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1346, 'help' => 6215, 'ensure_suffix' => '/',
				'storage' => array('grupper', 'bilag', 1, 'box1', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp server', 'ftp-server', 'host')),
			'finance.document_storage.ftp_user' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1347,
				'storage' => array('grupper', 'bilag', 1, 'box2', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'brugernavn', 'username')),
			'finance.document_storage.ftp_password' => array('sub' => 'ftp', 'type' => 'secret', 'label' => 1348,
				'storage' => array('grupper', 'bilag', 1, 'box3', 'urlencode', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'adgangskode', 'password')),
			'finance.document_storage.ftp_folder_vouchers' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1350, 'help' => 6218, 'default' => 'bilag',
				'storage' => array('grupper', 'bilag', 1, 'box4', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'mappe', 'folder', 'bilag')),
			'finance.document_storage.ftp_folder_documents' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1351, 'help' => 6218, 'default' => 'dokumenter',
				'storage' => array('grupper', 'bilag', 1, 'box5', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'mappe', 'folder', 'dokumenter')),
			'finance.document_storage.ftp_test' => array('sub' => 'ftp', 'type' => 'action', 'label' => 6208, 'help' => 6209,
				'confirm_title' => 6210, 'confirm' => 6211, 'run' => 'ftp_test', 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp test', 'test forbindelse', 'test connection')),
			'finance.document_storage.google_docs' => array('sub' => 'viewer', 'type' => 'bool', 'label' => 719, 'help' => 720, 'default' => false,
				'storage' => array('grupper', 'bilag', 1, 'box7', 'onEmpty', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'keywords' => array('google docs', 'viewer', 'visning')),

			// ---------------------------------------------------------------- G3.4 Payment terms & reminders
			'sales.reminders.responsible_user' => array('sub' => 'responsible', 'type' => 'select', 'label' => 225, 'help' => 6191, 'default' => '',
				'options_from' => 'users', 'options_mixed' => true,
				'storage' => array('grupper', 'DIV', 4, 'box1', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker,
				'keywords' => array('rykkeransvarlig', 'reminder manager', 'bruger', 'user')),
			'sales.reminders.responsible_mail' => array('sub' => 'responsible', 'type' => 'email', 'label' => 227, 'help' => 6192,
				'storage' => array('grupper', 'DIV', 4, 'box2', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker,
				'keywords' => array('rykkeransvarlig', 'mailadresse', 'e-mail')),
			'sales.reminders.days_1' => array('sub' => 'deadlines', 'type' => 'int', 'label' => 233, 'help' => 232, 'unit' => 1332, 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 4, 'box5', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker, 'validate' => array('range', 0, 365),
				'keywords' => array('rykker 1', 'reminder 1', 'frist', 'dage', 'days')),
			'sales.reminders.days_2' => array('sub' => 'deadlines', 'type' => 'int', 'label' => 235, 'help' => 234, 'unit' => 1332, 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 4, 'box6', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker, 'validate' => array('range', 0, 365),
				'keywords' => array('rykker 2', 'reminder 2', 'frist', 'dage', 'days')),
			'sales.reminders.days_3' => array('sub' => 'deadlines', 'type' => 'int', 'label' => 237, 'help' => 236, 'unit' => 1332, 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 4, 'box7', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker, 'validate' => array('range', 0, 365),
				'keywords' => array('rykker 3', 'reminder 3', 'frist', 'dage', 'days')),
			'sales.reminders.collection_lawyer' => array('sub' => 'collection', 'type' => 'creditor', 'creditor_as' => 'id', 'label' => 6193, 'help' => 6194,
				'storage' => array('grupper', 'DIV', 4, 'box9', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker,
				'keywords' => array('inkasso', 'inkassoadvokat', 'debt collection', 'kreditor', 'creditor')),
			'sales.reminders.fee_item_1' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6196, 'help' => 6197, 'per' => 'language', 'group_label' => array(6195, 1),
				'storage' => array('formularer', 6, 'xb', 'raw'), 'legacy' => array(780), 'keywords' => array('rykkergebyr', 'reminder fee', 'gebyr', 'rykker 1')),
			'sales.reminders.interest_item_1' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6198, 'help' => 6199, 'per' => 'language', 'group_label' => array(6195, 1),
				'storage' => array('formularer', 6, 'yb', 'raw'), 'legacy' => array(780), 'keywords' => array('rentevare', 'interest item', 'rykker 1')),
			'sales.reminders.interest_rate_1' => array('sub' => 'fees', 'type' => 'decimal', 'label' => 6200, 'help' => 6201, 'unit' => '%', 'per' => 'language', 'group_label' => array(6195, 1),
				'storage' => array('formularer', 6, 'str', 'raw'), 'legacy' => array(780), 'keywords' => array('rentesats', 'interest rate', 'rykker 1')),
			'sales.reminders.fee_item_2' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6196, 'help' => 6197, 'per' => 'language', 'group_label' => array(6195, 2),
				'storage' => array('formularer', 7, 'xb', 'raw'), 'legacy' => array(780), 'keywords' => array('rykkergebyr', 'reminder fee', 'gebyr', 'rykker 2')),
			'sales.reminders.interest_item_2' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6198, 'help' => 6199, 'per' => 'language', 'group_label' => array(6195, 2),
				'storage' => array('formularer', 7, 'yb', 'raw'), 'legacy' => array(780), 'keywords' => array('rentevare', 'interest item', 'rykker 2')),
			'sales.reminders.interest_rate_2' => array('sub' => 'fees', 'type' => 'decimal', 'label' => 6200, 'help' => 6201, 'unit' => '%', 'per' => 'language', 'group_label' => array(6195, 2),
				'storage' => array('formularer', 7, 'str', 'raw'), 'legacy' => array(780), 'keywords' => array('rentesats', 'interest rate', 'rykker 2')),
			'sales.reminders.fee_item_3' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6196, 'help' => 6197, 'per' => 'language', 'group_label' => array(6195, 3),
				'storage' => array('formularer', 8, 'xb', 'raw'), 'legacy' => array(780), 'keywords' => array('rykkergebyr', 'reminder fee', 'gebyr', 'rykker 3')),
			'sales.reminders.interest_item_3' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6198, 'help' => 6199, 'per' => 'language', 'group_label' => array(6195, 3),
				'storage' => array('formularer', 8, 'yb', 'raw'), 'legacy' => array(780), 'keywords' => array('rentevare', 'interest item', 'rykker 3')),
			'sales.reminders.interest_rate_3' => array('sub' => 'fees', 'type' => 'decimal', 'label' => 6200, 'help' => 6201, 'unit' => '%', 'per' => 'language', 'group_label' => array(6195, 3),
				'storage' => array('formularer', 8, 'str', 'raw'), 'legacy' => array(780), 'keywords' => array('rentesats', 'interest rate', 'rykker 3')),

			// ---------------------------------------------------------------- G6.3 E-mail
			'documents.email.smtp_host' => array('sub' => 'server', 'type' => 'text', 'label' => 6170, 'help' => 6184,
				'storage' => array('adresser', 'felt_1', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'mailserver', 'mail server', 'port')),
			'documents.email.smtp_user' => array('sub' => 'server', 'type' => 'text', 'label' => 225, 'help' => 749,
				'storage' => array('adresser', 'felt_2', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'brugernavn', 'username')),
			'documents.email.smtp_password' => array('sub' => 'server', 'type' => 'secret', 'label' => 324, 'help' => 750,
				'storage' => array('adresser', 'felt_3', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'adgangskode', 'password')),
			'documents.email.smtp_encryption' => array('sub' => 'server', 'type' => 'select', 'label' => 748, 'help' => 751, 'default' => '',
				'options' => array('' => 6171, 'ssl' => 'SSL', 'tls' => 'TLS'), 'options_mixed' => true,
				'storage' => array('adresser', 'felt_4', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'kryptering', 'encryption', 'ssl', 'tls')),
			'documents.email.sender_email' => array('sub' => 'sender', 'type' => 'email', 'label' => 6172, 'help' => 6173, 'per' => 'language', 'per_default_label' => 6183,
				'storage' => array('settings', 'email_settings', 'sender_email', 'raw'), 'legacy' => array(573, 6166),
				'keywords' => array('afsender', 'sender', 'afsender e-mail', 'sender e-mail', 'from')),
			'documents.email.sender_name' => array('sub' => 'sender', 'type' => 'text', 'label' => 6174, 'help' => 6175, 'per' => 'language', 'per_default_label' => 6183,
				'storage' => array('settings', 'email_settings', 'sender_name', 'raw'), 'legacy' => array(573, 6166),
				'keywords' => array('afsender', 'sender', 'afsendernavn', 'sender name')),
			'documents.email.customer_subject' => array('sub' => 'texts', 'type' => 'text', 'label' => 6176, 'help' => 1150,
				'storage' => array('settings', 'debitor', 'mailSubject', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mailtekst', 'mail text', 'emne', 'subject', 'kunder', 'customers')),
			'documents.email.customer_text' => array('sub' => 'texts', 'type' => 'textarea', 'label' => 6177, 'help' => 1151,
				'storage' => array('settings', 'debitor', 'mailText', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mailtekst', 'mail text', 'kunder', 'customers', '$kunde')),
			'documents.email.mysale_subject' => array('sub' => 'texts', 'type' => 'text', 'label' => 6178, 'help' => 1152,
				'storage' => array('settings', 'mySale', 'mailSubject', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mit salg', 'mysale', 'invitation', 'emne', 'subject')),
			'documents.email.mysale_text' => array('sub' => 'texts', 'type' => 'textarea', 'label' => 6179, 'help' => 6185,
				'storage' => array('settings', 'mySale', 'mailText', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mit salg', 'mysale', 'invitation', '$link')),
			'documents.email.paylist_text' => array('sub' => 'texts', 'type' => 'textarea', 'label' => 6180, 'help' => 6181,
				'storage' => array('settings', 'paylist', 'mailText', 'raw'),
				'keywords' => array('afregning', 'settlement', 'betalingsliste', 'payment list', 'kommission', 'commission')),

			// ---------------------------------------------------------------- G9 Integrations (list + drawer)
			'integrations.rest_api.key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'secret', 'label' => 819, 'help' => 820,
				'storage' => array('grupper', 'API', 1, 'box1', 'raw', 'row_name' => 'API valg'), 'legacy' => $api,
				'keywords' => array('api nøgle', 'api key', 'nøgle', 'token')),
			'integrations.rest_api.ip_list' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 821, 'help' => 822,
				'storage' => array('grupper', 'API', 1, 'box2', 'raw', 'row_name' => 'API valg'), 'legacy' => $api, 'validate' => array('ip_list'),
				'keywords' => array('ip', 'ip-adresse', 'ip address', 'whitelist')),
			'integrations.rest_api.client_url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 6061, 'help' => 830,
				'storage' => array('grupper', 'API', 1, 'box4', 'raw', 'row_name' => 'API valg'), 'legacy' => $api,
				'keywords' => array('api klient', 'api client', 'varesync')),
			'integrations.rest_api.client_url2' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 6153, 'help' => 6155,
				'storage' => array('grupper', 'API', 1, 'box5', 'raw', 'row_name' => 'API valg'), 'legacy' => $api),
			'integrations.rest_api.client_url3' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 6154, 'help' => 6155,
				'storage' => array('grupper', 'API', 1, 'box6', 'raw', 'row_name' => 'API valg'), 'legacy' => $api),
			'integrations.rest_api.invoice_email' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'bool', 'label' => 6062, 'help' => 6063, 'default' => false,
				'storage' => array('settings', 'api', 'api_invoice_email_enabled', 'onEmpty'), 'legacy' => $api,
				'keywords' => array('faktura e-mail', 'invoice e-mail')),
			'integrations.rest_api.db' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'info', 'label' => 6147, 'help' => 832, 'info' => 'saldi_db', 'audit' => false),
			'integrations.rest_api.url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'info', 'label' => 6148, 'help' => 836, 'info' => 'saldi_url', 'audit' => false),
			'integrations.rest_api.swagger' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'link', 'label' => 6149, 'href' => '../restapi/swagger-ui.html#/', 'button' => 6157, 'blank' => true, 'audit' => false,
				'keywords' => array('swagger', 'dokumentation', 'documentation')),
			'integrations.rest_api.generate_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'action', 'label' => 6064, 'help' => 6065,
				'confirm_title' => 6066, 'confirm' => 6067, 'run' => 'generate_api_key', 'legacy' => $api,
				'keywords' => array('ny api nøgle', 'new api key', 'generér', 'generate')),

			'integrations.webshop.url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'text', 'label' => 6070, 'help' => 6071,
				'storage' => array('grupper', 'DIV', 5, 'box2', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $api,
				'keywords' => array('shop url', 'webshop url', 'shopurl')),
			'integrations.webshop.charset' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'select', 'label' => 6072, 'default' => 'UTF-8',
				'options' => array('UTF-8' => 'UTF-8', 'ISO-8859-1' => 'ISO-8859-1'), 'options_literal' => true,
				'storage' => array('grupper', 'DIV', 5, 'box7', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $api,
				'keywords' => array('tegnsæt', 'charset', 'encoding', 'utf-8', 'iso-8859-1')),
			'integrations.webshop.sync_new' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'action', 'label' => 741, 'help' => 740,
				'confirm_title' => 741, 'confirm' => 740, 'run' => 'shop_sync_new', 'blank' => true, 'legacy' => $api,
				'keywords' => array('hent varer', 'fetch items', 'varesync')),
			'integrations.webshop.sync_update' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'action', 'label' => 2546, 'help' => 1726,
				'confirm_title' => 2546, 'confirm' => 1726, 'run' => 'shop_sync_update', 'blank' => true, 'legacy' => $api,
				'keywords' => array('opdater fra shop', 'update from shop', 'varesync')),

			'integrations.quickpay.agreement_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'text', 'label' => 6074, 'help' => 6075,
				'storage' => array('settings', 'quickpay', 'qp_agreement_id', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'aftale id', 'agreement id')),
			'integrations.quickpay.merchant' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'text', 'label' => 6076, 'help' => 691,
				'storage' => array('settings', 'quickpay', 'qp_merchant', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'merchant')),
			'integrations.quickpay.md5secret' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'secret', 'label' => 6077, 'help' => 6078,
				'storage' => array('settings', 'quickpay', 'qp_md5secret', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'md5', 'secret')),
			'integrations.quickpay.item_group' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'select', 'label' => 6161, 'help' => 6162, 'default' => '',
				'options_from' => 'item_groups', 'options_literal' => true,
				'storage' => array('settings', 'quickpay', 'qp_itemGrp', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'varegruppe', 'item group')),

			'integrations.gls.id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'text', 'label' => 865, 'help' => 866,
				'storage' => array('settings', 'GLS', 'gls_id', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'gls id', 'pakkelabel', 'parcel label')),
			'integrations.gls.user' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'text', 'label' => 867, 'help' => 868,
				'storage' => array('settings', 'GLS', 'gls_user', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'brugernavn', 'username')),
			'integrations.gls.contact_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'text', 'label' => 873, 'help' => 874,
				'storage' => array('settings', 'GLS', 'gls_ctId', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'kontakt id', 'contact id')),
			'integrations.gls.password' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'secret', 'label' => 869, 'help' => 870,
				'storage' => array('settings', 'GLS', 'gls_pass', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'adgangskode', 'password')),

			'integrations.dfm.agreement' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1020, 'help' => 1021,
				'storage' => array('settings', 'GLS', 'dfm_agree', 'raw'), 'legacy' => $divvalg, 'keywords' => array('danske fragtmænd', 'dfm', 'aftalenummer', 'agreement number')),
			'integrations.dfm.hub' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1022, 'help' => 1023,
				'storage' => array('settings', 'GLS', 'dfm_hub', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'hub')),
			'integrations.dfm.api_url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 3129, 'help' => 3130,
				'storage' => array('settings', 'GLS', 'dfm_url', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'api url')),
			'integrations.dfm.client_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1014, 'help' => 1015,
				'storage' => array('settings', 'GLS', 'dfm_id', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'clientid', 'client id')),
			'integrations.dfm.user' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1016, 'help' => 1017,
				'storage' => array('settings', 'GLS', 'dfm_user', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'brugernavn', 'username')),
			'integrations.dfm.password' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'secret', 'label' => 1018, 'help' => 1019,
				'storage' => array('settings', 'GLS', 'dfm_pass', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'password', 'adgangskode')),
			'integrations.dfm.shipping_type' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1024, 'help' => 1025,
				'storage' => array('settings', 'GLS', 'dfm_ship', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'shippingtype', 'shipping type')),
			'integrations.dfm.goods_type' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1026, 'help' => 1027,
				'storage' => array('settings', 'GLS', 'dfm_good', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'godstype', 'goods type')),
			'integrations.dfm.payment' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1028, 'help' => 1029,
				'storage' => array('settings', 'GLS', 'dfm_pay', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'betalingsmetode', 'payment method')),
			'integrations.dfm.goods_description' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 6081, 'help' => 1039,
				'storage' => array('settings', 'GLS', 'dfm_gooddes', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'godsbeskrivelse', 'goods description')),
			'integrations.dfm.delivery' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1058, 'help' => 1059,
				'storage' => array('settings', 'GLS', 'dfm_sercode', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'afleveringsmetode', 'delivery method')),
			'integrations.dfm.pickup' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'link', 'label' => 6082, 'help' => 6165, 'href' => 'diverse.php?sektion=div_valg', 'button' => 6157, 'audit' => false,
				'legacy' => $divvalg, 'keywords' => array('afhentningsadresse', 'afhentningsadresser', 'pickup address', 'pick-up address')),

			'integrations.easyubl.api_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'easyubl', 'type' => 'secret', 'label' => 6086, 'help' => 6087,
				'storage' => array('settings', 'easyUBL', 'apiKey', 'raw'), 'locked_if' => 'ht_keys:easyUBLApiKey', 'locked_text' => 6088,
				'keywords' => array('easyubl', 'nemhandel', 'e-faktura', 'oioubl', 'api nøgle', 'api key')),
			'integrations.easyubl.company_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'easyubl', 'type' => 'text', 'label' => 6089, 'help' => 6090,
				'storage' => array('settings', 'easyUBL', 'companyID', 'raw'),
				'keywords' => array('easyubl', 'nemhandel', 'virksomheds id', 'company id')),
			'integrations.app.api_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'app', 'type' => 'secret', 'label' => 6093, 'help' => 6094,
				'storage' => array('settings', 'app_api', 'apikey', 'raw'), 'locked_if' => 'ht_keys:aiApiKey', 'locked_text' => 6088,
				'keywords' => array('app', 'api nøgle', 'api key', 'bilagsgenkendelse')),
			'integrations.app.qr' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'app', 'type' => 'link', 'label' => 6095, 'help' => 6092, 'href' => 'barcodescan.php', 'button' => 6157, 'audit' => false,
				'keywords' => array('app', 'qr', 'qr-kode', 'barcode', 'app login', 'app barcode')),

			'integrations.mobilepay.client_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'text', 'label' => 6097, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'client_id', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'client id')),
			'integrations.mobilepay.client_secret' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'secret', 'label' => 6099, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'client_secret', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'client secret')),
			'integrations.mobilepay.subscription_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'secret', 'label' => 6100, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'subscriptionKey', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'subscription key')),
			'integrations.mobilepay.msn' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'text', 'label' => 6101, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'MSN', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'msn', 'merchant serial number')),
			'integrations.mobilepay.connect_webhook' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'action', 'label' => 6104, 'help' => 6030,
				'confirm_title' => 6105, 'confirm' => 6106, 'run' => 'mobilepay_webhook', 'blank' => true, 'visible_if' => array('setting_set', 'integrations.mobilepay.client_id'), 'legacy' => $divvalg,
				'keywords' => array('mobilepay', 'webhook')),
			'integrations.mobilepay.qr_codes' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'info', 'label' => 6163, 'help' => 6109, 'info' => 'mobilepay_qr', 'audit' => false,
				'visible_if' => array('setting_set', 'integrations.mobilepay.client_id'), 'keywords' => array('mobilepay', 'qr', 'qr-kode', 'kasse')),
			'integrations.mobilepay.create_qr' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'action', 'label' => 6108, 'help' => 6111,
				'confirm_title' => 6110, 'confirm' => 6111, 'run' => 'mobilepay_qr', 'visible_if' => array('setting_set', 'integrations.mobilepay.client_id'), 'legacy' => $divvalg,
				'keywords' => array('mobilepay', 'qr', 'qr-koder')),

			'integrations.flatpay.login' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'flatpay', 'type' => 'mini', 'label' => 6113, 'help' => 6114, 'mini' => 'flatpay', 'audit' => false,
				'legacy' => $divvalg, 'keywords' => array('flatpay', 'flatpay id', 'kortterminal', 'card terminal')),
			'integrations.vibrant.api_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'vibrant', 'type' => 'secret', 'label' => 2317, 'help' => 2318,
				'storage' => array('settings', 'globals', 'vibrant_auth', 'raw'), 'legacy' => $divvalg, 'keywords' => array('vibrant', 'api nøgle', 'api key')),
			'integrations.vibrant.terminal_login' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'vibrant', 'type' => 'mini', 'label' => 6150, 'help' => 6151, 'mini' => 'vibrant', 'audit' => false,
				'legacy' => $divvalg, 'keywords' => array('vibrant', 'login', 'terminal', 'kortterminal')),

			// ---------------------------------------------------------------- Personal (topbar spec §4)
			'personal.orders.autocomplete' => array('group' => 'personal', 'section' => 'profile', 'sub' => 'profile', 'scope' => 'user',
				'type' => 'bool', 'label' => 5704, 'help' => 5705, 'default' => true, 'permission' => 'any',
				'storage' => array('settings', 'ordre', 'ordreAutocomplete', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('autosøgning', 'autocomplete')),
			'personal.print.local_print' => array('group' => 'personal', 'section' => 'print', 'sub' => 'print', 'scope' => 'user',
				'type' => 'bool', 'label' => 6007, 'help' => 6008, 'default' => false, 'permission' => 'any',
				'storage' => array('settings', 'print', 'localPrint', 'onEmpty'), 'legacy' => $divvalg,
				'keywords' => array('lokal printer', 'local printer')),
		);

		$groups = getSettingsGroups();
		$sections = getSettingsSections();
		$expanded = array();
		$order = array_keys($defs);
		foreach ($defs as $key => $def) {
			if (!isset($def['group'])) {
				$parts = explode('.', $key);
				$def['group'] = $parts[0];
				$def['section'] = $parts[1];
			}
			$def += array('scope' => 'company', 'audit' => true, 'visible_if' => null, 'validate' => null, 'keywords' => array(), 'on_save' => null);
			if (!isset($def['permission'])) {
				$sectionId = $def['group'] . '.' . $def['section'];
				if (isset($sections[$sectionId]['permission'])) {
					$def['permission'] = $sections[$sectionId]['permission'];
				} else {
					$def['permission'] = isset($groups[$def['group']]) ? $groups[$def['group']]['permission'] : 'system.indstillinger';
				}
			}
			if (isset($def['per']) && $def['per'] === 'language') {
				// One field per form language (settings.group_id): the languages are the company's own rows.
				foreach (settings_form_languages() as $langId => $langName) {
					if ($langId === 0 && isset($def['per_default_label'])) {
						$langName = findtekst((string) $def['per_default_label'], isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1);
					}
					$expanded[$key . '.' . $langId] = array('scope' => 'group', 'scope_id' => (int) $langId, 'label_suffix' => $langName, 'per_key' => $key) + $def;
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && $def['per'] === 'pricelist') {
				// One field per price list (grupper art PL); the drawer of the list is item pl_<id>.
				foreach (settings_pricelist_rows() as $rowId => $rowName) {
					$expanded[$key . '.' . $rowId] = array('scope' => 'row', 'scope_id' => (int) $rowId, 'label_suffix' => $rowName, 'per_key' => $key, 'item' => 'pl_' . $rowId) + $def;
				}
				unset($defs[$key]);
				continue;
			}
			$defs[$key] = $def;
		}
		if ($expanded) {
			// The expanded fields go where the first 'per' field of their card was, grouped by language, so the form
			// shows every field of one language together.
			$slots = array();
			foreach ($expanded as $key => $def) {
				$slot = $def['group'] . '.' . $def['section'] . '.' . $def['sub'];
				$slots[$slot][$def['scope_id']][$key] = $def;
			}
			$out = array();
			$placed = array();
			foreach ($order as $key) {
				if (isset($defs[$key])) {
					$out[$key] = $defs[$key];
					continue;
				}
				$def = null;
				foreach ($expanded as $k => $d) {
					if ($d['per_key'] === $key) {
						$def = $d;
						break;
					}
				}
				if ($def === null) {
					continue;
				}
				$slot = $def['group'] . '.' . $def['section'] . '.' . $def['sub'];
				if (isset($placed[$slot])) {
					continue;
				}
				$placed[$slot] = true;
				foreach ($slots[$slot] as $langDefs) {
					foreach ($langDefs as $k => $d) {
						$out[$k] = $d;
					}
				}
			}
			$defs = $out;
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
			array('old' => array($d, 783), 'to' => array(array('company', null, 'diverse.php?sektion=kontoindstillinger'), array('documents', 'documents.email', null))),
			array('old' => array($d, 784), 'to' => array(array('organisation', 'organisation.commission', null))),
			array('old' => array($d, 786), 'to' => array(array('sales', 'sales.orders', null), array('sales', 'sales.debtor_card', null), array('purchase', 'purchase.orders', null), array('items', 'items.stock', null), array('personal', null, 'personalSettings.php'))),
			array('old' => array($d, 787), 'to' => array(array('items', 'items.stock', null), array('items', 'items.consignment', null), array('items', 'items.packaging', null))),
			array('old' => array($d, 788), 'to' => array(array('items', null, 'diverse.php?sektion=variant_valg'))),
			array('old' => array($d, 790), 'to' => array(array('integrations', 'integrations.connections', null))),
			array('old' => array($d, 791), 'to' => array(array('items', null, 'diverse.php?sektion=labels'))),
			array('old' => array($d, 792), 'to' => array(array('purchase', 'purchase.pricelists', null))),
			array('old' => array($d, 793), 'to' => array(array('sales', 'sales.reminders', null))),
			array('old' => array($d, 794), 'to' => array(array('sales', 'sales.debtor_card', null), array('finance', 'finance.cash_journal', null), array('sales', 'sales.mysale', null), array('documents', 'documents.print', null), array('company', null, 'diverse.php?sektion=div_valg'), array('integrations', 'integrations.connections', null))),
			array('old' => array($d, 796), 'to' => array(array('organisation', null, 'diverse.php?sektion=tjekliste'))),
			array('old' => array($d, 797), 'to' => array(array('finance', 'finance.document_storage', null))),
			array('old' => array($d, 170), 'to' => array(array('finance', 'finance.cash_journal', null))),
			array('old' => array(2732), 'to' => array(array('finance', 'finance.cash_journal', null))),
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
		// A query parameter, not #key: the shell keeps the page address in its own #.
		return 'settingsSection.php?s=' . rawurlencode($sectionId) . ($key !== '' ? '&field=' . rawurlencode($key) : '');
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
	 * The form languages of the company: 0 is the default (Danish and languages without their own row), then the
	 * VSPR rows the forms editor keeps. This is what includes/formFuncIncludes/sendMail.php looks the sender up by.
	 *
	 * @return array<int, string>
	 */
	/**
	 * The supplier price lists (grupper art PL): id => description.
	 *
	 * @return array<int, string>
	 */
	function settings_pricelist_rows(): array
	{
		static $rows = null;
		if ($rows === null || !empty($GLOBALS['settings_pricelists_changed'])) {
			$rows = array();
			unset($GLOBALS['settings_pricelists_changed']);
			$q = db_select("select id, beskrivelse from grupper where art = 'PL' order by beskrivelse, id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rows[(int) $r['id']] = trim((string) $r['beskrivelse']);
			}
		}
		return $rows;
	}

	function settings_form_language_name(int $langId): string
	{
		$languages = settings_form_languages();
		return ($langId > 0 && isset($languages[$langId])) ? $languages[$langId] : 'Dansk';
	}

	function settings_form_languages(): array
	{
		static $languages = null;
		if ($languages === null) {
			$languages = array(0 => 'Dansk');
			$q = db_select("select kodenr, box1 from grupper where art = 'VSPR' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ((int) $r['kodenr'] > 0) {
					$languages[(int) $r['kodenr']] = trim((string) $r['box1']);
				}
			}
		}
		return $languages;
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
