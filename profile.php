<?php
session_start();
require 'config.php';

// dacă nu ești logat, du-te la login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Procesăm POST-ul de schimbare email/parolă
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['user_id'];

    // 1) preluăm hash-ul curent
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $errors[] = 'Utilizator invalid.';
    } else {
        $hash = $row['password'];

        // 2) schimbare EMAIL
        if (!empty($_POST['action']) && $_POST['action'] === 'email_change') {
            $newEmail  = trim($_POST['new_email'] ?? '');
            $currentPw = $_POST['current_password'] ?? '';

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalid.';
            } elseif (!password_verify($currentPw, $hash)) {
                $errors[] = 'Parola curentă este incorectă.';
            } else {
                $upd = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                if ($upd->execute([$newEmail, $uid])) {
                    $success = 'Email-ul a fost actualizat cu succes.';
                } else {
                    $errors[] = 'Eroare la actualizarea email-ului.';
                }
            }
        }

        // 3) schimbare PAROLĂ
        if (!empty($_POST['action']) && $_POST['action'] === 'password_change') {
            $newPw       = $_POST['new_password'] ?? '';
            $confirmPw   = $_POST['confirm_password'] ?? '';
            $currentPwPw = $_POST['current_password_pw'] ?? '';

            if ($newPw !== $confirmPw) {
                $errors[] = 'Noile parole nu coincid.';
            } elseif (!password_verify($currentPwPw, $hash)) {
                $errors[] = 'Parola curentă este incorectă.';
            } else {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $upd     = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                if ($upd->execute([$newHash, $uid])) {
                    $success = 'Parola a fost schimbată cu succes.';
                } else {
                    $errors[] = 'Eroare la schimbarea parolei.';
                }
            }
        }
    }

    // redirecționăm dacă totul a mers OK
    if ($success && empty($errors)) {
        header('Location: profile.php');
        exit;
    }
}

// 4) Preluăm detaliile utilizatorului
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
  <!-- 1) Stilurile de bază (panel-btn, variabile, etc) -->
  <link rel="stylesheet" href="style.css">
  <!-- 2) Stilurile comune login/register -->
  <link rel="stylesheet" href="login.css">
  <!-- 3) Suprascrieri/ex Mic profile -->
  <link rel="stylesheet" href="profile.css">
</head>
<body>
  <header class="header">
    <button id="back" class="panel-btn back-btn">Go back</button>
  </header>

  <main class="login-page">
    <div class="profile-card-large">
      <h1>My account</h1>

      <?php if ($errors): ?>
        <ul class="errors">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="profile-content">

        <!-- DETALII + Change -->
        <div class="details">
          <p><strong>Username:</strong> @<?= $username ?></p>

          <p>
            <strong>Email:</strong> <?= $email ?>
            <button class="panel-btn change-btn" data-target="email-form">Change</button>
          </p>

          <p>
            <strong>Password:</strong> ********
            <button class="panel-btn change-btn" data-target="password-form">Change</button>
          </p>

          <p><strong>Date created:</strong> <?= $createdAt ?></p>

          <!-- FORM Schimbare EMAIL -->
          <form id="email-form" class="change-form" method="post" action="profile.php">
            <input type="hidden" name="action" value="email_change">

            <label>
              <span>New Email:</span>
              <input type="email" name="new_email" placeholder="you@example.com" required>
            </label>

            <label>
              <span>Current Password:</span>
              <input type="password" name="current_password" placeholder="••••••••" required>
            </label>

            <div class="form-buttons">
              <button type="submit"  class="panel-btn save-btn">Save</button>
              <button type="button" class="panel-btn cancel-btn">Cancel</button>
            </div>
          </form>

          <!-- FORM Schimbare PAROLĂ -->
          <form id="password-form" class="change-form" method="post" action="profile.php">
            <input type="hidden" name="action" value="password_change">

            <label>
              <span>New Password:</span>
              <input type="password" name="new_password" placeholder="••••••••" required>
            </label>

            <label>
              <span>Confirm New Password:</span>
              <input type="password" name="confirm_password" placeholder="••••••••" required>
            </label>

            <label>
              <span>Current Password:</span>
              <input type="password" name="current_password_pw" placeholder="••••••••" required>
            </label>

            <div class="form-buttons">
              <button type="submit"  class="panel-btn save-btn">Save</button>
              <button type="button" class="panel-btn cancel-btn">Cancel</button>
            </div>
          </form>
        </div>

        <!-- CARD IMAGINE -->
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
            <button type="submit" class="panel-btn save-btn">Save</button>
          </form>
        </div>
      </div>

      <button id="logout" class="panel-btn submit-btn">Logout</button>
    </div>
  </main>
  <script>
document.addEventListener('DOMContentLoaded', () => {
  // 1) ascunde toate change-form la start
  document.querySelectorAll('.change-form').forEach(f => {
    f.style.display = 'none';
  });

  // 2) Change → arată formularul țintă
  document.querySelectorAll('.change-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.target;
      document.querySelectorAll('.change-form').forEach(f => f.style.display = 'none');
      document.getElementById(id).style.display = 'flex';
    });
  });

  // 3) Cancel → ascunde formularul părinte
  document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const form = btn.closest('.change-form');
      if (form) form.style.display = 'none';
    });
  });

  // 4) Go back + Logout
  document.getElementById('back').addEventListener('click', () => history.back());
  document.getElementById('logout').addEventListener('click', () => {
    fetch('logout.php', { method: 'POST' })
      .then(() => location.href = 'login.php');
  });
});
</script>

</body>
</html>
