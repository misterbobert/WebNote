<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $username = $user ? $user['username'] : null;
} else {
    $username = null;
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
  <header class="header">
    <button id="hamburger" class="hamburger">☰</button>
  </header>

  <div class="container">
    <aside class="sidebar collapsed">

      <!-- CARD MAMĂ pentru profil -->
      <div class="profile-card">
        <?php if ($username): ?>
          <div class="profile">
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

    <main class="editor">
      <!-- Zona de editare note -->
    </main>
  </div>

  <script src="script.js"></script>
</body>
</html>
