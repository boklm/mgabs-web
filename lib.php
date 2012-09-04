<?php
/**
 * Mageia build-system quick status report script.
 *
 * @copyright Copyright (C) 2011 Mageia.Org
 *
 * @author Olivier Blin
 * @author Pascal Terjan
 * @author Romain d'Alverny
 * @author Michael Scherer
 *
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU GPL v2
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License aspublished by the
 * Free Software Foundation; either version 2 of the License, or (at your
 * option) any later version.
 *
*/

/**
 * Return a human-readable label for this package build status.
 *
 * @param array $pkg package information
 *
 * @return string
*/
function pkg_gettype($pkg)
{
    $labels = array(
        'rejected' => 'rejected',
        'upload'   => 'uploaded',
        'failure'  => 'failure',
        'done'     => 'partial',
        'build'    => 'building',
        'todo'     => 'todo'
    );

    foreach ($labels as $k => $v) {
        if (array_key_exists($k, $pkg['status'])) {
            return $v;
        }
    }

    return 'unknown';
}

/**
 * @param integer $num
 *
 * @return string
*/
function plural($num)
{
    if ($num > 1)
        return "s";
}

/**
 * Return timestamp from package key
 *
 * @param string $key package submission key
 *
 * @return integer
*/

function key2timestamp($key) {
    global $tz;

    $date = DateTime::createFromFormat("YmdHis", $key+0, $tz);
    if ($date <= 0)
        return null;

    return $date->getTimestamp();
}

/**
 * Return human-readable time difference
 *
 * @param integer $start timestamp
 * @param integer $end timestamp, defaults to now
 *
 * @return string
*/
function timediff($start, $end)
{
    if (is_null($end)) {
        $end = time();
    }
    $diff = $end - $start;
    if ($diff < 60) {
        return $diff . " second" . plural($diff);
    }
    $diff = round($diff/60);
    if ($diff < 60) {
        return $diff . " minute" . plural($diff);
    }
    $diff = round($diff/60);
    if ($diff < 24) {
        return $diff . " hour" . plural($diff);
    }
    $diff = round($diff/24);

    return $diff . " day" . plural($diff);
}


/**
 * Compare two duration strings
 *
 * @param string $a "1 hour" or "23 mins"
 * @param string $b
 *
 * @return integer
*/
function timesort($a, $b)
{
    $a = explode(' ', trim($a));
    $b = explode(' ', trim($b));

    if ($a[1] == 'hour' || $a[1] == 'hours') {
        $a[0] *= 3600;
    }

    if ($b[1] == 'hour' || $a[1] == 'hours') {
        $b[0] *= 3600;
    }

    if ($a[0] > $b[0]) {
        return 1;
    } elseif ($a[0] < $b[0]) {
        return -1;
    } else {
        return 0;
    }
}
