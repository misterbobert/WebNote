<?php
require 'config.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
$id  = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id) {
  echo json_encode(['success' => false, 'error' => 'ID invalid']);
  exit;
}

$stmt = $pdo->prepare("
  SELECT title, content, iv
    FROM notes
   WHERE id = ? AND user_id = ?
   LIMIT 1
");
$stmt->execute([$id, $uid]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $content = openssl_decrypt(base64_decode($row['content']), 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, base64_decode($row['iv']));
  echo json_encode([
    'success' => true,
    'title'   => $row['title'],
    'content' => $content
  ]);
} else {
  echo json_encode(['success' => false, 'error' => 'Note not found']);
}
