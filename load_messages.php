<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

$me    = $_SESSION['user_id'] ?? null;
$other = $_GET['with'] ?? null;
if (!$me || !$other) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
  SELECT sender_id, receiver_id, content, created_at
    FROM messages
   WHERE (sender_id=? AND receiver_id=?)
      OR (sender_id=? AND receiver_id=?)
ORDER BY created_at
");
$stmt->execute([$me,$other,$other,$me]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
