function isLogFile(path) {

    var ext = path.split(".").pop();
    if (["log", "done", "youri"].indexOf(ext) < 0) {
        return true;
    }

    return false;
}

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
    return text.replace(/.*(ok|succe|test|warn|info|deprecat|error|fail).*/gi, function (match, p1, p2, offset, string) {
        console.log([match, p1, offset, string]);
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
            cl = 'warn';
            break;

        case 'error':
        case 'fail':
            cl = 'error';
            break;
        }
        return '<span class="hl hl-' + cl + '">' + match + '</span>';
    });
}

$(function () {

    $('.status-link').on("click", function (ev) {
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
                        html: '<td colspan="5">loading</td>'
                    }
                ));
                $.get(
                    "/log_files.php",
                    {"k": $(this).attr("href")},
                    function (data) {
                        $("#" + elId).html('<td colspan="5">' + data + '</td>');
                    }
                );
            } else {
                el.toggle();
            }
        }
    });

    $("table#submitted-packages tbody").on("click", "tr td li a.view-inline", function (ev) {

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
                $(this).after($("<div />", {
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
                        )
                        .animate({ scrollTop: $("#" + elId).prop("scrollHeight") }, 1000);
                    }
                );
            } else {
                c.toggle();
            }
        }
    });
});