<?php
/**
 * Mageia maintdb quick filter.
 * Make use of http://pkgsubmit.mageia.org/data/maintdb.txt data.
 *
 * This only supports mapping 1 user to several packages
 * or 1 package to 1 user.
 *
 * @param string $uid (GET param) user id
 * @param string $pkg (GET param) package name
 * @param mixed  $json (GET param) whether to return result in text or JSON
 *
 * @return string 
 *
 * Returned format is either text format:
 * <code>
 * package_name user_name
 * package_name2 user_name2
 * </code>
 *
 * either JSON format:
 * <code>
 * {"user_name": ["package_name1", "package_name2"]}
 * </code>
 * 
 * TODO check if preg_match_all() is more efficient than exec('grep ...')
 * TODO if so, check security concerns for $uid and $pkg
 *
 * @copyright Copyright (C) 2011 Mageia.Org
 * @author Romain d'Alverny
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU GPL v2
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License aspublished by the
 * Free Software Foundation; either version 2 of the License, or (at your
 * option) any later version.
*/

/** Path to maintdb.txt */
$maintdb = realpath(__DIR__) . '/data/maintdb.txt';

/** User name */
$uid = isset($_GET['uid']) ? trim(htmlentities(strip_tags($_GET['uid']))) : null;

/** Package name */
$pkg = isset($_GET['pkg']) ? trim(htmlentities(strip_tags($_GET['pkg']))) : null;

/** Return format */
$json = isset($_GET['json']) ? true : false;

/** Returned data */
$return  = null;

$s = file_get_contents($maintdb);

if (null !== $uid) {
    if (preg_match_all(sprintf('/(.*) %s\n?/', $uid), $s, $res)) {
        $return = array($uid => $res[1]);
    }
} elseif (null !== $pkg) {
    if (preg_match_all(sprintf('/%s (.*)\n?/', $pkg), $s, $res)) {
        $return = array($res[1][0] => $pkg);
    }
}

if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return);
}
else {
    header('Content-Type: text/plain; charset: utf-8');
    if (is_array($return)) {
        foreach ($return as $u => $packages) {
            foreach ($packages as $p) {
                echo sprintf("%s %s\n", $p, $u);
            }
        }
    } else {
        echo "";
    }
}