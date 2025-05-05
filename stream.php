<?php
// stream.php  —  output: text/event-stream
session_write_close();                    // evită blocarea sesiunii
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("X-Accel-Buffering: no");          // pt. Nginx / disable buffering

require 'config.php';
$slug  = preg_replace('/[^A-Za-z0-9_-]/','', $_GET['slug'] ?? '');
if (!$slug) { http_response_code(400); exit; }

// pre‑iau timestamp-ul inițial
$stmt  = $pdo->prepare("SELECT updated_at FROM notes
                         WHERE slug=? AND user_id=0 AND editable=1");
$stmt->execute([$slug]);
$last  = $stmt->fetchColumn() ?: '1970-01-01 00:00:00';

while (true) {
    $stmt->execute([$slug]);
    $ts = $stmt->fetchColumn();
    if ($ts && $ts !== $last) {
        // luăm conținutul (decriptat) și îl trimitem
        $row = $pdo->prepare("SELECT content,iv FROM notes
                               WHERE slug=? AND user_id=0")->execute([$slug])->fetch(PDO::FETCH_ASSOC);
        $plain = openssl_decrypt(
           base64_decode($row['content']),
           'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA,
           base64_decode($row['iv'])
        );
        echo "data: ".json_encode(['content'=>$plain])."\n\n";
        @ob_flush(); @flush();
        $last = $ts;
    }
    sleep(1);   // 1 secundă latenta
}
