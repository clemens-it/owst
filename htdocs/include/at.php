<?php

function programAt($cmd)
{
    // Programs at and checks whether it was successful
    /*
    //Typical output from at:
    warning: commands will be executed using /bin/sh
    job 11 at Thu Dec 20 00:10:00 2012
    */
    $output = array(); //contains array with all output
    unset($retval); //return value

    if (empty($cmd)) {
        logEvent("programAt: command is empty", LLERROR);
        return false;
    }
    //at writes to stderr, so redirect to stdout
    $cmd .= ' 2>&1';

    //lloutput ist last line of output
    $lloutput = exec($cmd, $output, $retval);
    if ($retval == 0) {
        logEvent("Execution of $cmd queued with at. At output: ".$lloutput, LLINFO_ACTION);
    } else {
        //remove warning from output array if present
        if (isset($output[0]) && $output[0] == 'warning: commands will be executed using /bin/sh') {
            unset($output[0]);
        }
        logEvent("Queing with at failed. Return value: $retval. Output: ".implode("--", $output), LLERROR);
    }
    return ($retval == 0);
} //function program_at
