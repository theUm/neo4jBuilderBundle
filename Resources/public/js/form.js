$(document).ready(function () {
    function wrapContext(context) {
        if (!context) {
            context = 'body';
        }
        return $(context);
    }

    function initCalendars(context) {
        context = wrapContext(context);
        const defaultDate = function (date) {
            if (!date) return '';
            return ('0' + date.getDate()).slice(-2) + '.'
                + ('0' + (date.getMonth() + 1)).slice(-2) + '.'
                + date.getFullYear();
        };
        const defaultTime = function (time) {
            if (!time) return '';
            return ('0' + time.getHours()).slice(-2) + ':'
                + ('0' + (time.getMinutes())).slice(-2);
        };
        let dateCalendar = context.find('.ui.date.calendar');
        if (dateCalendar.length) {
            dateCalendar.calendar({
                type: 'date',
                monthFirst: false,
                formatter: {
                    date: defaultDate
                }
            });
        }
        let timeCalendar = context.find('.ui.time.calendar');
        if (timeCalendar.length) {
            timeCalendar.calendar({
                ampm: false,
                type: 'time',
                formatter: {
                    time: defaultTime
                }
            });
        }
        let datetimeCalendar = context.find('.ui.datetime.calendar');
        if (datetimeCalendar.length) {
            datetimeCalendar.calendar({
                ampm: false,
                monthFirst: false,
                formatter: {
                    date: defaultDate,
                    time: defaultTime
                }
            });
        }
    }

    if ($('.ui.calendar').length) {
        initCalendars();
    }

    let initDetachFileFields = function (context) {
        context = wrapContext(context);
        context.find('.detach-file').click(
            function (e) {
                e.preventDefault();
                let galleryButton = $(this);
                $.ajax({
                    type: 'POST',
                    url: galleryButton.attr('href'),
                }).done(function (data) {
                    if ((!!data.status) && (data.status === 'success')) {
                        let gallerybuttonParent = galleryButton.parent();
                        gallerybuttonParent.siblings().toggleClass('hidden');
                        gallerybuttonParent.siblings('.buttons').remove();
                        gallerybuttonParent.remove();
                    }
                });
            });
    };
    initDetachFileFields();

    let initFilePickerButton = function (context) {
        context = wrapContext(context);
        let modalContainer = $('.filepicker.modal');
        let selectedFieldValueIds = [];
        let modalContent = modalContainer.find('.content-data');
        let galleryContentDimmer = modalContainer.find('.dimmer');
        context.find('.filepicker.button').click(function () {
            let galleryButton = $(this);
            const isMultiple = galleryButton.data('multiple');
            const linkfieldValueUrl = galleryButton.data('link');

            let loadAllButton = modalContainer.find('.button.load-all');
            let loadTypeButton = modalContainer.find('.button.load-type');
            let saveSelectedButton = modalContainer.find('.button.save');

            modalContainer.modal({
                observeChanges: true,
                onHidden: function () {
                    modalContainer.find('.button').unbind('click');
                    modalContent.html('');
                    modalContent.siblings('.pager').remove();
                }
            });
            modalContainer.modal('show');

            let ajaxMediaLoading = function (url) {
                galleryContentDimmer.dimmer('show');
                modalContent.empty();
                $.ajax({
                    url: url,
                }).done(function (data) {
                    let galleryElements = $.parseHTML(data);
                    if (galleryElements.length > 0) {
                        let pager = galleryElements.pop();
                        modalContent.html(galleryElements);
                        modalContent.siblings('.pager').remove();
                        modalContent.parent().append(pager);

                    }
                }).then(function () {
                    modalContainer.modal('refresh');
                    galleryContentDimmer.dimmer('hide');
                    modalContainer.find('.ui.pagination.menu a.item').click(function (e) {
                        e.preventDefault();
                        let url = this.href;
                        ajaxMediaLoading(url);
                    });
                    modalContent.find('.ui.card').click(function () {
                        if (isMultiple === 1) {
                            let id = $(this).find('.content.media-sidebar').data('id');
                            if (!selectedFieldValueIds.indexOf(id) >= 0) {
                                selectedFieldValueIds.push(id);
                            }
                        } else {
                            selectedFieldValueIds = [$(this).find('.content.media-sidebar').data('id')];
                            modalContent.find('.ui.card').toggleClass('active', false);
                        }
                        this.classList.toggle('active');
                    });
                });
            };

            loadAllButton.click(function () {
                loadTypeButton.toggleClass('disabled', false);
                this.classList.toggle('disabled');
                ajaxMediaLoading(loadAllButton.data('url'))
            });
            loadTypeButton.click(function () {
                loadAllButton.toggleClass('disabled', false);
                this.classList.toggle('disabled');
                ajaxMediaLoading(galleryButton.data('url'))
            });

            saveSelectedButton.click(function () {
                if (selectedFieldValueIds.length > 0) {
                    galleryContentDimmer.dimmer('show');
                    let urlToLink = linkfieldValueUrl.replace(-1, selectedFieldValueIds.join('-'));
                    $.ajax({
                        url: urlToLink,
                    }).done(function () {
                        window.location.reload();
                    });
                    galleryContentDimmer.dimmer('hide');
                }
                modalContainer.find('.button').unbind('click');
                modalContainer.modal('hide');

            });

            loadTypeButton.click();
        });
    };
    initFilePickerButton();

    window.initSemanticSearch = function (context) {
        context = wrapContext(context);
        context.find('.semantic-search').each(
            function () {
                $(this).on('keyup keypress', function (e) {
                    let keyCode = e.keyCode || e.which;
                    if (keyCode === 13) {
                        e.preventDefault();
                        return false;
                    }
                });

                let options = JSON.parse(this.dataset.options);
                // if we have "updateChilds" option -> bind initSemanticSearch of that fields on change of this field
                // then change route vars dynamically, based on parent values
                if (options.updateChilds) {
                    options.onChange = function (value, text, $selectedItem) {

                        for (let character in options.updateChilds) {
                            if (options.updateChilds.hasOwnProperty(character)) {
                                for (let child in options.updateChilds[character]) {
                                    if (options.updateChilds[character].hasOwnProperty(child)) {
                                        // console.log([character, options.updateChilds[character][child]]);

                                        let childDropdown = $('.semantic-search.f-' + options.updateChilds[character][child]);
                                        childDropdown.parent().toggleClass('disabled', false);
                                        // console.log(['child:', childDropdown, childDropdown.dropdown('setting', 'apiSettings')]);

                                        let baseRoute = childDropdown.dropdown('setting', 'apiSettings').baseUrl;
                                        if (baseRoute) {
                                            let routeConfig = childDropdown.data('currentMap');
                                            if (typeof routeConfig === 'undefined') {
                                                routeConfig = [];
                                            }
                                            routeConfig[character] = (value !== '') ? value : character;
                                            childDropdown.data('currentMap', routeConfig);

                                            let newUrl = baseRoute;
                                            for (let routeParam in routeConfig) {
                                                if (routeConfig.hasOwnProperty(routeParam))
                                                    newUrl = newUrl.replace(routeParam, routeConfig[routeParam]);
                                            }
                                            childDropdown.dropdown('setting', 'apiSettings', {url: newUrl});
                                            childDropdown.dropdown('clear');
                                            childDropdown.dropdown('refresh');
                                        }
                                    }
                                }
                            }
                        }
                    };

                }
                $(this).dropdown(options);
            }
        );
    };


    // finds '.delete-this.button', binds on it ajax form submission
    window.initPopupConfirmation = function (context) {
        context = wrapContext(context);
        context.find('.delete-this.button').click(function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('.ui.basic.test.modal')
                .modal({
                    onApprove: function () {
                        let redTargetButton = $(e.currentTarget);
                        let parentSegment = redTargetButton.parent().parent();
                        let deleteForm;
                        if (parentSegment.hasClass('ui bottom attached tab segment active')) {
                            deleteForm = parentSegment.find('.delete.form');
                            $.ajax({
                                type: deleteForm.attr('method'),
                                url: deleteForm.attr('action'),
                                data: new FormData(deleteForm[0]),
                                processData: false,
                                contentType: false
                            }).done(function (data) {
                                if (!!data.status && data.status === 'deleted') {
                                    let currentDropdown = deleteForm.parent().siblings('.grayish.grid').find('.tabable.dropdown');
                                    currentDropdown.find('.menu.transition.hidden .item.active.selected').remove();
                                    currentDropdown.dropdown('clear');
                                    deleteForm.parent().empty();
                                    createFlashMessage('Обьект удалён!');
                                }
                            });
                        }
                    }
                })
                .modal('show');
        });
    };

    function createFlashMessage(message, type = 'success') {
        let newFlashMessage = $('<div class="ui ' + type + ' message"/>');
        newFlashMessage.append($('<i class="close icon"/>'));
        newFlashMessage.append($('<div class="header"/>').html(message));
        newFlashMessage.appendTo('.flashbag-container');
        newFlashMessage.click(function () {
            $(this).remove();
        });
    }

    function initTabableDropdown(context) {
        context = wrapContext(context);
        let dropdown = context.find('.tabable.dropdown');
        dropdown.dropdown({
            'forceSelection': false,
            onChange: function (value, text, $selectedItem) {
                if ($selectedItem) {
                    // find container of tabable dropdown and then find corresponding to tabableDropdown tab
                    changeTab($selectedItem);
                }
            }
        });
        context.find('.add-new-button')
            .click(function () {
                dropdown.dropdown('clear').dropdown('refresh');
                changeTab($(this));
            });
    }

    function changeTab(el) {
        // find container of tabable dropdown and then find corresponding to tabableDropdown tab
        let selectedTabContentEl = el.closest('.ui.accordion.field').find('.tab[data-change-tab="' + el.data('changeTab') + '"]');
        // console.log(selectedTabContentEl);
        if (el.data('url') !== undefined) {
            //do ajax
            $.ajax(el.data('url')).done(function (responseHTML) {
                selectedTabContentEl.html(responseHTML);
                initNewFields(selectedTabContentEl);
            });
        }
        let siblings = $(this).siblings('.tab.segment');
        siblings.toggleClass('active', false);
        selectedTabContentEl.siblings().toggleClass('active', false);
        selectedTabContentEl.toggleClass('active', true);
    }

    initTabableDropdown();

    $('.menu .item').tab();
    $('.secondary.tabular.menu .item').tab(
        {
            'onVisible': function () {
                $(this).find('.tabular.menu .item').first().click()
            }
        });
    $('.dynamic.tab.segment .menu .item')
        .tab({
            auto: true,
            onLoad: function () {
                initNewFields(this)
            }
        });

    //menu & fields

    sessionStorage.clear();
    initSemanticSearch();
    initPopupConfirmation();
    initObjectDeleteButton();

    function initObjectDeleteButton() {
        $('.delete-main-object.button').click(function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('.ui.basic.test.modal')
                .modal({
                    onApprove: function () {
                        $('.ui.delete.form.main-object').submit();
                    }
                })
                .modal('show');
        });
    }

    //checkbox linked to drodown. if toggled off  - dropdown disabled and reset to default
    $('.checkbox>.toggle-class').parent().change(function () {
        let context = $(this);
        let input = context.find('input');

        if (input.data('toggleClass')) {
            let relatedInput = $('.' + input.data('toggleClass'));
            if (input.is(':checked')) {
                relatedInput.toggleClass('disabled', false);
            } else {
                relatedInput.dropdown('set selected', relatedInput.find('option').first().val());
                relatedInput.toggleClass('disabled', true);
            }
        }
    });

    $('.checkbox input').toggleClass('hidden', false);

    function initSluggableFields(context) {
        context = wrapContext(context);
        context.find('.sluggable.field').each(
            function () {
                try {
                    let options = JSON.parse(this.dataset.options);
                    if ('base_field' in options) {
                        $(this).find('.slugify').click(function () {
                            let baseFieldVal = $(this).parents('.ui.segment').first().find(options.base_field).val();
                            let slug = getSlug(baseFieldVal, options.options);
                            if (options.slug_field !== '') {
                                $(options.slug_field).val(slug);
                            } else {
                                this.previousElementSibling.value = slug;
                            }
                        });
                    }
                } catch (e) {
                }
            }
        );
    }

    initSluggableFields();

    $('.ui.menu')
        .on('click', '.item', function () {
            if (!$(this).hasClass('dropdown')) {
                $(this)
                    .addClass('active')
                    .siblings('.item')
                    .removeClass('active');
            }
        });

    function initContentToolsButtons(context) {
        const editor = ContentTools.EditorApp.get();
        context = wrapContext(context);

        /** styles are commented now because of
         * @link https://github.com/GetmeUK/ContentTools/issues/362
         */
        ContentTools.StylePalette.add([
            new ContentTools.Style('Semantic UI table', 'table ui', ['table']),
            // new ContentTools.Style('Semantic UI table', ['ui'], ['table']),
            // new ContentTools.Style('Semantic UI p', 'ui', ['p']),
            // new ContentTools.Style('Semantic UI p moar', 'ui p-ui', ['p'])
        ]);

        // rebuild editor upon field button click
        context.find('.init-editable').click(function () {
            // source of html
            let hiddenSourceCode = $(this).siblings('.hidden.html.source.code');
            let backupSourceCode = $('.hidden.html.backup.code');
            let loremIpsumSource = $('.hidden.lorem-ipsum.html.code');
            // modal
            let editableModal = $('.ui.contenttools.modal');
            editableModal.modal('refresh');
            let editableModalContainer = editableModal.find('.content .ui.container');

            // buttons that need to be resetted onApprove, onDeny
            let resetButton = editableModal.find('.reset.button');
            let defaultButton = editableModal.find('.default.button');

            //set editable html to modal
            editableModalContainer.html(hiddenSourceCode.val());
            //show modal
            editableModal
                .modal({
                    onApprove: function () {
                        if (editor.isEditing()) {
                            editor.stop(true);
                        }
                        //save edited html
                        hiddenSourceCode.val(editableModalContainer.html());
                        resetButton.off('click');
                    },
                    onDeny: function () {
                        if (editor.isEditing()) {
                            editor.stop();
                        }
                        resetButton.off('click');
                    },
                    //callback to go editable mode upon start
                    onVisible: function () {
                        editor.start();
                    },
                    closable: false,
                    observeChanges: true,
                    autofocus: false
                })
                .modal('show');
            defaultButton.click(function () {
                if (editor.isEditing()) {
                    editor.stop();
                }
                editableModalContainer.html(loremIpsumSource.html());
                hiddenSourceCode.val(loremIpsumSource.html());
                if (!editor.isEditing()) {
                    editor.start();
                }
            });
            resetButton.click(function () {
                hiddenSourceCode.val(backupSourceCode.val());
                editableModal.find('.cancel').click();
            });

            editor.init('*[data-editable]', 'data-name', null, false);
        });
    }

    //contenttools modal + wysiwyg
    if ($('.ui.contenttools.modal').length) {
        initContentToolsButtons();
    }


    //all field funcions initialization
    let initNewFields = function (context) {
        context = wrapContext(context);
        context.find('.tabular.menu .item').tab();
        context.find('.checkbox').checkbox();
        context.find('.checkbox input').toggleClass('hidden', false);
        context.find('.ui.accordion').accordion();

        initTabableDropdown(context);
        initCalendars(context);
        initDetachFileFields(context);
        initFilePickerButton(context);
        initSluggableFields(context);
        initContentToolsButtons(context);

        initPopupConfirmation(context);
        initTabSaveButtons(context);
        initSemanticSearch(context);
    };

    function submitAjaxForm($form, callback) {
        let form = $form[0];
        $form.toggleClass('hidden', true);
        $form.parent().toggleClass('loading', true);
        $.ajax({
            type: $form.attr('method'),
            url: $form.attr('action'),
            data: new FormData(form),
            processData: false,
            contentType: false
        }).done(function (data) {
            $form.html(data);
            initNewFields($form.parent().parent());
            $form.toggleClass('hidden', false);
            // if we just created new object - refresh dropdown
            if ($form.parent().data('new') === true) {
                $form.parent().siblings().first().find('.dropdown').dropdown('clear').dropdown('refresh');
            }
            $form.parent().toggleClass('loading', false);
            sessionStorage.clear();

            if (typeof callback === "function") {
                callback($form);
            }
        });
    }

    function initTabSaveButtons(context) {
        context = wrapContext(context);
        context.find('.save-tab').click(function (e) {
            e.preventDefault();
            e.stopPropagation();
            let editableForm = $(this).parents('form');
            if (editableForm.length > 0) {
                submitAjaxForm(editableForm, function (form) {
                    let submitButtonDataset = form.find('.save-tab')[0].dataset;
                    let shouldMoveForm = (submitButtonDataset.dataType === "1" && submitButtonDataset.formIsValid === "1" && submitButtonDataset.fromEdit !== "1");
                    let newSelectItemDiv = form.find('.new-object-tab');
                    if (shouldMoveForm && newSelectItemDiv.length > 0) {
                        let dropdown = form.parent().siblings('.grayish.grid').find('.selection.dropdown');
                        let newSelectItem = newSelectItemDiv.children()[0];
                        let newTab = newSelectItemDiv.children()[1];

                        dropdown.children('.menu').append(newSelectItem);
                        form.parent().parent().append(newTab);
                        dropdown.dropdown('refresh');
                        dropdown.dropdown('set selected', newSelectItem.innerText);
                        newSelectItemDiv.remove();
                    }
                });
            }
        });
    }

    initTabSaveButtons();
});