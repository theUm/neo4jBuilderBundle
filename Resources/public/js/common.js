$('.message .close').on('click', function () {
    $(this).closest('.message').transition('fade');
});

$('.hamburger').on('click', function () {
    "use strict";
    $('.ui.sidebar').sidebar('toggle');
});


$('.ui.rating').rating();
$('.checkbox').checkbox();
$('.ui.accordion').accordion();
$('.ui.regular.dropdown').dropdown();
