<?php
/**
 **********
 * WARNING
 **********
 * This is a SAMPLE script and should NOT be used without caution. Please pay
 * attention to the README.md instructions regarding security.
 *
 **************
 * Explanation
 **************
 * This script uses the PHP GinkClient to generate a token, then uses that token
 * in the automatic login page of the Infleet Portal v1.3 in combination with
 * that page's redirection feature to open the Live Tracking immediately using
 * the saved username & password in the gink.ini file
 */

$fnIni = './iframe-embed.ini';
if ( is_readable($fnIni) && ($ini = @parse_ini_file($fnIni)) ) {
    if ( empty($ini['username']) || empty($ini['password']) ) {
        header("Content-type: text/plain", true, 500);
        die("INI file missing 'username' and 'password' combination");
    }
}

require_once "lib/GinkClient.php";
$client = new GinkClient();

// get the Gateway Object using the token method, ensure we have token
$goGateway = $client->token($ini['username'], $ini['password']);
if ( ! empty($goGateway->_error) ) {
    header("Content-type: text/plain", true, 500);
    die($goGateway->_error . "\n" . $goGateway->body);
}
else if ( empty($goGateway->token) ) {
    header("Content-type: text/plain", true, 500);
    die("No token retrieved on Gateway?!?!");
}

// Use token in auto-login + redirection
$nextPage = empty($ini['redirect']) ? "live.html" : $ini['redirect'];
$url = sprintf(
    "https://infleet.bornemann.net/v1.3/login.html?token=%s&redirect=%s"
    ,$goGateway->token
    ,$nextPage
    );
header("Location: {$url}", true, 307);
