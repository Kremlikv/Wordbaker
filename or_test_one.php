<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$model = 'deepseek/deepseek-chat-v3-0324:free';
$sys = 'Reply ONLY as {"distractors":["...","...","..."]} (JSON).';
$user = 'Czech: "stůl" -> correct German: "Tisch". Return 12 plausible wrong answers, JSON only.';

$payload = [
  'model' => $model,
  'response_format' => ['type' => 'json_object'],
  'messages' => [
    ['role'=>'system','content'=>$sys],
    ['role'=>'user','content'=>$user],
  ],
  'max_tokens' => 200,
  'temperature' => 0.4,
];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    "Authorization: Bearer $OPENROUTER_API_KEY",
  ],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_TIMEOUT => 25,
]);
$res = curl_exec($ch);
$info = curl_getinfo($ch);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP: ".($info['http_code'] ?? 'n/a')."\n";
if ($err) echo "cURL: $err\n";
echo "RAW:\n$res\n";
