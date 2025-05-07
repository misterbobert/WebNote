<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? null;
$noteId = $data['noteId'] ?? '';
$title = $data['title'] ?? 'Untitled note';
$content = $data['content'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if (!$noteId) {
    echo json_encode(['success' => false, 'message' => 'Note ID missing']);
    exit;
}

// Verificăm dacă notița există deja
$stmt = $pdo->prepare("SELECT id FROM notes WHERE user_id = ? AND id = ?");
$stmt->execute([$userId, $noteId]);
$exists = $stmt->fetchColumn();

if ($exists) {
    // Update
    $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ? WHERE user_id = ? AND id = ?");
    $stmt->execute([$title, $content, $userId, $noteId]);
    echo json_encode(['success' => true, 'message' => 'Note updated']);
} else {
    // Insert
    $stmt = $pdo->prepare("INSERT INTO notes (id, user_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$noteId, $userId, $title, $content]);
    echo json_encode(['success' => true, 'message' => 'Note saved']);
}
