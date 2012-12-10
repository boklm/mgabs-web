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

/** Where is the current app located. */
$g_webapp_dir = '/var/www/bs';

/** Full system path where packages are uploaded. */
$upload_dir = '/var/lib/schedbot/uploads';

/** How long a history should we keep, in days. */
$max_modified = 2;

/** How many nodes are available. */
$g_nodes_count = 2;

/** html > body > h1 title */
$title = 'Build system status';

/** Should crawlers index this page or not? meta[robots] tag.*/
$robots = 'index,nofollow,nosnippet,noarchive';

/** */
$g_root_url = 'http://pkgsubmit.mageia.org/';

/** URL to view a package svn revision. %d is replaced by the revision  */
$package_commit_url = 'http://svnweb.mageia.org/packages?view=revision&revision=%d';

/** name of the theme */
$theme_name = 'mageia';

/** themes directory */
$themes_dir = $g_webapp_dir . '/themes/';
