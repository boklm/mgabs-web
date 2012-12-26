<html>
<head>
<?php

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
		if (preg_match("/(.*)-([^-]*-[^-]*mga)[1-9].src.rpm/", $entry, $matches)) {
			$packages[$matches[1]] = $matches[2];
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
			$rpm = $matches[1];
			$status = $matches[2];
			if ($status != "ok" && $status != "unknown" && $status != "not_on_this_arch") {
				$prev_failure[$rpm] = 1;
			}
		}
	}
	fclose($status_file);
}

$success = Array();
$failure = Array();
$fixed = Array();
$removed = Array();

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
			array_push($failure, $rpm);
			preg_match("/(.*)-([^-]*-[^-]*mga)[1-9].src.rpm/", $rpm, $matches);
			if(!$packages[$matches[1]]) {
				$removed[$rpm] = 1;
			} elseif ($packages[$matches[1]] != $matches[2]) {
				$fixed[$rpm] = 1;
			}
		}
	}
}
fclose($status_file);

sort($success);
sort($failure);

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
foreach ($runs as $r) {
	$text = $r . (($r > $latest) ? ' (in progress)' : '');
	
	if ($r==$run) {
		echo $text;
	} else {
		echo '<a href="'.$_SERVER["PHP_SELF"].'?run='.$r.'">'.$text.'</a>';
	}
	echo ' ';
}

echo "</div>\n";
echo "<h1>$succes_percent% Success</h1>\n";
echo "$nb_fixed packages have been fixed since this run and $nb_removed have been removed.<br/> If no new package was broken, success rate next time should be $estimated_percent%.<br/>\n";
echo "<div style='float:left'><h1>Failed builds ($nb_failed/$nb_tried):</h1><ul>";

foreach ($failure as $rpm) {
	$status = "";
	if ($fixed[$rpm]) {
		$status = " <span style='color:green;'><b>Fixed!</b></span>";
	} elseif ($removed[$rpm]) {
		$status = " <span style='color:yellow;'><b>Removed</b></span>";
	} elseif ($prev && !$prev_failure[$rpm]) {
		$status = " <span style='color:red;'><b>New!</b></span>";
	}
	if (file_exists("$base_dir/$rpm/")) {
		echo "<li><a href='$base_dir/$rpm/'>$rpm</a>$status</li>\n";
	} else {
		echo "<li>$rpm $status</li>\n";
	}
}

echo "</ul></div><div style='float:right'><h1>Successful builds ($nb_success/$nb_tried):</h1><ul>";

foreach ($success as $rpm) {
	echo "<li><a href='$base_dir/$rpm/'>$rpm</a></li>\n";
}

?>
</ul></div>
</body>
</html>
