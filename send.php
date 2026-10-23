<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Yekaterinburg'); // Оренбург, UTC+5 — для даты в письме

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Настройки (берутся из переменных окружения или .env) ────────────────────
$to   = getenv('MAIL_TO')   ?: 'protechnologii22@yandex.ru';
$from = getenv('MAIL_FROM') ?: 'info@pro-technologii.ru'; // ящик, через который идёт отправка (как на pro-technologii.ru)
// ─────────────────────────────────────────────────────────────────────────────

$name    = trim(strip_tags($_POST['name']    ?? ''));
$phone   = trim(strip_tags($_POST['phone']   ?? ''));
$company = trim(strip_tags($_POST['company'] ?? ''));
$comment = trim(strip_tags($_POST['comment'] ?? ''));
$consent = $_POST['consent'] ?? '';

if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
    exit;
}

// Без согласия на обработку ПДн заявку не принимаем (152-ФЗ)
if ($consent !== 'yes') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Нет согласия на обработку персональных данных']);
    exit;
}

$subject = 'Новая заявка с сайта — ' . $name;

$body  = "Новая заявка с сайта «IT-консалтинг»\n";
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
$body .= "Согласие на обработку ПДн: получено (Политика, ред. от 23.10.2026)\n";

$headers  = "From: =?UTF-8?B?" . base64_encode("Сайт it-consalting24.ru") . "?= <{$from}>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

// -f задаёт адрес отправителя в конверте — иначе SMTP Яндекса отклонит письмо
$sent = mail($to, $encodedSubject, $body, $headers, '-f' . $from);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка отправки письма']);
}
