<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/diverseIncludes/save_flatpay_id.php ---------- lap 3.9.9----2023.03.15-------
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
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
$permission_key = 'system.indstillinger';
include ("../../includes/online.php");
include ("../../includes/std_func.php");

// 20260928 Sawaneh Security 4.0 (A10): the Flatpay login is exchanged for the GUID here on the server,
//                  so username/password never leave the browser for a third-party host and are never logged.
if (function_exists('require_permission')) {
	require_permission('settings.integrations.keys', 'write');
}
$post = json_decode(file_get_contents('php://input'));
$username = isset($post->username) ? (string) $post->username : '';
$password = isset($post->password) ? (string) $post->password : '';
if ($username === '' || $password === '') {
	http_response_code(400);
	print "Missing login";
	exit;
}
$ch = curl_init('https://socket.flatpay.dk/socket/guid');
curl_setopt_array($ch, array(
	CURLOPT_POST           => true,
	CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
	CURLOPT_POSTFIELDS     => json_encode(array('username' => $username, 'password' => $password)),
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT        => 15,
));
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$id = trim((string) $body);
if ($status !== 200 || $id === '' || !preg_match('/^[A-Za-z0-9-]{8,64}$/', $id)) {
	http_response_code(401);
	print "Login failed";
	exit;
}
$id = db_escape_string($id);
$qtxt = "SELECT var_value FROM settings WHERE var_name='flatpay_auth'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

# If the row alreayd exsists
if ($r) {
  $qtxt = "UPDATE settings SET var_value='$id' WHERE var_name='flatpay_auth'";
  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
# If the row needs to be created in the database
} else {
  $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('flatpay_auth', 'globals', '$id', 'The flatpay auth GUID')";
  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

print "Success";
?>

