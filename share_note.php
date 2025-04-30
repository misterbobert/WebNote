<?php
require 'config.php';
header('Content-Type: application/json');

// 1) Colectează datele trimise via fetch()
$content = trim($_POST['content'] ?? '');
$slug    = trim($_POST['slug']    ?? '');
$title   = trim($_POST['title']   ?? 'Untitled note');

if ($content === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing content or slug']);
    exit;
}

// 2) Criptează conținutul
$method    = 'AES-256-CBC';
$key       = ENCRYPTION_KEY;
$iv_len    = openssl_cipher_iv_length($method);
$iv        = random_bytes($iv_len);
$cipher    = openssl_encrypt($content, $method, $key, OPENSSL_RAW_DATA, $iv);
$c_b64     = base64_encode($cipher);
$iv_b64    = base64_encode($iv);

// 3) Dacă slug există deja pentru user_id=0 → UPDATE, altfel INSERT
$stmt = $pdo->prepare("SELECT id FROM notes WHERE slug = ? AND user_id = 0");
$stmt->execute([$slug]);

if ($row = $stmt->fetch()) {
    // UPDATE
    $upd = $pdo->prepare("
      UPDATE notes
         SET title   = ?,
             content = ?,
             iv      = ?
       WHERE id = ?
    ");
    $upd->execute([$title, $c_b64, $iv_b64, $row['id']]);
} else {
    // INSERT
    $ins = $pdo->prepare("
      INSERT INTO notes (user_id, title, content, iv, slug)
      VALUES (0, ?, ?, ?, ?)
    ");
    $ins->execute([$title, $c_b64, $iv_b64, $slug]);
}

// 4) Construiește link-ul public și trimite-l în JSON
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
      . '://'. $_SERVER['HTTP_HOST']
      . rtrim(dirname($_SERVER['REQUEST_URI']), '/')
      . '/';

echo json_encode([
  'link' => $base . rawurlencode($slug)
]);
