<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title><?php echo strip_tags($title); ?></title>
    <meta name="robots" content="<?php echo $robots; ?>">
    <link rel="home" href="<?php echo $g_root_url; ?>">
    <link rel="author" href="http://www.mageia.org/">
    <link rel="icon" type="image/png" href="themes/mageia/favicon.png">
    <link rel="stylesheet" href="themes/mageia/style.css">
    <meta name="viewport" content="width=900,initial-scale=1,user-scalable=yes">
</head>
<body class="contribute">
<?php

$figures_list = array();

if (!isset($_GET['package'])) {

    // TODO should be cached.
    $missing_deps_count = preg_match_all("/<item>/m", file_get_contents("http://check.mageia.org/cauldron/dependencies.rss"), $matches);
    $unmaintained_count = file_exists(__DIR__ . '/data/unmaintained.txt') ? count(file(__DIR__ . '/data/unmaintained.txt')) : 0;

    if ($missing_deps_count > 0
        || $unmaintained_count > 0
    ) {
        if ($missing_deps_count > 0) {
            $figures_list[] = sprintf('<strong>%d</strong> <a rel="nofollow" href="%s">broken <abbr title="dependencies">deps.</abbr></a>',
                                $missing_deps_count,
                                'http://check.mageia.org/cauldron/dependencies.html'
            );
        }

        if ($unmaintained_count > 0) {
            $figures_list[] = sprintf('<strong>%d</strong> <a rel="nofollow" href="%s">unmaintained</a>',
                                $unmaintained_count,
                                'data/unmaintained.txt'
            );
        }

        if (count($figures_list) > 0)
            $figures_list[count($figures_list)-1] .= sprintf(' <a href="%s" class="action-btn" title="%s">%s</a>',
                                                        'https://wiki.mageia.org/en/Importing_packages',
                                                        'YES you can help!', 'pick one');
    }

    preg_match_all('/<span class="bz_result_count">(\d+)/', file_get_contents("https://bugs.mageia.org/buglist.cgi?quicksearch=%40qa-bugs+-kw%3Avali"), $matches);
    $qa_bugs = $matches[1][0];
    if ($qa_bugs > 0) {
        $figures_list[] = sprintf('<strong>%d</strong> <a rel="nofollow" href="%s">update%s to validate</a>
                                    <a href="%s" class="action-btn" title="%s">%s</a>',
                $qa_bugs,
                'http://mageia.madb.org/tools/updates',
                plural($qa_bugs),
                'https://wiki.mageia.org/en/QA_process_for_validating_updates',
                'YES you can help!', 'see how'
        );
    }

    $html_figures = null;
    if (count($figures_list) > 0) {
        $html_figures = 'Packages: ' . implode(', ', $figures_list) . '.';
    }

?>
    <header id="mgnavt">
        <h1><?php echo $title ?></h1>
        <ul>
            <li><a href="#stats">Stats</a></li>
            <li><?php echo $html_figures; ?></li>
        </ul>
    </header>
    <article>
<?php
}
?>
