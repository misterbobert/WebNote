<?php
// send_friend_request.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error'=>'Trebuie să fii autentificat']);
  exit;
}
$requester = $_SESSION['user_id'];
$handle    = trim($_POST['handle'] ?? '');
if ($handle==='' || $handle[0]!=='@') {
  http_response_code(400);
  echo json_encode(['error'=>'Handle invalid']);
  exit;
}
$username = substr($handle,1);
$stmt = $pdo->prepare('SELECT id FROM users WHERE username=?');
$stmt->execute([$username]);
if (!$row = $stmt->fetch()) {
  http_response_code(404);
  echo json_encode(['error'=>'Utilizator inexistent']);
  exit;
}
$receiver = $row['id'];
if ($receiver===$requester) {
  http_response_code(400);
  echo json_encode(['error'=>'Nu te poţi adăuga pe tine însuţi']);
  exit;
}
// verificăm duplicat
$stmt = $pdo->prepare("
  SELECT 1 FROM friendships
   WHERE (requester_id=? AND receiver_id=?)
      OR (requester_id=? AND receiver_id=?)
");
$stmt->execute([$requester,$receiver,$receiver,$requester]);
if ($stmt->fetch()) {
  http_response_code(409);
  echo json_encode(['error'=>'Cerere deja trimisă sau deja prieteni']);
  exit;
}
// inserăm
$stmt = $pdo->prepare("
  INSERT INTO friendships
    (requester_id, receiver_id, status, created_at)
  VALUES
    (?, ?, 'pending', NOW())
");
$stmt->execute([$requester,$receiver]);
echo json_encode(['success'=>true]);
