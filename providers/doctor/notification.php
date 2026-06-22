<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$db   = "rafiq";
$user = "postgres";
$pass = "123456789";

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $patient_id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    if (!$patient_id || $role !== 'patient') {
        die("Patient session not found. Please login again.");
    }

    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    // تأكيد وجود جدول الإشعارات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            notification_id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            booking_id INT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link TEXT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Mark as read
    if (isset($_GET['read'])) {
        $notification_id = (int)$_GET['read'];

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = TRUE
            WHERE notification_id = ?
              AND user_id = ?
        ");
        $stmt->execute([$notification_id, $patient_id]);

        header("Location: patient_notifications.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT notification_id, booking_id, title, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family:Arial, sans-serif;
            background:#f7f9fc;
            margin:0;
            padding:30px;
        }

        .box{
            max-width:900px;
            margin:auto;
        }

        h1{
            margin-bottom:25px;
        }

        .item{
            background:#fff;
            border:1px solid #ddd;
            border-radius:12px;
            padding:18px;
            margin-bottom:15px;
            box-shadow:0 4px 12px rgba(0,0,0,0.05);
        }

        .unread{
            border-left:5px solid #16a34a;
        }

        .title{
            font-weight:bold;
            font-size:18px;
            margin-bottom:8px;
        }

        .msg{
            color:#444;
            margin-bottom:10px;
            line-height:1.6;
        }

        .time{
            color:#888;
            font-size:13px;
            margin-bottom:12px;
        }

        .btn{
            display:inline-block;
            text-decoration:none;
            padding:10px 14px;
            border-radius:8px;
            margin-right:8px;
            font-weight:bold;
        }

        .pay{
            background:#2563eb;
            color:white;
        }

        .read{
            background:#eee;
            color:#333;
        }

        .empty{
            background:#fff;
            border-radius:12px;
            padding:25px;
            text-align:center;
            color:#666;
            border:1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>My Notifications</h1>

        <?php if (empty($notifications)): ?>
            <div class="empty">No notifications found.</div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="item <?= !$n['is_read'] ? 'unread' : '' ?>">
                    <div class="title"><?= h($n['title']) ?></div>
                    <div class="msg"><?= h($n['message']) ?></div>
                    <div class="time"><?= h($n['created_at']) ?></div>

                    <?php if (!empty($n['link'])): ?>
                        <a class="btn pay" href="<?= h($n['link']) ?>">Go to Payment</a>
                    <?php endif; ?>

                    <?php if (!$n['is_read']): ?>
                        <a class="btn read" href="?read=<?= (int)$n['notification_id'] ?>">Mark as Read</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>