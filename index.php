<?php

/* Copyright (C) 2011 Oliver Blin                                         *\
/**************************************************************************\
* This program is free software; you can redistribute it and/or modify it  *
* under the terms of the GNU General Public License aspublished by the     *
* Free Software Foundation; either version 2 of the License, or (at your   *
* option) any later version.                                               *
\**************************************************************************/

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

# Temporary until initial mirror is ready
chdir("data");
$nb_rpm = shell_exec('rpm -qp --qf "%{SOURCERPM}\n" /distrib/bootstrap/distrib/cauldron/i586/media/core/release/*.rpm | sort -u | tee src.txt | wc -l');
$nb_rpm_mga = shell_exec('grep mga src.txt | tee src.mga.txt | wc -l');
shell_exec('grep -v mga src.txt > src.mdv.txt');
#########################################

chdir($upload_dir);

$all_files = shell_exec("find \( -name '*.rpm' -o -name '*.src.rpm.info' -o -name '*.youri' -o -name '*.lock' -o -name '*.done' \) ! -ctime $max_modified");

preg_match_all("!^\./(\w+)/((\w+)/(\w+)/(\w+)/(\d+)\.(\w+)\.(\w+)\.(\d+))_?(.+)(\.src\.rpm(?:\.info)?|\.youri|\.lock|\.done)$!m", $all_files, $matches, PREG_SET_ORDER);

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
 * @param string $key
 *
 * @return string
*/
function key2date($key) {
    global $tz;    
    $date = DateTime::createFromFormat("YmdHis", $key+0, $tz);
    $diff = time() - $date->getTimestamp();
    if ($diff<60)
       return $diff . " second" . plural($diff) . " ago";
    $diff = round($diff/60);
    if ($diff<60)
       return $diff . " minute" . plural($diff) . " ago";
    $diff = round($diff/60);
    if ($diff<24)
       return $diff . " hour" . plural($diff) . " ago";
    $diff = round($diff/24);

    return $diff . " day" . plural($diff) . " ago";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $title ?></title>
    <meta name="robots" content="<?php echo $robots; ?>">
    <style type="text/css">
    table { 
        border-spacing: 0;
        font-family: Helvetica; font-size: 80%;
        border: 1px solid #ccc;
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
    </style>
</head>
<body>
    <h1><?php echo $title ?></h1>

<?php

# Temporary until initial mirror is ready
echo sprintf(
    '<p><a href="%s">%d src.rpm</a> rebuilt for Mageia out of <a href="%s">%d</a>
    (<a href="%s">list of Mandriva packages still present</a>).</p>',

    'data/src.mga.txt', $nb_rpm_mga,
    'data/src.txt', $nb_rpm,
    'data/src.mdv.txt'
);

#########################################
echo '<table>',
    '<caption>Packages submitted in the past ', $max_modified * 24, '&nbsp;hours.</caption>',
    '<tr><th>Submitted</th><th>User</th><th>Package</th><th>Target</th><th>Media</th><th colspan="2">Status</th></tr>';

$s = '';
$tmpl = <<<T
<tr class="%s">
    <td>%s</td>
    <td><a href="?user=%s">%s</a></td>
    <td>%s</td>
    <td>%s</td>
    <td>%s/%s</td>
    <td class="status-box"></td>
    <td>%s</td>
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

// feedback labels
$badges = array(
    'uploaded' => 'Congrats %s! \o/',
    'failure'  => 'Booooo! /o\\',
    'todo'     => '',
    'building' => '',
    'partial'  => '',
    'built'    => ''
);

foreach ($pkgs as $key => $p) {
    $p['type'] = pkg_gettype($p);

    $stats[$p['type']] += 1;
    $s .= sprintf($tmpl,
        $p['type'],
        key2date($key),
        $p['user'], $p['user'],
        $p['package'],
        $p['version'],
        $p['media'], $p['section'],
        sprintf($badges[$p['type']], $p['user'])
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

    $s .= '</td></tr>';
}
echo $s, '</table>';

$s = '<div id="stats"><table><tr><th>Status</th><th>Count</th><th>%</th></tr>';
foreach ($stats as $k => $v) {
    $s .= sprintf('<tr><th>%s</th><td>%d</td><td>%d%%</td></tr>',
        $k, $v, round($v/$total*100));
}
$s .= '</table></div>';

echo $s;

?>
</body>
</html>
