<?php
session_start();
require 'config.php';

// 1) dacă nu eşti logat, mergi la login.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2) preluăm user info
$stmt = $pdo->prepare('SELECT username, image_url FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$username = htmlspecialchars($user['username']);
$imageUrl = $user['image_url'] ? htmlspecialchars($user['image_url']) : null;

// 3) preluăm notiţele şi decriptăm pentru preview
$notes = [];
$stmt = $pdo->prepare('SELECT id, content, iv FROM notes WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cipher = base64_decode($row['content']);
    $iv     = base64_decode($row['iv']);
    $text   = openssl_decrypt($cipher, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    $preview = mb_strimwidth($text, 0, 30, '…');
    $notes[] = [
      'id'      => $row['id'],
      'preview' => htmlspecialchars($preview)
    ];
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Webnote</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <!-- HEADER -->
  <header class="header">
    <button id="hamburger" class="hamburger">☰</button>
  </header>

  <div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar collapsed">
      <!-- Profile card -->
      <div class="profile-card">
        <div class="profile">
          <?php if ($imageUrl): ?>
            <img src="<?= $imageUrl ?>" alt="Avatar" class="profile-img">
          <?php else: ?>
            <div class="avatar"></div>
          <?php endif; ?>
          <span class="username">@<?= $username ?></span>
          <button id="manage-btn" class="manage-btn"
                  onclick="location.href='profile.php'">
            Manage
          </button>
        </div>
      </div>
      <hr>
      <!-- Notes panel -->
      <div class="notes-panel">
        <button id="new-note" class="panel-btn">Create new note</button>
        <div id="notes-list" class="notes-list">
          <?php foreach ($notes as $n): ?>
            <button type="button"
                    class="panel-btn"
                    onclick="location.href='note.php?id=<?= $n['id'] ?>'">
              <?= $n['preview'] ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

    <!-- EDITOR -->
    <main class="editor">
      <div class="editor-card">
        <form action="save_note.php" method="post" class="editor-form">
          <button type="submit" class="panel-btn save-note-btn">
            Save to account
          </button>
          <textarea
            name="content"
            class="editor-input"
            placeholder="Type your note here…"
            required></textarea>
        </form>
      </div>
    </main>
  </div>

  <script>
    // Toggle sidebar
    document.getElementById('hamburger').addEventListener('click', () => {
      document.querySelector('.sidebar').classList.toggle('collapsed');
      document.querySelector('.sidebar').classList.toggle('open');
      document.getElementById('hamburger').classList.toggle('open');
    });

    // New note = golește textarea
    document.getElementById('new-note').addEventListener('click', () => {
      document.querySelector('.editor-input').value = '';
    });
  </script>
</body>
</html>
