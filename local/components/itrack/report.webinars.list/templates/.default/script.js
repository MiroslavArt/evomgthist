;(function () {
    BX.namespace('iTrack.Component.ReportWebinarsList');

    BX.iTrack.Component.ReportWebinarsList = {
        openList: function(webinarId, counter) {
            var url = webinarId + '/' + counter + '/';
            BX.SidePanel.Instance.open(location.pathname + url, {
                cacheable: false
            });
        }
    };
})();