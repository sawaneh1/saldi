<?php
// ---- includes/settings/virtualStorage.php --- lap 5.0.0 --- 2026.10.04 ---
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
// 20261004 Sawaneh Settings redesign: a setting whose value is derived from several stored fields (storage
//                  'virtual'). Read and written here, audited like any other setting by SettingsService.

/**
 * The value of a virtual setting.
 */
function settings_virtual_get(string $name): string
{
	if ($name === 'document_storage') {
		// G2.6: internal storage is box6 'on'; own FTP is box6 empty with a server; otherwise none.
		$r = db_fetch_array(db_select("select box1, box2, box6 from grupper where art = 'bilag' order by id limit 1", __FILE__ . " linje " . __LINE__));
		if (!$r) {
			return '';
		}
		if ($r['box6'] === 'on') {
			return 'internFTP';
		}
		return (trim((string) $r['box1']) !== '' && trim((string) $r['box2']) !== '') ? 'externFTP' : '';
	}
	return '';
}

/**
 * Store a virtual setting. Same writes as the old Bilagshåndtering page (systemdata/diverse.php sektion=bilag);
 * its update of regnskab.bilag in the master database is left out, nothing reads that column.
 */
function settings_virtual_set(string $name, string $raw): void
{
	if ($name === 'document_storage') {
		if (!db_fetch_array(db_select("select id from grupper where art = 'bilag'", __FILE__ . " linje " . __LINE__))) {
			db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5, box6, box7) values ('Bilag og dokumenter', '1', 'bilag', '', '', '', '', '', '', '')", __FILE__ . " linje " . __LINE__);
		}
		if ($raw === 'internFTP') {
			db_modify("update grupper set box6 = 'on' where art = 'bilag'", __FILE__ . " linje " . __LINE__);
		} elseif ($raw === 'externFTP') {
			db_modify("update grupper set box6 = '' where art = 'bilag'", __FILE__ . " linje " . __LINE__);
		} else {
			// None: the old page cleared the server details too.
			db_modify("update grupper set box1 = '', box2 = '', box3 = '', box6 = '' where art = 'bilag'", __FILE__ . " linje " . __LINE__);
		}
	}
}
