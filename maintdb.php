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
 *
 *
 * @param mixed  $txt (GET param) whether to return result in text or JSON (default)
 * @param string $iurt (GET param) return a iurt-specific response format: only the username
 *
 * @return string 
 *
 * Returned format is JSON format as a default:
 * <code>
 * {"maintainers": {"username": => {"packages" => ["package_name1", "package_name2"]}}}
 * </code>
 * or
 * <code>
 * {"packages": {"package_name": {"maintainers" => ["user1", "user2"]}}}
 * </code>
 *
 * either specific, iurt format (use ?pkg=...&iurt in query string)
 * <code>
 * user_name
 * </code>
 *
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

$iurt = isset($_GET['iurt']) ? true : false;

/** Returned data */
$return  = array();

$s = file_get_contents($maintdb);

if (null !== $uid) {
    $pkg = null;
    if (preg_match_all(sprintf('/(.*) %s\n?/', $uid), $s, $res)) {
        $return = array(
            'maintainers' => array(
                $uid => array(
                    'packages' => $res[1]
                )
            )
        );
    }
} elseif (null !== $pkg) {
    $uid = null;
    if (preg_match_all(sprintf('/%s (.*)\n?/', $pkg), $s, $res)) {
        $return = array(
            'packages' => array(
                $pkg => array(
                    'maintainers' => array($res[1][0])
                )
            )
        );
    }
}

if ($iurt && $pkg) {
    header('Content-Type: text/plain; charset: utf-8');
    if (isset($return['packages']))
        echo $return['packages'][$pkg]['maintainers'][0], "\n";
    else
        echo "\n";
}
else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return);
}