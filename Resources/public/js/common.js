$('.message .close').on('click', function () {
    $(this).closest('.message').transition('fade');
});

$('.hamburger').on('click', function () {
    "use strict";
    $('.ui.sidebar').sidebar('toggle');
});


$('.ui.rating').rating();
$('.checkbox').checkbox();
$('.ui.accordion').not('.ui.accordion.field').accordion();
$('.ui.regular.dropdown').dropdown();
