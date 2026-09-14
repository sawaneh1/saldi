<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.0 --- 2024.02.27 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php
// 20260914 Sawaneh JOB-131: Per-register "terminal prints card receipt" choice - sends Nets
//                     printerWidth 0 for that register's terminal after login and skips Saldi's
//                     receipt-printer copy; login status check restored; credentials and bearer
//                     token no longer logged; request values cast/allow-listed.

@session_start();
$s_id = session_id();

#print '<head>';
#print '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap" rel="stylesheet">';
#print '</head>';

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$css = "../../css/flatpay.css";

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

// Add logging function
function writeLog($message, $level = 'INFO') {
    global $db;
    $logFile = '../../temp/'.$db.'/lane3000_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

$raw_amount    = (float) usdecimal(ifset($_GET, 'amount', 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id      = (int) ifset($_GET, 'id', 0);
$indbetaling   = (int) ifset($_GET, 'indbetaling', 0);
$return_url    = ifset($_GET, 'return_url', 'pos_ordre.php');
if (!in_array($return_url, array('pos_ordre.php', 'ordre.php'), true)) {
	$return_url = 'pos_ordre.php';
}
$kasse = (int) ifset($_GET, 'kasse', ifset($_COOKIE, 'saldi_pos', 0));

// Build the return URL base with all parameters except cardscheme (which is added in JS)
$return_url_base = '../' . $return_url;
$return_url_params = '?id=' . urlencode($ordre_id) . '&godkendt=OK&indbetaling=' . urlencode($indbetaling) . '&amount=' . urlencode($raw_amount) . '&modtaget=' . urlencode($raw_amount);

// Log initialization
writeLog("Lane3000 payment started - Amount: $raw_amount, Order ID: $ordre_id, Kasse: $kasse, Session: $s_id");

print "<div id='container'>";
print "<span>Lane3000 terminal startet, afventer kort.</span>";
print "<h3>$pretty_amount kr.</h3>";
print "<div id='status' style='background-color: #fbbc04' >Afventer kort...</div>";
print "<div id='notice' style='color: #b00; display: none'></div>";
print "<span>Terminalen timer ud om </span><span id='timestatus'>120</span><span> sekunder</span><br>";
print "<button id='continue' class='btn' onClick='failed();' disabled style='display: block'>Tilbage</button>";
print "<button id='continue-success' class='btn' onClick='successed();'>Tilbage</button>";
print "</div>";
print "<div id='bg'></div>";

$type = ($raw_amount < 0) ? "returnOfGoods" : "purchase";
$amount = abs($raw_amount) * 100;

writeLog("Transaction type: $type, Amount in cents: $amount");

# Get settings
$q=db_select("select var_value from settings where var_name = 'flatpay_auth'",__FILE__ . " linje " . __LINE__);
$guid = db_fetch_array($q)[0];

# Get terminal id
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$terminal_id = trim(ifset(explode(chr(9), (string) ifset(db_fetch_array($q), 0, '')), $kasse - 1, ''));
if($terminal_id == "test"){
    include "lane3000-sim.php";
    exit;
}
writeLog("Terminal ID retrieved: $terminal_id");

// Fetch printserver
$r = db_fetch_array(db_select("select box3 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
$x = $kasse - 1;
$tmp = explode(chr(9), $r['box3']);
$printserver = trim($tmp[$x]);
if (!$printserver) $printserver = 'localhost';
elseif ($printserver == 'box' || $printserver == 'saldibox') {
	$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
	if ($fp = fopen($filnavn, 'r')) {
		$printserver = trim(fgets($fp));
		fclose($fp);
	}
}

# Print setup
$printfile = 'https://'.$_SERVER['SERVER_NAME'];
$printfile.= str_replace('debitor/payments/lane3000.php',"temp/$db/receipt_$kasse.txt",$_SERVER['PHP_SELF']);

writeLog("Print file URL: $printfile");

# Per-register choice (Systemdata -> POS): the terminal prints the card receipt itself, or Saldi's receipt printer does.
# Nets cloud terminals keep this on Nets' side, so lane3000 sends printerWidth 0 for this terminal after each login.
$terminalPrinter = (get_settings_value("terminal_printer", "POS", "off", null, $kasse) == 'on');
writeLog("Terminal printer setting for kasse $kasse: " . ($terminalPrinter ? 'on - terminal prints, Saldi receipt print skipped' : 'off - Saldi receipt printer'));
?>

<script>

var counting = false;
const terminalId = <?php print json_encode($terminal_id); ?>;
const terminalPrinter = <?php print $terminalPrinter ? 'true' : 'false'; ?>;

// Add client-side logging function
function logToServer(message, level = 'INFO') {
    fetch('log_lane3000.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            level: level,
            timestamp: new Date().toISOString(),
            ordre_id: '<?php print $ordre_id; ?>',
            db: '<?php print $db; ?>'
        })
    }).catch(err => console.error('Logging failed:', err));
}

function countdown(i) {
    document.getElementById("timestatus").innerText = i;
    if (i != 0 && counting) {
        setTimeout(() => {
            countdown(i-1);
        }, 1000);
    } else if (i === 0) {
        logToServer('Countdown reached zero - timeout', 'WARNING');
    }
}

const failed = (event) => {
    logToServer('User clicked failed/back button', 'INFO');
    window.location.replace('../<?php print $return_url; ?>?id=<?php print urlencode($ordre_id); ?>&godkendt=afvist')
}

// Variable used to check weather or not to leave the page
var finished = false;

// FAIL CONDITION
function fail(err) {
    logToServer(`Payment failed: ${err}`, 'ERROR');
    var elm = document.getElementById('status');
    elm.style.backgroundColor = '#ea3a3a';
    elm.innerText = `Fejl: ${err}`;
    document.getElementById('bg').style.backgroundColor = '#fb9389';
    document.getElementById('continue').style.display = 'block';
    document.getElementById('continue').disabled = false;
}

// Non-blocking warning shown under the status while the payment carries on
function notice(txt) {
    var elm = document.getElementById('notice');
    elm.innerText = txt;
    elm.style.display = 'block';
}

function leave(cardScheme) {
    if (!finished) {
        setTimeout(function() { leave(cardScheme); }, 2500);
    } else {
        logToServer(`Payment successful, redirecting with card scheme: ${cardScheme}`, 'INFO');
        window.location.replace('<?php print $return_url_base . $return_url_params; ?>&cardscheme=' + encodeURIComponent(cardScheme));
    }
}

// GET API KEY
async function get_api_key(baseurl) {
    const initialLogPromise = logToServer('Starting API key request', 'INFO');
    document.getElementById('status').innerText = "Authorizer...";
    const data = {
        "username": <?php print json_encode(get_settings_value("username", "move3500", "", null, $kasse)); ?>,
        "password": <?php print json_encode(get_settings_value("password", "move3500", "", null, $kasse)); ?>
    };
    if (!data.username || !data.password) {
        await Promise.allSettled([
            initialLogPromise,
            logToServer('Move3500 login is not configured for this register (settings var_grp move3500, pos_id)', 'ERROR'),
            Promise.resolve(fail(<?php print json_encode(findtekst('5153|Terminal-login er ikke sat op for denne kasse', $sprog_id)); ?>))
        ]);
        return null;
    }

    try {
        const fetchPromise = fetch(
            `${baseurl}login`,
            {
                method: 'post',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            }
        );
        // Wait for both initial logging and fetch request
        const [logResult, fetchResult] = await Promise.allSettled([initialLogPromise, fetchPromise]);
        // Check if initial logging failed
        if (logResult.status === 'rejected') {
            console.error('Initial logging failed:', logResult.reason);
        }

        // Check if fetch failed
        if (fetchResult.status === 'rejected') {
            const errorMsg = `Network error: ${fetchResult.reason.message}`;
            await Promise.allSettled([
                logToServer(`API key request exception: ${fetchResult.reason.message}`, 'ERROR'),
                Promise.resolve(fail(errorMsg))
            ]);
            return null;
        }

        const res = fetchResult.value;
        const jsondata = await res.json().catch(() => ({}));

        // Log status only - the body carries the bearer token
        const responseLogPromise = logToServer(`API key request response - Status: ${res.status}, token received: ${jsondata.token ? 'yes' : 'no'}`, 'INFO');

        if (res.status != 200 || !jsondata.token) {
            const errorMsg = jsondata.error || `Login failed (HTTP ${res.status})`;
            await Promise.allSettled([
                responseLogPromise,
                logToServer(`API key request failed - Status: ${res.status}, Error: ${errorMsg}`, 'ERROR'),
                Promise.resolve(fail(errorMsg))
            ]);
            return null;
        }

        if (terminalPrinter) {
            await set_terminal_printer(baseurl, jsondata.token);
        }

        // Wait for both success logging and response logging to complete
        await Promise.allSettled([
            logToServer('API key retrieved successfully', 'INFO'),
            responseLogPromise
        ]);

        return jsondata.token;

    } catch (error) {
        // Handle any unexpected errors
        const errorMsg = `Network error: ${error.message}`;
        await Promise.allSettled([
            logToServer(`API key request exception: ${error.message}`, 'ERROR'),
            Promise.resolve(fail(errorMsg))
        ]);
        return null;
    }
}

// TELL NETS THAT THIS TERMINAL PRINTS ITS OWN RECEIPT
// Cloud terminals hold the printer choice on Nets' side; printerWidth 0 = "use the terminal's printer".
// Only sent for registers with the terminal-printer setting on. A failure is logged and shown but
// does not stop the payment.
async function set_terminal_printer(baseurl, apikey) {
    const url = `${baseurl}terminal/${terminalId}/settings`;
    logToServer(`Terminal settings request - URL: ${url}, Data: {"printerWidth":0}`, 'DEBUG');
    try {
        const putRes = await fetch(
            url,
            {
                method: 'put',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `bearer ${apikey}`
                },
                body: JSON.stringify({
                    "printerWidth": 0
                }),
            }
        );
        const putBody = (await putRes.text().catch(() => '')).substring(0, 500);
        if (putRes.ok) {
            await logToServer(`Terminal settings updated (printerWidth 0) - Status: ${putRes.status}`, 'INFO');
        } else {
            await logToServer(`Terminal settings update failed - Status: ${putRes.status}, Response: ${putBody}`, 'ERROR');
            notice(<?php print json_encode(findtekst('5154|Terminalens printerindstilling kunne ikke opdateres hos Nets', $sprog_id)); ?> + ` (HTTP ${putRes.status})`);
        }
    } catch (putError) {
        await logToServer(`Terminal settings update exception: ${putError.message}`, 'ERROR');
        notice(<?php print json_encode(findtekst('5154|Terminalens printerindstilling kunne ikke opdateres hos Nets', $sprog_id)); ?> + ` (${putError.message})`);
    }
}

async function print_str(baseurl, apikey, data) {
    const initialLogPromise = logToServer('Starting receipt printing', 'INFO');
    document.getElementById('status').innerText = "Printer...";
    
    try {
        const saveReceiptPromise = fetch(
            'save_receipt.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: data, 
                    id: '<?php print $ordre_id; ?>',
                    type: 'move3500',
                    kasse: '<?php print $kasse; ?>',
                    terminal_id: '<?php print $terminal_id; ?>'
                })
            }
        );
        
        // Wait for both initial logging and save receipt request
        const [logResult, saveResult] = await Promise.allSettled([initialLogPromise, saveReceiptPromise]);
        
        // Check if initial logging failed
        if (logResult.status === 'rejected') {
            console.error('Initial print logging failed:', logResult.reason);
        }
        
        // Check if save receipt failed
        if (saveResult.status === 'rejected') {
            await Promise.allSettled([
                logToServer(`Receipt saving failed: ${saveResult.reason.message}`, 'ERROR'),
                Promise.resolve() // Continue anyway
            ]);
        } else {
            // Wait for success logging to complete
            await Promise.allSettled([
                logToServer('Receipt saved successfully', 'INFO')
            ]);
        }

        if (terminalPrinter) {
            // The terminal has printed the receipt itself (printerWidth 0) - no copy on Saldi's receipt printer
            await logToServer('Receipt printed by the terminal - Saldi print skipped', 'INFO');
        } else {
            window.open("http://<?php echo $printserver ?>/saldiprint.php?bruger_id=99&bonantal=1&printfil=<?php print $printfile; ?>&skuffe=0&gem=1','','width=200,height=100");
            await logToServer('Print command sent', 'INFO');
        }

        finished = true;
        
    } catch (error) {
        // Handle any unexpected errors
        await Promise.allSettled([
            logToServer(`Receipt printing failed: ${error.message}`, 'ERROR')
        ]);
        // Continue anyway, don't fail the whole transaction
        finished = true;
    }
}

// START PAYMENT ON TERMINAL
async function start_payment(baseurl, apikey, amount) {
    logToServer(`Starting payment on terminal - Amount: ${amount}`, 'INFO');
    const data = {
        "transactionType": "<?php print $type; ?>",
        "amount": <?php print $amount; ?>
    }
    
    // Log the exact request details for debugging
    logToServer(`Transaction request - URL: ${baseurl}terminal/${terminalId}/transaction, Data: ${JSON.stringify(data)}`, 'DEBUG');
    console.log('Transaction request data:', data);
    
    try {
        var res = await fetch(
            `${baseurl}terminal/${terminalId}/transaction`,
            {
                method: 'post',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `bearer ${apikey}`
                },
                body: JSON.stringify(data),
            }
        );

        counting = false;
        var jsondata = await res.json();
     
        logToServer(`Payment response - Status: ${res.status}, Data: ${JSON.stringify(jsondata)}`, 'INFO');
        
        if (res.status != 201) {
            logToServer(`Payment failed - Status: ${res.status}, Error: ${jsondata.failure?.error || 'Unknown error'}`, 'ERROR');
            fail(jsondata.failure?.error || 'Payment failed');
        } else {
            const cardScheme = jsondata.result[0].cardType;
            logToServer(`Payment successful - Card type: ${cardScheme}`, 'INFO');
            
            jsondata.result[0].customerReceipt.replace("\r", "");
            var lines = jsondata.result[0].customerReceipt.split("\r\n");
            lines = lines.join("\n");

            await print_str(baseurl, apikey, lines);
            leave(cardScheme);
        }
    } catch (error) {
        logToServer(`Payment request exception: ${error.message}`, 'ERROR');
        fail(`Network error: ${error.message}`);
    }
}

async function start() {
    logToServer('Payment process started', 'INFO');
    // https://connectcloud-test.aws.nets.eu/v1/
    const baseurl = "https://connectcloud.aws.nets.eu/v1/";
    var elm = document.getElementById('status');

    const apikey = await get_api_key(baseurl);
    if (!apikey || elm.innerText.includes("Fejl:")) {
        logToServer('Failed to get API key, stopping process', 'ERROR');
        return;
    }
    
    counting = true;
    countdown(121);
    document.getElementById('status').innerText = "Afventer kort...";
    await start_payment(baseurl, apikey, <?php print $amount; ?>);
    if (elm.innerText.includes("Fejl:")) {
        logToServer('Payment process completed with error', 'ERROR');
        return;
    }
    
    logToServer('Payment process completed successfully', 'INFO');
}

logToServer('Page loaded, starting payment process', 'INFO');
start();

</script>
