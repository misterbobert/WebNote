<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Neautentificat.']);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, title, content, iv, slug
    FROM notes
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);

function decrypt_note($b64cipher, $b64iv) {
    $cipher = base64_decode($b64cipher);
    $iv     = base64_decode($b64iv);
    return openssl_decrypt($cipher, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
}

$notes = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $full    = decrypt_note($r['content'], $r['iv']);
    $preview = mb_strimwidth($full, 0, 30, '…');
    $notes[] = [
        'id'      => $r['id'],
        'title'   => $r['title'],
        'preview' => $preview,
        'full'    => $full,
        'slug'    => $r['slug'],
    ];
}

echo json_encode(['success' => true, 'notes' => $notes]);
