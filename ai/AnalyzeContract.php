<?php
namespace AI;

class AnalyzeContract
{
    const DataMissing = 'Отсутствуют необходимые данные';

    /**
     * Запускает анализ договора и посещает запросы к ИИ в очередь
     * @param string $filePath Путь к файлу в формате doc или docx
     * @param int $recordID Запись в таблице agency_contracts
     * @param string $LLM Языковая модель
     * @return array Массив с ID созданных записей в таблице log_ai_request или ошибками
     * */
    public static function analyzeFile($filePath, $recordID, $LLM = AI::Gemini_exp)
    {
        $agencyContract = DB::getAssoc("
            SELECT 
                RecordID,
                lair.LogID
            FROM agency_contracts
            LEFT JOIN log_ai_request lair ON lair.GroupUID = 'AgencyContract.{$recordID}'
            WHERE RecordID = '{$recordID}'
        ");
        if (empty($agencyContract['RecordID'])) {
            return ['error' => "Запись с RecordID = {$recordID} в agency_contracts не найдена"];
        }
        elseif ($agencyContract['LogID']) {
            return ['error' => 'Для этого файла уже начат анализ'];
        }

        $fileText = self::getTextFromDocx($filePath);
        if ($fileText['error']){
            return $fileText;
        }
        else {
            $fileText = $fileText['text'];
        }

        $valid = self::validateText($fileText);
        if ($valid['error']){
            return $valid;
        }

        $sectionOrder = [
            "ТИП ДОГОВОРА",
            "ОБЪЕКТ НЕДВИЖИМОСТИ",
            "ПРАВА ВЛАДЕНИЯ ОБЪЕКТОМ",
            "СРОК ДОГОВОРА",
            "ВОЗНАГРАЖДЕНИЕ",
            "УСЛОВИЯ ВЫПЛАТЫ",
            "СРОК ВЫПЛАТЫ ВОЗНАГРАЖДЕНИЯ",
            "ПОДПИСАНИЕ АКТА ОКАЗАНИЯ УСЛУГ",
            "ОТВЕТСТВЕННОСТЬ ЗА ПРОСРОЧКУ",
            "ВОЗВРАТ ВОЗНАГРАЖДЕНИЯ",
            "РЕГИСТРАЦИЯ КЛИЕНТА",
            "РАСТОРЖЕНИЕ ДОГОВОРА",
            "ПОВЫШЕННЫЕ ОБЯЗАТЕЛЬСТВА",
            "НУМЕРАЦИЯ ПУНКТОВ"
        ];
        $rulesMD = file_get_contents(__DIR__.'/contract_rules.md');
        $rules = [];
        $section = false;
        $subsectionAlias = false;
        foreach (explode("\n", $rulesMD) as $ruleRow) {
            $ruleRow = trim($ruleRow);
            if (empty($ruleRow)) continue;

            if (mb_substr($ruleRow, 0, 3) == '## ') {
                $section = substr($ruleRow, 3);
                $rules[$section] = [
                    'rules' =>      [],
                    'standard' =>   [],
                    'attention' =>  [],
                    'problems' =>   [],
                ];
            }
            elseif (mb_substr($ruleRow, 0, 4) == '### ') {
                $subsection = mb_strtolower(substr($ruleRow, 4));
                $subsectionAlias = false;
                if ($subsection == 'правила') $subsectionAlias = 'rules';
                elseif ($subsection == 'стандарт') $subsectionAlias = 'standard';
                elseif ($subsection == 'обрати внимание') $subsectionAlias = 'attention';
                elseif ($subsection == 'проблемы') $subsectionAlias = 'problems';
            }
            elseif (mb_substr($ruleRow, 0, 2) == '- ') {
                $item = substr($ruleRow, 2);
                $rules[$section][$subsectionAlias][] = $item;
            }
        }

        if (empty($rules)) {
            return ['error' => 'Не удалось сформулировать правила анализа. Файл contract_rules.md отсутствует или заполнен некорректно'];
        }

        $resultIDs = [];
        foreach($sectionOrder as $section){
            $prompt = self::createSectionPrompt($section, $rules[$section], $fileText);
            $result = AI::saveRequest($prompt, $LLM, [
                'Callback' => '\AI\AnalyzeContract::parseCallback',
                'CallbackData' => [
                    'section' => $section,
                    'filePath' => $filePath,
                ],
                'Context' => [[
                    'role' => 'system',
                    'content' => 'Ты - опытный юрист, специализирующийся на защите интересов агентств недвижимости.'
                ]],
                'GroupUID' => "AgencyContract.{$recordID}",
            ]);
            if ($result['ID']) {
                $resultIDs[] = intval($result['ID']);
            }
        }
        return [
            'ID' => $resultIDs,
            'warning' => $valid['warning']
        ];
    }

    /**
    * Ищет название клиента в тексте
    * @param $text
    * @return string Название клиента
    * */
    static function extractClientFromText($text)
    {
        $patterns = [
            # Ищем клиента после слова "Клиент" или "клиент"
            '(?:[Кк]лиент[ом]?\b(?!.*(?:Принципал|Агент).{0,50}).{0,50}?(ООО\s+[«"].*?[»"]))',

            # Ищем клиента после слова "Арендатор" или "арендатор"
            '(?:[Аа]рендатор[ом]?\b(?!.*(?:Принципал|Агент).{0,50}).{0,50}?(ООО\s+[«"].*?[»"]))',

            # Ищем в квадратных скобках с упоминанием клиента
            '\[(?:[^]]*?[Кк]лиент[^]]*?)(ООО\s+[«"].*?[»"])[^]]*?\]',

            # Ищем после двоеточия с упоминанием клиента
            '[Кк]лиент\s*:\s*(ООО\s+[«"].*?[»"])',

            # Ищем после тире с упоминанием клиента
            '[Кк]лиент\s*[-—–]\s*(ООО\s+[«"].*?[»"])',

            # Ищем в начале строки с упоминанием клиента
            '^\s*[Кк]лиент\s*[-—–:]\s*(ООО\s+[«"].*?[»"])',

            # Ищем конкретно ООО «СелфКиоск»
            '(ООО\s+[«"]СелфКиоск[»"])'
        ];
        $clientName = '';
        foreach ($patterns as $pattern) {
            mb_ereg($pattern, $text,$matches);
            $match = $matches[0];
            if ($match && !mb_ereg_match('Принципал|Агент', $match)) {
                $clientName = $match;
                break;
            }
        }

        if (empty($clientName)) {
            mb_ereg('[Кк]лиент\s*[-—–]\s*(ООО\s+[«"].*?[»"])', $text, $allCompanies);
            $knownCompanies = [
                'прогресс.*финанс',
                'офисы\s+онлайн',
                'управляющая\s+компания',
                'столичная\s+недвижимость'
            ];
            foreach ($allCompanies as $company) {
                foreach ($knownCompanies as $knownCompany) {
                    if (mb_ereg_match($knownCompany, $company)) continue 2;
                }
                $clientName = $company;
                break;
            }
        }

        return $clientName;
    }

    /**
     * Получает текст из файла doc или docx
     * @param string $filePath Путь к файлу в формате doc или docx
     * @return array Массив с текстом или ошибкой
     * */
    public static function getTextFromDocx($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension == 'doc') {
            if (($fh = fopen($filePath, 'r')) !== false) {
                $headers = fread($fh, 0xA00);

                $n1 = ( ord($headers[0x21C]) - 1 );
                $n2 = ( ( ord($headers[0x21D]) - 8 ) * 256 );
                $n3 = ( ( ord($headers[0x21E]) * 256 ) * 256 );
                $n4 = ( ( ( ord($headers[0x21F]) * 256 ) * 256 ) * 256 );

                $textLength = ($n1 + $n2 + $n3 + $n4);

                $extracted_plaintext = fread($fh, $textLength);
                $extracted_plaintext = mb_convert_encoding( $extracted_plaintext, 'UTF-8', 'UTF-16LE' );

                $text = preg_replace("/[^а-яёЁa-zA-Z0-9\s,.\-\n\r\t@\/_()«»:'\"+№%]/iu",'', $extracted_plaintext);
                return ['text' => $text];
            } else {
                return ['error' => 'Невозможно прочитать файл'];
            }
        }
        elseif ($extension == 'docx') {
            $content = '';
            $zip = zip_open($filePath);

            if (!$zip || is_numeric($zip)) return false;

            while ($zip_entry = zip_read($zip)) {
                if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
                if (zip_entry_name($zip_entry) != "word/document.xml") continue;
                $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                zip_entry_close($zip_entry);
            }

            zip_close($zip);

            $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
            $content = str_replace('</w:r></w:p>', "\r\n", $content);
            $text = strip_tags($content);
            return ['text' => $text];
        }
        else {
            return ['error' => 'Неизвестный формат файла'];
        }
    }

    /**
     * Валидация текста договора с отбрасыванием приложений
     * @param $text
     * @return array Массив с успехом или ошибкой
     * */
    static function validateText($text)
    {
        $lowerText = mb_strtolower(trim($text));
        if (empty($lowerText)) {
            return ['error' => 'Пустой текст договора'];
        }
        $textLen = mb_strlen($text);
        if ($textLen < 100) {
            return ['error' => "Текст договора слишком короткий: {$textLen} символов"];
        }

	    //return ['success' => true];

        /*$cutoffKeywords = ["Приложение", "Форма акта представления клиентов", "Форма акта оказания услуг"];
        foreach ($cutoffKeywords as $keyword) {
            $cutPos = mb_strpos($lowerText, mb_strtolower($keyword));
            if ($cutPos !== false) {
                $lowerText = mb_substr($lowerText, 0, $cutPos);
            }
        }*/


        $requiredTerms = [
            'договор' => ['договор', 'соглашение'],
            'стороны' => ['стороны', 'заказчик', 'исполнитель', 'агент', 'принципал'],
            'предмет' =>['предмет', 'поручает', 'обязуется'],
            'срок' => ['срок', 'период', 'действует', 'действия'],
            'вознаграждение' => ['вознаграждение', 'оплата услуг', 'стоимость услуг', 'агентское вознаграждение', 'оплатить услуги'],
            'порядок оплаты' => ['порядок оплаты', 'порядок расчетов', 'оплата производится', 'выплата вознаграждения', 'выплачивает']
        ];

        $missingTerms = [];
        foreach ($requiredTerms as $termKey => $terms) {
            foreach ($terms as $term) {
                if (mb_strpos($lowerText, $term) === false) {
                    $missingTerms[$termKey][] = $term;
                }
            }
        }

        $warning = [];
        foreach ($missingTerms as $termKey => $terms) {
            if (count($terms) !== count($requiredTerms[$termKey]))
                continue;
            else {
                $warning[] = "В основном тексте договора не найден элемент: {$termKey}";
            }
            if (in_array($termKey, ['вознаграждение', 'предмет', 'стороны', 'договор'])) {
                return ['error' => "Отсутствует критический элемент договора: {$termKey}"];
            }
        }
        return [
            'success' => true,
            'warning' => $warning
        ];
    }

    /**
     * Создание промпта для анализа секции
     * @param string $section Название секции
     * @param array $rules Перечень правил для секции
     * @param string $text Текст договора
     * @return string Промпт
     */
    static function createSectionPrompt($section, $rules, $text){
        $pRules = $rules['rules'] ? implode("\n", $rules['rules']) : "";
        $pStandard = $rules['standard'] ? implode("\n", $rules['standard']) : "";
        $pAttention = $rules['attention'] ? implode("\n", $rules['attention']) : "";
        $pProblems = $rules['problems'] ? implode("\n", $rules['problems']) : "";
        $prompt = "Правила анализа:
$pRules

Стандарт:
$pStandard

На что обратить внимание:
$pAttention

Возможные проблемы:
$pProblems

Текст договора:
{$text}

";

        if (mb_strtolower($section) == "нумерация пунктов"){
            $prompt = "Проанализируй нумерацию пунктов и подпунктов в договоре.
            
{$prompt}

Предоставь структурированный ответ в следующем формате:

АНАЛИЗ:
Обязательно! Подробно опиши структуру нумерации в договоре:
1. Как организована нумерация основных пунктов
2. Как организована нумерация подпунктов
3. Какой стиль оформления списков используется
4. Есть ли нарушения в нумерации
5. Есть ли несоответствия в ссылках на пункты

ЦИТАТА:
Обязательно при наличии нарушений! Приведи примеры конкретных пунктов с неправильной нумерацией или оформлением.

РИСКИ:
Обязательно! Перечисли все найденные нарушения в нумерации. Если нарушений нет, укажи 'Риски не выявлены'.
- Риск 1 (с указанием конкретных пунктов)
- Риск 2
...

РЕКОМЕНДАЦИИ:
Если есть нарушения, предложи конкретные исправления для каждого случая.
- Рекомендация 1
- Рекомендация 2
...

ДЕЙСТВИЯ:
Если есть нарушения, укажи конкретные действия по исправлению нумерации.
Если нарушений нет, укажи 'Корректировка не требуется'.
- Действие 1
- Действие 2
...

УРОВЕНЬ РИСКА:
Обязательно! Оцени уровень риска (critical/high/medium/low/none).
Используй:
- critical: серьезные нарушения, затрудняющие понимание договора
- high: множественные нарушения нумерации
- medium: отдельные нарушения, не влияющие на понимание
- low: незначительные отклонения в оформлении
- none: нарушений нет

Важно: 
1. В анализе обязательно опиши структуру нумерации, даже если нарушений нет
2. При наличии нарушений обязательно приведи конкретные примеры в цитатах
3. Действия должны быть конкретными и привязанными к выявленным нарушениям
";
        }
        else {
            $prompt = "Проанализируй следующий раздел договора: {$section}

{$prompt}

Предоставь структурированный ответ в следующем формате:

АНАЛИЗ:
Обязательно! Подробно опиши анализ раздела. Если информация отсутствует, укажи это и объясни почему.

ЦИТАТА:
Опционально. Приведи релевантные цитаты из договора, если они есть и подтверждают анализ.

РИСКИ:
Обязательно! Перечисли выявленные риски. Если рисков нет, укажи 'Риски не выявлены'.
- Риск 1
- Риск 2
...

РЕКОМЕНДАЦИИ:
Обязательно, если есть риски! Предложи конкретные рекомендации по исправлению каждого риска.
- Рекомендация 1
- Рекомендация 2
...

ДЕЙСТВИЯ:
Обязательно, если есть рекомендации! Укажи конкретные действия для реализации рекомендаций.
- Действие 1
- Действие 2
...

УРОВЕНЬ РИСКА:
Обязательно! Оцени уровень риска (critical/high/medium/low/none).
Используй:
- critical: для критических проблем, требующих немедленного вмешательства
- high: для серьезных проблем, требующих скорого решения
- medium: для проблем, которые нужно решить, но не срочно
- low: для незначительных проблем
- none: если проблем нет

Важно: 
1. Всегда начинай каждую секцию с её названия заглавными буквами (АНАЛИЗ:, ЦИТАТА: и т.д.)
2. Обязательно включи секции АНАЛИЗ, РИСКИ и УРОВЕНЬ РИСКА
3. Остальные секции включай при необходимости
4. Если в разделе нет проблем, укажи это явно
";
        }

        return $prompt;
    }

    /**
     * Анализ секции
     * @param string $response Ответ от ИИ
     * @param string $sectionName Название секции
     * @return array Данные о секции
     */
    static function parseResponse($response, $sectionName)
    {
        $sections = [
            'АНАЛИЗ:' => [],
            'ЦИТАТА:' => [],
            'РИСКИ:' => [],
            'РЕКОМЕНДАЦИИ:' => [],
            'ДЕЙСТВИЯ:' => [],
            'УРОВЕНЬ РИСКА:' => []
        ];
        $currentSection = false;
        $rows = explode("\n", $response);
        $end = 'end';
        $rows[] = $end;

        foreach ($rows as $row) {
            $row = trim($row, ' *#');
            if (empty($row) || $row == $end){
                continue;
            }

            foreach ($sections as $sectionKey => $section) {
                if (mb_strpos($row, $sectionKey) === 0) {
                    $currentSection = $sectionKey;
                    continue 2;
                }
            }

            if ($sectionName != 'НУМЕРАЦИЯ ПУНКТОВ' || $currentSection !== 'ЦИТАТА:') {
                $row = trim($row, '- ');
                if (empty($row)) {
                    continue;
                }
            }

            $sections[$currentSection][] = $row;
        }

        if (!empty($sections['РИСКИ:'])) {
            if (empty($sections['РЕКОМЕНДАЦИИ:'])) {
                $sections['РЕКОМЕНДАЦИИ:'] = ["Требуются рекомендации по выявленным рискам"];
            }
            if (empty($sections['ДЕЙСТВИЯ:'])){
                $sections['ДЕЙСТВИЯ:'] = ["Требуются конкретные действия по рекомендациям"];
            }
        }
        else {
            $sections['РИСКИ:'] = ['Риски не выявлены'];
        }

        $riskLevel = mb_strtolower(trim(implode("\n", $sections['УРОВЕНЬ РИСКА:'])));
        foreach (['critical', 'high', 'medium', 'low', 'none'] as $riskType) {
            $keyPos = mb_strpos($riskLevel, $riskType);
            if ($keyPos !== false){
                $riskLevel = $riskType;
                break;
            }
        }

        if (!in_array($riskLevel, ['critical', 'high', 'medium', 'low', 'none'])){
            $riskLevel = 'none';
        }

        return [
            'title' => $sectionName,
            'analysis' => implode("\n", $sections['АНАЛИЗ:']) ?: self::DataMissing,
            'quote' => implode("\n", $sections['ЦИТАТА:']),
            'risks' => $sections['РИСКИ:'],
            'recommendations' => $sections['РЕКОМЕНДАЦИИ:'],
            'actions' => $sections['ДЕЙСТВИЯ:'],
            'risk_level' => $riskLevel
        ];
    }

    static function normalizeKey($key)
    {
        return str_replace(' ','_', mb_strtoupper($key));
    }

    /**
     * Собирает структурированные данные о секциях, адресе, заказчике, исполнителе и клиенте
     * @param array $results Массив с анализами секций
     * @param string $filePath Путь к файлу договора
     * @return array Данные о секции
     */
    static function createOutputJson($results, $filePath){
        $address = self::DataMissing;

        if ($results['ОБЪЕКТ_НЕДВИЖИМОСТИ']){
            $analysis = $results['ОБЪЕКТ_НЕДВИЖИМОСТИ']['analysis'];
            $quote = $results['ОБЪЕКТ_НЕДВИЖИМОСТИ']['quote'];
            $textToSearch = [$analysis, $quote];


            foreach ($textToSearch as $text){
                foreach (explode("\n", $text) as $line) {
                    $start = mb_strpos($line, '[');
                    $end = mb_strpos($line, ']');
                    if ($start !== false && $end !== false && $end > $start) {
                        $address = mb_substr($line, $start + 1, $end - $start - 1);
                        break 2;
                    } else {
                        foreach (['адрес:', 'расположен:', 'находится:'] as $keyWord) {
                            $keyPos = mb_strpos(mb_strtolower($line), $keyWord);
                            if ($keyPos !== false) {
                                $address = trim(explode(':', $line)[1], '[]". ');
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $allText = '';
        $fileText = self::getTextFromDocx($filePath);
        if (!$fileText['error']){
            $allText = $fileText['text'];
        }
        else {
            foreach ($results as $result){
                $allText .= $result['analysis']."\n";
                $allText .= $result['quote']."\n";
            }
        }


        $customerPatterns = [
            'ООО\s+[«"].*?[»"]\s*[«"]Д\.У\.[»"]\s*.*?[«"]Столичная\s+недвижимость[»"]',
            'ООО\s+[«"]Управляющая\s+компания\s+[«"]Прогресс-Финанс[»"]\s*[«"]Д\.У\.[»"]\s*Закрытого\s+паевого\s+инвестиционного\s+фонда\s+недвижимости\s+[«"]Столичная\s+недвижимость[»"]'
        ];
        $customerName = self::DataMissing;
        foreach ($customerPatterns as $pattern){
            mb_ereg($pattern, $allText,$customerMatches);
            if ($customerMatches[0]){
                $customerName = explode('ООО ', $customerMatches[0]);
                $customerName = 'ООО '.end($customerName);
                break;
            }
        }

        mb_ereg('ООО\s+[«"]ОФИСЫ\s+ОНЛАЙН[»"]', $allText,$executorMatches);
        $executorName = $executorMatches[0]?: self::DataMissing;

        $clientName = self::DataMissing;
        if ($quote = $results['ВОЗНАГРАЖДЕНИЕ']['quote']){
            mb_ereg('АГЕНТ\s+([«"]?(?:ООО|ЗАО|ОАО|ИП|ПАО)\s+[«""]?[^"«»]+)', $quote,$clientMatches);
            if ($clientMatches[0]){
                $clientName = trim($clientMatches[0]);
                $clientName = str_replace(['"', '«', '»'], '', $clientName);
            }
        }

        if (empty($clientName) || $clientName == self::DataMissing){
            $clientName = self::extractClientFromText($allText);
        }

        return [
            'metadata' => [
                'analysis_date' => date('c'),
                'version' => '1.0',
            ],
            'sections' => $results,
            'brief' => [
                'address' => $address,
                'customer' => $customerName,
                'executor' => $executorName,
                'client' => $clientName,
            ]
        ];
    }

    /**
     * Коллбэк для очереди log_ai_request
     * @return array Массив с текстом или ошибкой
     */
    public static function parseCallback($params)
    {
        $logID = $params['LogID'];
        $logGroupUID = DB::getItem("SELECT GroupUID FROM log_ai_request WHERE LogID = '{$logID}'");
        $allLogs = DB::getAssocs("SELECT Answer, CallbackData FROM log_ai_request WHERE GroupUID = '{$logGroupUID}'");

        $noAnswer = 0;
        foreach ($allLogs as $log){
            if (!$log['Answer']){
                $noAnswer++;
            }
        }

        if ($noAnswer != 0){
            return ['success' => true];
        }

        $results = [];
        $filePath = '';
        foreach ($allLogs as $logData){
            $cbData = json_decode($logData['CallbackData'], true);
            $filePath = $cbData['filePath'];
            $sectionName = $cbData['section'];
            $response = $logData['Answer'];
            $results[self::normalizeKey($sectionName)] = self::parseResponse($response, $sectionName);
        }

        $json = self::createOutputJson($results, $filePath);

        if ($json){
            $serializeResult = json_encode($json, JSON_UNESCAPED_UNICODE);

            $recordID = explode('AgencyContract.', $logGroupUID)[1];
            \helpers\AgencyContracts::updateReviewedContract([
                'Result' => $serializeResult,
                'RecordID' => $recordID
            ]);
            return ['success' => true];
        }
        return ['error' => $results];
    }
}