<?php

declare(strict_types=1);

$to            = 'hasanrafiul32@gmail.com'; 
$siteName      = 'Droidco';
$fromAddress   = 'hello@droidco.co';
$fromName      = 'Droidco Contact';
$envelopeFrom  = 'hello@droidco.co';
$subjectPrefix = 'New Contact Form Submission';

session_start();
$now = time();
if (!isset($_SESSION['last_submit_ts'])) {
    $_SESSION['last_submit_ts'] = 0;
}
$secondsSinceLast = $now - (int)$_SESSION['last_submit_ts'];

function isAjax(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($accept, 'application/json') !== false) || (strtolower($xhr) === 'xmlhttprequest') || (strtolower($xhr) === 'fetch');
}

function respondJson(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function respondHtml(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html lang=\"en\"><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\" />
<title>Contact | {$safeMsg}</title>
<body style=\"font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; padding: 2rem; color: #e2e8f0; background:#0b0b1a\">
  <div style=\"max-width: 720px; margin: 0 auto; background:#1f1b3a; padding: 1.5rem; border-radius: 12px; border:1px solid rgba(255,255,255,0.1)\">
    <p>{$safeMsg}</p>
    <p><a href=\"/\" style=\"color:#a78bfa; text-decoration: underline\">Back to site</a></p>
  </div>
</body></html>";
    exit;
}

function finish(bool $ok, string $message): void {
    if (isAjax()) {
        respondJson($ok ? 200 : 400, ['ok' => $ok, 'message' => $message]);
    } else {
        respondHtml($ok ? 200 : 400, $message);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, 'Invalid request method.');
}

if ($secondsSinceLast < 30) {
    finish(false, 'Please wait a few seconds before sending another message.');
}

$honeypot = trim((string)($_POST['website'] ?? '')); // honeypot: should be empty
$name     = trim((string)($_POST['name'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$subject  = trim((string)($_POST['subject'] ?? ''));
$message  = trim((string)($_POST['message'] ?? ''));
$consent  = isset($_POST['consent']);

// Honeypot check: pretend success to avoid tipping off bots
if ($honeypot !== '') {
    $_SESSION['last_submit_ts'] = $now;
    finish(true, 'Thanks! If this were a real submission, we’d be in touch soon.');
}

// Validate fields
$errors = [];
if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    $errors[] = 'Please enter your name (2–120 characters).';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    $errors[] = 'Please enter a valid email address.';
}
if ($message === '' || mb_strlen($message) < 10) {
    $errors[] = 'Please enter a message (at least 10 characters).';
}
if (!$consent) {
    $errors[] = 'You must agree to be contacted about your inquiry.';
}
if (mb_strlen($subject) > 160) {
    $errors[] = 'Subject is too long (160 characters max).';
}

if (!empty($errors)) {
    finish(false, implode(' ', $errors));
}

$visitorName  = $name;
$visitorEmail = $email;
$cleanSubject = $subject !== '' ? $subject : 'Contact via website';
$ip           = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua           = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$timeIso      = gmdate('Y-m-d\TH:i:s\Z');

$plainBody = <<<TXT
You have a new message from the {$siteName} contact form.

Name: {$visitorName}
Email: {$visitorEmail}
Subject: {$cleanSubject}

Message:
{$message}

---
Meta:
IP: {$ip}
User-Agent: {$ua}
Time (UTC): {$timeIso}
TXT;

$encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
$headers = [];
$headers[] = "From: {$encodedFromName} <{$fromAddress}>";
$headers[] = "Reply-To: \"{$visitorName}\" <{$visitorEmail}>";
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "X-Mailer: PHP/" . phpversion();

$headersStr = implode("\r\n", $headers);

$finalSubject = "{$subjectPrefix}: {$cleanSubject}";

$mailOk = false;
if (function_exists('mail')) {
    $additionalParams = $envelopeFrom ? "-f {$envelopeFrom}" : '';
    $mailOk = @mail($to, $finalSubject, $plainBody, $headersStr, $additionalParams);
}

if ($mailOk) {
    $_SESSION['last_submit_ts'] = $now;
    finish(true, 'Thanks! Your message has been sent.');
} else {
    finish(false, 'Sorry, we could not send your message at this time. Please email us at hello@droidco.co.');
}