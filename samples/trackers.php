<?php
/**
 * this script demonstrates how to use the GinkClient library to list
 * information about the available GPS trackers.
 */

require_once 'lib/err_quit.php';// for fatal errors
require_once 'lib/GinkClient.php';// create a gink client
$client = new GinkClient();

// check basic number of args
if ( count($argv) < 3 ) {
    $msg = "Lists trackers for user in gink-ws\n";
    $msg .= "USAGE: php {$_SERVER['PHP_SELF']} user pass\n";
    err_quit($msg, 1);
}

// read arguments
list($junk, $user, $pass) = $argv;

// get the gateway (it will have the registration_url)
$goGateway = $client->token($user, $pass);
if ( ! empty($goGateway->_error) ) {
    var_dump($goGateway);
    err_quit("Error: could not get gateway\n", 2);
}

$goTrackers = $client->get($goGateway->trackers_url);
if ( ! empty($goTrackers->_error) ) {
    var_dump($goTrackers);
    err_quit("Error: could not get trackers\n", 3);
}

// read out the available trackers
$format = "%-15s %-20s %-12s %-12s %-6s %-20s\n";
printf($format, "IMEI", "Name", "Start", "Expires", "Keep", "Model");
foreach($goTrackers->trackers as $goTrack) {
    printf(
        $format,
        $goTrack->imei,
        $goTrack->name,
        $goTrack->start_ts ? date('Y-m-d', $goTrack->start_ts) : "-",
        $goTrack->end_ts ? date('Y-m-d', $goTrack->end_ts) : "-",
        $goTrack->data_retention,
        $goTrack->model
        );
}
