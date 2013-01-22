<html>
<head>
<?php

function parse_package($rpm) {
	if (preg_match("/(.*)-([^-]*-[^-]*mga)[1-9].src.rpm/", $rpm, $matches)) {
	    return Array('package' => $matches[1], 'version' => $matches[2]);
	} else {
	    return false;
	}
}

$runs = Array();
$handle = opendir('cauldron/x86_64/core/');
while (false !== ($entry = readdir($handle))) {
	if (preg_match("/^....-..-..$/", $entry, $matches)) {
		array_push($runs, $matches[0]);
	}
}
closedir($handle);
sort($runs);

$latest = readlink("cauldron/x86_64/core/latest");
$run = $_GET['run'];
if (!$run) {
	$run = $latest;
}

foreach ($runs as $r) {
	if ($r==$run) {
		break;
	}
	$prev = $r;
}

$packages = Array();
if ($handle = opendir('/distrib/bootstrap/distrib/cauldron/SRPMS/core/release/')) {
	while (false !== ($entry = readdir($handle))) {
		if ($parsed = parse_package($entry)) {
			$packages[$parsed['package']] = $entry;
		}
	}
	closedir($handle);
}

$prev_failure = Array();
if ($prev) {
	$base_dir = "cauldron/x86_64/core/$prev";
	$status_name = "$base_dir/status.core.log";
	$status_file = fopen($status_name, "r");
	while (!feof($status_file)) {
		$line = fgets($status_file);
		if (preg_match("/^(.*): (.*)$/", $line, $matches)) {
			$rpm = parse_package($matches[1]);
			$status = $matches[2];
			if ($status != "ok" && $status != "unknown" && $status != "not_on_this_arch") {
				$prev_failure[$rpm['package']] = 1;
			}
		}
	}
	fclose($status_file);
}

$success = Array();
$failure = Array();
$fixed = Array();
$removed = Array();
$broken = Array();

$base_dir = "cauldron/x86_64/core/$run";


$status_name = "$base_dir/status.core.log";
if (!file_exists($status_name)) {
	echo "Invalid run";
	exit;
}

$status_file = fopen($status_name, "r");
while (!feof($status_file)) {
	$line = fgets($status_file);
	if (preg_match("/^(.*): (.*)$/", $line, $matches)) {
		$rpm = $matches[1];
		$status = $matches[2];
		if ($status == "ok") {
			array_push($success, $rpm);
		} elseif ($status != "unknown" && $status != "not_on_this_arch"){
			$failure[$rpm] = $status;
			$parsed = parse_package($rpm);
			$package = $parsed['package'];
			if(!$prev_failure[$package]) {
				$broken[$rpm] = 1;
			}
			if(!$packages[$package]) {
				$removed[$rpm] = 1;
			} else {
				$build_stat = stat("$base_dir/$rpm");
				$pkg_stat = stat('/distrib/bootstrap/distrib/cauldron/SRPMS/core/release/'.$packages[$package]);
				if ($pkg_stat['mtime'] > $build_stat['mtime']) {
					$fixed[$rpm] = 1;
				}
			}
		}
	}
}
fclose($status_file);

sort($success);
ksort($failure);

$nb_failed = count($failure);
$nb_success = count($success);
$nb_fixed = count($fixed);
$nb_removed = count($removed);
$nb_tried = $nb_failed + $nb_success;
$succes_percent = round($nb_success*1000/$nb_tried)/10;
$estimated_percent = round(($nb_success+$nb_fixed)*1000/($nb_tried-$nb_removed))/10;

echo "<title>$succes_percent% Success</title>\n";
echo "</head><body>\n";

echo "<div style='position:absolute;right:0;top:0;'>";
echo "<form><select name='run' onChange='document.location.href=\"".$_SERVER["PHP_SELF"]."?run=\"+this.form.run.value'>";
foreach ($runs as $r) {
	$in_progress = ($r > $latest) ? ' (in progress)' : '';
	$selected = ($r == $run) ? ' selected' : '';
	echo "<option value='$r'$selected>$r$in_progress</option>";
}
echo "</select></form></div>\n";
echo "<h1>$succes_percent% Success</h1>\n";
echo "$nb_fixed packages have been fixed since this run and $nb_removed have been removed.<br/> If no new package was broken, success rate next time should be $estimated_percent%.<br/>\n";
echo "<div style='float:left'><h1>Failed builds ($nb_failed/$nb_tried):</h1><ul style='list-style:none;'>";

foreach ($failure as $rpm => $error) {
	$parsed = parse_package($rpm);
	$history_link = '<a href="history.php?package='.$parsed['package'].'">[h]</a>';
	$status_html = "";
	if ($fixed[$rpm]) {
		$status_html = " <img src='icons/state-fixed.png' title='Fixed!' />";
	} elseif ($removed[$rpm]) {
		$status_html = " <img src='icons/state-removed.png' title='Removed' />";
	} elseif ($broken[$rpm]) {
		$status_html = " <img src='icons/state-new.png' title='New!' />";
	}
	$error_html = $error;
	if (file_exists("icons/error-$error.png")) {
		$error_html = "<img src='icons/error-$error.png' title='$error'/>";
	}
	if (file_exists("$base_dir/$rpm/")) {
		echo "<li>$error_html <a href='$base_dir/$rpm/'>$rpm</a> $status_html $history_link</li>\n";
	} else {
		echo "<li>$error_html $rpm $status_html $history_link</li>\n";
	}
}

echo "</ul></div><div style='float:right'><h1>Successful builds ($nb_success/$nb_tried):</h1><ul>";

foreach ($success as $rpm) {
	$parsed = parse_package($rpm);
	$history_link = '<a href="history.php?package='.$parsed['package'].'">[h]</a>';
	if (file_exists("$base_dir/$rpm/")) {
		echo "<li><a href='$base_dir/$rpm/'>$rpm</a> $history_link</li>\n";
	} else {
		echo "<li>$rpm $history_link</li>\n";
	}
}

?>
</ul></div>
</body>
</html>
