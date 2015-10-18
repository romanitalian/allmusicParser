<?php
/**
 * User: romanitalian
 * Date: 28.08.2015
 * Time: 9:28
 */

# Create our client object.
$gmclient = new GearmanClient();

# Add default server (localhost).
$gmclient->addServer();

echo "Sending job\n";

# Send reverse job
do {
    $result = $gmclient->do("reverse", "Hello!");

    # Check for various return packets and errors.
    switch($gmclient->returnCode()) {
        case GEARMAN_WORK_DATA:
            echo "Data: $result\n";
            break;
        case GEARMAN_WORK_STATUS:
            list($numerator, $denominator) = $gmclient->doStatus();
            echo "Status: $numerator/$denominator complete\n";
            break;
        case GEARMAN_WORK_FAIL:
            echo "Failed\n";
            exit;
        case GEARMAN_SUCCESS:
            echo "Success: $result\n";
            break;
        default:
            echo "RET: ".$gmclient->returnCode()."\n";
            exit;
    }
} while($gmclient->returnCode() != GEARMAN_SUCCESS);
