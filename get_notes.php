<?php
session_start();
require 'config.php';

// Verifică dacă utilizatorul este autentificat
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'Nu ești autentificat']);
    exit;
}

// Interogare pentru a aduce toate notele utilizatorului
$stmt = $pdo->prepare("
  SELECT id, title, content, slug, editable
  FROM notes
  WHERE user_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$uid]);

// Obține toate notele
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verifică dacă există note
if ($notes) {
    echo json_encode(['success' => true, 'notes' => $notes]);
} else {
    echo json_encode(['success' => false, 'error' => 'Nu sunt note pentru acest utilizator']);
}
?>
