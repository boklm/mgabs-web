<?php
/**
 * Mageia build-system quick status report script.
 * List unmaintained packages, with a twist.
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

$data = file('data/unmaintained.txt');
//$data = array_slice($data, 0, 10);

$groups = array();

foreach ($data as $package) {
    $package = trim($package);
    if (substr($package, 0, 8) == 'libgnome') {
        $groups['gnome'][] = $package;
    } elseif (substr($package, 0, 3) == 'lib') {
        $p = substr($package, 3);
        $groups[$p][] = $package;
    } else {
        $p = explode('-', $package);
        if (count($p) > 1) {
            $groups[$p[0]][] = $package;
        } else {
            $groups[$package][] = $package;
        }
    }
}

$s = count($groups) . ' groups for ' . count($data) . ' packages.';
$s .= '<ul class="groups">';

$s .= array_reduce($groups, function ($res, $el) {
    return $res . '<li>' . implode(', ', array_map(function ($ela) {
        $spec_url = sprintf('http://svnweb.mageia.org/packages/cauldron/%s/current/SPECS/%s.spec?view=markup', $ela, $ela);
        return sprintf('<a href="%s">%s</a>', $spec_url, $ela);
    }, $el)) . '</li>';
});
$s .= '</<ul>';

echo <<<S
<style>
ul.groups { list-style: none; margin: 0; padding: 0; }
    ul.groups li { display: inline-block; padding: 1em; background: #eee; margin: 1px; font-family: Verdana; font-size: 70%; }
</style>

<h1>Unmaintained packages</h1>
<p>Pick one and become a Mageia packager super-hero! (TODO how? why?)</p>
<p>A group means that you may want to pick all packages within the same group, for consistency and efficiency.</p>
<p>Don't hesitate to ask on #mageia-dev, and to notify if you take maintenance of one package.</p>
$s
S;
