<?php
$package=preg_replace('/[^[:alnum:]_.+-]/', '', $_GET['package']);
if (!$package) {
	die("Invalid package");
}
$db = new SQLite3('autobuild.db');
$package_id = $db->querySingle("SELECT Id FROM Packages WHERE Name = '$package'");
if (!$package_id)  {
        die("Invalid package");
}
echo "<h1>History of package \"$package\"</h1>\n";
$result = $db->query("SELECT date(datetime(Start, 'unixepoch')) as run, ResultValues.Name FROM Runs, Results, ResultValues WHERE Runs.Id = Results.Run AND Results.Result = ResultValues.Id AND Results.Package = '$package_id' ORDER BY Start DESC");
while ($entry = $result->fetchArray(SQLITE3_ASSOC)) {
    $run = $entry['run'];
    $build_result = $entry['Name'];
    echo "<a href='results.php?run=$run' >$run</a> ";
    $base_dir = "cauldron/x86_64/core/$run";
    if ($link = glob("$base_dir/$package-*.src.rpm/")) {
	    echo "<a href='$link[0]'>$build_result</a><br/>\n";
    } else {
	    echo "$build_result<br/>\n";
    }	    
}

?>
