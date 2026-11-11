<?php
// birthday.php — PHP 5.6, файл в кодировке UTF-8.

function fail($message)
{
    echo($message . PHP_EOL);
    exit(1);
}

function parseTodayNews($body, DateTime $now)
{
    // Не разрешаем DTD и загрузку внешних сущностей из RSS.
    if (stripos($body, '<!DOCTYPE') !== false) {
        fail('RSS содержит недопустимый DOCTYPE.');
    }

    $previous = libxml_use_internal_errors(true);
    $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($rss === false || !isset($rss->channel)) {
        fail('Не удалось разобрать RSS новостей.');
    }

    $headlines = array();
    foreach ($rss->channel->item as $item) {
        $date = trim((string) $item->pubDate);
        if ($date === '') {
            continue;
        }

        try {
            $publishedAt = new DateTime($date);
        } catch (Exception $e) {
            continue;
        }

        $publishedAt->setTimezone($now->getTimezone());
        if ($publishedAt > $now || $publishedAt->format('Y-m-d') !== $now->format('Y-m-d')) {
            continue;
        }

        $title = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item->title)));
        if ($title !== '' && !in_array($title, $headlines, true)) {
            $headlines[] = $title;
        }

        if (count($headlines) === 3) {
            return $headlines;
        }
    }

    fail('В RSS нет трёх заголовков за сегодняшний день по времени Елизово.');
}

function getTodayNews(DateTime $now)
{
    $curl = curl_init('https://www.kommersant.ru/rss/news.xml');
    if ($curl === false) {
        fail('Не удалось инициализировать загрузку RSS.');
    }

    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        fail('Ошибка загрузки RSS: ' . $error);
    }
    if ($httpCode !== 200) {
        fail('RSS вернул HTTP ' . $httpCode);
    }

    return parseTodayNews($body, $now);
}

function appendNews($message, array $headlines)
{
    $message .= "\nНовости дня (Коммерсантъ):";
    // Оставляем запас до лимита SMS.ru: 8 частей по 67 символов.
    $limit = (int) floor((500 - mb_strlen($message, 'UTF-8') - 12) / 3);
    if ($limit < 10 || count($headlines) !== 3) {
        fail('Невозможно уместить три заголовка в поздравлении.');
    }

    foreach ($headlines as $index => $title) {
        $message .= "\n" . ($index + 1) . '. ' . $title;
    }

    return $message;
}

$birthYear = 1978;
$birthMonth = 2;
$birthDay = 29;
$phone = '7800553535';
$apiId = '123456789';

$now = new DateTime('now', new DateTimeZone('Asia/Kamchatka'));

$birthdayDate = clone $now;
$birthdayDate->setDate(
    (int) $now->format('Y'),
    $birthMonth,
    $birthDay
);

if ($now->format('Y-m-d') === $birthdayDate->format('Y-m-d')
    && $now->format('H:i') === '12:00'
) {
    if (!extension_loaded('curl')) {
        fail('Необходимо расширение PHP cURL.');
    }
    if (!extension_loaded('SimpleXML') || !extension_loaded('mbstring')) {
        fail('Необходимы расширения PHP SimpleXML и mbstring.');
    }

    $message = sprintf('Анна Николаевна Шульц %02d.%02d.%04d г.р., уроженка г.Владивосток, проживающая по адресу г.Елизово, ул.Ленина д.2 кв.3., с днём рождения! <3', $birthDay, $birthMonth, $birthYear);
    $message = appendNews($message, getTodayNews($now));

    $params = array(
        'api_id' => $apiId,
        'to'     => $phone,
        'msg'    => $message,
        'json'   => 1,
        // 'test'   => 1 // Тест без отправки и списания денег
    );

    $curl = curl_init('https://sms.ru/sms/send');

    if ($curl === false) {
        fail('Не удалось инициализировать cURL.');
    }

    curl_setopt_array($curl, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        fail('Ошибка соединения с SMS.ru: ' . $curlError);
    }

    if ($httpCode !== 200) {
        fail('SMS.ru вернул HTTP ' . $httpCode);
    }

    $response = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($response)) {
        fail('SMS.ru вернул некорректный JSON.');
    }

    if (!isset($response['status']) || $response['status'] !== 'OK') {
        $code = isset($response['status_code']) ? $response['status_code'] : 'unknown';

        fail('Ошибка запроса SMS.ru, код: ' . $code);
    }

    $sms = isset($response['sms'][$phone]) ? $response['sms'][$phone] : null;

    if (!is_array($sms)
        || !isset($sms['status'])
        || $sms['status'] !== 'OK') {
        $code = is_array($sms) && isset($sms['status_code'])
            ? $sms['status_code']
            : 'unknown';

        fail('SMS не принято на отправку, код: ' . $code);
    }

    echo !empty($params['test'])
        ? "Тестовый запрос выполнен, SMS не отправлено.\n"
        : "SMS принято сервисом на отправку.\n";
}
else {
    exit(0);
}