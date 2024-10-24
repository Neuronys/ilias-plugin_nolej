/**
 * Check for updates.
 * @param {string} endpoint
 * @param {string} redirectTo
 * @param {number} interval seconds
 */
function xnlj_check_updates(endpoint, redirectTo, interval = 10) {
    // Minimum 1 second.
    if (interval < 1) {
        interval = 1;
    }

    setInterval(() => {
        il.Util.sendAjaxGetRequestToUrl(
            endpoint,
            {},
            {},
            function (o) {
                if (o.responseText !== undefined && o.responseText == "update") {
                    window.location = redirectTo;
                }
            }
        );
    }, interval * 1000);
}
