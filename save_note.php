<?php
session_start();
require 'config.php';

// 1) verificăm login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2) colectăm datele din formular
$uid     = $_SESSION['user_id'];
$id      = isset($_POST['id']) && ctype_digit($_POST['id'])
          ? (int)$_POST['id']
          : null;
$title   = trim($_POST['title']   ?? '');
$content = trim($_POST['content'] ?? '');
$slug    = trim($_POST['slug']    ?? '');  // codul de share, poate fi gol

// 3) validăm minim
if ($title === '' || $content === '') {
    // poți adăuga un mesaj de eroare aici
    header('Location: index.php');
    exit;
}

// 4) criptează content-ul în AES-256-CBC
$method   = 'AES-256-CBC';
$key      = ENCRYPTION_KEY;
$iv_len   = openssl_cipher_iv_length($method);
$iv       = random_bytes($iv_len);
$cipher   = openssl_encrypt($content, $method, $key, OPENSSL_RAW_DATA, $iv);
$cipher_b64 = base64_encode($cipher);
$iv_b64     = base64_encode($iv);

// 5) dacă am id → UPDATE, altfel → INSERT
if ($id) {
    // UPDATE note existentă, inclusiv slug dacă a fost trimis
    $sql = "
      UPDATE notes
         SET title   = ?,
             content = ?,
             iv      = ?
    ";
    $params = [$title, $cipher_b64, $iv_b64];

    if ($slug !== '') {
        $sql .= ", slug = ?";
        $params[] = $slug;
    }

    $sql .= " WHERE id = ? AND user_id = ?";
    $params[] = $id;
    $params[] = $uid;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

} else {
    // INSERT nouă notiță (cu slug dacă există)
    $sql = "
      INSERT INTO notes
         (user_id, title, content, iv" . ($slug !== '' ? ", slug" : "") . ")
      VALUES
         (?, ?, ?, ?" . ($slug !== '' ? ", ?" : "") . ")
    ";
    $params = [$uid, $title, $cipher_b64, $iv_b64];
    if ($slug !== '') {
        $params[] = $slug;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

// 6) redirect înapoi la index
header('Location: index.php');
exit;
