<?php
/**
 * Mageia build-system quick status report script.
 *
 * @copyright Copyright (C) 2011 Olivier Blin
 *
 * @author Pascal Terjan
 * @author Romain d'Alverny
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

# Temporary until initial mirror is ready
chdir("data");
$nb_rpm = shell_exec('rpm -qp --qf "%{SOURCERPM}\n" /distrib/bootstrap/distrib/cauldron/i586/media/core/release/*.rpm | sort -u | tee src.txt | wc -l');
$nb_rpm_mga = shell_exec('grep mga src.txt | tee src.mga.txt | wc -l');
shell_exec('grep -v mga src.txt > src.mdv.txt');
#########################################

chdir($upload_dir);

$all_files = shell_exec("find \( -name '*.rpm' -o -name '*.src.rpm.info' -o -name '*.youri' -o -name '*.lock' -o -name '*.done' \) ! -ctime $max_modified -printf \"%p\t%T@\\n\"");
$re = "!^\./(\w+)/((\w+)/(\w+)/(\w+)/(\d+)\.(\w+)\.(\w+)\.(\d+))_?(.+)(\.src\.rpm(?:\.info)?|\.youri|\.lock|\.done)\s+(\d+\.\d+)$!m";
$r = preg_match_all($re,
    $all_files,
    $matches,
    PREG_SET_ORDER);

$pkgs = array();
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
    $pkgs[$key]['status'][$status] = 1;
    $ext = $val[11];
    if ($ext == '.src.rpm.info') {
        preg_match("!^(?:@\d+:)?(.*)!", $data, $name);
        $pkgs[$key]['package'] = $name[1];
    } else if ($ext == '.src') {
        $pkgs[$key]['status']['src'] = 1;
    } else if ($ext == '.youri') {
        $pkgs[$key]['status']['youri'] = 1;
    } else if ($ext == '.lock') {
        // parse build bot from $data
        $pkgs[$key]['status']['build'] = 1;
    } else if ($ext == '.done') {
        $pkgs[$key]['buildtime']['start'] = key2timestamp($val[6]);
        $pkgs[$key]['buildtime']['end'] = round($val[12]);
        $pkgs[$key]['buildtime']['diff'] = $pkgs[$key]['buildtime']['end'] - $pkgs[$key]['buildtime']['start'];
    }
}
// sort by key in reverse order to have more recent pkgs first
krsort($pkgs);

/**
 * @param array $pkg
 *
 * @return string
*/
function pkg_gettype($pkg) {
    if (array_key_exists("rejected", $pkg["status"]))
        return "rejected";
    if (array_key_exists("youri", $pkg["status"])) {
        if (array_key_exists("src", $pkg["status"]))
            return "youri";
        else
            return "uploaded";
    }
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo strip_tags($title); ?></title>
    <meta name="robots" content="<?php echo $robots; ?>">
    <style type="text/css">
    .clear { clear: both; }
    table { 
        border-spacing: 0;
        font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 80%;
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
    tr.youri { background: #aacc66; }

    td.status-box { width: 1em; height: 1em; }
    tr.uploaded td.status-box { background: green; }
    tr.failure td.status-box, tr.rejected td.status-box { background: red; }
    tr.todo td.status-box { background: white; }
    tr.building td.status-box { background: yellow; }
    tr.partial td.status-box { background: blue; }
    tr.built td.status-box { background: #00ccff; }
    tr.youri td.status-box { background: olive; }
    
    #stats { float: right; }
    #score { margin-bottom: 2em; font-family: Helvetica, Verdana, Arial, sans-serif; }
    #score-box { width: 100px; height: 100px; background: #faa; }
    #score-meter { width: 100px; background: #afa; }
    </style>
</head>
<body>
    <h1><?php echo $title ?></h1>

<?php
if (!is_null($g_user))
    echo '<a href="/">&laquo;&nbsp;Back to full list</a>';

# Temporary until initial mirror is ready
echo sprintf(
    '<p><a href="%s">%d src.rpm</a> rebuilt for Mageia out of <a href="%s">%d</a>
    (<a href="%s">list of Mandriva packages still present</a>).</p>',

    'data/src.mga.txt', $nb_rpm_mga,
    'data/src.txt', $nb_rpm,
    'data/src.mdv.txt'
);

#########################################

$s = '';
$tmpl = <<<T
<tr class="%s">
    <td>%s</td>
    <td><a href="?user=%s">%s</a></td>
    <td>%s</td>
    <td>%s</td>
    <td>%s/%s</td>
    <td class="status-box"></td>
T;

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

// feedback labels
$badges = array(
    'uploaded' => 'Congrats %s! \o/',
    'failure'  => 'Booooo! /o\\',
    'todo'     => '',
    'building' => '',
    'partial'  => '',
    'built'    => ''
);

if ($total > 0) {
    foreach ($pkgs as $key => $p) {
        $p['type'] = pkg_gettype($p);

        $stats[$p['type']] += 1;

        if (!array_key_exists($p['user'], $users))
            $users[$p['user']] = 1;
        else
            $users[$p['user']] += 1;

        $s .= sprintf($tmpl,
            $p['type'],
            timediff(key2timestamp($key)) . ' ago',
            $p['user'], $p['user'],
            $p['package'],
            $p['version'],
            $p['media'], $p['section']
        );
    
        $typelink = '';
        if ($p['type'] == 'failure') {
           $typelink = '/uploads/' . $p['type'] . '/' . $p['path'];
        } elseif ($p['type'] == 'rejected') {
           $typelink = '/uploads/' . $p['type'] . '/' . $p['path'] . '.youri';
        }

        $s .= '<td>';
        $s .= ($typelink != '') ?
            sprintf('<a href="%s">%s</a>', $typelink, $p['type']) :
            $p['type'];

        $s .= '</td><td>';
        if ($p['type'] == 'uploaded')
            $s .= timediff($p['buildtime']['start'], $p['buildtime']['end']);
        $s .= '</td>';
        //$s .= '<td>' . sprintf($badges[$p['type']], $p['user']) . '</td>';
        $s .= '</tr>';
    }
    // Table
    echo '<table>',
        '<caption>Packages submitted in the past ', $max_modified * 24, '&nbsp;hours.</caption>',
        '<tr><th>Submitted</th><th>User</th><th>Package</th><th>Target</th><th>Media</th><th colspan="2">Status</th></tr>',
        $s,
        '</table>';

    // Stats
    $s = '<div id="stats">';
    $score = round($stats['uploaded']/$total * 100);
    $s .= sprintf('<div id="score"><h3>Score: %d/100</h3>
        <div id="score-box"><div id="score-meter" style="height: %dpx;"></div></div></div>',
        $score, $score);

    $s .= '<table><caption>Stats.</caption><tr><th colspan="2">Status</th><th>Count</th><th>%</th></tr>';
    foreach ($stats as $k => $v) {
        $s .= sprintf('<tr class="%s"><td class="status-box"></td><td>%s</td><td>%d</td><td>%d%%</td></tr>',
            $k, $k, $v, round($v/$total*100));
    }

    $s .= '</table><br />';

    $s .= '<table><caption>Packagers</caption><tr><th>User</th><th>Packages</th></tr>';
    foreach ($users as $k => $v)
        $s .= sprintf('<tr><td><a href="/?user=%s">%s</a></td><td>%d</td></tr>',
            $k, $k, $v);

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
    <p>Generated at <?php echo $date_gen; ?>.</p>
</body>
</html>
