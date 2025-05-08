<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

$userId = 0;

$title    = trim($_POST['title']    ?? '');
$content  = trim($_POST['content']  ?? '');
$slug     = trim($_POST['slug']     ?? '');
$editable = isset($_POST['editable']) ? 1 : 0;

if (!$title || !$content || !$slug) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

// Verificare dacă slugul e deja folosit
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE slug = ?");
$stmt->execute([$slug]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'Slug already used']);
    exit;
}

// Salvăm direct contentul (nu criptat)
$insert = $pdo->prepare("
    INSERT INTO notes (user_id, title, slug, content, editable)
    VALUES (?, ?, ?, ?, ?)
");
$ok = $insert->execute([$userId, $title, $slug, $content, $editable]);

echo json_encode(['success' => $ok]);
exit;
