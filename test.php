<?php

error_reporting(E_ALL);

require_once 'msac.php';

$msac = new MSAC(MSAC::BADMINTON);

$date = new \DateTime($argv[1]);
$time     = $argv[2];
$duration = intval($argv[3]);
$count    = intval($argv[4]) ? intval($argv[4]) : 1;


try {
    $msac->fetchAvailability($date);
    $msac->printAvailability();
    $courts = $msac->findCourts($time, $duration, $count);
    echo 'Booking courts '.implode(' ', $courts).PHP_EOL;
} catch (\RuntimeException $e) {
    echo $e->getMessage().PHP_EOL;
    exit;
}

echo 'Success!'.PHP_EOL;
