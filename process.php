<?php
    require 'filesystem.php';

    function getMostRecent() {
        header('Content-Type: application/json');

        $details = main();
        $arr = array('status' => $details['status'],
                     'url' => $details['file_display'], 
                     'time_elapsed' => $details['time_elapsed']);
        echo json_encode($arr);
    }

    if (isset($_GET['do']) && $_GET['do'] === "getMostRecent") {
        getMostRecent();
    }
?>    