<?php
// ===============================
// FORCE CORS (MUST BE FIRST)
// ===============================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===============================
// INPUT VALIDATION
// ===============================
if (!isset($_GET['video_id']) || empty($_GET['video_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "No Video ID provided"
    ]);
    exit;
}

$videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['video_id']);

// ===============================
// TRANSCRIPT FUNCTION
// ===============================
function get_youtube_transcript($vId) {

    $url = "https://www.youtube.com/youtubei/v1/player";

    $data = [
        "videoId" => $vId,
        "context" => [
            "client" => [
                "hl" => "en",
                "clientName" => "WEB",
                "clientVersion" => "2.20240210.00.00"
            ]
        ]
    ];

    $options = [
        "http" => [
            "header" =>
                "Content-Type: application/json\r\n" .
                "User-Agent: Mozilla/5.0\r\n",
            "method" => "POST",
            "content" => json_encode($data),
            "timeout" => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return false;

    $resData = json_decode($response, true);
    if (!$resData) return false;

    $tracks =
        $resData["captions"]["playerCaptionsTracklistRenderer"]["captionTracks"]
        ?? null;

    if (!$tracks) return false;

    $track = $tracks[0];

    foreach ($tracks as $t) {
        if ($t["languageCode"] === "en" && !isset($t["kind"])) {
            $track = $t;
            break;
        }
    }

    $xmlData = @file_get_contents($track["baseUrl"]);
    if ($xmlData === false) return false;

    $xml = @simplexml_load_string($xmlData);
    if ($xml === false) return false;

    $text = "";
    foreach ($xml->text as $line) {
        $text .= html_entity_decode((string)$line) . " ";
    }

    return trim($text);
}

// ===============================
// RESPONSE
// ===============================
$transcript = get_youtube_transcript($videoId);

if ($transcript) {
    echo json_encode([
        "success" => true,
        "transcript" => $transcript
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "No transcript found"
    ]);
}
