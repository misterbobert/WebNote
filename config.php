<?php
// config.php

// 1) Parametri conexiune DB
$host    = 'localhost';
$db      = 'webnote';
$user    = 'root';    // schimbă dacă ai alt user
$pass    = '';        // pune parola dacă ai
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // în dezvoltare, afișează eroarea; în producție, loghează și arată un mesaj generic
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}

// 2) Cheia AES-256 (32 bytes), reprezentată în hex (64 caractere)
//    Generează-ți propria cheie cu: bin2hex(random_bytes(32))
define(
  'ENCRYPTION_KEY',
  hex2bin('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef')
);
