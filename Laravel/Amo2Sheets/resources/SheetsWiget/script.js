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
            },
            system = self.system(),
            endpoint = 'https://7263913-ce143862.twc1.net',
            endpointAPI = endpoint+'/api/sheets/';

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
                pipeline_id: window.location.pathname.split('/')[3],
                filter_url: window.location.search,
                uuid: uuid
            };

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
                                $(document).trigger('a2s-render_settings');
                                APP.notifications.show_message({
                                    header: self.i18n('advanced').title,
                                    text: 'Выгрузка удалена',
                                });
                            }
                        });
                    }
                })

                // Нажал на "сохранить"
                modal.$modal.on('submit', '#a2s-form_filter', function (e){
                    e.preventDefault();

                    modal.$modal.find('button').addClass('button-input-disabled');
                    let formData = arrToObj($(e.currentTarget).serializeArray(), 'name');

                    $.ajax({
                        url: endpointAPI+'filter',
                        method: 'POST',
                        data: {
                            domain: system.domain,
                            pipeline_id: oldFilter.pipeline_id,
                            filter_url: oldFilter.filter_url,
                            uuid: uuid,
                            name: formData.filter_name.value,
                            comment: formData.filter_comment.value,
                            amouser_id: system.amouser_id,
                        },
                        success: function () {
                            modal.destroy();
                            $(document).trigger('a2s-render_settings');
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

        /**
         * Редактирование или создание выгрузки
         * @param uuid
         */
        function storeConnection(uuid = null)
        {
            // TODO Редактор полей
            let oldConnection = uuid ? A2S.connectionKeys[uuid] : {};

            self.getTemplate('connection_modal', {}, function (template) {
                let modalBody = template.render(
                        $.extend(oldConnection, {
                            filterData: A2S.filterKeys
                        })
                    ),
                    modal = new Modal({
                        init: function (modalSrc) {
                            modalSrc
                                .trigger('modal:loaded')
                                .html(modalBody)
                                .trigger('modal:centrify');
                        }
                    });

                // Нажал на "удалить"
                modal.$modal.on('click', '.delete_connection', function (){
                    modal.destroy();
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
                                $(document).trigger('a2s-render_settings');
                                APP.notifications.show_message({
                                    header: self.i18n('advanced').title,
                                    text: 'Выгрузка удалена',
                                });
                            }
                        });
                    }
                })

                // Нажал на "сохранить"
                modal.$modal.on('submit', '#a2s-form_connection', function (e){
                    e.preventDefault();

                    modal.$modal.find('button').addClass('button-input-disabled');
                    let formData = arrToObj($(e.currentTarget).serializeArray(), 'name');

                    $.ajax({
                        url: endpointAPI+'connection',
                        method: 'POST',
                        data: {
                            uuid: uuid,
                            domain: system.domain,
                            filter_id: formData.filter_id.value,
                            amouser_id: system.amouser_id,
                            sheet_id: formData.sheet_id.value
                        },
                        success: function () {
                            modal.destroy();
                            $(document).trigger('a2s-render_settings');
                            APP.notifications.show_message({
                                header: self.i18n('advanced').title,
                                text: 'Выгрузка сохранена',
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
                console.log('render');
                let area = self.system().area;
                if (area === 'outer_space' && APP.getWidgetsArea() === 'leads-pipeline'){
                    let buttonExist = $('#a2s-save_filter')[0];
                    if (!buttonExist) {
                        let ul = $('ul.context-menu-pipeline');
                        self.getTemplate('save_filter', {}, function (template) {
                            let saveFilter = template.render();
                            ul.append(saveFilter);
                        });

                        $(document).off('click', '#a2s-save_filter');
                        $(document).on('click', '#a2s-save_filter', function (){
                            storeFilter();
                        })
                    }
                }

                return true;
            },
            init: _.bind(function () {
                console.log('init');
                let settings = self.get_settings();
                if ($('link[href="' + settings.path + '/style.css?v=' + settings.version + '"').length < 1) {
                    $('head').append('<link href="' + settings.path + '/style.css?v=' + settings.version + '" type="text/css" rel="stylesheet">');
                }

                $(document).on('click', '#amo2sheets_advanced .a2s-tabs_nav_item', function (e){
                    let elm = $(e.currentTarget),
                        elmParent = elm.closest('.a2s-tabs'),
                        target = elm.data('tab');

                    elmParent.find('.a2s-tabs_nav_item').removeClass('a2s-active');
                    elmParent.find('.a2s-tabs_pane').removeClass('a2s-active');

                    elm.addClass('a2s-active');
                    $('#'+target).addClass('a2s-active');
                });

                return true;
            }, this),
            bind_actions: function () {
                return true;
            },
            settings: function () {
                console.log('settings');
                let modalBody = $('.widget-settings__modal.'+self.params.widget_code),
                settingsArea = modalBody.find('#widget_settings__fields_wrapper');

                function buildSettings() {
                    settingsArea.html();
                    modalBody.off('click', '#a2s-oauth');
                    modalBody.off('click', '#a2s-oauth_logout');

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

                            modalBody.on('click', '#a2s-oauth', function () {
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

                            modalBody.on('click', '#a2s-oauth_logout', function () {
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
                                            v.full_url = `https://${system.domain}/leads/pipeline/${v.pipeline_id}/${v.filter_url}`;
                                            return v;
                                        });

                                    A2S.filterKeys = arrToObj(filterList, 'uuid');
                                    A2S.connectionKeys = arrToObj(connectionData, 'uuid');
                                    A2S.userKeys = allUsersKeys;

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

                                    workArea.on('click', '#a2s-add_connection', function () {
                                        storeConnection();
                                    });

                                    workArea.on('click', '.a2s-sync_connection', function (e) {
                                        let uuid = $(e.currentTarget).data('uuid'),
                                            confirmModal = new ConfirmModal({
                                                disable_overlay_click: false,
                                                accept_text: 'Да',
                                                decline_text: 'Отмена',
                                                text: 'Синхронизация сделок',
                                                message: [
                                                    {
                                                        text: 'Все сделки будут удалены из выбранной Google Таблицы и выгружены заново, это может занять время.',
                                                    },
                                                    {
                                                        text: 'Продолжить?',
                                                    },
                                                ],
                                            });

                                        confirmModal.options.accept = function () {
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
                                                    });
                                                    if (!syncResult.data.next_page) break;
                                                }
                                            }
                                            syncByPage().then();


                                            // $.ajax({
                                            //     url: endpointAPI+'sync_connection',
                                            //     method: 'POST',
                                            //     data: {
                                            //         domain: system.domain,
                                            //         uuid: uuid
                                            //     },
                                            //     success: function () {
                                            //         confirmModal.destroy();
                                            //         APP.notifications.show_message({
                                            //             header: self.i18n('advanced').title,
                                            //             text: 'Синхронизация начата',
                                            //         });
                                            //     }
                                            // });
                                        }
                                    });
                                }
                            });
                        }
                    });
                }

                $(document).on('a2s-render_settings', renderSettings);

                renderSettings();
            }, self),
        };

        return this;
    };
});
