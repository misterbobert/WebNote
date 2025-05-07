<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// Forțăm notița partajată să fie publică (user_id = 0)
$userId = 0;

$title    = trim($_POST['title']    ?? '');
$content  = trim($_POST['content']  ?? '');
$slug     = trim($_POST['slug']     ?? '');
$editable = isset($_POST['editable']) ? 1 : 0;

if (!$slug || !$content) {
    echo json_encode(['success' => false, 'error' => 'Slug și conținut sunt obligatorii.']);
    exit;
}

// Verificăm dacă slugul e deja folosit
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE slug = ?");
$stmt->execute([$slug]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'Slugul există deja.']);
    exit;
}

// Criptăm conținutul
$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt($content, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
$b64iv = base64_encode($iv);
$b64cipher = base64_encode($encrypted);

// Salvăm notița
$insert = $pdo->prepare("
    INSERT INTO notes (user_id, title, slug, content, iv, editable)
    VALUES (?, ?, ?, ?, ?, ?)
");
$ok = $insert->execute([$userId, $title, $slug, $b64cipher, $b64iv, $editable]);

if ($ok) {
    echo json_encode([
        'success' => true,
        'link'    => $slug // doar slug, nu cu domeniu (frontend îl construiește)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error'   => 'Eroare la salvarea în baza de date.'
    ]);
}
