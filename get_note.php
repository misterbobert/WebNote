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
  SELECT id, title, content, iv, slug, editable
  FROM notes
  WHERE user_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$uid]);

// Obține toate notele
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verifică dacă există note
if ($notes) {
    // Decriptează conținutul fiecărei note
    foreach ($notes as &$note) {
        // Asigură-te că content este decriptat
        $note['full'] = decrypt_note($note['content'], $note['iv']);
    }

    echo json_encode(['success' => true, 'notes' => $notes]);
} else {
    echo json_encode(['success' => false, 'error' => 'Nu sunt note pentru acest utilizator']);
}

// Funcția de decriptare
function decrypt_note($b64cipher, $b64iv) {
    $cipher = base64_decode($b64cipher);
    $iv = base64_decode($b64iv);
    return openssl_decrypt(
        $cipher, 'AES-256-CBC',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
}
?>
