define([
    'jquery',
    'underscore',
    'twigjs',
    'lib/components/base/modal',
    'lib/components/base/confirm',
], function ($, _, Twig, Modal, ConfirmModal) {
    return function () {
        let self = this,
            A2S = {
                filterKeys: {},
                connectionKeys: {},
                userKeys: {},
                connectionFields: {}
            },
            system = self.system(),
            endpoint = 'https://7263913-ce143862.twc1.net',
            endpointAPI = endpoint+'/api/sheets/',
            fieldTypeNames = {
                other: 'Другой',
                deal: 'Сделка',
                contact: 'Контакт',
            };

        document.A2S = A2S;

        /**
         * Формирует объект ключ-значение из массива и имени ключа
         * @param array
         * @param key
         * @returns {{}}
         */
        function arrToObj(array, key = 'id'){
            let obj = {};
            $.each(array, function (k, v){
                obj[v[key]] = v;
            });
            return obj;
        }

        /**
         * Редактирование или создание фильтра
         * @param uuid
         */
        function storeFilter(uuid = null)
        {
            let oldFilter = uuid? A2S.filterKeys[uuid]: {
                filter_url: window.location.search,
                uuid: uuid
            };
            if (new URLSearchParams(window.location.search).has('skip_filter')){
                new Modal()._showError('Фильтры не заданы', false);
                return false;
            }

            self.getTemplate('filter_modal', {}, function (template) {
                let modalBody = template.render(oldFilter),
                    modal = new Modal({
                        init: function (modalSrc) {
                            modalSrc
                                .trigger('modal:loaded')
                                .html(modalBody)
                                .trigger('modal:centrify');
                        }
                    });

                // Нажал на "удалить"
                modal.$modal.on('click', '.delete_filter', function (){
                    modal.destroy();
                    let confirmModal = new ConfirmModal({
                        disable_overlay_click: false,
                        accept_text: 'Да',
                        decline_text: 'Отмена',
                        text: 'Удаление фильтра',
                        message: [
                            {
                                text: 'Фильтр будет удалён, продолжить?',
                            },
                        ],
                    });

                    confirmModal.options.accept = function () {
                        $.ajax({
                            url: endpointAPI+'filter',
                            method: 'DELETE',
                            data: {
                                domain: system.domain,
                                uuid: uuid
                            },
                            success: function () {
                                confirmModal.destroy();
                                $(document).trigger('a2s_render-settings');
                                APP.notifications.show_message({
                                    header: self.i18n('advanced').title,
                                    text: 'Выгрузка удалена',
                                });
                            }
                        });
                    }
                })

                // Нажал на "сохранить"
                modal.$modal.on('submit', '#a2s_form-filter', function (e){
                    e.preventDefault();

                    modal.$modal.find('button').addClass('button-input-disabled');
                    let formData = arrToObj($(e.currentTarget).serializeArray(), 'name');

                    $.ajax({
                        url: endpointAPI+'filter',
                        method: 'POST',
                        data: {
                            domain: system.domain,
                            filter_url: oldFilter.filter_url,
                            uuid: uuid,
                            name: formData.filter_name.value,
                            comment: formData.filter_comment.value,
                            amouser_id: system.amouser_id,
                        },
                        success: function () {
                            modal.destroy();
                            $(document).trigger('a2s_render-settings');
                            APP.notifications.show_message({
                                header: self.i18n('advanced').title,
                                text: 'Фильтр сохранён',
                            });
                        },
                        error: function (response){
                            new Modal()._showError(response.responseJSON.message, false);
                            modal.$modal.find('button').removeClass('button-input-disabled');
                        }
                    });
                });
            });
        }

        this.getTemplate = _.bind(function (template, params, callback) {
            params = (typeof params == 'object') ? params : {};
            template = template || '';

            return this.render({
                href: '/templates/' + template + '.twig',
                base_path: this.params.path,
                v: this.get_version(),
                load: callback
            }, params);
        }, this);

        this.callbacks = {
            render: function () {
                if (['leads-pipeline', 'leads'].includes(APP.getWidgetsArea())){
                    let buttonExist = $('#a2s_save-filter')[0];
                    if (!buttonExist) {
                        let ul = $('ul.context-menu-pipeline');
                        self.getTemplate('save_filter', {}, function (template) {
                            let saveFilter = template.render();
                            ul.append(saveFilter);
                        });

                        $(document).off('click', '#a2s_save-filter');
                        $(document).on('click', '#a2s_save-filter', function (){
                            storeFilter();
                        })
                    }
                }

                return true;
            },
            init: _.bind(function () {
                let settings = self.get_settings();
                if ($('link[href="' + settings.path + '/style.css?v=' + settings.version + '"').length < 1) {
                    $('head').append('<link href="' + settings.path + '/style.css?v=' + settings.version + '" type="text/css" rel="stylesheet">');
                }

                $(document).on('click', '#a2s_advanced .a2s_tabs-nav-item', function (e){
                    let elm = $(e.currentTarget),
                        elmParent = elm.closest('.a2s_tabs'),
                        target = elm.data('tab');

                    elmParent.find('.a2s_tabs-nav-item').removeClass('a2s_active');
                    elmParent.find('.a2s_tabs-pane').removeClass('a2s_active');

                    elm.addClass('a2s_active');
                    $('#'+target).addClass('a2s_active');
                });

                return true;
            }, this),
            bind_actions: function () {
                return true;
            },
            settings: function () {
                let modalBody = $('.widget-settings__modal.'+self.params.widget_code),
                settingsArea = modalBody.find('.widget_settings_block__item_field');

                function buildSettings() {
                    settingsArea.html();
                    modalBody.off('click', '#a2s_oauth');
                    modalBody.off('click', '#a2s_oauth-logout');

                    $.ajax({
                        url: endpointAPI+'oauth-check',
                        method: 'GET',
                        data: {
                            domain: system.domain
                        },
                        success: function (response){
                            let oauthUrl = response.data.url;
                            self.getTemplate('oauth_button', {}, function (template) {
                                let oauthButton = template.render({
                                    url: oauthUrl,
                                    alreadyAuth: response.data.auth
                                });
                                settingsArea.html(oauthButton);
                            });

                            modalBody.on('click', '#a2s_oauth', function () {
                                function successOAuth(e){
                                    if (e.origin !== endpoint) return;
                                    buildSettings();
                                    APP.notifications.show_message({
                                        header: self.i18n('advanced').title,
                                        text: 'Google аккаунт привязан',
                                    });
                                }

                                window.removeEventListener('message', successOAuth);
                                window.addEventListener('message', successOAuth);
                                window.open(oauthUrl);
                            });

                            modalBody.on('click', '#a2s_oauth-logout', function () {
                                let confirmModal = new ConfirmModal({
                                    disable_overlay_click: false,
                                    accept_text: 'Да',
                                    decline_text: 'Отмена',
                                    //text: 'Синхронизация сделок',
                                    message: [
                                        {
                                            text: 'Выгрузки перестанут работать, выйти из Google аккаунта?',
                                        }
                                    ],
                                });

                                confirmModal.options.accept = function () {
                                    $.ajax({
                                        url: endpointAPI+'oauth-logout',
                                        method: 'POST',
                                        data: {
                                            domain: system.domain
                                        },
                                        success: function () {
                                            confirmModal.destroy();
                                            buildSettings();
                                            APP.notifications.show_message({
                                                header: self.i18n('advanced').title,
                                                text: 'Google аккаунт отвязан',
                                            });
                                        }
                                    });
                                }
                            });
                        }
                    });
                }

                buildSettings();
                return true
            },
            onInstall: function (){
                $.ajax({
                    url: '/api/v4/webhooks',
                    method: 'POST',
                    data: {
                        destination: endpointAPI+'event',
                        settings: [
                            'add_lead',
                            'update_lead',
                            'delete_lead',
                            // 'status_lead',
                            // 'responsible_lead',
                            // 'restore_lead',
                        ]
                    }
                });
            },
            onSave: function () {
                return true;
            },
            destroy: function () {
                $.ajax({
                    url: '/api/v4/webhooks',
                    method: 'DELETE',
                    data: {
                        destination: endpointAPI+'event'
                    }
                });

                $.ajax({
                    url: endpointAPI+'oauth-logout',
                    method: 'POST',
                    data: {
                        domain: system.domain
                    }
                });
            },
            advancedSettings: _.bind(function () {
                let workArea = $('#work-area-' + self.get_settings().widget_code);

                function storeConnection(uuid = null)
                {
                    let oldConnection = uuid ? A2S.connectionKeys[uuid] : {
                        active: true,
                        sheet_fields: [{
                            name: 'empty',
                            order: 1,
                            type: 'other'
                        }]
                    };
                    oldConnection.filterData = Object.values(A2S.filterKeys).sort((a, b) => a.name.localeCompare(b.name));

                    $.each(oldConnection.sheet_fields, function (k, field){
                        field.type_name = fieldTypeNames[field.type];
                        field.label = field.name;
                        if (!field.custom) {
                            field.label = A2S.connectionFields[field.type][field.name].name;
                        }
                    })

                    self.getTemplate('connection_edit', {}, function (template) {
                        let page = template.render(oldConnection);
                        workArea.html(page);

                        let connectionArea = $('#a2s_connection-edit');
                        connectionArea.on('click', '.a2s_add-field', function (){
                            let order = connectionArea.find('.a2s_field-row').length+1;

                            self.getTemplate('add_field_modal', {}, function (template) {
                                let modalBody = template.render({
                                        connectionFields: A2S.connectionFields,
                                        fieldTypeNames: fieldTypeNames
                                    }),
                                    modal = new Modal({
                                        init: function (modalSrc) {
                                            modalSrc
                                                .trigger('modal:loaded')
                                                .html(modalBody)
                                                .trigger('modal:centrify');
                                        }
                                    });

                                modal.$modal.on('submit', '#a2s_form-add-field', function (e) {
                                    e.preventDefault();
                                    let formData = arrToObj($(e.currentTarget).serializeArray(), 'name'),
                                        fieldCustom = $('#a2s_field-custom').is(':checked'),
                                        fieldName = fieldCustom? formData['field-name'].value : formData['field-name-select'].value,
                                        fieldType = formData['field-type'].value;

                                    self.getTemplate('connection_field_row', {}, function (template) {
                                        let newRow = template.render({field: {
                                                custom: fieldCustom,
                                                type: fieldType,
                                                order: order,
                                                name: fieldName,
                                                label: fieldCustom? fieldName: A2S.connectionFields[fieldType][fieldName].name,
                                                type_name: fieldTypeNames[fieldType]
                                            }});
                                        $('#a2s_fields-table').append(newRow);

                                    });
                                    modal.destroy();
                                });
                            });
                        });


                        // Нажал на "удалить"
                        connectionArea.on('click', '.a2s_delete-connection', function (){
                            let confirmModal = new ConfirmModal({
                                disable_overlay_click: false,
                                accept_text: 'Да',
                                decline_text: 'Отмена',
                                text: 'Удаление выгрузки',
                                message: [
                                    {
                                        text: 'Выгрузка будет удалена, продолжить?',
                                    },
                                ],
                            });

                            confirmModal.options.accept = function () {
                                $.ajax({
                                    url: endpointAPI+'connection',
                                    method: 'DELETE',
                                    data: {
                                        domain: system.domain,
                                        uuid: uuid
                                    },
                                    success: function () {
                                        confirmModal.destroy();
                                        $(document).trigger('a2s_render-settings');
                                        APP.notifications.show_message({
                                            header: self.i18n('advanced').title,
                                            text: 'Выгрузка удалена',
                                        });
                                    }
                                });
                            }
                        })

                        // Нажал на "сохранить"
                        connectionArea.on('submit', '#a2s_form-connection', function (e){
                            e.preventDefault();

                            connectionArea.find('button').addClass('button-input-disabled');
                            let formData = arrToObj($(e.currentTarget).serializeArray(), 'name'),
                                sheetUrl = formData.sheet_id.value,
                                sheetFields = [],
                                sheetID;

                            $('.a2s_field-row').each(function (k,v){
                                sheetFields.push({
                                    name: $(v).find('[name="field-name"]').val(),
                                    type: $(v).data('field_type'),
                                    order: $(v).find('[name="field-order"]').val(),
                                    custom: $(v).data('field_custom')*1,
                                });
                            });

                            sheetFields.sort((a, b) => a.order - b.order);
                            $.each(sheetFields, function (k,v){
                                v.order = k+1;
                            });

                            if (sheetUrl.includes('/')){
                                sheetID = new URL(sheetUrl).pathname.split('/')[3];
                            } else {
                                sheetID = sheetUrl;
                            }


                            $.ajax({
                                url: endpointAPI+'connection',
                                method: 'POST',
                                data: {
                                    uuid: uuid,
                                    domain: system.domain,
                                    filter_id: formData.filter_id.value,
                                    amouser_id: system.amouser_id,
                                    sheet_id: sheetID,
                                    sheet_fields: sheetFields,
                                    active: $('#a2s_active').is(':checked')*1
                                },
                                success: function () {
                                    $(document).trigger('a2s_render-settings');
                                    APP.notifications.show_message({
                                        header: self.i18n('advanced').title,
                                        text: 'Выгрузка сохранена',
                                    });
                                },
                                error: function (response){
                                    new Modal()._showError(response.responseJSON.message, false);
                                    connectionArea.find('button').removeClass('button-input-disabled');
                                }
                            });
                        });
                    });
                }

                function renderSettings(){
                    let loader = new Modal();

                    $.ajax({
                        url: endpointAPI+'full-data',
                        method: 'GET',
                        data: {
                            domain: system.domain
                        },
                        success: function (beData){
                            $.ajax({
                                url: '/api/v4/users',
                                method: 'GET',
                                complete: function (userData) {

                                    workArea.off('click');
                                    if (!beData.success){
                                        new Modal()._showError(self.i18n('error').no_response, false);
                                        return false;
                                    }

                                    let filterList = beData.data.filters,
                                        connectionList = beData.data.connections,
                                        allUsers = userData._total_items ? userData._embedded.users : [],
                                        allUsersKeys = arrToObj(allUsers),
                                        connectionData = $.map(connectionList, function (v) {
                                            v.author_name = (allUsersKeys[v.author_id] || {}).name;
                                            return v;
                                        }),
                                        filterData = $.map(filterList, function (v) {
                                            v.author_name = (allUsersKeys[v.author_id] || {}).name;
                                            v.full_url = `https://${system.domain}/leads/list${v.filter_url}`;
                                            return v;
                                        });

                                    connectionData.sort((a, b) => a.filter_name.localeCompare(b.filter_name));
                                    filterData.sort((a, b) => a.name.localeCompare(b.name));

                                    A2S.filterKeys = arrToObj(filterList, 'uuid');
                                    A2S.connectionKeys = arrToObj(connectionData, 'uuid');
                                    A2S.userKeys = allUsersKeys;
                                    A2S.connectionFields = beData.data.connection_fields;

                                    self.getTemplate('advanced_settings', {}, function (template) {
                                        let page = template.render({
                                            title: self.i18n('advanced').title,
                                            connectionData: connectionData,
                                            filterData: filterData
                                        });
                                        workArea.html(page);
                                    });

                                    loader.destroy();

                                    workArea.on('click', '.edit_connection', function (e){
                                        e.preventDefault();
                                        storeConnection($(e.currentTarget).data('uuid'));
                                    });

                                    workArea.on('click', '.edit_filter', function (e){
                                        e.preventDefault();
                                        storeFilter($(e.currentTarget).data('uuid'));
                                    });

                                    workArea.on('click', '#a2s_add-connection', function () {
                                        storeConnection();
                                    });

                                    workArea.on('click', '.a2s_sync-connection', function (e) {
                                        let uuid = $(e.currentTarget).data('uuid'),
                                            confirmModal = new ConfirmModal({
                                                disable_overlay_click: false,
                                                accept_text: 'Да',
                                                decline_text: 'Отмена',
                                                text: 'Синхронизация сделок',
                                                message: [
                                                    {
                                                        text: 'Все сделки будут удалены из выбранной Google Таблицы и выгружены заново, это может занять время',
                                                    },
                                                    {
                                                        text: 'Проверьте что выключили выгрузку перед синхронизацией',
                                                    },
                                                    {
                                                        text: 'Продолжить?',
                                                    },
                                                ],
                                            });

                                        confirmModal.options.accept = function () {
                                            let modalBody = confirmModal.modal.$modal.find('.modal-body__inner');
                                            modalBody.find('.modal-body__paragraph-text, .modal-body__actions').remove();
                                            modalBody.append(`<p class="modal-body__paragraph-text">Сделок выгружено: <span id="a2s_sync-connection-count">0</span></p>`);
                                            modalBody.append(`<p class="modal-body__paragraph-text" id="a2s_sync-connection-loader">...</p>`);
                                            let syncCount = $('#a2s_sync-connection-count'),
                                                syncLoader = $('#a2s_sync-connection-loader'),

                                                loaderInterval = setInterval(function (){
                                                    let dotsCnt = syncLoader.html().length;
                                                    if (dotsCnt === 5){
                                                        syncLoader.html('.')
                                                    }
                                                    else {
                                                        syncLoader.html(syncLoader.html()+'.');
                                                    }
                                                },750);

                                            async function syncByPage() {
                                                for (let page = 1; ; page++) {
                                                    let syncResult = await $.ajax({
                                                        url: endpointAPI+'sync-connection',
                                                        method: 'POST',
                                                        data: {
                                                            domain: system.domain,
                                                            uuid: uuid,
                                                            page: page
                                                        },
                                                        success: function (response){
                                                            syncCount.html(syncCount.html() * 1 + response.data.count_done * 1);
                                                        },
                                                        error: function (response){
                                                            confirmModal.destroy();
                                                            new Modal()._showError(response.responseJSON.message, false);
                                                        }
                                                    });
                                                    if (!syncResult.data.next_page){
                                                        syncLoader.html('Выгрузка завершена<br><span class="a2s_text-warning">Не забудьте включить выгрузку!<span>');
                                                        break;
                                                    }
                                                }
                                            }
                                            syncByPage().finally(function () {
                                                clearInterval(loaderInterval);
                                            });
                                        }
                                    });
                                }
                            });
                        }
                    });
                }

                $(document).on('click', '.a2s_render-settings', renderSettings);

                $(document).on('a2s_render-settings', renderSettings);

                renderSettings();
            }, self),
        };

        return this;
    };
});
