<?php
session_start();
require 'config.php';

$uid = $_SESSION['user_id'] ?? null;
$id   = $_POST['id']   ?? null;
$slug = $_POST['slug'] ?? null;

if (!$uid || (! $id && ! $slug)) {
  echo json_encode(['success'=>false,'error'=>'Permisiuni insuficiente']);
  exit;
}

if ($id) {
  $stmt = $pdo->prepare("DELETE FROM notes WHERE id=? AND user_id=?");
  $stmt->execute([$id, $uid]);
} else {
  $stmt = $pdo->prepare("DELETE FROM notes WHERE slug=? AND user_id=0");
  $stmt->execute([$slug]);
}

echo json_encode(['success'=>true]);
