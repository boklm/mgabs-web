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

/** Full system path where packages are uploaded. */
$upload_dir = '/var/lib/schedbot/uploads';

/** How long a history should we keep, in days. */
$max_modified = 2;

/** html > body > h1 title */
$title = '<a href="http://mageia.org/">Mageia</a> build system status';

/** Should crawlers index this page or not? meta[robots] tag.*/
$robots = 'index,nofollow,nosnippet,noarchive';

/** */
$g_root_url = 'http://pkgsubmit.mageia.org/';