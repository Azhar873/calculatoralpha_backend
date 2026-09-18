<?php
/**
 * Analyze a calculator screenshot with Groq Vision.
 * The provider key must only exist on the server (GROQ_API_KEY environment variable).
 */

if (!function_exists('sendResponse')) {
    function sendResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}

require_once __DIR__ . '/../config/groq.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Only POST requests are supported.'], 405);
}

if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(['error' => 'Please upload a calculator screenshot.'], 400);
}

$upload = $_FILES['screenshot'];
if ($upload['size'] > 10 * 1024 * 1024) {
    sendResponse(['error' => 'The image must be smaller than 10 MB.'], 413);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($upload['tmp_name']);
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedTypes, true)) {
    sendResponse(['error' => 'Only PNG, JPG, and WEBP images are supported.'], 415);
}

$apiKey = getenv('GROQ_API_KEY') ?: GROQ_API_KEY;
if (!$apiKey || $apiKey === 'PASTE_NEW_GROQ_KEY_HERE') {
    sendResponse(['error' => 'Groq is not configured on the server. Add GROQ_API_KEY to the backend environment.'], 503);
}

$imageData = base64_encode(file_get_contents($upload['tmp_name']));
$prompt = <<<'PROMPT'
Analyze this image. Do not explain or reason. Start your response immediately with one compact, valid JSON object on one line. Detect whether it is a multiple-choice math question or a calculator form.
{
  "title": "short calculator name",
  "description": "one sentence",
    "type": "mcq or calculator",
    "question": "the complete visible math question",
    "options": [{"label":"a","text":"option text"}],
    "correctAnswer": "the exact correct option label and answer",
    "explanation": "short step-by-step explanation proving the answer",
  "resultLabel": "result name",
  "formula": "a safe arithmetic expression using only field ids",
  "fields": [{"id":"lowercase_identifier","label":"visible label","unit":"unit or empty","value":0}]
}
Rules: For an MCQ or number-pattern puzzle, transcribe the complete question and every option, determine the rule, solve it, and return the correct option in correctAnswer with a short explanation. For a calculator form, identify every numeric input. Use unique lowercase snake_case ids and numeric values. The formula must use only ids, numbers, parentheses, +, -, *, /, and ^. For questions, use an empty fields array and formula "0". Do not output reasoning, a markdown code fence, or any text before or after the JSON. If uncertain, explain the uncertainty instead of guessing.
PROMPT;

$model = getenv('GROQ_MODEL') ?: (defined('GROQ_MODEL') ? GROQ_MODEL : '');
if (!$model || $model === 'PASTE_ACCESSIBLE_VISION_MODEL_ID_HERE' || $model === 'openai/gpt-oss-20b') {
    sendResponse(['error' => 'Configure an accessible Groq vision model in backend/config/groq.php. openai/gpt-oss-20b is text-only and cannot read screenshots.'], 503);
}

$requestBody = json_encode([
    'model' => $model,
    'temperature' => 0,
    'max_tokens' => 2000,
    'reasoning_effort' => 'none',
    'messages' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . $imageData]],
        ],
    ]],
]);

$curl = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $requestBody,
]);
$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $curlError) {
    sendResponse(['error' => 'Could not reach Groq.'], 502);
}

$providerResponse = json_decode($responseBody, true);
$providerError = $providerResponse['error']['message'] ?? null;
if ($statusCode >= 400 || $providerError) {
    sendResponse([
        'error' => 'Groq request failed: ' . ($providerError ?: 'HTTP ' . $statusCode),
        'details' => $providerResponse['error']['failed_generation'] ?? null,
    ], 502);
}

$content = $providerResponse['choices'][0]['message']['content'] ?? '';
$content = trim((string) $content);
$content = preg_replace('/<think>.*?(<\/think>|$)/is', '', $content);
$content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

// Qwen can add surrounding text, so try every balanced JSON object it returns.
$candidates = [];
for ($start = 0; ($jsonStart = strpos($content, '{', $start)) !== false; $start = $jsonStart + 1) {
    $depth = 0;
    $inString = false;
    $escaped = false;
    for ($index = $jsonStart; $index < strlen($content); $index++) {
        $character = $content[$index];
        if ($inString) {
            if ($escaped) $escaped = false;
            elseif ($character === '\\') $escaped = true;
            elseif ($character === '"') $inString = false;
            continue;
        }
        if ($character === '"') $inString = true;
        if ($character === '{') $depth++;
        if ($character === '}') {
            $depth--;
            if ($depth === 0) {
                $candidates[] = substr($content, $jsonStart, $index - $jsonStart + 1);
                break;
            }
        }
    }
}
$calculator = null;
foreach ($candidates as $candidate) {
    $candidate = preg_replace('/^\xEF\xBB\xBF/', '', trim($candidate));
    $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate);
    $decoded = json_decode($candidate, true);
    if (is_array($decoded)) {
        $calculator = $decoded;
        break;
    }
}
if (!is_array($calculator)) {
    sendResponse([
        'error' => 'Groq returned an invalid calculator draft.',
        'details' => json_last_error_msg(),
        'provider_output' => substr($content, 0, 500),
    ], 502);
}

$fields = [];
foreach (($calculator['fields'] ?? []) as $field) {
    $id = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($field['id'] ?? ''));
    if (!$id || count($fields) >= 20) continue;
    $fields[] = [
        'id' => $id,
        'label' => trim((string) ($field['label'] ?? $id)),
        'unit' => trim((string) ($field['unit'] ?? '')),
        'value' => is_numeric($field['value'] ?? null) ? (float) $field['value'] : 0,
    ];
}

$formula = (string) ($calculator['formula'] ?? '0');
$formula = preg_replace('/\s+/', ' ', trim($formula));
if (!preg_match('/^[a-zA-Z0-9_+\-*\/().\s^]+$/', $formula)) $formula = '0';

sendResponse([
    'title' => trim((string) ($calculator['title'] ?? 'Screenshot calculator')) ?: 'Screenshot calculator',
    'description' => trim((string) ($calculator['description'] ?? '')),
    'type' => ($calculator['type'] ?? '') === 'mcq' ? 'mcq' : 'calculator',
    'question' => trim((string) ($calculator['question'] ?? '')),
    'options' => is_array($calculator['options'] ?? null) ? array_values(array_map(function ($option) {
        return [
            'label' => trim((string) ($option['label'] ?? '')),
            'text' => trim((string) ($option['text'] ?? '')),
        ];
    }, $calculator['options'])) : [],
    'correctAnswer' => trim((string) ($calculator['correctAnswer'] ?? '')),
    'explanation' => trim((string) ($calculator['explanation'] ?? '')),
    'resultLabel' => trim((string) ($calculator['resultLabel'] ?? 'Result')) ?: 'Result',
    'formula' => $formula,
    'fields' => $fields,
], $statusCode >= 400 ? 502 : 200);
