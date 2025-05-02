<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// 1) Verificăm login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Not logged in']);
    exit;
}
$uid = $_SESSION['user_id'];

// 2) Colectăm parametrii
$fr_id  = $_POST['fr_id']  ?? null;
$action = $_POST['action'] ?? null;
if (!$fr_id || !in_array($action, ['accept','reject'], true)) {
    echo json_encode(['success'=>false,'error'=>'Invalid parameters']);
    exit;
}

// 3) Ne asigurăm că cererea există și e pe pending
$stmt = $pdo->prepare("
  SELECT id 
    FROM friendships 
   WHERE id = ? 
     AND receiver_id = ? 
     AND status = 'pending'
   LIMIT 1
");
$stmt->execute([$fr_id, $uid]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'error'=>'Request not found or already processed']);
    exit;
}

// 4) Facem update
$newStatus = $action === 'accept' ? 'accepted' : 'declined';
$stmt = $pdo->prepare("
  UPDATE friendships
     SET status       = ?,
         responded_at = NOW()
   WHERE id = ?
");
$stmt->execute([$newStatus, $fr_id]);

echo json_encode(['success'=>true,'action'=>$newStatus]);
