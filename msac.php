<?php

class MSAC {

    const TABLE_TENNIS = 1082;
    const BADMINTON    = 1087;

    private $type;
    private $date;
    private $url = 'https://secure.activecarrot.com/public/facility/iframe/33/%s/%s';

    public function __construct($type) {
        $this->type = $type;
    }

    private $availability;

    public function fetchAvailability($date) {
        $this->date = $date;
        $url = sprintf($this->url, $this->type, $date->format('Y-m-d'));
        $file = file($url);

        $court    = 0;
        $i        = 0;
        $bookings = [];
        $events   = false;

        /* Parse Javascript */
        foreach ($file as $line) {
            if (preg_match('!, events: \[!', $line)) {
                $events = true;
                $court++;
                $i = 0;
                continue;
            }
            if (preg_match('!}\);!', $line)) {
                $events = false;
            }

            if (!$events) {
                continue;
            }

            $line = trim($line);

            if (preg_match('!\{$!', $line)) {
                $i++;
                continue;
            }

            if (preg_match('!^[^a-z]*[a-zA-Z]+:!', $line)) {
                $key   = trim(preg_replace('!^[^a-z]*([a-zA-Z]+): .*$!', '$1', $line));
                $value = trim(preg_replace('!^[^a-z]*[a-zA-Z]+: (.*),$!', '$1', $line));
                switch ($key) {
                    case 'title':
                        $value = preg_replace('!\'(.*)\'.*$!', '\1', $value);
                        break;
                    case 'allDay':
                        $value = $value == 'true';
                        break;
                    case 'start':
                    case 'end':
                        $plainDateFields = preg_replace('!^.*new Date\((.*)\).*$!', '\1', $line);
                        list($year, $month, $day, $hour, $min) = explode(', ', $plainDateFields);
                        $value = new \DateTime($date->format('Y-m-d'));
                        $value->setTime($hour, $min);
                        break;
                }
                $bookings[$court][$i][$key] = $value;
            }

        }

        $this->availability = $bookings;
    }

    public function printAvailability() {
        if (empty($this->availability)) {
            throw new \RuntimeException('No availability found. Run fetchAvailability() first');
        }

        echo '           ';
        for ($h = 6; $h <= 23; $h++) {
            printf('%02d   ', $h);
        }
        echo PHP_EOL;

        $time = clone $this->date;
        foreach ($this->availability as $court => $entries) {
            echo sprintf('[Court %02d] ', $court);
            for ($h = 6; $h <= 23; $h++) {
                for ($m = 0; $m < 60; $m += 15) {
                    $time->setTime($h, $m);
                    $available = true;
                    foreach ($entries as $entry) {
                        if ($time >= $entry['start'] and $time < $entry['end']) {
                            $available = false;
                            break;
                        }
                    }
                    echo $available ? '.' : 'X';
                }
                echo ' ';
            }
            echo PHP_EOL;
        }
    }

    public function findCourts($time, $duration, $count = 1) {
        if (empty($this->availability)) {
            throw new \RuntimeException('No availability found. Run fetchAvailability() first');
        }

        if (!preg_match('!^[0-9]{1,2}:[0-9]{2}$!', $time)) {
            throw new \RuntimeException('Time format must me HH:mm');
        }

        list($hour, $min) = explode(':', $time);
        $min = intval($min);

        if ($hour < 6 or $hour > 23) {
            throw new \RuntimeException('Can only book between 6:00 and 23:00');
        }

        if (!in_array($min, [0, 15, 30, 45])) {
            throw new \RuntimeException('A booking can only start every quarter of an hour.');
        }

        if (!$duration) {
            throw new \RuntimeException('Booking duration has to be greater than 0.');
        }

        if ($duration % 15) {
            throw new \RuntimeException('Booking duration has to be in 15 minute intervals.');
        }

        $startDate = clone $this->date;
        $startDate->setTime($hour, $min);
        $endDate = clone $startDate;
        $endDate->modify(sprintf("+%d minute", $duration));

        $availableCourts = [];
        /* Find all available courts during requested time period */
        foreach ($this->availability as $court => $entries) {
            $free = true;
            foreach ($entries as $entry) {
                if ($startDate < $entry['end'] and $endDate > $entry['start']) {
                    $free = false;
                }
            }
            if ($free) {
                $availableCourts[] = $court;
            }
        }

        if (count($availableCourts) < $count) {
            throw new \RuntimeException(sprintf('Only %d courts available', count($availableCourts)));
        }

//        echo count($availableCourts).' courts available: '.implode(' ', $availableCourts).PHP_EOL;

        /* Find horizontally adjacent courts */
        $courtsToBook = [];
        if ($count < 4) {
            foreach ($availableCourts as $court) {
                $free = true;
                $row = intval(($court - 1)/3);
                for ($i = 1; $i < $count; $i++) {
                    if (!in_array($court + $i, $availableCourts)) {
                        $free = false;
                        break;
                    }
                    /* Make sure courts are in the same row */
                    if ($row != intval(($court + $i - 1) / 3)) {
                        $free = false;
                        break;
                    }
                }
                if ($free) {
                    for ($i = 0; $i <= $count-1; $i++) {
                        $courtsToBook[] = $court+$i;
                    }
                    break;
                }
            }

        }

        /* Find vertically adjacent courts */
        if (empty($courtsToBook) and $count < 3) {
            foreach ($availableCourts as $court) {
                if (in_array($court + 3, $availableCourts)) {
                    $courtsToBook[] = $court;
                    $courtsToBook[] = $court + 3;
                    break;
                }
            }
        }

        /* No adjacent courts found, booking whatever is there */
        if (empty($courtsToBook)) {
            while (count($courtsToBook) < $count) {
                $courtsToBook[] = array_pop($availableCourts);
            }
        }

        if (count($courtsToBook) < $count) {
            throw new \RuntimeException('Not enough courts available');
        }

        return $courtsToBook;
    }

}
