<?php

require_once 'msac.php';

$msac = new MSAC();

$date = new \DateTime('2017-01-30');

$msac->fetchAvailability(MSAC::BADMINTON, $date);

$msac->printAvailability();
