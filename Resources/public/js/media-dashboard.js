$(document).ready(function () {

    // on click on any card bottom - sidebar with edit FieldValueNode form will appear
    $('.media-sidebar').click(function () {
        let dataUrl = $(this).data('url');
        let sidebar = $('.ui.sidebar');
        let dimmer = sidebar.find('.dimmer');
        sidebar.sidebar({
            'scrollLock': true,
            'closable': true,
            'onVisible': function () {
                dimmer.toggleClass('active', false);
                let ajaxFormContainer = sidebar.find('.form-content');
                ajaxFormContainer.api({
                    url: dataUrl,
                    on: 'now',
                    onComplete: function (response) {
                        ajaxFormContainer.html(response);
                        initAjaxForm(ajaxFormContainer);
                    },
                });
            }
        }).sidebar('toggle');

        sidebar.find('.cancel.label').click(function () {
            sidebar.sidebar('hide');
        });

    });

    /**
     * sends ajax form and replaces current form by that form from ajax
     * @param context
     */
    function initAjaxForm(context) {
        let relatedCardId = context.children('form').data('id');
        let relatedCard = $('#media_' + relatedCardId + '.ui.card');
        context.find('.menu .item').tab();
        window.initSemanticSearch(context);
        //bind js to form submit
        context.find("form").on('submit', function (e) {
            e.preventDefault();
            let dimmer = $(context.siblings('.ui.dimmer'));
            let loader = dimmer.find('.loader');
            dimmer.toggleClass('active', true);
            loader.toggleClass('disabled', false);
            // ajax form send
            $.ajax({
                type: $(this).attr('method'),
                url: $(this).attr('action'),
                data: new FormData(this),
                processData: false,
                contentType: false,
            }).done(function (data) {
                // update corresponding data & init new form
                context.html(data);
                let newImage = context.find('.bordered.image>img');
                relatedCard.find('.image img').attr('src', newImage.data('url'));
                relatedCard.find('.content.media-sidebar .header').html(newImage.data('name'));
                initAjaxForm(context);
                loader.toggleClass('disabled', true);
                dimmer.toggleClass('active', false);
            })
        });
        // delete button
        context.find('.detach-file').click(
            function (ev) {
                ev.preventDefault();
                let thisButton = $(this);
                $.ajax({
                    type: 'POST',
                    url: thisButton.attr('href'),
                }).done(function (data) {
                    if ((!!data.status) && (data.status === 'success')) {
                        relatedCard.remove();
                        // close sidebar & remove corresponded card
                        $('.ui.sidebar').sidebar('hide');
                    }
                });
            });
    }

    // field inits
    // $('body').find('.checkbox').checkbox();

    $('.media-sidebar:eq(1)').click();

});