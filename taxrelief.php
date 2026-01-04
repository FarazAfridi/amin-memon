<?php

// header("Access-Control-Allow-Origin: http://127.0.0.1:5500");
// header("Access-Control-Allow-Methods: *");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Content-Type: application/json; charset=utf-8");

// Preflight
// if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
//   http_response_code(204);
//   exit;
// }

// Only POST
// if ($_SERVER["REQUEST_METHOD"] !== "POST") {
//   http_response_code(405);
//   echo json_encode(["ok" => false, "error" => "Method not allowed"]);
//   exit;
// }

// Basic anti-bot / abuse protections (recommended)
// $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// If you want to restrict to your domain, uncomment + set your domain:
// if ($origin && !preg_match('#^https?://(www\.)?taxexpertsofoc\.com$#i', $origin)) {
//   http_response_code(403);
//   echo json_encode(['ok' => false, 'error' => 'Forbidden origin']);
//   exit;
// }

// Helper: safely read POST values
function postv(string $key): string {
  $v = $_POST[$key] ?? '';
  if (is_array($v)) return '';
  return trim((string)$v);
}

function postarr(string $key): array {
  $v = $_POST[$key] ?? [];
  if (!is_array($v)) return [];
  // trim + remove empties
  $out = [];
  foreach ($v as $item) {
    $t = trim((string)$item);
    if ($t !== '') $out[] = $t;
  }
  return $out;
}

function join_pipe(array $arr): string {
  return implode(' | ', $arr);
}

// Convert "YYYY-MM-DDTHH:MM" (datetime-local) into America/Los_Angeles formatted string
function convertToPacificUSFormat(string $localDateTime): string {
  if ($localDateTime === '') return '';
  try {
    // Treat input as "local time" on the server; if your server is not Pacific,
    // you can still force interpretation by creating it in America/Los_Angeles.
    $tz = new DateTimeZone('America/Los_Angeles');
    $dt = new DateTime($localDateTime, $tz);
    return $dt->format('m/d/Y, h:i A');
  } catch (Exception $e) {
    return '';
  }
}

// Convert discharge date to m/d/Y
function formatDateUS(string $date): string {
  if ($date === '') return '';
  try {
    $dt = new DateTime($date);
    return $dt->format('m/d/Y');
  } catch (Exception $e) {
    return '';
  }
}

// Build your payload (same keys you used in JS)
$preferredCallLocal = postv('preferred_call_time');
$preferredCallPST   = convertToPacificUSFormat($preferredCallLocal);

$dischargeRaw  = postv('discharge_date');
$dischargeDate = formatDateUS($dischargeRaw);

$payload = [
  "What prompted you to seek tax relief?" => join_pipe(postarr('reason')),
  "Do you have any unfiled tax years?" => postv('unfiled'),
  "Which tax years are unfiled?" => join_pipe(postarr('tax_years')),
  "How much tax debt do you owe?" => postv('debt'),
  "Do you owe federal or state taxes?" => postv('jurisdiction'),
  "What type of tax issue do you have?" => postv('issue_type'),
  "Are you currently in bankruptcy?" => postv('bankruptcy'),
  "When do you expect your bankruptcy to discharge?" => postv('discharge_timing'),
  "First name" => postv('firstname'),
  "Last name" => postv('lastname'),
  "Email" => postv('email'),
  "Phone" => postv('phone'),
  "Discharge Date" => $dischargeDate,
  "Preferred Call Time" => $preferredCallPST,
];

// IMPORTANT: put your Google Apps Script URL ONLY here (server-side)
$googleEndpoint = "https://script.google.com/macros/s/AKfycbwqgK_1sh9oGu39Dhm7JxgFdVQ9l9dO1ZaBiC_R_25FzYldyAQWpPwCohRply5NyMoGxg/exec";

// Send as x-www-form-urlencoded (same as your JS)
$postFields = http_build_query($payload);

// Use cURL
$ch = curl_init($googleEndpoint);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $postFields,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
  ],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 15,
]);

$responseBody = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// If your Apps Script returns 200 even for errors, you may need to parse $responseBody.
// For now, treat HTTP >= 400 as failure.
if ($curlErr) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $curlErr]);
  exit;
}

if ($httpCode >= 400 && $httpCode !== 0) {
  http_response_code(502);
  echo json_encode(['ok' => false, 'error' => "Upstream error", 'status' => $httpCode]);
  exit;
}
echo "DONEEE"
echo json_encode(['ok' => true]);