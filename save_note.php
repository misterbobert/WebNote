<?php
session_start();
require 'config.php';

// 1) verifică dacă ești logat
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Neautentificat']);
    exit;
}

$uid     = $_SESSION['user_id'];
$id      = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : null;
$slug    = trim($_POST['slug'] ?? '');
$title   = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

// 2) validare minimă
if ($title === '' || $content === '') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Titlul și conținutul sunt obligatorii.']);
    exit;
}

// 3) criptare AES
$method     = 'AES-256-CBC';
$key        = ENCRYPTION_KEY;
$iv_len     = openssl_cipher_iv_length($method);
$iv         = random_bytes($iv_len);
$cipher     = openssl_encrypt($content, $method, $key, OPENSSL_RAW_DATA, $iv);
$cipher_b64 = base64_encode($cipher);
$iv_b64     = base64_encode($iv);

try {
    // 4) UPDATE dacă avem id sau slug
    if ($id || $slug !== '') {
        $sql = "UPDATE notes SET title = ?, content = ?, iv = ?";
        $params = [$title, $cipher_b64, $iv_b64];

        if ($slug !== '') {
            $sql .= ", slug = ?";
            $params[] = $slug;
        }

        $sql .= $id ? " WHERE id = ? AND user_id = ?" : " WHERE slug = ? AND user_id = ?";
        $params[] = $id ?: $slug;
        $params[] = $uid;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // 5) INSERT dacă nu există id și slug
        $sql = "
            INSERT INTO notes (user_id, title, content, iv" . ($slug !== '' ? ", slug" : "") . ")
            VALUES (?, ?, ?, ?" . ($slug !== '' ? ", ?" : "") . ")
        ";
        $params = [$uid, $title, $cipher_b64, $iv_b64];
        if ($slug !== '') {
            $params[] = $slug;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // 6) succes
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Eroare la salvare: ' . $e->getMessage()]);
    exit;
}
