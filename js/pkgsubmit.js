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
    });

    $("table#submitted-packages tbody").on("click", "tr td li a.view-inline", function (ev) {

        // only open text log files
        var ext = $(this).attr("href").split(".").pop();
        if (["log", "done", "youri"].indexOf(ext) < 0)
            return true;

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
                    .append($("<textarea />", {
                        id: elId,
                        class: "file-view",
                        html: "loading..."
                    }))
                );

                $.get(
                    "/" + $(this).attr("href"),
                    {},
                    function (data) {
                        $("#" + elId).html(data)
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
                    }
                );
            } else {
                c.toggle();
            }
        }
    });
});