<?php
session_start();
require 'config.php';

$uid = $_SESSION['user_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$noteId = trim($_POST['id'] ?? '');

if (empty($title) || empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Titlul și conținutul sunt obligatorii.']);
    exit;
}

if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'Trebuie să fii autentificat pentru a salva notița.']);
    exit;
}

try {
    if ($noteId) {
        // Update note existentă
        $stmt = $pdo->prepare("
            UPDATE notes 
            SET title = ?, content = ?, updated_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$title, $content, $noteId, $uid]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notița a fost actualizată cu succes.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Notița nu a fost găsită sau nu ai permisiuni pentru a o modifica.']);
        }

    } else {
        // Creare notiță nouă
        $stmt = $pdo->prepare("
            INSERT INTO notes (user_id, title, content, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$uid, $title, $content]);

        $newNoteId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'message' => 'Notița a fost creată cu succes.', 'noteId' => $newNoteId]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'A apărut o eroare la salvarea notiței: ' . $e->getMessage()]);
}
?>
