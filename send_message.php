<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

$sender   = $_SESSION['user_id'] ?? null;
$receiver = $_POST['to']     ?? null;
$content  = trim($_POST['content'] ?? '');

if (!$sender || !$receiver || $content === '') {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Parametri invalizi']);
  exit;
}

$stmt = $pdo->prepare("
  INSERT INTO messages (sender_id,receiver_id,content)
  VALUES (?,?,?)
");
try {
  $stmt->execute([$sender,$receiver,$content]);
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Eroare DB']);
}
