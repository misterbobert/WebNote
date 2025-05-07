<?php
require 'config.php';
header('Content-Type: application/json');

// Doar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodă invalidă.']);
    exit;
}

$slug    = $_POST['slug']    ?? '';
$content = $_POST['content'] ?? '';

$slug    = trim($slug);
$content = trim($content);

if (!$slug || !$content) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Slug sau conținut lipsă.']);
    exit;
}

// Verificăm dacă nota partajată există
$stmt = $pdo->prepare("SELECT id, iv FROM notes WHERE slug = ? AND user_id = 0 AND editable = 1 LIMIT 1");
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Notița nu există sau nu e editabilă.']);
    exit;
}

// Criptăm noul conținut
$iv = base64_decode($row['iv']);
$encrypted = openssl_encrypt($content, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
$b64cipher = base64_encode($encrypted);

// Salvăm în baza de date
$update = $pdo->prepare("UPDATE notes SET content = ?, updated_at = NOW() WHERE id = ?");
$ok = $update->execute([$b64cipher, $row['id']]);

echo json_encode(['success' => $ok]);
