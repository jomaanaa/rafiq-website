<?php
/**
 * Rafiq Chatbot — AI backend endpoint (uses Groq API)
 * POST /general/chatbot_api.php
 *
 * Groq is free, fast, and works in Egypt.
 * API key lives in: /pgdb/chatbot_config.php  (never sent to browser)
 */

require __DIR__ . '/../pgdb/chatbot_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

/* ── Parse request ────────────────────────────────────────── */
$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$userMessage = trim((string)($body['message'] ?? ''));
$history     = is_array($body['history'] ?? null) ? $body['history'] : [];

if ($userMessage === '') {
    echo json_encode(['reply' => 'Please type a message.', 'source' => 'error']);
    exit;
}

/* ── No key configured ────────────────────────────────────── */
if (!defined('RAFIQ_AI_KEY') || RAFIQ_AI_KEY === '') {
    echo json_encode([
        'reply'  => "⚙️ **Setup needed:** Open `/pgdb/chatbot_config.php` and paste your free Groq key into `RAFIQ_AI_KEY`.\n\nGet a free key at **https://console.groq.com** (sign in with Google, click API Keys → Create API Key). No credit card needed.",
        'source' => 'fallback',
        'setup'  => true
    ]);
    exit;
}

/* ── Call Groq ────────────────────────────────────────────── */
[$reply, $error] = callGroq($userMessage, $history);

if ($reply !== null) {
    echo json_encode(['reply' => $reply, 'source' => 'groq']);
} else {
    echo json_encode([
        'reply'  => "Sorry, I couldn't reach the AI right now. Error: $error\n\nPlease try again in a moment.",
        'source' => 'error'
    ]);
}

/* ════════════════════════════════════════════════════════════
   Groq API — OpenAI-compatible, free, works worldwide
   Endpoint: https://api.groq.com/openai/v1/chat/completions
   ════════════════════════════════════════════════════════════ */
function callGroq(string $userMsg, array $history): array
{
    /* Build messages array: system prompt first, then history, then user */
    $messages = [
        ['role' => 'system', 'content' => RAFIQ_SYSTEM_PROMPT]
    ];

    /* Append conversation history for multi-turn context */
    foreach ($history as $turn) {
        $role    = ($turn['role'] ?? '') === 'model' ? 'assistant' : 'user';
        $content = trim((string)($turn['content'] ?? ''));
        if ($content !== '') {
            $messages[] = ['role' => $role, 'content' => $content];
        }
    }

    /* Current user message */
    $messages[] = ['role' => 'user', 'content' => $userMsg];

    $payload = json_encode([
        'model'       => RAFIQ_AI_MODEL,
        'messages'    => $messages,
        'temperature' => RAFIQ_AI_TEMP,
        'max_tokens'  => RAFIQ_AI_TOKENS,
        'stream'      => false,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . RAFIQ_AI_KEY,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,   // safe for localhost/XAMPP
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [null, "cURL error: $curlErr"];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? "HTTP $httpCode — $response";
        return [null, $msg];
    }

    $text = $data['choices'][0]['message']['content'] ?? null;
    if ($text === null) {
        return [null, 'No content in response: ' . substr($response, 0, 300)];
    }

    return [trim($text), null];
}
