/**
 * Allow toggling display of password fields on register/change pass.
 */
(function ($) {
    $.toggleShowPassword = function (options) {
        var settings = $.extend({
            field: "#password",
            control: "#toggle_show_password",
        }, options);

        var control = $(settings.control);
        var field = $(settings.field)

        control.bind('click', function () {
            nextIcon = control.next().children('i');
            if (control.is(':checked')) {
                nextIcon.removeClass('fa-eye');
                nextIcon.addClass('fa-eye-slash');
                field.attr('type', 'text');
            } else {
                nextIcon.removeClass('fa-eye-slash');
                nextIcon.addClass('fa-eye');
                field.attr('type', 'password');
            }
        })
    };
}(jQuery));


/**
 * Inline SVG so we can use CSS on it.
 */
$(document).ready(function() {
    $('img.inline-svg[src$=".svg"]').each(function() {
        var $img = jQuery(this);
        var imgURL = $img.attr('src');
        var attributes = $img.prop("attributes");

        $.get(imgURL, function(data) {
            // Get the SVG tag, ignore the rest
            var $svg = jQuery(data).find('svg');

            // Remove any invalid XML tags
            $svg = $svg.removeAttr('xmlns:a');

            // Loop through IMG attributes and apply on SVG
            $.each(attributes, function() {
                $svg.attr(this.name, this.value);
            });

            // Replace IMG with SVG
            $img.replaceWith($svg);
        }, 'xml');
    });
});

/**
 * Generic window resize event.
 */
var lastWindowWidth  = window.innerWidth,
    lastWindowHeight = window.innerHeight;

$(window).resize(function() {

    EventBus.dispatch('window:resize');

    if (lastWindowWidth != window.innerWidth) {
        EventBus.dispatch('window:resize:width');
        lastWindowWidth = window.innerWidth;
    }

    if (lastWindowHeight != window.innerHeight) {
        EventBus.dispatch('window:resize:height');
        lastWindowHeight = window.innerHeight;
    }

});

