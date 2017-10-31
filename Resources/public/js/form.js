$(document).ready(function () {
    function initCalendars(context) {
        if (!context) {
            context = 'body';
        }
        context = $(context);
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

    // finds '.delete-this.button', binds on it ajax form submission
    window.initPopupConfirmation = function (context) {
        if (!context) {
            context = 'body';
        }
        context = $(context);
        context.find('.delete-this.button').click(function (e) {
            $('.ui.basic.test.modal')
                .modal({
                    onApprove: function () {
                        let listUrl = e.currentTarget.dataset.url;
                        let deleteForm = $(e.currentTarget).parent().siblings('form')[0];
                        let deleteFormJq = $(deleteForm);
                        let thisContentTab = deleteFormJq.parent();
                        $.ajax({
                            type: deleteFormJq.attr('method'),
                            url: deleteFormJq.attr('action'),
                            data: new FormData(deleteForm),
                            processData: false,
                            contentType: false
                        }).done(function (data) {
                            if (!!data.status && data.status === 'deleted') {
                                //after deletion we need to redirect user to objects list or to remove deleted data-object
                                if ((typeof thisContentTab.data('tab') === "undefined") || (thisContentTab.data('tab') === 'main_object_form')) {
                                    window.location = listUrl;
                                } else {
                                    let newFlashMessage = $('<div class="ui success message"/>');
                                    newFlashMessage.append($('<i class="close icon"/>'));
                                    newFlashMessage.append($('<div class="header"/>').html('Обьект удалён!'));
                                    newFlashMessage.appendTo('.flashbag-container');
                                    newFlashMessage.click(function () {
                                        $(this).remove();
                                    });
                                    thisContentTab.siblings('.menu').find('.item.active').remove();
                                    thisContentTab.siblings('.menu').find('.item').last().click();
                                    thisContentTab.remove();
                                }
                            }
                        });
                    }
                })
                .modal('show');
        });
    };

    if ($('.ui.calendar').length) {
        initCalendars();
    }

    let initDetachFileFields = function (context) {
        if (!context) {
            context = 'body';
        }
        context = $(context);
        context.find('.detach-file').click(
            function (ev) {
                ev.preventDefault();
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
        if (!context) {
            context = 'body';
        }
        context = $(context);
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

    window.initSemanticSearch = function initSemanticSearch(context) {
        if (!context) {
            context = 'body';
        }
        context = $(context);
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
                                            console.log(options.updateChilds[character][child]);
                                            console.log(routeConfig);
                                            console.log({'originalRoute': baseRoute, 'newRoute': newUrl});
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

    function initTabableDropdown(context) {
        if (!context) {
            context = 'body';
        }
        context = $(context);
        context.find('.tabable.dropdown')
            .dropdown({
                onChange: function (value, text, $selectedItem) {
                    let siblings = $(this).siblings('.tab.segment');
                    siblings.toggleClass('active', false);
                    $(this).siblings('.tab[data-change-tab="' + $selectedItem.data('changeTab') + '"]').toggleClass('active', true);
                }
            });
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
    window.initSemanticSearch();
    window.initPopupConfirmation();

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
        if (!context) {
            context = 'body';
        }
        context = $(context);
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
        if (!context) {
            context = 'body';
        }
        context = $(context);

        const editor = ContentTools.EditorApp.get();

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

    //functions
    let initNewFields = function (context) {
        context = $(context);
        context.find('.tabular.menu .item').tab();
        initTabableDropdown(context);
        initCalendars(context);
        initDetachFileFields(context);
        initFilePickerButton(context);
        initSluggableFields(context);
        initContentToolsButtons(context);
        window.initSemanticSearch(context);

        context.find('.checkbox').checkbox();
        context.find('.checkbox input').toggleClass('hidden', false);
        context.find('.ui.accordion').accordion();
        context.find("form[name='obj_fields']").on('submit', function (e) {
            e.preventDefault();
            let target = $(e.target);
            target.toggleClass('hidden', true);
            target.parent().toggleClass('loading', true);
            $.ajax({
                type: $(this).attr('method'),
                url: $(this).attr('action'),
                data: new FormData(this),
                processData: false,
                contentType: false
            }).done(function (data) {
                let thisTabContent = target.parent();

                let newForm = $.parseHTML(data)[0];
                let newObjId = newForm.dataset.id;
                let newObjName = newForm.dataset.name;
                let newUrl = newForm.dataset.url;

                //if form has ID data attribute  then we just created object. Leta add it to tab menu & menu content div
                if (!target.data('id') && newObjId) {
                    let newTab = $('<div class="item" data-tab="woob_obj_rootType_' + newObjId + '"/>').text((newObjName) ? newObjName : ('tid ' + newObjId));
                    newTab.insertBefore(thisTabContent.siblings('.menu').find('.item').last());
                    let newTabContent = $('<div class="ui bottom attached tab segment" data-tab="woob_obj_rootType_' + newObjId + '" data-url="' + newUrl + '"/>');
                    newTabContent.append(newForm);
                    thisTabContent.parent().append(newTabContent);
                    initNewFields(newTabContent);
                    //clear "+" tab content
                    thisTabContent.html('');
                    newTab.tab();
                    newTab.click();
                } else {
                    thisTabContent.html(data);
                    initNewFields(thisTabContent);
                    thisTabContent.siblings('.menu').find('.item.active').tab();
                }
                thisTabContent.toggleClass('loading', false);
                sessionStorage.clear();
            });
        });

        window.initPopupConfirmation(context);
    };
});