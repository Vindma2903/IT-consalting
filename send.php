<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Yekaterinburg'); // Оренбург, UTC+5 — для даты в письме

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Настройки почты ─────────────────────────────────────────────────────────
// Пароль в git не хранится. Рядом с send.php на сервере создаётся файл
// mail-config.php (он в .gitignore) такого вида:
//
//   <?php
//   return [
//       'host'      => 'mail.example.ru',            // ваш mailserver
//       'port'      => 587,                          // 587 = STARTTLS, 465 = SSL
//       'user'      => 'no-reply@it-consalting24.ru',// ящик из postfixadmin
//       'pass'      => 'пароль_ящика',
//       'from'      => 'no-reply@it-consalting24.ru',
//       'from_name' => 'Сайт it-consalting24.ru',
//       'to'        => 'protechnologii22@yandex.ru', // куда приходят заявки
//   ];
$cfgFile = __DIR__ . '/mail-config.php';
$cfg = is_file($cfgFile) ? (require $cfgFile) : [];
if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['pass'])) {
    error_log('send.php: не заполнен mail-config.php');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Почта на сайте не настроена']);
    exit;
}
$cfg += [
    'port'      => 587,
    'from'      => $cfg['user'],
    'from_name' => 'Сайт it-consalting24.ru',
    'to'        => 'protechnologii22@yandex.ru',
];
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

$error = smtp_send($cfg, $cfg['to'], $subject, $body);

if ($error === null) {
    echo json_encode(['success' => true]);
} else {
    error_log('send.php SMTP: ' . $error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка отправки письма']);
}

// ─── Отправка через SMTP с авторизацией (без сторонних библиотек) ────────────
// Возвращает null при успехе или текст ошибки.
function smtp_send(array $cfg, string $to, string $subject, string $body): ?string
{
    $port   = (int)$cfg['port'];
    $scheme = $port === 465 ? 'ssl://' : 'tcp://';
    $ctx    = stream_context_create(['ssl' => [
        'peer_name'        => $cfg['host'],
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ]]);

    $fp = @stream_socket_client($scheme . $cfg['host'] . ':' . $port, $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "нет соединения с {$cfg['host']}:{$port} — {$errstr}";
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (?string $line, int $expect) use ($fp, $read): ?string {
        if ($line !== null) {
            fwrite($fp, $line . "\r\n");
        }
        $resp = $read();
        if ((int)substr($resp, 0, 3) !== $expect) {
            return trim("ожидали {$expect}, получили: " . trim($resp));
        }
        return null;
    };

    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $fromDomain = substr(strrchr($cfg['from'], '@'), 1);

    $steps = [[null, 220], [$ehlo, 250]];
    foreach ($steps as [$line, $code]) {
        if ($e = $cmd($line, $code)) { fclose($fp); return $e; }
    }

    if ($port !== 465) {
        if ($e = $cmd('STARTTLS', 220)) { fclose($fp); return $e; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            fclose($fp);
            return 'не удалось включить TLS (проверьте сертификат на mailserver)';
        }
        if ($e = $cmd($ehlo, 250)) { fclose($fp); return $e; }
    }

    $headers  = 'Date: ' . date('r') . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $fromDomain . ">\r\n";
    $headers .= 'From: =?UTF-8?B?' . base64_encode($cfg['from_name']) . "?= <{$cfg['from']}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $message  = $headers . "\r\n" . chunk_split(base64_encode($body), 76, "\r\n");

    $steps = [
        ['AUTH LOGIN', 334],
        [base64_encode($cfg['user']), 334],
        [base64_encode($cfg['pass']), 235],
        ["MAIL FROM:<{$cfg['from']}>", 250],
        ["RCPT TO:<{$to}>", 250],
        ['DATA', 354],
        [$message . '.', 250],
    ];
    foreach ($steps as $i => [$line, $code]) {
        if ($e = $cmd($line, $code)) {
            fclose($fp);
            return ($i <= 2 ? 'авторизация не прошла: ' : '') . $e;
        }
    }

    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return null;
}
