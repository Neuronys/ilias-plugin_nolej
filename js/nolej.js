/**
 * Check for updates.
 * @param {string} url
 */
function xnlj_check_updates(url) {
    setInterval(() => {
        il.Util.sendAjaxGetRequestToUrl(
            url,
            {},
            {},
            function (o) {
                if (o.responseText !== undefined && o.responseText == "reload") {
                    // location.reload(); // This may cause re-submit warning
                    window.location = window.location.href;
                }
            }
        );
    }, 2000);
}
