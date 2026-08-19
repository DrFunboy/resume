(function ($) {
    'use strict';

    const endpoint = 'connector.php';
    const liked_class = 'like-btn-liked';
    const loading_class = 'like-btn-loading';

    /**
     * Отправка запроса лайк/анлайк для конкретной кнопки.
     */
    function sendLikeRequest($button) {
        let resourceId = parseInt($button.data('id'), 10),
            isLiked = $button.data('liked') === 1 || $button.hasClass(liked_class),
            action = isLiked ? 'unlike' : 'like';

        // Некорректный ID или запрос уже выполняется
        if (!resourceId || $button.hasClass(loading_class)) {
            return;
        }

        $button.addClass(loading_class).prop('disabled', true);

        $.ajax({
            url: endpoint,
            method: 'POST',
            dataType: 'json',
            data: {
                id: resourceId,
                action: action
            }
        })
            .done(function (response) {
                if (!response || response.success !== true) {
                    handleError($button, response && response.error);
                    return;
                }

                updateButtonState($button, response);
            })
            .fail(function (jqXHR) {
                let message = 'Произошла ошибка, попробуйте позже';
                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    message = jqXHR.responseJSON.error;
                }
                handleError($button, message);
            })
            .always(function () {
                $button.removeClass(loading_class).prop('disabled', false);
            });
    }

    /**
     * Обновить кнопку и счётчик
     * @param $button
     * @param response
     */
    function updateButtonState($button, response) {
        let $count = $button.find('.like-count');

        $count.text(response.count);
        $button.data('liked', response.liked ? 1 : 0);
        $button.toggleClass(liked_class, !!response.liked);
    }

    /**
     * Обработка ошибки
     * @param $button
     * @param message
     */
    function handleError($button, message) {
        console.error('Like widget error:', message || 'Неизвестная ошибка.');
    }

    $(function () {
        $(document).on('click', '.like-btn', function (event) {
            event.preventDefault();
            sendLikeRequest($(this));
        });
    });
})(jQuery);