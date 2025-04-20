<?php
session_start();
require 'config.php';

// Preluăm username-ul dacă ești logat
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
  <!-- HEADER FIX -->
  <header class="header">
    <button id="hamburger" type="button" class="hamburger">☰</button>
  </header>

  <div class="container">
    <!-- SIDEBAR (ascuns implicit) -->
    <aside class="sidebar collapsed">
      <div class="profile">
        <?php if ($username): ?>
          <span class="username">@<?= htmlspecialchars($username) ?></span>
          <button id="manage-btn" type="button" class="manage-btn">Manage</button>
        <?php else: ?>
          <button id="login-btn" type="button" class="login-btn">Login</button>
        <?php endif; ?>
      </div>
      <hr>
      <div class="notes-panel">
        <button id="new-note" type="button" class="panel-btn">Create new note</button>
        <div id="notes-list" class="notes-list"></div>
        <button id="toggle-notes" type="button" class="panel-btn more-btn">
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
