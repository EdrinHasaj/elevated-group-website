<?php
/**
 * Contact form handler for Elevated Coaching Group.
 *
 * Receives a POST from the #cform contact form and emails it to the inbox
 * below. Returns JSON so the front end can show success/failure inline.
 */

/* ------------------------------------------------------------------ *
 * SETTINGS
 *
 * SENDER_DOMAIN must be the domain this site is actually hosted on.
 * Mail is sent FROM an address at that domain so it passes the SPF/DKIM
 * records Bluehost publishes for it. Sending "from" the visitor's own
 * address instead is what makes these forms land in spam or bounce.
 * ------------------------------------------------------------------ */
const MAIL_TO        = 'joana@elevatedcoachinggroup.com';  // where enquiries land
const SENDER_DOMAIN  = 'elevatedcoachinggroup.com';     // confirmed live domain
const SENDER_LOCAL   = 'website';                       // -> website@elevatedcoachinggroup.com

header('Content-Type: application/json; charset=utf-8');

function fail($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('Method not allowed.', 405);
}

/* Bots fill in every field they can see, including hidden ones. A real
 * visitor leaves this empty. Answer 200 so the bot thinks it succeeded. */
if (trim($_POST['website'] ?? '') !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

/* Strip CR/LF from anything that goes into a mail header, otherwise a
 * submitter can inject extra headers and turn this into an open relay. */
function header_safe($value) {
    return trim(str_replace(["\r", "\n", "%0a", "%0d"], ' ', $value));
}

function field($key, $max = 500) {
    $value = trim(isset($_POST[$key]) ? $_POST[$key] : '');
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $max)
        : substr($value, 0, $max);
}

$name     = header_safe(field('name', 120));
$email    = header_safe(field('email', 200));
$company  = header_safe(field('company', 160));
$phone    = header_safe(field('phone', 60));
$interest = header_safe(field('interest', 120));
$message  = field('message', 5000);

if ($name === '')  fail('Please enter your name.');
if ($email === '') fail('Please enter your email address.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('That email address does not look valid.');
}

$body = "New enquiry from the Elevated Coaching Group website\n"
      . str_repeat('-', 52) . "\n\n"
      . "Name:     {$name}\n"
      . "Email:    {$email}\n"
      . "Company:  " . ($company  !== '' ? $company  : '—') . "\n"
      . "Phone:    " . ($phone    !== '' ? $phone    : '—') . "\n"
      . "Interest: " . ($interest !== '' ? $interest : '—') . "\n\n"
      . "Message:\n"
      . ($message !== '' ? $message : '(no message provided)') . "\n\n"
      . str_repeat('-', 52) . "\n"
      . 'Sent ' . date('D, j M Y \a\t g:ia T') . "\n";

$from    = SENDER_LOCAL . '@' . SENDER_DOMAIN;
$subject = 'Website enquiry from ' . $name;

$headers = [
    'From'                      => sprintf('Elevated Coaching Group <%s>', $from),
    'Reply-To'                  => sprintf('%s <%s>', $name, $email),
    'X-Mailer'                  => 'PHP/' . phpversion(),
    'MIME-Version'              => '1.0',
    'Content-Type'              => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
];

$header_lines = [];
foreach ($headers as $key => $value) {
    $header_lines[] = $key . ': ' . $value;
}

/* -f sets the envelope sender, which is what the receiving server checks
 * SPF against. Without it Bluehost uses the cPanel user and Gmail is
 * far more likely to junk the message. */
$sent = mail(
    MAIL_TO,
    $subject,
    $body,
    implode("\r\n", $header_lines),
    '-f' . $from
);

if (!$sent) {
    error_log('contact.php: mail() failed for submission from ' . $email);
    fail('We could not send your message. Please email us directly.', 500);
}

echo json_encode(['ok' => true]);
