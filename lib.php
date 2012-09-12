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
 * List all packages submitted to the BS.
 *
 * @param string $upload_dir
 * @param integer $max_modified
 *
 * @return array
*/
function get_submitted_packages($upload_dir, $max_modified)
{
    $cwd = getcwd();
    chdir($upload_dir);

    $matches   = array();
    $all_files = shell_exec("find \( -name '*.rpm' -o -name '*.src.rpm.info' -o -name '*.lock' -o -name '*.done' -o -name '*.upload' \) -ctime -$max_modified -printf \"%p\t%T@\\n\"");
    $re        = "!^\./(\w+)/((\w+)/(\w+)/(\w+)/(\d+)\.(\w+)\.(\w+)\.(\d+))_?(.*)(\.src\.rpm(?:\.info)?|\.lock|\.done|\.upload)\s+(\d+\.\d+)$!m";
    $r         = preg_match_all($re,
                    $all_files,
                    $matches,
                    PREG_SET_ORDER);

    chdir($cwd);

    return $matches;
}


/**
 *
 * @param array $list_of_files such as returned by get_submitted_packages()
 * @param string $package to filter against
 * @param string $user to filter against
 *
 * @return array()
*/
function get_refined_packages_list($list_of_files, $package = null, $user = null)
{
    $pkgs  = array();
    $hosts = array();

    $buildtime_total = array();
    $build_dates     = array_fill_keys(range(0, 23), 0);

    foreach ($list_of_files as $val) {

        if (!is_null($user) && $user != $val[7]) {
            continue;
        }
        $key = $val[6] . $val[7];
        if (!array_key_exists($key, $pkgs)) {

            $pkgs[$key] = array(
                'status'   => array(),
                'path'     => $val[2],
                'version'  => $val[3],
                'media'    => $val[4],
                'section'  => $val[5],
                'user'     => $val[7],
                'host'     => $val[8],
                'job'      => $val[9],
                'revision' => '',
                'summary'  => '',
                'package'  => ''
            );
        }
        $status = $val[1];
        $data   = $val[10];
        if (preg_match("/@(\d+):/", $data, $revision)) {
            $pkgs[$key]['revision'] = $revision[1];
        }

        $pkgs[$key]['status'][$status] = 1;
        $ext                           = $val[11];

        if ($ext == '.src.rpm.info') {
            preg_match("!^(?:@\d+:)?(.*)!", $data, $name);
            $pkgs[$key]['package'] = $name[1];
        } else if ($ext == '.src.rpm') {
            $pkgs[$key]['status']['src'] = 1;
        } else if ($ext == '.upload') {
            $pkgs[$key]['status']['upload'] = 1;
        } else if ($ext == '.lock') {
            preg_match("!(.*)\.iurt\.(.*)\.\d+\.\d+!", $data, $buildhost);
            if (!$hosts[$buildhost[2]]) {
                $hosts[$buildhost[2]]= array();
            }
            $hosts[$buildhost[2]][$buildhost[1]] = $key;
            if ($pkgs[$key]['status']['build']) {
                array_push($pkgs[$key]['status']['build'], $buildhost[2]);
            } else {
                $pkgs[$key]['status']['build'] = array($buildhost[2]);
            }
        } else if ($ext == '.done') {
            // beware! this block is called twice for a given $key

            $pkgs[$key]['buildtime']['start'] = key2timestamp($val[6]);
            $pkgs[$key]['buildtime']['end']   = round($val[12]);
            $pkgs[$key]['buildtime']['diff']  = $pkgs[$key]['buildtime']['end'] - $pkgs[$key]['buildtime']['start'];

            @$build_dates[gmdate('G', $pkgs[$key]['buildtime']['start'])] += 1;

            // keep obviously dubious values out of there
            // 12 hours is be an acceptable threshold given current BS global perfs
            // as of April 2011
            if ($pkgs[$key]['buildtime']['diff'] < 43200) {
                $buildtime_total[$key] = $pkgs[$key]['buildtime']['diff'];
            }
        }
    }

    // filter packages if a package name was provided
    if (!is_null($package)) {
        foreach ($pkgs as $key => $pkg) {
            preg_match("/^(.*)-[^-]*-[^-]*$/", $pkg['package'], $name);
            if ($package != $name[1]) {
                unset($pkgs[$key]);
            }
        }
    }

    // sort by key in reverse order to have more recent pkgs first
    krsort($pkgs);
    ksort($build_dates);

    $build_count     = count($buildtime_total);
    $buildtime_total = array_sum($buildtime_total);

    // above block. OUTPUT: $pkgs, $build_dates, $buildtime_total, $hosts

    return array(
        $pkgs,
        $hosts,
        $build_count,
        $build_dates,
        $buildtime_total
    );
}

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
function timediff($start, $end = null)
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
function publish_stats_headers($stats, $buildtime_total, $buildtime_avg, $build_count, $last_package = null)
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


    header(sprintf('X-BS-Buildtime: %d', round($buildtime_total)));
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
        'width':500,
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
        arsort($data);
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
        'width':500,
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
                if ($d[0] >= 20) {
                    if (!array_key_exists('20 minutes', $newdata)) {
                        $newdata['20 minutes'] = $count;
                    } else {
                        $newdata['20 minutes'] += $count;
                    }
                } elseif ($d[0] >= 10) {
                    if (!array_key_exists('10 minutes', $newdata)) {
                        $newdata['10 minutes'] = $count;
                    } else {
                        $newdata['10 minutes'] += $count;
                    }
                } else {
                    $newdata[$duration] = $count;
                }
            }
        }
        uksort($newdata, "timesort");

        $rows  = array("['Duration', 'Builds']");
        foreach ($newdata as $duration => $count) {

            if     ($duration == '0 second')   { $duration = '<1'; }
            elseif ($duration == '10 minutes') { $duration = '>10'; }
            elseif ($duration == '20 minutes') { $duration = '>20'; }
            elseif ($duration == '60 minutes') { $duration = '>60'; }
            else {
                $duration = explode(' ', $duration);
                $duration = $duration[0];
            }

            $rows[] = sprintf("['%s', %d]", $duration, $count);
        }
        $rows = implode(', ', $rows);
        return <<<S
function draw_buildtime_chart() {
    var data = google.visualization.arrayToDataTable([
        {$rows}
    ]);
    var options = {
        title: 'How long are most of the builds?',
        hAxis: {title: 'Duration (in minutes)'},
        'width':500,
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
    var UTCOffsetInHours = new Date().getTimezoneOffset()/-60;
    //alert(UTCOffsetInHours);
    //TODO rotate hours in \$rows so it represents local time.

    var data = google.visualization.arrayToDataTable([
        {$rows}
    ]);
    var options = {
        title: 'When do builds happen? (UTC - working on a local time fix)',
        hAxis: {title: 'Hours'},
       'width':500,
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

