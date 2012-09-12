<?php
/**
 * Mageia build-system quick status report script.
 * List log files related to $_GET['k'] build path.
 *
 * @copyright Copyright (C) 2012 Mageia.Org
 *
 * @author Romain d'Alverny
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU GPL v2
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License aspublished by the
 * Free Software Foundation; either version 2 of the License, or (at your
 * option) any later version.
*/

$key = isset($_GET['k']) ? trim(strip_tags(html_entity_decode($_GET['k']))) : null;

if (is_null($key)) {
    die('.');
}

require 'conf.php';

// FIXME this expects to remove /uploads from $key. Could be different in the future.
$key  = substr($key, 8);
$path = realpath($upload_dir . $key);
$job  = explode('/', $key);
$job  = end($job);

if (false !== strpos($key, 'rejected')) {
    $valid = file_exists($path);
    $type  = 'rejected';
    $job   = str_replace('.youri', '', $job);
    $path  = str_replace('.youri', '', $path);
} else {
    $valid = is_dir($path);
    $type  = 'regular';
}

if (!$valid) {
    header('Status: 404 Not Found');
    header('HTTP/1.0 404 Not Found');
    die('Sorry, not found');
}

$list = array();

if ($type == 'rejected') {

    $list = glob($path . '.*');

} else {

    $list = glob_recursive_tree($path . '/*');

    $others = array(
        '.youri',
        '_i586.done',
        '_x86_64.done'
    );

    foreach ($others as $suffix) {
        $f = $path . $suffix;
        if (file_exists($f)) {
            $list[] = $f;
        }
    }
}

echo sprintf('<h4>%s</h4>', $job),
    print_list($list);

// lib code below.

/**
 * Format size in human-readable format.
 *
 * @param integer $a_bytes size in bytes
 *
 * @return string
 *
 * @author    yatsynych
 * @link      http://www.php.net/manual/fr/function.filesize.php#106935
*/
function _format_bytes($a_bytes)
{
    if ($a_bytes < 1024) {
        return $a_bytes .' B';
    } elseif ($a_bytes < 1048576) {
        return round($a_bytes / 1024, 2) .' KiB';
    } elseif ($a_bytes < 1073741824) {
        return round($a_bytes / 1048576, 2) . ' MiB';
    } elseif ($a_bytes < 1099511627776) {
        return round($a_bytes / 1073741824, 2) . ' GiB';
    } elseif ($a_bytes < 1125899906842624) {
        return round($a_bytes / 1099511627776, 2) .' TiB';
    } elseif ($a_bytes < 1152921504606846976) {
        return round($a_bytes / 1125899906842624, 2) .' PiB';
    } elseif ($a_bytes < 1180591620717411303424) {
        return round($a_bytes / 1152921504606846976, 2) .' EiB';
    } elseif ($a_bytes < 1208925819614629174706176) {
        return round($a_bytes / 1180591620717411303424, 2) .' ZiB';
    } else {
        return round($a_bytes / 1208925819614629174706176, 2) .' YiB';
    }
}

/**
 * @param string $pattern
 * @param integer $flags
 *
 * @return array
 *
 * @author    Mike
 * @link      http://www.php.net/manual/fr/function.glob.php#106595
 *
 * Does not support flag GLOB_BRACE
 *
*/
function glob_recursive($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
    }

    return $files;
}

function glob_recursive_tree($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $dirtop = explode('/', $dir);
        $files[end($dirtop)] = glob_recursive_tree($dir . '/' . basename($pattern), $flags);
    }

    return $files;
}

function print_list($list)
{
    global $upload_dir, $path;

    $l = array();
    foreach ($list as $f) {
        if (!is_string($f)) {
            continue;
        }

        if (is_dir($f)) {
            $top = basename($f);
            $l[] = sprintf('<li><span class="dir">%s</span>%s</li>', $top, print_list($list[$top]));
        } elseif (file_exists($f)) {
            $l[] = sprintf('<li><a href="%s" rel="nofollow" class="view-inline">%s</a> <span class="filesize">(%s)</span></li>',
                'uploads' . str_replace($upload_dir, '', $f),
                basename($f),
                _format_bytes(filesize($f))
            );
        }
    }
    return sprintf('<ul>%s</ul>', implode($l));
}
