<?php
session_start();
require 'config.php';

// 1) validate
$content  = $_POST['content'] ?? '';
$title    = trim($_POST['title'] ?? '') ?: 'Untitled note';
$slug     = trim($_POST['slug']  ?? '');
$editable = isset($_POST['editable']) && (int)$_POST['editable'] === 1 ? 1 : 0;

if ($content === '' || $slug === '') {
    http_response_code(400);
    exit('Missing content or slug');
}

// 2) encrypt
$method = 'AES-256-CBC';
$key    = ENCRYPTION_KEY;
$iv     = random_bytes(openssl_cipher_iv_length($method));
$cipher = openssl_encrypt($content, $method, $key, OPENSSL_RAW_DATA, $iv);
$b64c   = base64_encode($cipher);
$b64iv  = base64_encode($iv);

// 3) upsert
$stmt = $pdo->prepare("SELECT id FROM notes WHERE slug = ? AND user_id = 0");
$stmt->execute([$slug]);
if ($row = $stmt->fetch()) {
    $upd = $pdo->prepare("
      UPDATE notes
         SET title   = ?,
             content = ?,
             iv      = ?,
             editable= ?
       WHERE id = ?");
    $upd->execute([$title, $b64c, $b64iv, $editable, $row['id']]);
} else {
    $ins = $pdo->prepare("
      INSERT INTO notes
        (user_id,title,content,iv,slug,editable)
      VALUES
        (0,?,?,?,?,?)");
    $ins->execute([ $title, $b64c, $b64iv, $slug, $editable ]);
}

// 4) redirect back into /WebNote/{slug}
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');  // "/WebNote"
header('Location: '.$base.'/'.rawurlencode($slug));
exit;
