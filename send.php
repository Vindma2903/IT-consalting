<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Настройки (берутся из переменных окружения или .env) ────────────────────
$to   = getenv('MAIL_TO')   ?: 'akolomiec10@gmail.com';
$from = getenv('MAIL_FROM') ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'pro-technologies.ru');
// ─────────────────────────────────────────────────────────────────────────────

$name    = trim(strip_tags($_POST['name']    ?? ''));
$phone   = trim(strip_tags($_POST['phone']   ?? ''));
$company = trim(strip_tags($_POST['company'] ?? ''));
$comment = trim(strip_tags($_POST['comment'] ?? ''));

if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
    exit;
}

$subject = 'Новая заявка с сайта — ' . $name;

$body  = "Новая заявка с сайта «Про технологии»\n";
$body .= str_repeat('─', 40) . "\n\n";
$body .= "Имя:      {$name}\n";
$body .= "Телефон:  {$phone}\n";
if ($company !== '') {
    $body .= "Компания: {$company}\n";
}
if ($comment !== '') {
    $body .= "\nСообщение:\n{$comment}\n";
}
$body .= "\n" . str_repeat('─', 40) . "\n";
$body .= "Дата: " . date('d.m.Y H:i') . "\n";

$headers  = "From: Сайт Про технологии <{$from}>\r\n";
$headers .= "Reply-To: {$from}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$sent = mail($to, $encodedSubject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка отправки письма']);
}
