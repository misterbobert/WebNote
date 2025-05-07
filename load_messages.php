<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilizatorul nu este autentificat.'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$withId = isset($_GET['with']) ? intval($_GET['with']) : 0;

if (!$withId) {
    echo json_encode([
        'success' => false,
        'message' => 'ID-ul destinatarului nu a fost specificat.'
    ]);
    exit;
}

try {
    if (!$pdo) {
        throw new Exception('Conexiunea la baza de date a eșuat.');
    }

    $stmt = $pdo->prepare("
        SELECT m.*, u.username AS sender_username
          FROM messages m
          JOIN users u ON m.sender_id = u.id
         WHERE (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)
      ORDER BY m.created_at
    ");
    $stmt->execute([$userId, $withId, $withId, $userId]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'debug' => 'Loaded ' . count($messages) . ' messages'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Eroare la încărcarea mesajelor.',
        'error' => $e->getMessage(),
        'debug' => 'userId: ' . $userId . ', withId: ' . $withId
    ]);
}
