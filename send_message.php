<?php
session_start();
 

header('Content-Type: application/json');
require 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $receiver_id = $input['receiver_id'];
    $message = $input['message'];
    $sender_id = $_SESSION['user_id'];

    if (empty($receiver_id) || empty($message)) {
        $response['message'] = 'Receiver ID sau mesajul este gol.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$sender_id, $receiver_id, $message]);

            $response['success'] = true;
            $response['message'] = 'Mesaj trimis cu succes.';
        } catch (PDOException $e) {
            $response['message'] = 'Eroare la trimiterea mesajului: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
