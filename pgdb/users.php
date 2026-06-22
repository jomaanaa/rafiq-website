<?php
require 'db.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $action = $_POST['action'] ?? '';

    // REGISTER
    if ($action === 'register') {

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(["status" => "error", "message" => "Missing fields"]);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hashedPassword]);

        echo json_encode(["status" => "success", "message" => "User registered"]);
        exit;
    }

    // LOGIN
    if ($action === 'login') {

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            echo json_encode([
                "status" => "success",
                "message" => "Login successful"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid credentials"
            ]);
        }
        exit;
    }
}
?>