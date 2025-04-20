<?php
// login.php
require 'config.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // autentificare reușită
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'User/parolă incorecte.';
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Login – Webnote</title>
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
      <h1>Login</h1>

      <?php if ($error): ?>
        <p class="errors"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="post" action="login.php" id="login-form">
        <label>
          <span>User:</span>
          <input type="text" name="username" placeholder="username" required>
        </label>
        <label>
          <span>Parolă:</span>
          <input type="password" name="password" placeholder="••••••••" required>
        </label>
        <button type="submit" class="btn submit-btn">Login</button>
      </form>

      <p class="signup">
        Nu ai cont?
        <a href="register.php">Înregistrează-te</a>
      </p>
    </div>
  </main>

  <script>
    document.getElementById('back').addEventListener('click', () => history.back());
  </script>
</body>
</html>
