/**
 * Mageia build-system quick status report script.
 * Javascript utilities.
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

/**
 * Is the file in path expected to be in text/plain or not?
 * We just know what .log, .done and .youri files are.
 *
 * @param string path
 *
 * @return boolean
*/
function isLogFile(path) {

    var ext = path.split(".").pop();
    if (["log", "done", "youri"].indexOf(ext) < 0) {
        return true;
    }

    return false;
}

/**
 * Is the file in path expected to be a short one (1, 2 lines at most)
 * or not?
 *
 * We just know that status.log and whatever.done files are one-liners.
 *
 * @param string path
 *
 * @return boolean
*/
function isShortFile(path) {

    var ext  = path.split(".").pop();
    var file = path.split("/").pop();

    if (["done"].indexOf(ext) >= 0
        || ["status.log"].indexOf(file) >= 0) {
        return true;
    }

    return false;
}

/**
 * Inject <span /> elements with appropriate classes into given text
 * to allow for highlighting specific portions of a text file.
 *
 * Here, log files with ok|success|test|warning|info|error|fail|etc.
 *
 * @param string text
 *
 * @return string
*/
function highlight_text(text) {
    return text.replace(/.*(ok|succe|test|warn|info|deprecat|error|fail|non\-standard|abort).*/gi, function (match, p1, p2, offset, string) {
        var cl = 'none';
        switch (p1.toLowerCase()) {
        case 'succe':
        case 'ok':
            cl = 'ok';
            break

        case 'test':
        case 'info':
            cl = 'info';
            break;

        case 'warn':
        case 'deprecat':
        case 'non-standard':
            cl = 'warn';
            break;

        case 'error':
        case 'fail':
        case 'abort':
            cl = 'error';
            break;
        }
        return '<span class="hl hl-' + cl + '">' + match + '</span>';
    });
}

/**
*/
function build_log_files_list(ev) {
    if (!ev.metaKey) {
        ev.preventDefault();

        var key  = $(this).attr("href");
        var elId = 'e' + key.replace(/\/|\./g, '-');
        var el   = $("#" + elId);

        if (el.length == 0) {
            $(this).parent().parent().after($("<tr />",
                {
                    class: "build-files-list",
                    id: elId,
                    html: '<td colspan="4">loading</td>'
                }
            ));
            $.get(
                "/log_files.php",
                {"k": $(this).attr("href")},
                function (data) {
                    $("#" + elId).html('<td colspan="4">' + data + '</td>');
                }
            );
        } else {
            el.toggle();
        }
    }
}

/**
*/
function show_log_file(ev) {

    if (isLogFile($(this).attr("href"))) {
        return true;
    }

    if (!ev.metaKey) {
        ev.preventDefault();

        var elId = 'view-' + $(this).attr("href").replace(/\/|\./g, '-');
        var cId  = elId + '-container';
        var c    = $("#" + cId);
        var el   = $("#" + elId);

        if (c.length == 0) {
            $(this).next().after($("<div />", {
                    id: cId
                })
                .addClass(isShortFile($(this).attr("href")) ? "short" : "")
                .append($("<div />", {
                    id: elId,
                    class: "file-view",
                    html: "loading..."
                }))
            );

            $.get(
                "/" + $(this).attr("href"),
                {},
                function (data) {
                    $("#" + elId).html(highlight_text(data))
                    .before(
                        $("<div />", {
                            class: "controls"
                        })
                        .append($("<button />", {
                                class: "gototop",
                                html: "top"
                            }).on("click", function (ev) {
                                $("#" + elId).animate({ scrollTop: 0 }, 200);
                            })
                        )
                        .append($("<button />", {
                                class: "gotobo",
                                html: "bottom"
                            }).on("click", function (ev) {
                                var d = $("#" + elId);
                                d.animate({ scrollTop: d.prop("scrollHeight") }, 200);
                            })
                        )
                        .append($("<button />", {
                                class: "close",
                                html: "close"
                            }).on("click", function (ev) {
                                $("#" + cId).toggle();
                            })
                        )
                    )
                    .animate({ scrollTop: $("#" + elId).prop("scrollHeight") }, 1000);
                }
            );
        } else {
            c.toggle();
        }
    }
}

$(function () {

    $('.status-link').on("click", build_log_files_list);

    $("table#submitted-packages tbody").on("click", "tr td li a.view-inline", show_log_file);
});