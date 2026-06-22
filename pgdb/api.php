<?php
header("Content-Type: application/json");

// Get request type from URL
$request = $_GET['request'] ?? '';

switch ($request) {

    case 'users':
        require 'users.php';
        break;

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Invalid request"
        ]);
        break;
}
?>