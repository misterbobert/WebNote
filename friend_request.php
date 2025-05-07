<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$frId = $data['frId'] ?? null;
$action = $data['action'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$userId || !$frId || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    if ($action === 'accept') {
        $stmt = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$frId, $userId]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM friendships WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$frId, $userId]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
