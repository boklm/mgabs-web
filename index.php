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

/**
 * @param array $pkg
 *
 * @return string
*/
function pkg_gettype($pkg) {
    if (array_key_exists("rejected", $pkg["status"]))
        return "rejected";
    if (array_key_exists("upload", $pkg["status"]))
        return "uploaded";
    if (array_key_exists("failure", $pkg["status"]))
        return "failure";
    if (array_key_exists("done", $pkg["status"]))
        return "partial";
    if (array_key_exists("build", $pkg["status"]))
        return "building";
    if (array_key_exists("todo", $pkg["status"]))
        return "todo";
    return "unknown";
}

/**
 * @param integer $num
 *
 * @return string
*/
function plural($num) {
    if ($num > 1)
        return "s";
}

/**
 * Return timestamp from package key
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

function timediff($start, $end) {
/**
 * Return human-readable time difference
 *
 * @param integer $start timestamp
 * @param integer $end timestamp, defaults to now
 *
 * @return string
*/
    if (is_null($end)) {
        $end = time();
    }
    $diff = $end - $start;
    if ($diff<60)
       return $diff . " second" . plural($diff);
    $diff = round($diff/60);
    if ($diff<60)
       return $diff . " minute" . plural($diff);
    $diff = round($diff/60);
    if ($diff<24)
       return $diff . " hour" . plural($diff);
    $diff = round($diff/24);

    return $diff . " day" . plural($diff);
}

$g_user = isset($_GET['user']) ? htmlentities(strip_tags($_GET['user'])) : null;

$upload_dir = '/home/schedbot/uploads';
$max_modified = 2;
$title = '<a href="http://mageia.org/">Mageia</a> build system status';
$robots = 'index,nofollow,nosnippet,noarchive';
if ($g_user) {
    $title .= ' for ' . $g_user . "'s packages";
    $robots = 'no' . $robots;
}
$tz = new DateTimeZone('UTC');
$date_gen = date('c');

chdir($upload_dir);

$all_files = shell_exec("find \( -name '*.rpm' -o -name '*.src.rpm.info' -o -name '*.lock' -o -name '*.done' -o -name '*.upload' \) -ctime -$max_modified -printf \"%p\t%T@\\n\"");
$re = "!^\./(\w+)/((\w+)/(\w+)/(\w+)/(\d+)\.(\w+)\.(\w+)\.(\d+))_?(.*)(\.src\.rpm(?:\.info)?|\.lock|\.done|\.upload)\s+(\d+\.\d+)$!m";
$r = preg_match_all($re,
    $all_files,
    $matches,
    PREG_SET_ORDER);

$pkgs = array();
$hosts = array();

$buildtime_total = array();
$buid_dates = array();

foreach ($matches as $val) {

    if ($_GET['user'] && ($_GET['user'] != $val[7])) {
        continue;
    }
    $key = $val[6] . $val[7];
    if (!is_array($pkgs[$key])) {

        $pkgs[$key] = array(
            'status'  => array(),
            'path'    => $val[2],
            'version' => $val[3],
            'media'   => $val[4],
            'section' => $val[5],
            'user'    => $val[7],
            'host'    => $val[8],
            'job'     => $val[9]
        );
    }
    $status = $val[1];
    $data = $val[10];
    if (preg_match("/@(\d+):/", $data, $revision)) {
        $pkgs[$key]['revision'] = $revision[1];
    }
    $pkgs[$key]['status'][$status] = 1;
    $ext = $val[11];
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
        if ($pkgs[$key]['status']['build'])
            array_push($pkgs[$key]['status']['build'], $buildhost[2]);
        else
            $pkgs[$key]['status']['build'] = array($buildhost[2]);
    } else if ($ext == '.done') {
        // beware! this block is called twice for a given $key

        $pkgs[$key]['buildtime']['start'] = key2timestamp($val[6]);
        $pkgs[$key]['buildtime']['end'] = round($val[12]);
        $pkgs[$key]['buildtime']['diff'] = $pkgs[$key]['buildtime']['end'] - $pkgs[$key]['buildtime']['start'];
        
        @$build_dates[date('H', $pkgs[$key]['buildtime']['start'])] += 1;
        
        // keep obviously dubious values out of there
        // 12 hours is be an acceptable threshold given current BS global perfs
        // as of April 2011
        if ($pkgs[$key]['buildtime']['diff'] < 43200) {
            $buildtime_total[$key] = $pkgs[$key]['buildtime']['diff'];
        }
    }
}

// filter packages if a package name was provided
if ($_GET['package']) {
    foreach ($pkgs as $key => $pkg) {
        preg_match("/^(.*)-[^-]*-[^-]*$/", $pkg['package'], $name);
        if ($_GET['package'] != $name[1]) {
            unset($pkgs[$key]);
        }
    }
}

// sort by key in reverse order to have more recent pkgs first
krsort($pkgs);
ksort($build_dates);

$build_count = count($buildtime_total);
$buildtime_total = array_sum($buildtime_total);

// count all packages statuses
$stats = array(
    'uploaded' => 0,
    'failure'  => 0,
    'todo'     => 0,
    'building' => 0,
    'partial'  => 0,
    'built'    => 0,
);
$total = count($pkgs);

// count users' packages
$users = array();

if ($total > 0) {
    foreach ($pkgs as $key => $p) {
        $pkgs[$key]['type'] = pkg_gettype($p);

        $stats[$pkgs[$key]['type']] += 1;

        if (!array_key_exists($p['user'], $users))
            $users[$p['user']] = 1;
        else
            $users[$p['user']] += 1;
    }
}

// check if emi is running
$stat = stat("/var/lib/schedbot/tmp/upload");
if ($stat) {
	$upload_time = $stat['mtime'];
}

// publish stats as headers

foreach ($stats as $k => $v) {
    Header("X-BS-Queue-$k: $v");
}

$w = $stats['todo'] - 10;
if($w < 0)
    $w = 0;
$w = $w * 60;
Header("X-BS-Throttle: $w");

if ($_GET['last'] && $total > 0) {
    reset($pkgs);
    $last = current($pkgs);
    Header("X-BS-Package-Status: ".$last['type']);
}

$buildtime_total = $buildtime_total / 60;
header(sprintf('X-BS-Buildtime: %d', round($buildtime_total)));
$buildtime_avg = round($buildtime_total / $build_count, 2);
header(sprintf('X-BS-Buildtime-Average: %5.2f', $buildtime_avg));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo strip_tags($title); ?></title>
    <meta name="robots" content="<?php echo $robots; ?>">
    <link rel="icon" type="image/png" href="favicon.png" />
    <style type="text/css">
    body, table {
        font-family: Verdana, "Trebuchet MS", "Lucida Grande", "Lucida Sans", Verdana, Tahoma, Arial, sans-serif;
    }
    body { font-size: 80%; }
    .clear { clear: both; }
    table { 
        border-spacing: 0;
        border: 1px solid #ccc;
        float: left;
    }
    table tr { padding: 0; margin: 0; }
    table th { padding: 0.2em 0.5em; margin: 0; border-bottom: 2px solid #ccc; border-right: 1px solid #ccc; }
    table td { padding: 0; margin: 0; padding: 0.2em 0.5em; border-bottom: 1px solid #ccc; }
 
    tr { background: transparent; }
    tr.uploaded { background: #bbffbb; }
    tr.failure, tr.rejected { background: #ffbbbb; }
    tr.todo { background: white; }
    tr.building { background: #ffff99; }
    tr.partial { background: #bbbbff; }
    tr.built { background: #cceeff; }

    td.status-box { width: 1em; height: 1em; }
    tr.uploaded td.status-box { background: green; }
    tr.failure td.status-box, tr.rejected td.status-box { background: red; }
    tr.todo td.status-box { background: white; }
    tr.building td.status-box { background: yellow; }
    tr.partial td.status-box { background: blue; }
    tr.built td.status-box { background: #00ccff; }
    
    #stats { float: right; }
    #score { margin-bottom: 2em; font-family: Helvetica, Verdana, Arial, sans-serif; }
    #score-box { width: 100px; height: 100px; background: #faa; }
    #score-meter { width: 100px; background: #afa; }
    </style>
</head>
<body>
    <h1><?php echo $title ?></h1>

<?php

$bannerfile = dirname(__FILE__) . '/banner.html';
if (file_exists($bannerfile)) {
    echo file_get_contents($bannerfile);
}

if (!is_null($g_user) || $_GET['package'])
    echo '<a href="/">&laquo;&nbsp;Back to full list</a>';

if (!$_GET['package']) {

$missing_deps_count = preg_match_all("/<item>/m", file_get_contents("http://check.mageia.org/cauldron/dependencies.rss"), $matches);
$unmaintained_count = count(file(__DIR__ . '/data/unmaintained.txt'));
if ($missing_deps_count > 0 || $unmaintained_count > 0) {
    echo "<p>";
    if ($missing_deps_count > 0)
        echo "<a href=\"http://check.mageia.org/cauldron/dependencies.html\">$missing_deps_count broken dependencies</a>. ";
    if ($unmaintained_count > 0)
	echo "<a href=\"data/unmaintained.txt\">$unmaintained_count unmaintained packages</a>. ";
    echo '<a href="https://wiki.mageia.org/en/Importing_packages">You can help!</a></strong></p>';
}

if ($upload_time) {
	echo sprintf('<p>Upload in progress for %s.</p>', timediff($upload_time));
}

$buildtime_stats = array();

// Builds in progress
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
     '<caption>', count($hosts), ' builds in progress.</caption>',
     '<tr><th>Machine</th><th>Arch</th><th>User</th><th>Package</th><th>Target</th><th>Media</th></tr>',
     $s,
     '</table></div>';
echo '<div class="clear"></div>';

}

// Build queue
$s = '';
$tmpl = <<<T
<tr class="%s">
    <td>%s</td>
    <td><a href="?user=%s">%s</a></td>
    <td><a href="http://svnweb.mageia.org/packages?view=revision&revision=%d" title="%s">%s</a></td>
    <td>%s</td>
    <td>%s/%s</td>
    <td class="status-box"></td>
T;

if ($total > 0) {
    foreach ($pkgs as $key => $p) {
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
           if (!is_dir("..$typelink")) {
              $typelink = '';
           }
        }
        $typestr = $p['type'];
        if ($p['status']['build']) {
            $typealt = 'Building on';
            foreach ($p['status']['build'] as $h)
                $typealt .= " $h";
            $typestr = "<span title='$typealt'>$typestr</a>";
        }

        $s .= '<td>';
        $s .= ($typelink != '') ?
            sprintf('<a href="%s">%s</a>', $typelink, $typestr) :
            $typestr;

        $s .= '</td><td>';
        if ($p['type'] == 'uploaded') {
            $tdiff = timediff($p['buildtime']['start'], $p['buildtime']['end']); // use $p['buildtime']['diff']; instead?
            $s .= $tdiff;
            $tdiff = floor(($p['buildtime']['end'] - $p['buildtime']['start']) / 60)*60;
            @$buildtime_stats[timediff(0, $tdiff)] += 1;
        }
        $s .= '</td>';
        $s .= '</tr>';
    }
    // Table
    echo '<table>',
        '<caption>', $total, ' packages submitted in the past ', $max_modified * 24, '&nbsp;hours.</caption>',
        '<tr><th>Submitted</th><th>User</th>
            <th>Package</th><th>Target</th><th>Media</th>
            <th colspan="2">Status</th><th>Build time</th></tr>',
        $s,
        '</table>';

    // Stats
    $s = '<div id="stats">';
    $score = round($stats['uploaded']/$total * 100);
    $s .= sprintf('<div id="score"><h3>Score: %d/100</h3>
        <div id="score-box"><div id="score-meter" style="height: %dpx;"></div></div></div>',
        $score, $score);

    $s .= '<table style="width: 100%"><caption>Stats.</caption><tr><th colspan="2">Status</th><th>Count</th><th>%</th></tr>';
    foreach ($stats as $k => $v) {
        $s .= sprintf('<tr class="%s"><td class="status-box"></td><td>%s</td><td>%d</td><td>%d%%</td></tr>',
            $k, $k, $v, round($v/$total*100));
    }

    $s .= '</table><br /><br />';

    $s .= '<table style="width: 100%"><caption>Packagers</caption><tr><th>User</th><th>Packages</th></tr>';
    arsort($users);
    foreach ($users as $k => $v)
        $s .= sprintf('<tr><td><a href="/?user=%s">%s</a></td><td>%d</td></tr>',
            $k, $k, $v);

    $s .= '</table><br /><br />';

    /**
    */
    function timesort($a, $b)
    {
        $a = explode(' ', trim($a));
        $b = explode(' ', trim($b));

        if ($a[1] == 'hour' || $a[1] == 'hours')
            $a[0] *= 3600;

        if ($b[1] == 'hour' || $b[1] == 'hours')
            $b[0] *= 3600;

        if ($a[0] > $b[0])
            return 1;
        elseif ($a[0] < $b[0])
            return -1;

        return 0;
    }
    uksort($buildtime_stats, "timesort");

    $bts = '';
    $max = max($buildtime_stats);
    foreach ($buildtime_stats as $time => $count) {
        $bts .= sprintf('<tr><td>%s</td><td><span style="width: %dpx; height: 10px; background: #aaa; display: block;" title="%d"></span></td></tr>',
            $time == "0 second" ? "< 1 minute" : $time,
            round($count/$max*100),
            $count);

        $tmp = explode(' ', $time);
    }

    $s .= '<table style="width: 100%;"><caption>Build time</caption>';

    $s .= sprintf('<tr><td>Total time</td><td>%s hours</td></tr>
        <tr><td>Average</td><td>%s minutes</td></tr>
        <tr><td>Builds count</td><td>%s</td></tr>',
        round($buildtime_total / 60, 2),
        $buildtime_avg,
        $buildtime_cnt);

    $s .= '<tr><th title="Build time">Duration</th><th title="Packages number">Pack. nb.</th></tr>';
    $s .= $bts;
    $s .= '</table><span style="font-size: 85%;">Does not take<br />build failures<br />into account.</span>';

    $s .= '<table><caption>Build times</caption>';
    $max = max($build_dates);
    foreach ($build_dates as $time => $count)
        $s .= sprintf('<tr><td>%d</td><td><span style="width: %dpx; height: 10px; background: #aaa; display: block;" title="%d"></span></td></tr>',
            $time,
            round($count / $max * 100),
            $count);
    $s .= '</table>';

    $s .= '</div>';

    echo $s;
}
else
{
    echo sprintf('<p>No package has been submitted in the past %d&nbsp;hours.</p>',
        $max_modified * 24);
}

?>
    <div class="clear"></div>
    <hr />
    <p>Generated at <?php echo $date_gen; ?>.
        Code for this page is in <a href="http://svnweb.mageia.org/soft/build_system/web/">http://svnweb.mageia.org/soft/build_system/web/</a>.</p>
</body>
</html>
