<?php
session_start();
require 'config.php';

// Preluăm username și URL-ul imaginii dacă ești logat
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT username, image_url FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $user ? $user['username'] : null;
    $imageUrl = $user ? $user['image_url'] : null;
} else {
    $username = null;
    $imageUrl = null;
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
  <!-- HEADER FIX -->
  <header class="header">
    <button id="hamburger" class="hamburger">☰</button>
  </header>

  <div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar collapsed">
      <div class="profile-card">
        <?php if ($username): ?>
          <div class="profile">
            <?php if ($imageUrl): ?>
              <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Avatar" class="profile-img">
            <?php else: ?>
              <div class="avatar"></div>
            <?php endif; ?>
            <span class="username">@<?= htmlspecialchars($username) ?></span>
            <button id="manage-btn" class="manage-btn">Manage</button>
          </div>
        <?php else: ?>
          <button id="login-btn" class="login-btn">Login</button>
        <?php endif; ?>
      </div>

      <hr>

      <div class="notes-panel">
        <button id="new-note" class="panel-btn">Create new note</button>
        <div id="notes-list" class="notes-list"></div>
        <button id="toggle-notes" class="panel-btn more-btn">
          <span id="toggle-label">more</span>
          <span id="toggle-arrow">∨</span>
        </button>
      </div>
    </aside>

    <!-- EDITOR -->
    <main class="editor">
      <!-- Zona de editare note -->
    </main>
  </div>

  <script src="script.js"></script>
</body>
</html>
