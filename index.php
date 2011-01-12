<?php

/* Copyright (C) 2011 Oliver Blin                                         *\
/**************************************************************************\
* This program is free software; you can redistribute it and/or modify it  *
* under the terms of the GNU General Public License aspublished by the     *
* Free Software Foundation; either version 2 of the License, or (at your   *
* option) any later version.                                               *
\**************************************************************************/

error_reporting(E_ALL);

$upload_dir = "/home/schedbot/uploads";
$max_modified = 2;
$title = "Mageia build system";

chdir($upload_dir);
$all_files = shell_exec("find \( -name '*.rpm' -o -name '*.src.rpm.info' -o -name '*.youri' -o -name '*.lock' \) ! -ctime $max_modified");

preg_match_all("!^\./(\w+)/((\w+)/(\w+)/(\w+)/(\d+)\.(\w+)\.(\w+)\.(\d+))_?(.+)(\.src\.rpm(?:\.info)?|\.youri|\.lock)$!m", $all_files, $matches, PREG_SET_ORDER);

$pkgs = array();
foreach ($matches as $val) {
    $key = $val[6];
    if (!is_array($pkgs[$key])) {
        $pkgs[$key] = array();
        $pkgs[$key]["status"]  = array();
	$pkgs[$key]["path"]    = $val[2];
	$pkgs[$key]["version"] = $val[3];
        $pkgs[$key]["media"]   = $val[4];
        $pkgs[$key]["section"] = $val[5];
        $pkgs[$key]["user"]    = $val[7];
        $pkgs[$key]["host"]    = $val[8];
        $pkgs[$key]["job"]     = $val[9];
    }

    $status = $val[1];
    $pkgs[$key]["status"][$status] = 1;
    $data = $val[10];
    $ext = $val[11];
    if ($ext == ".src.rpm.info") {
        preg_match("!^(?:@\d+:)?(.*)!", $data, $name);
        $pkgs[$key]["package"] = $name[1];
    } else if ($ext == ".src") {
        $pkgs[$key]["status"]["src"] = 1;
    } else if ($ext == ".youri") {
        $pkgs[$key]["status"]["youri"] = 1;
    } else if ($ext == ".lock") {
        // parse build bot from $data
        $pkgs[$key]["status"]["build"] = 1;
    }
}
// sort by key in reverse order to have more recent pkgs first
krsort($pkgs);
?>
<html>

<head>
<title><? echo $title ?></title>
<style type="text/css">
td.todo {
  color: black;
}
td.building {
  color: fuchsia;
}
td.partial {
  color: purple;
}
td.built {
  color: blue;
}
td.youri {
  color: olive
}
td.uploaded {
  color: green;
}
td.failure, td.failure a, td.rejected, td.rejected a {
  color: red;
}
</style>
</head>

<body>
<h1><? echo $title ?></h1>

<table>
<?
function pkg_gettype($pkg) {
    if (array_key_exists("rejected", $pkg["status"]))
        return "rejected";
    if (array_key_exists("youri",    $pkg["status"])) {
        if (array_key_exists("src",    $pkg["status"]))
	    return "youri";
	else
	    return "uploaded";
    }
    if (array_key_exists("failure",  $pkg["status"]))
        return "failure";
    if (array_key_exists("done",     $pkg["status"]))
        return "partial";
    if (array_key_exists("build",    $pkg["status"]))
        return "building";
    if (array_key_exists("todo",     $pkg["status"]))
        return "todo";
    return "unknown";
}

foreach ($pkgs as $key => $p) {
    $p["type"] = pkg_gettype(&$p);
    echo "<tr>\n";
    echo "<td>" . $p["user"] . "</td>\n";
    echo "<td>" . $p["package"] . "</td>\n";
    echo "<td>" . $p["version"] . "</td>\n";
    echo "<td>" . $p["media"] . "/" . $p["section"] . "</td>\n";
    $typelink = "";
    if ($p["type"] == "failure") {
       $typelink = "/uploads/" . $p["type"] . "/" . $p["path"];
    } else if ($p["type"] == "rejected") {
       $typelink = "/uploads/" . $p["type"] . "/" . $p["path"] . ".youri";
    }
    echo "<td class='" . $p["type"] . "'>";
    if ($typelink)
        echo "<a href='$typelink'>";
    echo $p["type"];
    if ($typelink)
        echo "</a>";
    echo "</td>\n";;
    echo "</tr>\n";
}
?>
</table>

</body>

</html>
