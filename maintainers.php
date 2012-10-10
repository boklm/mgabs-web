<?php
/**
 * Mageia build-system quick status report script.
 * Show how maintainership is distributed among packagers.
 *
 * TODO(rda) show the capacity of all packagers?
 *
 * @copyright Copyright (C) 2012 Mageia.Org
 *
 * @author Romain d'Alverny
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU GPL v2
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License aspublished by the
 * Free Software Foundation; either version 2 of the License, or (at your
 * option) any later version.
*/

$data = file('data/maintdb.txt');

$maintainers = array();

foreach ($data as $line) {
    $line = explode(" ", $line);
    $p = trim($line[0]);
    $m = trim($line[1]);

    $maintainers[$m] += 1;
}
arsort($maintainers);

$half = 0;
$pkgCount = 0;

$stats = array(
    'packagers'    => count($maintainers) - 1,
    'packages'     => count($data),
    'unmaintained' => $maintainers['nobody']
);

foreach ($maintainers as $name => $count) {
    if ($name == 'nobody')
        continue;

    $pkgCount += $count;
    $pkgr     += 1;
    if ($pkgCount > $stats['packages'] * 0.50)
        break;
}

$stats['half_maintainers'] = $pkgr;

?>
<html>
<head>
    <style>
    body { font-family: Georgia; }
    .pkgrs { margin: 0; padding: 0; }
        li.pkgr { margin: 0 1em 0.8em 1em; list-style-position: inside; padding: 0.5em; width: 300px; }
            .uid { font-weight: bold; font-family: Verdana; }
            .pkg-count, .pkg-more { font-size: 90%; color: #888; display: block; }
            .pkgr-nobody { background: lightyellow; }
    .figure { font-weight: bold; font-size: 120%; }
    </style>
</head>
<body>
    
    <h1>Mageia packaging facts</h1>
    <ul>
        <li><span class="figure"><?php echo number_format($stats['packages'])?></span> packages
            <ul>
                <li><span class="figure"><?php echo $stats['unmaintained']?></span> have no maintainer</li>
            </ul></li>
        <li><span class="figure"><?php echo $stats['packagers']?></span> packagers
            <ul>
                <li><span class="figure"><?php echo $stats['half_maintainers']?></span> maintain more than 50% of all packages.</li>
                <li>they are: <?php
echo implode(
    ', ',
    array_map(
        function ($value, $key) {
            if ($key == 'nobody')
                return null;

            return $key . ' (' . $value . ')';
        },
        array_slice($maintainers, 0, $stats['half_maintainers']+1),
        array_slice(array_keys($maintainers), 0, $stats['half_maintainers']+1)
    )
); ?></li>
            </ul></li>
    </ul>
    <div id="maintainers"></div>
    <div id="chart_div" style="width: 900px; height: 500px;"></div>
    <?php

$rows = array();
foreach ($maintainers as $name => $count) {
    $rows[] = sprintf("['%s', 'Global', %d]", $name, $count);
}
$rows = implode(",\n", $rows);
?>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">

      // Load the Visualization API and the piechart package.
      //google.load('visualization', '1.0', {'packages':['corechart']});
      google.load("visualization", "1", {packages:["treemap"]});

      function drawChart3() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
            ['Location', 'Parent', 'Count'],
          ['Global', null, 0],
          <?php echo $rows;?>
        ]);

        // Create and draw the visualization.
        var tree = new google.visualization.TreeMap(document.getElementById('chart_div'));
        tree.draw(data, {
            /*
          minColor: '#f00',
          midColor: '#ddd',
          maxColor: '#0d0',
          */
          headerHeight: 15,
          fontColor: 'black',
          showScale: true});
        }
      // Set a callback to run when the Google Visualization API is loaded.
      google.setOnLoadCallback(drawChart3);

    </script>
</body>
</html>