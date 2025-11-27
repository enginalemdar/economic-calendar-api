<?php
/**
 * Economic Calendar API.
 *
 * It uses the investing.com as data source and using a "web crawling" methodology,
 * relevant data is captured and returned in a more well-structured data model, in this
 * case it will return a JSON.
 *
 * There is no guarantees about the availability or stability of this API, changes
 * can be done in source page that can result in a crash of crawler methodology.
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/vendor/kub-at/php-simple-html-dom-parser/src/KubAT/PhpSimple/HtmlDomParser.php";

use KubAT\PhpSimple\HtmlDomParser;

/**
 * JSON hata döndür ve çık.
 */
function errorResponse(string $message, int $statusCode = 502): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error'   => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Temizlik fonksiyonu
 */
function sanitize(?string $str): ?string
{
    if ($str === null) {
        return null;
    }
    return trim(str_replace("&nbsp;", "", $str));
}

// --- 1) Kaynak sayfayı User-Agent ile çekmeyi dene ---

$url = "https://sslecal2.forexprostools.com/";

$opts = [
    'http' => [
        'method' => 'GET',
        'header' =>
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) " .
            "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36\r\n" .
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
            "Accept-Language: en-US,en;q=0.5\r\n" .
            "Connection: close\r\n"
    ]
];

$context = stream_context_create($opts);

// HtmlDomParser'ın file_get_html metodu 3. parametre olarak context alabiliyor
$dom = HtmlDomParser::file_get_html($url, false, $context, 0);

// 403 vs durumunda $dom false gelir, bunu yakalıyoruz
if (!$dom) {
    errorResponse("Kaynak ekonomik takvim sayfasına ulaşılamadı (403 Forbidden veya başka bir HTTP hatası).");
}

// --- 2) Tabloyu bul (id içinde # olmamalı!) ---

// ÖNEMLİ: getElementById('#ecEventsTable') YANLIŞ, doğru: 'ecEventsTable'
$table = $dom->getElementById('ecEventsTable');

if (!$table) {
    errorResponse("Kaynak sayfada 'ecEventsTable' id'li tablo bulunamadı. Sayfanın HTML yapısı değişmiş olabilir.");
}

$rows = $table->find("tr[id*='eventRowId']");
$data = [];

// --- 3) Satırları parse et ---

foreach ($rows as $element) {
    $economy  = $element->find("td.flagCur", 0);
    $impactTd = $element->find("td.sentiment", 0);

    $nameTd      = $element->find("td.event", 0);
    $actualTd    = $element->find("td.act", 0);
    $forecastTd  = $element->find("td.fore", 0);
    $previousTd  = $element->find("td.prev", 0);

    $impactIcons = $impactTd ? $impactTd->find("i.grayFullBullishIcon") : [];

    $data[] = [
        "economy"  => $economy ? sanitize($economy->text()) : null,
        "impact"   => is_array($impactIcons) ? count($impactIcons) : 0,
        "date"     => isset($element->attr["event_timestamp"]) ? $element->attr["event_timestamp"] : null,
        "name"     => $nameTd ? sanitize($nameTd->text()) : null,
        "actual"   => $actualTd ? sanitize($actualTd->text()) : null,
        "forecast" => $forecastTd ? sanitize($forecastTd->text()) : null,
        "previous" => $previousTd ? sanitize($previousTd->text()) : null,
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
exit;
