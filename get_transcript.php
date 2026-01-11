<?php
/* ===============================
   CORS HEADERS (MUST BE FIRST)
================================ */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ===============================
   INPUT VALIDATION
================================ */
if (!isset($_GET['video_id']) || empty($_GET['video_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "No video ID provided"
    ]);
    exit;
}

$videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['video_id']);

/* ===============================
   FETCH PLAYER DATA (WITH RETRY)
================================ */
function fetch_player_data($videoId) {
    $url = "https://www.youtube.com/youtubei/v1/player";

    $payload = [
        "videoId" => $videoId,
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
            "content" => json_encode($payload),
            "timeout" => 10
        ]
    ];

    $context = stream_context_create($options);

    // First attempt
    $response = @file_get_contents($url, false, $context);

    // Retry once if failed (YouTube instability)
    if ($response === false) {
        sleep(1);
        $response = @file_get_contents($url, false, $context);
    }

    return $response ? json_decode($response, true) : null;
}

/* ===============================
   EXTRACT TRANSCRIPT
================================ */
function get_youtube_transcript($videoId) {

    $data = fetch_player_data($videoId);
    if (!$data) return false;

    $tracks =
        $data["captions"]["playerCaptionsTracklistRenderer"]["captionTracks"]
        ?? null;

    if (!$tracks || empty($tracks)) return false;

    $selectedTrack = null;

    /* ===============================
       PRIORITY LOGIC (RELAXED)
    ================================ */

    // 1. Prefer ANY English variant (en, en-US, en-IN, etc.)
    foreach ($tracks as $track) {
        if (strpos($track["languageCode"], "en") === 0) {
            $selectedTrack = $track;
            break;
        }
    }

    // 2. If no English found, take the first available caption
    if (!$selectedTrack) {
        $selectedTrack = $tracks[0];
    }

    /* ===============================
       FETCH XML CAPTIONS
    ================================ */
    $xmlData = @file_get_contents($selectedTrack["baseUrl"]);
    if ($xmlData === false) return false;

    $xml = @simplexml_load_string($xmlData);
    if ($xml === false) return false;

    $text = "";
    foreach ($xml->text as $line) {
        $text .= html_entity_decode((string)$line, ENT_QUOTES | ENT_XML1) . " ";
    }

    return trim($text);
}

/* ===============================
   RESPONSE
================================ */
$transcript = get_youtube_transcript($videoId);

if ($transcript) {
    echo json_encode([
        "success" => true,
        "transcript" => $transcript
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" =>
            "Captions are currently unavailable via YouTube API for this video."
    ]);
}
