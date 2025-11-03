<?php
// Contact form handler using PHPMailer (no Composer).
// Point your form to this file: <form action="/contact_phpmailer.php" method="POST" novalidate>

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

// CONFIG — update these
$TO_EMAIL       = 'hasanrafiul32@gmail.com';       // Where to receive messages
$FROM_NAME      = 'Droidco';
$FROM_EMAIL     = 'hasanrafiul32@gmail.com';       // Use the same Gmail you’ll authenticate with (or a verified alias)
$SUBJECT_PREFIX = 'Droidco Contact';

// Gmail SMTP
$SMTP_HOST   = 'smtp.gmail.com';
$SMTP_PORT   = 587;                             // 587 (TLS) or 465 (SSL)
$SMTP_USER   = 'hasanrafiul32@gmail.com';          // Same as FROM_EMAIL (recommended)
$SMTP_PASS   = 'bfem kyjk lpqz djch'; // DO NOT commit this; use env in production
$SMTP_SECURE = 'tls';                           // 'tls' for 587, 'ssl' for 465

// Helpers
function is_ajax() {
  return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
      || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}
function respond($ok, $message) {
  if (is_ajax()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
  }
  header('Location: ' . ($ok ? 'index.html?sent=1#contact' : 'index.html#contact'));
  exit;
}
function sanitize_header($v) {
  return trim(preg_replace('/[\r\n]+/', ' ', (string)$v));
}

// Health check on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  header('Content-Type: text/plain; charset=utf-8');
  echo 'OK';
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  respond(false, 'Method not allowed.');
}

// Honeypot
if (!empty($_POST['website'] ?? '')) {
  respond(true, 'OK');
}

// Inputs
$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$consent = isset($_POST['consent']);

// Validate
if ($name === '' || $email === '' || $message === '' || !$consent) {
  http_response_code(422);
  respond(false, 'Please complete all required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  respond(false, 'Please enter a valid email address.');
}
if (mb_strlen($name) > 120 || mb_strlen($email) > 160 || mb_strlen($subject) > 160 || mb_strlen($message) > 5000) {
  http_response_code(422);
  respond(false, 'One or more fields exceed the maximum length.');
}

// Build content
$clean_subject = sanitize_header(($SUBJECT_PREFIX ? $SUBJECT_PREFIX . ': ' : '') . ($subject !== '' ? $subject : 'New Inquiry'));
$ip   = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$time = date('c');

$body_text = implode("\r\n", array_filter([
  "New contact form submission from Droidco.co",
  "",
  "Name: {$name}",
  "Email: {$email}",
  ($subject !== '' ? "Subject: {$subject}" : null),
  "",
  "Message:",
  $message,
  "",
  "—",
  "Meta:",
  "IP: {$ip}",
  "User-Agent: {$ua}",
  "Time: {$time}",
]));

// Send via Gmail SMTP
try {
  $mail = new PHPMailer(true);

  // For debugging (disable in production):
  // $mail->SMTPDebug = 2;
  // $mail->Debugoutput = function($str){ error_log('SMTP: ' . $str); };

  $mail->isSMTP();
  $mail->Host       = $SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = $SMTP_USER;
  $mail->Password   = $SMTP_PASS;
  $mail->SMTPSecure = ($SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = $SMTP_PORT;
  $mail->CharSet    = 'UTF-8';

  // From must be your Gmail (or a verified alias in Gmail “Send mail as”)
  $mail->setFrom($FROM_EMAIL, $FROM_NAME);

  // Recipient (your inbox)
  $mail->addAddress($TO_EMAIL);

  // Put the visitor’s address in Reply-To so you can reply directly
  $mail->addReplyTo($email, $name);

  $mail->Subject = $clean_subject;
  $mail->Body    = $body_text;
  $mail->isHTML(false); // plain text

  $mail->send();
  respond(true, 'Thanks! Your message has been sent.');
} catch (Exception $e) {
  http_response_code(500);
  respond(false, 'We could not send your message. Please try again later.');
}