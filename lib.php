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


/**
 * Publish BS stats as HTTP headers for remote services to adapt.
 *
 * @param array $stats
 * @param integer $buildtime_total
 * @param integer $build_count
 * @param array $last_package
 *
 * @return void
*/
function publish_stats_headers($stats, $buildtime_total, $build_count, $last_package = null)
{
    foreach ($stats as $k => $v) {
        header("X-BS-Queue-$k: $v");
    }

    $w = $stats['todo'] - 10;
    if ($w < 0) {
        $w = 0;
    }
    $w = $w * 60;
    header("X-BS-Throttle: $w");

    if (!is_null($last_package)) {
        header("X-BS-Package-Status: ".$last_package['type']);
    }

    $buildtime_total = $buildtime_total / 60;
    header(sprintf('X-BS-Buildtime: %d', round($buildtime_total)));


    $buildtime_avg = ($build_count == 0) ?
        0 :
        round($buildtime_total / $build_count, 2);
    header(sprintf('X-BS-Buildtime-Average: %5.2f', $buildtime_avg));
}

/**
 * check if emi is running
 *
 * @return
*/
function get_upload_time()
{
    if (file_exists('/var/lib/schedbot/tmp/upload')) {
        $stat = stat('/var/lib/schedbot/tmp/upload');
        if ($stat) {
            return $stat['mtime'];
        }
    }

    return null;
}

/**
 * Build and return stats about all packages.
 *
 * @todo should not alter/return $pkgs
 *
 * @param array $pkgs
 *
 * @return array (array, array, integer, array)
*/
function build_stats($pkgs)
{
    // count all packages statuses
    $stats = array(
        'todo'     => 0,
        'building' => 0,
        'partial'  => 0,
        'uploaded' => 0,
        'rejected' => 0,
        'failure'  => 0
    );
    $total = count($pkgs);

    // count users' packages
    $users = array();

    if ($total > 0) {
        foreach ($pkgs as $key => $p) {
            $pkgs[$key]['type'] = pkg_gettype($p);

            $stats[$pkgs[$key]['type']] += 1;

            if (!array_key_exists($p['user'], $users)) {
                $users[$p['user']] = 1;
            } else {
                $users[$p['user']] += 1;
            }
        }
    }

    return array(
        $stats,
        $users,
        $total,
        $pkgs
    );
}


class mga_bs_charts
{

    public static function js_init()
    {
        return <<<S
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">

  // Load the Visualization API and the piechart package.
  google.load('visualization', '1.0', {'packages':['corechart']});

  // Set a callback to run when the Google Visualization API is loaded.
  google.setOnLoadCallback(drawChart);
</script>
S;
    }

    public static function js_draw_charts()
    {
        return <<<S
function drawChart() {
    draw_status_chart();
    draw_buildtime_chart();
    draw_buildschedule_chart();
    draw_packagers_chart();
}
S;
    }

    public static function js_draw_status_chart($stats, $id)
    {
        $rows = array();
        foreach ($stats as $status => $count) {
            $rows[] = sprintf("['%s', %d]", $status, $count);
        }
        $rows = implode(', ', $rows);
        $s = <<<S
function draw_status_chart() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Status');
    data.addColumn('number', 'Packages');
    data.addRows([{$rows}]);

    var options = {
        'title':'Packages status',
        'width':600,
        'height':200,
        'colors': ['white', 'yellow', 'blue', 'green', 'orange', 'red'],
        'backgroundColor': '#f8f8f8'
    };

    var chart = new google.visualization.PieChart(document.getElementById('{$id}'));
    chart.draw(data, options);
}
S;
        return $s;
    }

    public static function js_draw_packagers_chart($data, $id)
    {
        $rows = array();
        foreach ($data as $packager => $count) {
            $rows[] = sprintf("['%s', %d]", $packager, $count);
        }
        $rows = implode(', ', $rows);
        $s = <<<S
function draw_packagers_chart() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Packagers');
    data.addColumn('number', 'Packages');
    data.addRows([{$rows}]);

    var options = {
        'title':'Packagers',
        'width':600,
        'height':200,
        'backgroundColor': '#f8f8f8',
        'sliceVisibilityThreshold': 2/90
    };

    var chart = new google.visualization.PieChart(document.getElementById('{$id}'));
    chart.draw(data, options);
}
S;
        return $s;
    }

    public static function js_draw_buildtime_chart($data, $id)
    {
        // first pass
        $newdata = array();
        foreach ($data as $duration => $count) {
            if (false !== strpos($duration, 'hour')) {
                $newdata['60 minutes'] += $count;
            } else {
                $d = explode(' ', $duration);
                if ($d[0] > 20) {
                    $newdata['21 minutes'] += $count;
                } else {
                    $newdata[$duration] = $count;
                }
            }
        }
        uksort($newdata, "timesort");

        $rows  = array("['Duration', 'Builds']");
        foreach ($newdata as $duration => $count) {
            if ($duration == '0 second')
                $duration = '< 1 minute';
            elseif ($duration == '21 minutes')
                $duration = '> 20 minutes';
            elseif ($duration == '60 minutes')
                $duration = '> 1 hour';

            $rows[] = sprintf("['%s', %d]", $duration, $count);
        }
        $rows = implode(', ', $rows);
        return <<<S
function draw_buildtime_chart() {
    var data = google.visualization.arrayToDataTable([
        {$rows}
    ]);
    var options = {
        title: 'How long are most builds?',
        hAxis: {title: 'Time'},
        'width':600,
        'height':200,
        'backgroundColor': '#f8f8f8'
    };
    var chart = new google.visualization.ColumnChart(document.getElementById('{$id}'));
    chart.draw(data, options);
}
S;
    }

    public static function js_draw_buildschedule_chart($data, $id)
    {
        $rows = array("['Hour', 'Builds']");
        foreach ($data as $hour => $count) {
            $rows[] = sprintf("['%s', %d]", $hour, $count);
        }
        $rows = implode(', ', $rows);
        return <<<S
function draw_buildschedule_chart() {
    var data = google.visualization.arrayToDataTable([
        {$rows}
    ]);
    var options = {
        title: 'When did builds happen? (CET)',
        hAxis: {title: 'Hours'},
       'width':600,
       'height':200,
       'curveType': 'function',
       'backgroundColor': '#f8f8f8'
    };
    var chart = new google.visualization.LineChart(document.getElementById('{$id}'));
    chart.draw(data, options);
}
S;
    }

}

