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

$liveUrl = $goGateway->live_url;

// do an endless loop
do {
    $goLive = $client->get($liveUrl);
    if ( ! empty($goLive->_error) ) {
        var_dump($goLive);
        err_quit("Error: could not get Live Data\n", 3);
    }

    if ( count($goLive->updates) ) {
        printf("[%s] UPDATES:\n", date("Y-m-d H:i:s"));
        // read out the available trackers
        $format = "   %-15s %-25s %-21s %-21s\n";
        printf($format, "IMEI", "Name", "Last Heard", "Last Position");
        foreach($goLive->updates as $goUpdate) {
            printf(
                $format,
                $goUpdate->imei,
                $goUpdate->name,
                $goUpdate->comm_ts ? date('Y-m-d H:i:s', $goUpdate->comm_ts) : "-",
                $goUpdate->pos_ts ? date('Y-m-d H:i:s', $goUpdate->pos_ts) : "-"
                );
        }
    }
    else {
        printf("[%s] No updates\n", date("Y-m-d H:i:s"));
    }

    printf("   Next URL: %s\n", $liveUrl = $goLive->live_url);
    printf("   Calling again in %d seconds...\n", $refresh = $goLive->refresh);
    sleep($refresh);

} while(true);
