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
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1><?php echo $title ?></h1>

<?php

$bannerfile = dirname(__FILE__) . '/banner.html';
if (file_exists($bannerfile)) {
    echo file_get_contents($bannerfile);
}

if (!is_null($g_user) || isset($_GET['package'])) {
    echo '<a href="/">&laquo;&nbsp;Back to full list</a>';
}

echo '<ul>';
$figures_list = array();

if (!isset($_GET['package'])) {

    // TODO should be cached.
    $missing_deps_count = preg_match_all("/<item>/m", file_get_contents("http://check.mageia.org/cauldron/dependencies.rss"), $matches);
    $unmaintained_count = file_exists(__DIR__ . '/data/unmaintained.txt') ? count(file(__DIR__ . '/data/unmaintained.txt')) : 0;

    if ($missing_deps_count > 0
        || $unmaintained_count > 0
    ) {
        if ($missing_deps_count > 0) {
            $figures_list[] = sprintf('<span class="figure">%d</span> <a href="%s">broken dependencies</a>', 
                                $missing_deps_count,
                                'http://check.mageia.org/cauldron/dependencies.html'
            );
        }

        if ($unmaintained_count > 0) {
            $figures_list[] = sprintf('<span class="figure">%d</span> <a href="%s">unmaintained package%s</a>',
                                $unmaintained_count,
                                'data/unmaintained.txt',
                                plural($unmaintained_count)
            );
        }

        if (count($figures_list) > 0)
            $figures_list[count($figures_list)-1] .= sprintf(' &raquo; <a href="%s" class="action-btn">%s</a>',
                                                        'https://wiki.mageia.org/en/Importing_packages',
                                                        'you can help!');
    }

    preg_match_all('/<span class="bz_result_count">(\d+)/', file_get_contents("https://bugs.mageia.org/buglist.cgi?quicksearch=%40qa-bugs+-kw%3Avali"), $matches);
    $qa_bugs = $matches[1][0];
    if ($qa_bugs > 0) {
        $figures_list[] = sprintf('<span class="figure">%d</span> <a href="%s">package update%s to validate</a> &raquo; <a href="%s" class="action-btn">%s</a>',
                $qa_bugs,
                'https://bugs.mageia.org/buglist.cgi?quicksearch=%40qa-bugs+-kw%3Avali',
                plural($qa_bugs),
                'https://wiki.mageia.org/en/QA_process_for_validating_updates',
                'you can help!'
        );
    }

    if (count($figures_list) > 0) {
        echo array_reduce($figures_list, function ($res, $e) { return $res . '<li><p>' . $e . '</p></li>'; }, '');
    }

    $buildtime_stats = array();

    // Builds in progress
    if (count($hosts) > 0) {
        echo '<li>',
            sprintf('<p><span class="figure">%d</span> build%s in progress:</p>', count($hosts), plural(count($hosts)));

        $s = '';
        $tmpl = <<<TB
<tr>
    <td>%s</td>
    <td>%s</td>
    <td><a href="?user=%s">%s</a></td>
    <td>%s</td>
    <td>%s</td>
    <td>%s/%s</td>
</tr>
TB;
        foreach ($hosts as $machine => $b) {
            foreach ($b as $arch => $key) {
                $s .= sprintf($tmpl,
                    $machine,
                    $arch,
                    $pkgs[$key]['user'], $pkgs[$key]['user'],
                    $pkgs[$key]['package'],
                    $pkgs[$key]['version'],
                    $pkgs[$key]['media'], $pkgs[$key]['section']);
            }
        }
        echo '<div align="center"><table>',
             '<tr><th>Machine</th><th>Arch</th><th>User</th><th>Package</th><th>Target</th><th>Media</th></tr>',
             $s,
             '</table></div>',
             '<div class="clear"></div>',
             '</li>';
    } else {
        echo '<li><p>No build in progress.</p></li>';
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
    <td class="timeinfo">%s</td>
    <td><a href="?user=%s">%s</a></td>
    <td><a href="http://svnweb.mageia.org/packages?view=revision&revision=%d" title="%s">%s</a></td>
    <td>%s</td>
    <td>%s/%s</td>
    <td class="status-box"></td>
T;

if ($total > 0) {
    foreach ($pkgs as $key => $p) {
        if (trim($p['package']) == '') {
            continue;
        }

        $s .= sprintf($tmpl,
            $p['type'],
            timediff(key2timestamp($key)) . ' ago',
            $p['user'], $p['user'],
            $p['revision'],
            addslashes($p['summary']),
            $p['package'],
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
           if (!is_dir($typelink)) {
              $typelink = '';
           }
        }
        $typestr = $p['type'];
        if ($p['status']['build']) {
            $typealt = 'Building on';
            foreach ($p['status']['build'] as $h) {
                $typealt .= " $h";
            }
            $typestr = "<span title='$typealt'>$typestr</a>";
        }

        $s .= '<td>';
        $s .= ($typelink != '') ?
            sprintf('<a href="%s" class="status-link">%s</a>', $typelink, $typestr) :
            $typestr;

        $s .= '</td><td class="timeinfo">';
        if ($p['type'] == 'uploaded') {
            $tdiff = timediff($p['buildtime']['start'], $p['buildtime']['end']); // use $p['buildtime']['diff']; instead?
            $s    .= $tdiff;
            $tdiff = floor(($p['buildtime']['end'] - $p['buildtime']['start']) / 60)*60;

            @$buildtime_stats[timediff(0, $tdiff)] += 1;
        }
        $s .= '</td>';
        $s .= '</tr>';
    }
    echo sprintf('<li><p><span class="figure">%d</span> packages submitted in the past %d&nbsp;hours:</p>', $total, $max_modified * 24);

    // Last submitted packages
    echo '<table>',
        '<thead><tr><th>Submitted</th><th>User</th>
            <th>Package</th><th>Target</th><th>Media</th>
            <th colspan="2">Status</th><th>Build time</th></tr></thead>',
        '<tbody>', $s, '</tbody>',
        '</table>';

    // Stats
    $s = '<div id="stats">
        <div id="status-chart"></div>
        <div id="packagers-chart"></div>';

    $s .= sprintf(
        '<table style="width: 70%%; margin: 2em 0 2em 80px;">
            <tr><td>Total time</td><td>%s hours</td></tr>
            <tr><td>Average</td><td>%s minutes</td></tr>
            <tr><td>Builds count</td><td>%s</td></tr>
            </table>',
        round($buildtime_total / 60, 2),
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
    <script>
    $(function () {
        $('.status-link').click(function (ev) {
            ev.preventDefault();
            var key = $(this).attr("href");
            var elId = 'e' + key.replace(/\/|\./g, '-');

            if ($("#" + elId).length == 0) {
                $(this).parent().parent().after($("<tr />",
                    {
                        class: "build-files-list",
                        id: elId,
                        html: '<td colspan="8">loading</td>'
                    }
                ));
                $.get(
                    "/log_files.php",
                    {"k": $(this).attr("href")},
                    function (data) {
                        $("#" + elId).html('<td colspan="2"></td><td colspan="6">' + data + '</td>');
                    }
                );
            } else {
                $("#" + elId).toggle();
            }
            return false;
        });
    });
    </script>
    <div class="clear"></div>
    <hr />
    <p>Generated at <?php echo $date_gen; ?>.
        Code for this page is in <a href="http://svnweb.mageia.org/soft/build_system/web/">http://svnweb.mageia.org/soft/build_system/web/</a>.</p>
</body>
</html>
