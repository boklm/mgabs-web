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
 *
 * Shows submitted packages in the past $max_modified 24 hours and their
 * status (built & uploaded, failed build, rejected, etc.).
 *
 * This was written anew in Jan. 2011 because existing Mandriva build-system
 * web report code was not clearly licensed at this very time.
*/

error_reporting(E_ALL);

require __DIR__ . '/conf.php';
require __DIR__ . '/lib.php';

// sanity checks
if (!is_dir($upload_dir)) {
    $msg = "$upload_dir does not exist on this system. Please check your config.";
    error_log($msg);
    die($msg);
}

$g_user = isset($_GET['user']) ? htmlentities(strip_tags($_GET['user'])) : null;

if ($g_user) {
    $title .= ' for ' . $g_user . "'s packages";
    $robots = 'no' . $robots;
}
$tz       = new DateTimeZone('UTC');
$date_gen = date('c');

$matches = get_submitted_packages($upload_dir, $max_modified);

list($pkgs, $hosts, $build_count, $build_dates, $buildtime_total) = get_refined_packages_list(
    $matches,
    isset($_GET['package']) ? $_GET['package'] : null,
    isset($_GET['user']) ? $_GET['user'] : null
);

list($stats, $users, $total, $pkgs) = build_stats($pkgs);

$buildtime_total = $buildtime_total / 60;
$buildtime_avg   = ($build_count == 0) ?
    0 :
    round($buildtime_total / $build_count, 2);

publish_stats_headers(
    $stats,
    $buildtime_total,
    $buildtime_avg,
    $build_count,
    (isset($_GET['last']) && $total > 0) ? reset($pkgs) : null
);

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title><?php echo strip_tags($title); ?></title>
    <meta name="robots" content="<?php echo $robots; ?>">
    <link rel="home" href="<?php echo $g_root_url; ?>">
    <link rel="author" href="http://www.mageia.org/">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body class="contribute">
    <header id="mgnavt">
        <h1><?php echo $title ?></h1>
    </header>
    <article>
<?php

$bannerfile = dirname(__FILE__) . '/banner.html';
if (file_exists($bannerfile)) {
    echo file_get_contents($bannerfile);
}

if (!is_null($g_user) || isset($_GET['package'])) {
    echo '<a href="/">&laquo;&nbsp;Back to full list</a>';
}

echo '<ul class="figures">';
$figures_list = array();

if (!isset($_GET['package'])) {

    // TODO should be cached.
    $missing_deps_count = preg_match_all("/<item>/m", file_get_contents("http://check.mageia.org/cauldron/dependencies.rss"), $matches);
    $unmaintained_count = file_exists(__DIR__ . '/data/unmaintained.txt') ? count(file(__DIR__ . '/data/unmaintained.txt')) : 0;

    if ($missing_deps_count > 0
        || $unmaintained_count > 0
    ) {
        if ($missing_deps_count > 0) {
            $figures_list[] = sprintf('<span class="figure">%d</span> <a rel="nofollow" href="%s">broken dependencies</a>',
                                $missing_deps_count,
                                'http://check.mageia.org/cauldron/dependencies.html'
            );
        }

        if ($unmaintained_count > 0) {
            $figures_list[] = sprintf('<span class="figure">%d</span> <a rel="nofollow" href="%s">unmaintained package%s</a>',
                                $unmaintained_count,
                                'data/unmaintained.txt',
                                plural($unmaintained_count)
            );
        }

        if (count($figures_list) > 0)
            $figures_list[count($figures_list)-1] .= sprintf(' <a href="%s" class="action-btn" title="%s">%s</a>',
                                                        'https://wiki.mageia.org/en/Importing_packages',
                                                        'YES you can!', 'you can help!');
    }

    preg_match_all('/<span class="bz_result_count">(\d+)/', file_get_contents("https://bugs.mageia.org/buglist.cgi?quicksearch=%40qa-bugs+-kw%3Avali"), $matches);
    $qa_bugs = $matches[1][0];
    if ($qa_bugs > 0) {
        $figures_list[] = sprintf('<span class="figure">%d</span> <a rel="nofollow" href="%s">package update%s to validate</a>
                                    <a href="%s" class="action-btn" title="%s">%s</a>',
                $qa_bugs,
                'https://bugs.mageia.org/buglist.cgi?quicksearch=%40qa-bugs+-kw%3Avali',
                plural($qa_bugs),
                'https://wiki.mageia.org/en/QA_process_for_validating_updates',
                'YES you can!', 'you can help!'
        );
    }

    if (count($figures_list) > 0) {
        echo array_reduce($figures_list, function ($res, $e) { return $res . '<li><p>' . $e . '</p></li>'; }, '');
    }

    echo '</ul><ul>';
    $buildtime_stats = array();

    // Builds in progress
    if (count($hosts) > 0) {
        echo '<li>',
            sprintf('<p><span class="figure">%d</span> build%s in progress:</p>', count($hosts), plural(count($hosts)));

        $s = '';
        $tmpl = <<<TB
<tr>
    <td><span class="package">%s</span></td>
    <td><a rel="nofollow" href="?user=%s">%s</a></td>
    <td>%s <span class="media">%s/%s</span></td>
    <td>%s <span class="media">%s</span></td>
</tr>
TB;
        foreach ($hosts as $machine => $b) {
            foreach ($b as $arch => $key) {
                $s .= sprintf($tmpl,
                    $pkgs[$key]['package'],
                    $pkgs[$key]['user'], $pkgs[$key]['user'],
                    $pkgs[$key]['version'], $pkgs[$key]['media'], $pkgs[$key]['section'],
                    $machine, $arch);
            }
        }
        echo '<div align="center"><table><thead><tr>',
             '<th>Package</th>
                <th>User</th>
                <th>Target <span class="media">media</span></th>
                <th>Machine <span class="media">arch</span></th></tr></thead><tbody>',
             $s,
             '</tbody></table></div>',
             '<div class="clear"></div>',
             '</li>';
    } else {
        //echo '<li><p>No build in progress.</p></li>';
    }
}

$upload_time = get_upload_time();
if (!is_null($upload_time)) {
    echo sprintf('<li><p>Upload in progress for %s.</p></li>', timediff($upload_time));
}


// Build queue
$s    = '';
$tmpl = <<<T
<tr class="%s">
    <td><a rel="nofollow" href="%s" title="%s" class="package">%s</a>
        <span class="revision"><a href="%s">view changes @ r%d</a></span></td>
    <td><a rel="nofollow" href="?user=%s" class="committer">%s</a>
        <span class="timeinfo">%s</span></td>
    <td>%s
        <span class="media">%s/%s</span></td>
    <td class="status-box"></td>
T;

if ($total > 0) {
    foreach ($pkgs as $key => $p) {
        if (trim($p['package']) == '') {
            continue;
        }
        $revision_link = sprintf('http://svnweb.mageia.org/packages?view=revision&revision=%d', $p['revision']);

        $s .= sprintf($tmpl,
            $p['type'],
            $revision_link,
            addslashes($p['summary']),
            $p['package'],
            $revision_link, $p['revision'],
            $p['user'], $p['user'],
            timediff(key2timestamp($key)) . ' ago',
            $p['version'],
            $p['media'], $p['section']
        );

        $typelink = '';
        if ($p['type'] == 'failure') {
            $typelink = '/uploads/' . $p['type'] . '/' . $p['path'];
        } elseif ($p['type'] == 'rejected') {
            $typelink = '/uploads/' . $p['type'] . '/' . $p['path'] . '.youri';
        } else {
            $typelink = '/uploads/done/' . $p['path'];
            if (!is_dir(realpath($upload_dir . '/..' . $typelink))) {
                $typelink = '';
            }
        }
        $typestr = $p['type'];
        if (isset($p['status']['build'])) {
            $typealt = 'Building on';
            foreach ($p['status']['build'] as $h) {
                $typealt .= " $h";
            }
            $typestr = "<span title='$typealt'>$typestr</a>";
        }

        $s .= '<td>';

        $show_time = '';
        if ($p['type'] == 'uploaded') {
            $tdiff = timediff($p['buildtime']['start'], $p['buildtime']['end']); // use $p['buildtime']['diff']; instead?
            $show_time = '<span class="timeinfo">' . $tdiff . '</span>';

            $tdiff = floor(($p['buildtime']['end'] - $p['buildtime']['start']) / 60)*60;
            @$buildtime_stats[timediff(0, $tdiff)] += 1;
        }
        $s .= ($typelink != '')
            ? sprintf('<a rel="nofollow" href="%s" class="status-link"><span class="status-box"></span> %s %s</a>',
                $typelink, $typestr, $show_time)
            : sprintf('<span class="status-box"></span> %s %s',
                $typestr, $show_time);

        $s .= '</td></tr>';
    }
    echo sprintf('<li><p><span class="figure">%d</span> packages submitted in the past %d&nbsp;hours:</p>', $total, $max_modified * 24);

    // Last submitted packages
    echo '<table id="submitted-packages">',
        '<thead><tr>
            <th>Package</th>
            <th>Who <span class="timeinfo">when</span></th>
            <th>Target <span class="media">media</span></th>
            <th>Status <span class="timeinfo">build time</span></th>
        </tr></thead>',
        '<tbody>', $s, '</tbody>',
        '</table>';

    // Stats
    $s = '<div id="stats">
        <div id="status-chart"></div>
        <div id="packagers-chart"></div>';

    $total_buildtime = round($buildtime_total / 60, 1);
    $avail_capacity  = 24 * $max_modified * $g_nodes_count;
    $capacity_used   = round($total_buildtime / $avail_capacity * 100, 1);
    $s .= sprintf(
        '<table style="width: 70%%; margin: 2em 0 2em 80px;">
            <tr><td>Total time</td><td>%s hours (%s%% of capacity with %d nodes)</td></tr>
            <tr><td>Average</td><td>%s minutes</td></tr>
            <tr><td>Builds count</td><td>%s</td></tr>
            </table>',
        $total_buildtime,
        $capacity_used,
        $g_nodes_count,
        $buildtime_avg,
        $build_count
    );

    $s .= '<br /><br />
        <div id="buildtime-chart"></div>
        <div id="buildschedule-chart"></div>
    </div>';

    echo $s, '</li></ul>';

    uksort($buildtime_stats, "timesort");
    echo '<script>',
        mga_bs_charts::js_draw_status_chart($stats, 'status-chart'),
        mga_bs_charts::js_draw_buildtime_chart($buildtime_stats, 'buildtime-chart'),
        mga_bs_charts::js_draw_buildschedule_chart($build_dates, 'buildschedule-chart'),
        mga_bs_charts::js_draw_packagers_chart($users, 'packagers-chart'),
        mga_bs_charts::js_draw_charts(),
        '</script>';
        echo mga_bs_charts::js_init();
}
else
{
    echo sprintf('<li><p>No package has been submitted in the past %d&nbsp;hours.</p></li></ul>',
        $max_modified * 24);
}

?>
    </ul>
    <script src="js/jquery.js"></script>
    <script src="js/pkgsubmit.js"></script>
    <script src="//nav.mageia.org/js"></script>
    <div class="clear"></div>
    <hr />
    <p>Generated at <?php echo $date_gen; ?>.
        Code for this page is in <a rel="nofollow" href="http://svnweb.mageia.org/soft/build_system/web/">http://svnweb.mageia.org/soft/build_system/web/</a>.</p>
    </article>
</body>
</html>
