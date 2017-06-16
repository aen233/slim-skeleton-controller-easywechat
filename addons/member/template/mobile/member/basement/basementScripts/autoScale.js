//等比缩放
!(function(doc, win) {
    var timer,
        docEle = doc.documentElement,
        evt = "onorientationchange" in window ? "orientationchange" : "resize",
        setFontSize = function() {
            var width = docEle.getBoundingClientRect().width;
            width && (docEle.style.fontSize = 20 * (width / 320) + "px");
        };

    win.addEventListener(evt, function() {
        clearTimeout(timer);
        timer = setTimeout(setFontSize, 1000);
    }, false);
    doc.addEventListener("DOMContentLoaded", setFontSize, false);
}(document, window));