<?php

/**
 * for CLI only.  exits with a message and given error code.  does some
 * colorization based on english keywords just for fun, too.
 */
function err_quit($msg, $code) {
    // split the lines, color lines based on word usage
    $lines = preg_split('/(\\r\\n|\\r|\\n)+/', $msg);
    foreach($lines as $line ) {
        // look for USAGE message
        if ( preg_match('/error/i', $line) ) {
            fwrite(STDERR, "\033[1;31m" . $line . "\n");//red
        }
        else if ( preg_match('/^php\\s|usage/i', $line) ) {
            fwrite(STDERR, "\033[1;37m" . $line . "\n");//white
        }
        else if ( preg_match('/^note/i', $line)) {
            fwrite(STDERR, "\033[36m" . $line . "\n");//blue
        }
        else {
            fwrite(STDERR, "\033[0m" . $line . "\n");//default
        }
    }
    // reset
    fwrite(STDERR, "\033[0m");
    exit($code);
}
