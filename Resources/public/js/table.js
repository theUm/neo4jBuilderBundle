$(document).ready(function () {

    window.selectedIds = [];

    $('.ui.dropdown').dropdown();

    $('#markAll').click(function () {
        $('.grid-like.table .checkbox.item').checkbox($(this).parent().checkbox('is checked') ? 'check' : 'uncheck');
    });

    $('#filter_comments').click(function () {
        let dropdownValue = $('.filter_comments').dropdown('get value');
        if (dropdownValue.length > 0) {
            window.location.href = dropdownValue;
        }
    });


    $('#mass_edit_comments').click(function () {
        let actionUrl = $('.mass_edit_comments').dropdown('get value');
        if (actionUrl.length > 0 && window.selectedIds.length > 0) {
            console.log(actionUrl);
            actionUrl += '?ids=' + window.selectedIds.join(',');

            $.ajax({
                type: 'POST',
                url: actionUrl
            }).done(function (data) {
                let messageEl = $('#comments_update_result');
                let isSuccess = (data.status === 'success');
                messageEl.toggleClass('negative', !isSuccess);
                messageEl.toggleClass('positive', isSuccess);
                messageEl.text(data.message);
                messageEl.toggleClass('hidden', false);

                //update changed table satuses
                if (isSuccess) {
                    data.payload.updatedIds.forEach(function (el) {
                        $('.comm-' + el).text(data.payload.status);
                    });
                }
            });
        }
    });

    $('#comments_update_result').click(function () {
        $(this).toggleClass('hidden', true);
    });

    $('.data.checkbox').checkbox({
        onChecked: function () {
            let commentId = $(this).attr('name');
            if (window.selectedIds.indexOf(commentId) === -1) {
                window.selectedIds.push(commentId);
            }
            console.log(window.selectedIds);
        },
        onUnchecked: function () {
            let commentId = $(this).attr('name');
            let index = window.selectedIds.indexOf(commentId);
            if (index !== -1) {
                window.selectedIds.splice(index, 1);
            }
            console.log(window.selectedIds);
        },
    });

});