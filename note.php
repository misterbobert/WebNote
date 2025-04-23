<?php
session_start();
require 'config.php';
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
  SELECT content, iv
    FROM notes
   WHERE id = ? AND user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  die('Notiță inexistentă sau acces interzis.');
}

// decriptare
$cipher = base64_decode($row['content']);
$iv     = base64_decode($row['iv']);
$text   = openssl_decrypt(
  $cipher,
  'AES-256-CBC',
  ENCRYPTION_KEY,
  OPENSSL_RAW_DATA,
  $iv
);
?>
<!DOCTYPE html>
<html lang="ro">
<head>…</head>
<body>
  <header>…</header>
  <main class="editor">
    <div class="editor-card">
      <pre><?= htmlspecialchars($text) ?></pre>
    </div>
  </main>
</body>
</html>
