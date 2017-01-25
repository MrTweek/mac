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

    public function fetchAvailability($type, $date) {
        $this->date = $date;
        $url = sprintf($this->url, $type, $date->format('Y-m-d'));
        $file = file($url);

        $court    = 0;
        $i        = 0;
        $bookings = [];
        $events   = false;

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
                        if ($time > $entry['start'] and $time < $entry['end']) {
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
}
