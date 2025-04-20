<?php
session_start();
require 'config.php';

// dacă nu ești logat, du-te la login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// dacă s-a trimis URL-ul imaginii, salvăm în baza de date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_url'])) {
    $url = trim($_POST['image_url']);
    $stmt = $pdo->prepare('UPDATE users SET image_url = ? WHERE id = ?');
    $stmt->execute([$url, $_SESSION['user_id']]);
    header('Location: profile.php');
    exit;
}

// preluăm datele userului
$stmt = $pdo->prepare('SELECT email, username, created_at, image_url FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$email     = htmlspecialchars($user['email']);
$username  = htmlspecialchars($user['username']);
$createdAt = htmlspecialchars($user['created_at']);
$imageUrl  = htmlspecialchars($user['image_url']);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Profile – Webnote</title>
  <link rel="stylesheet" href="login.css">
  <link rel="stylesheet" href="profile.css">
</head>
<body>
  <header class="header">
    <button id="back" class="btn back-btn">Go back</button>
  </header>

  <main class="login-page">
    <div class="profile-card-large">
      <h1>My account</h1>

      <div class="profile-content">
        <!-- Detalii cont -->
        <div class="details">
          <p><strong>Username:</strong> @<?= $username ?></p>
          <p><strong>Email:</strong> <?= $email ?></p>
          <p><strong>Date created:</strong> <?= $createdAt ?></p>
        </div>

        <!-- Card imagine + formular URL -->
        <div class="image-card">
          <?php if ($imageUrl): ?>
            <img src="<?= $imageUrl ?>" alt="Profile image">
          <?php else: ?>
            <div class="placeholder">No image</div>
          <?php endif; ?>

          <form method="post" action="profile.php">
            <input
              type="url"
              name="image_url"
              placeholder="Image URL"
              value="<?= $imageUrl ?>"
              required>
            <button type="submit" class="btn save-btn">Save</button>
          </form>
        </div>
      </div>

      <button id="logout" class="btn submit-btn">Logout</button>
    </div>
  </main>

  <script>
    document.getElementById('back').addEventListener('click', () => history.back());
    document.getElementById('logout').addEventListener('click', () => {
      fetch('logout.php', { method: 'POST' }).then(() => {
        location.href = 'login.php';
      });
    });
  </script>
</body>
</html>
