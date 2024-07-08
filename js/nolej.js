function checkNolejUpdates(documentUrl) {
    setInterval(() => {
        il.Util.sendAjaxGetRequestToUrl(
            documentUrl,
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
