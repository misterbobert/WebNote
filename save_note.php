<?php
// save_note.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$content = trim($_POST['content']  ?? '');
$title   = trim($_POST['title']    ?? '');
if ($content !== '' && $title !== '') {
    // criptare AES-256-CBC
    $method = 'AES-256-CBC';
    $key    = ENCRYPTION_KEY;
    $iv_len = openssl_cipher_iv_length($method);
    $iv     = random_bytes($iv_len);

    $cipher_raw = openssl_encrypt(
      $content, $method, $key,
      OPENSSL_RAW_DATA, $iv
    );
    $cipher_b64 = base64_encode($cipher_raw);
    $iv_b64     = base64_encode($iv);

    // salvare inclusiv titlu
    $stmt = $pdo->prepare("
      INSERT INTO notes (user_id, title, content, iv)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
      $_SESSION['user_id'],
      $title,
      $cipher_b64,
      $iv_b64
    ]);
}

header('Location: index.php');
exit;
