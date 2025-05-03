<?php
session_start();
require 'config.php';

// primim via fetch()
$slug    = $_POST['slug']    ?? '';
$content = $_POST['content'] ?? '';

if (!$slug || trim($content) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing slug or content']);
    exit;
}

// criptează conținutul
$iv     = random_bytes(openssl_cipher_iv_length('AES-256-CBC'));
$cipher = openssl_encrypt($content, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);

// actualizează nota doar dacă e share-uită și editabilă
$stmt = $pdo->prepare("
  UPDATE notes
     SET content = ?, iv = ?
   WHERE slug = ? AND user_id = 0 AND editable = 1
");
$stmt->execute([
  base64_encode($cipher),
  base64_encode($iv),
  $slug
]);

echo json_encode(['success' => true]);
