/*
 * For Filelist Plugin
 *
 * @author joachimmueller
 */

/*
 * run on document load, setup everything we need
 */
jQuery(function () {
    "use strict";

    // CONFIG

    // these 2 variable determine popup's distance from the cursor
    // you might want to adjust to get the right result
    var xOffset = 10;
    var yOffset = 30;

    // END CONFIG

    jQuery("img.filelist_preview").hover(function (e) {
        this.t = this.title;
        this.title = "";

        var c;
        if (this.t !== "") {
            c = "<br/>" + this.t;
        } else {
            c = "";
        }
        jQuery("body").append("<p id='__filelist_preview'><img src='" + this.src + "' alt='Image preview' />" + c + "</p>");
        jQuery("#__filelist_preview")
            .css("top", (e.pageY - xOffset) + "px")
            .css("left", (e.pageX + yOffset) + "px")
            .css("max-width", "300px")
            .css("max-height", "300px")
            .css("position", "absolute")
            .fadeIn("fast");
    }, function () {
        this.title = this.t;
        jQuery("#__filelist_preview").remove();
    });
    jQuery("img.filelist_preview").mousemove(function (e) {
        jQuery("#__filelist_preview")
            .css("top", (e.pageY - xOffset) + "px")
            .css("left", (e.pageX + yOffset) + "px")
            .css("position", "absolute");
    });
});
