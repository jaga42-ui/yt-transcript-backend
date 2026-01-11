<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_GET['video_id']) || empty($_GET['video_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "No video ID provided"
    ]);
    exit;
}

$videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['video_id']);

/* ===============================
   TRY TIMEDTEXT (MOST STABLE)
================================ */
function fetch_timedtext($videoId) {
    $url = "https://video.google.com/timedtext?lang=en&v=" . $videoId;

    $xmlData = @file_get_contents($url);
    if ($xmlData === false || trim($xmlData) === "") {
        return false;
    }

    $xml = @simplexml_load_string($xmlData);
    if ($xml === false || empty($xml->text)) {
        return false;
    }

    $text = "";
    foreach ($xml->text as $line) {
        $text .= html_entity_decode((string)$line) . " ";
    }

    return trim($text);
}

$transcript = fetch_timedtext($videoId);

if ($transcript) {
    echo json_encode([
        "success" => true,
        "transcript" => $transcript
    ]);
    exit;
}

/* ===============================
   FAILURE RESPONSE
================================ */
echo json_encode([
    "success" => false,
    "error" =>
        "Captions are not accessible programmatically for this video."
]);
