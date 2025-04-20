<?php
session_start();
require 'config.php';

// Dacă nu ești autentificat, redirecționează la login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Preia detaliile utilizatorului
$stmt = $pdo->prepare('SELECT email, username, created_at FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$email     = htmlspecialchars($user['email']);
$username  = htmlspecialchars($user['username']);
$createdAt = htmlspecialchars($user['created_at']);
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
      <h1>Contul meu</h1>
      <div class="details">
        <p><strong>Username:</strong> @<?= $username ?></p>
        <p><strong>Email:</strong> <?= $email ?></p>
        <p><strong>Data creării:</strong> <?= $createdAt ?></p>
      </div>
      <button id="logout" class="btn submit-btn">Logout</button>
    </div>
  </main>

  <script>
    // Go back
    document.getElementById('back').addEventListener('click', () => history.back());
    // Logout: distruge sesiunea și redirecționează
    document.getElementById('logout').addEventListener('click', () => {
      fetch('logout.php', { method: 'POST' })
        .then(() => location.href = 'login.php');
    });
  </script>
</body>
</html>
