<?php
// register.php
require 'config.php';
session_start();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if ($password !== $confirm) {
        $errors[] = 'Parolele nu coincid.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (email, username, password) VALUES (?, ?, ?)');
        if ($stmt->execute([$email, $username, $hash])) {
            header('Location: login.php');
            exit;
        } else {
            $errors[] = 'Înregistrarea a eșuat.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Register – Webnote</title>
  <link rel="stylesheet" href="login.css">
  <style>
  /* fundal mai deschis pentru input-uri */
  input[type="email"],
  input[type="text"],
  input[type="password"] {
    background: rgba(92,128,125,0.3);
    color: #FFFFFF;
    border: none;
    border-radius: 4px;
    padding: 0.5rem;
    margin-top: 0.25rem;
  }
  input::placeholder {
    color: rgba(255,255,255,0.7);
  }
</style>

</head>
<body>
  <header class="header">
    <button id="back" class="btn back-btn">Go back</button>
  </header>

  <main class="login-page">
    <div class="login-card">
      <h1>Register</h1>

      <?php if ($errors): ?>
        <ul class="errors">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post" action="register.php" id="register-form">
        <label>
          <span>Email:</span>
          <input type="email" name="email" placeholder="you@example.com" required>
        </label>
        <label>
          <span>User:</span>
          <input type="text" name="username" placeholder="username" required>
        </label>
        <label>
          <span>Parolă:</span>
          <input type="password" name="password" placeholder="••••••••" required>
        </label>
        <label>
          <span>Confirmă parola:</span>
          <input type="password" name="confirm" placeholder="••••••••" required>
        </label>
        <button type="submit" class="btn submit-btn">Register</button>
      </form>

      <p class="signup">
        Ai deja cont?
        <a href="login.php">Autentifică-te</a>
      </p>
    </div>
  </main>

  <script>
    document.getElementById('back').addEventListener('click', () => history.back());
  </script>
</body>
</html>
