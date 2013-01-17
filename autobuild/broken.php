<?php
header("Content-type: text/plain");

$srpm_dir = '/distrib/bootstrap/distrib/cauldron/SRPMS/core/release/';

$run = readlink("cauldron/x86_64/core/latest");
$packages = Array();
if ($handle = opendir($srpm_dir)) {
	while (false !== ($entry = readdir($handle))) {
		if (preg_match("/(.*)-([^-]*-[^-]*mga)[1-9].src.rpm/", $entry, $matches)) {
			$packages[$matches[1]] = $entry;
		}
	}
	closedir($handle);
}

$failure = Array();

$base_dir = "cauldron/x86_64/core/$run";

$status_name = "$base_dir/status.core.log";

$status_file = fopen($status_name, "r");
while (!feof($status_file)) {
	$line = fgets($status_file);
	if (preg_match("/^(.*): (.*)$/", $line, $matches)) {
		$rpm = $matches[1];
		$status = $matches[2];
		if ($status != "ok" && $status != "unknown" && $status != "not_on_this_arch"){
			preg_match("/(.*)-([^-]*-[^-]*mga)[1-9].src.rpm/", $rpm, $matches);
			$package = $matches[1];
			$version = $matches[2];
			if($packages[$package]) {
				$build_stat = stat("$base_dir/$rpm");
				$pkg_stat = stat($srpm_dir.$packages[$package]);
				if ($pkg_stat['mtime'] <= $build_stat['mtime']) {
					$failure[$rpm] = $status;
				}
			}
		}
	}
}
fclose($status_file);

ksort($failure);

foreach ($failure as $rpm => $error) {
	echo "$rpm: $error\n";
}
?>
