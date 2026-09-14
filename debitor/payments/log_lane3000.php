<?php
// Client-side log sink for debitor/payments/lane3000.php. Appends to temp/<db>/lane3000.log
// for the logged-in session's own account only - the account comes from the session, never
// from the request body.
// 20260914 Sawaneh JOB-131: Require a logged-in session and take the log directory from it
//                     instead of the client-supplied "db"; sanitise level/message/ordre_id.

@session_start();
$s_id = session_id();

$bg = "nix";
$header = 'nix';
$modulnr = 0;
$title = 'lane3000';

header('Content-Type: application/json');

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");

if (!$db) {
	http_response_code(403);
	echo json_encode(['error' => 'Not logged in']);
	exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
	http_response_code(400);
	echo json_encode(['error' => 'Invalid input']);
	exit;
}

$logDir = '../../temp/' . $db;
// temp/$db is cleared daily, so recreate it if it is missing before writing
if (!is_dir($logDir)) mkdir($logDir, 0777, true);

$level = preg_replace('/[^A-Z]/', '', strtoupper((string) ifset($input, 'level', 'INFO')));
if (!$level) $level = 'INFO';
$message  = str_replace(array("\r", "\n"), ' ', (string) ifset($input, 'message', 'No message'));
$ordre_id = (int) ifset($input, 'ordre_id', 0);

$logEntry = "[" . date('Y-m-d H:i:s') . "] [CLIENT-$level] [Order: $ordre_id] $message" . PHP_EOL;
file_put_contents("$logDir/lane3000.log", $logEntry, FILE_APPEND | LOCK_EX);

echo json_encode(['status' => 'logged']);
