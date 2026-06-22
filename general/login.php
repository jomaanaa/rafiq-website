<?php
session_start();
$error = "";

if (isset($_POST["login"])) {
    $email = trim($_POST["email"] ?? '');
    $pwd   = $_POST["pass"] ?? '';

    if ($email === '' || $pwd === '') {
        $error = "Please enter email and password.";
    } else {
        require __DIR__ . '/../pgdb/db.php';

        try {
            $query = '
                SELECT
                    u.user_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.password,
                    u.role,
                    a.user_id   AS admin_id,
                    p.user_id   AS patient_id,
                    d.user_id   AS doctor_id,
                    i.user_id   AS interpreter_id,
                    c.user_id   AS caregiver_id,
                    dr.user_id  AS driver_id,
                    prov.status AS provider_status
                FROM "user" u
                LEFT JOIN admin a        ON u.user_id = a.user_id
                LEFT JOIN patient p      ON u.user_id = p.user_id
                LEFT JOIN doctor d       ON u.user_id = d.user_id
                LEFT JOIN interpreter i  ON u.user_id = i.user_id
                LEFT JOIN caregiver c    ON u.user_id = c.user_id
                LEFT JOIN driver dr      ON u.user_id = dr.user_id
                LEFT JOIN provider prov  ON u.user_id = prov.user_id
                WHERE u.email = :email
                LIMIT 1
            ';

            $stmt = $pdo->prepare($query);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($pwd, $user['password'])) {
                session_unset();
                session_destroy();

                session_start();
                session_regenerate_id(true);

                $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                if ($fullName === '') $fullName = 'User';

                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $fullName;

                // ADMIN — same app flow: admin signs in from the main login page.
                if (($user['role'] ?? '') === 'admin' || !empty($user['admin_id'])) {
                    $_SESSION['role']       = 'admin';
                    $_SESSION['admin_id']   = $user['user_id'];
                    $_SESSION['admin_name'] = $fullName;

                    header("Location: ../admin/admin_dashboard.php");
                    exit();
                }

                // PATIENT
                if (!is_null($user['patient_id']) || ($user['role'] ?? '') === 'patient') {
                    $_SESSION['role'] = 'patient';
                    $_SESSION['patient_id'] = $user['user_id'];

                    header("Location: ../patient/patient_homepage.php");
                    exit();
                }

                // DOCTOR
                elseif (!is_null($user['doctor_id'])) {
                    if (($user['provider_status'] ?? '') === 'pending') {
                        $_SESSION['role'] = 'provider';
                        header("Location: ../providers/pending.php");
                        exit();
                    }
                    if (($user['provider_status'] ?? '') === 'rejected') {
                        $error = "Your application was not approved. Please contact support.";
                    } else {
                        $_SESSION['role'] = 'provider';
                        $_SESSION['provider_id'] = $user['user_id'];
                        $_SESSION['provider_type'] = 'doctor';
                        header("Location: ../providers/doctor/doctor_homepage.php");
                        exit();
                    }
                }

                // INTERPRETER
                elseif (!is_null($user['interpreter_id'])) {
                    if (($user['provider_status'] ?? '') === 'pending') {
                        $_SESSION['role'] = 'provider';
                        header("Location: ../providers/pending.php");
                        exit();
                    }
                    if (($user['provider_status'] ?? '') === 'rejected') {
                        $error = "Your application was not approved. Please contact support.";
                    } else {
                        $_SESSION['role'] = 'provider';
                        $_SESSION['provider_id'] = $user['user_id'];
                        $_SESSION['provider_type'] = 'interpreter';
                        header("Location: ../providers/interpreter/int_homepage.php");
                        exit();
                    }
                }

                // CAREGIVER
                elseif (!is_null($user['caregiver_id'])) {
                    if (($user['provider_status'] ?? '') === 'pending') {
                        $_SESSION['role'] = 'provider';
                        header("Location: ../providers/pending.php");
                        exit();
                    }
                    if (($user['provider_status'] ?? '') === 'rejected') {
                        $error = "Your application was not approved. Please contact support.";
                    } else {
                        $_SESSION['role'] = 'provider';
                        $_SESSION['provider_id'] = $user['user_id'];
                        $_SESSION['provider_type'] = 'caregiver';
                        header("Location: ../providers/caregiver/caregiver_homepage.php");
                        exit();
                    }
                }

                // DRIVER
                elseif (!is_null($user['driver_id'])) {
                    if (($user['provider_status'] ?? '') === 'pending') {
                        $_SESSION['role'] = 'provider';
                        header("Location: ../providers/pending.php");
                        exit();
                    }
                    if (($user['provider_status'] ?? '') === 'rejected') {
                        $error = "Your application was not approved. Please contact support.";
                    } else {
                        $_SESSION['role'] = 'provider';
                        $_SESSION['provider_id'] = $user['user_id'];
                        $_SESSION['provider_type'] = 'driver';
                        header("Location: ../providers/driver/driver_portal.php");
                        exit();
                    }
                }

                else {
                    $error = "User role not found.";
                }

            } else {
                $error = "Invalid email or password.";
            }

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rafiq Login</title>

<style>

/* BODY */
body {
    margin: 0;
    font-family: 'Arial', sans-serif;
    background-color: #404066;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

/* MAIN CARD */
.login-card {
    display: flex;
    width: 900px;
    height: 550px;
    border-radius: 25px;
    overflow: hidden;
    background: #FFFFFF;
}

/* LEFT SIDE */
.left-side {
    flex: 1;
    background: #F2F2F6;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 40px;
    text-align: center;
}

.left-side img {
    width: 320px;
    margin-bottom: 30px;
}

.left-text {
    font-size: 18px;
    color: #2B2C41;
}

/* RIGHT SIDE */
.right-side {
    flex: 1;
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.logo {
    text-align: center;
    margin-bottom: 20px;
}

.logo img {
    width: 120px;
}

.right-side h2 {
    text-align: center;
    margin-bottom: 40px;
    color: #2B2C41;
}

/* FORM */
form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

label {
    font-weight: 600;
    color: #2B2C41;
}

input {
    padding: 14px;
    border-radius: 12px;
    border: 1px solid #D1D1D1;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #404066;
}

button {
    padding: 14px;
    border-radius: 12px;
    background-color: #404066;
    color: white;
    font-weight: 600;
    border: none;
    cursor: pointer;
    font-size: 15px;
}

button:hover {
    background-color: #2B2C41;
}

/* SIGNUP */
.signup-text {
    margin-top: 20px;
    text-align: center;
    font-size: 14px;
}

.signup-text a {
    color: #404066;
    font-weight: 600;
    text-decoration: none;
}

/* ERROR */
.alert-danger {
    background-color: #B53535;
    color: white;
    border-radius: 10px;
    text-align: center;
    padding: 12px;
    margin-top: 20px;
}

</style>
</head>

<body>

<div class="login-card">

    <!-- LEFT SIDE -->
    <div class="left-side">
        <img src="../pictures/pic1.jpeg" alt="Illustration">
        <p class="left-text">
            Your indispensable digital <br>
            companion.
        </p>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right-side">

        <div class="logo">
            <img src="../pictures/rafiq_logo.png" alt="Logo">
        </div>

        <h2>Welcome back!</h2>

        <form method="post">

            <label>Email</label>
            <input type="email" name="email" placeholder="example@gmail.com" required>

            <label>Password</label>
            <input type="password" name="pass" placeholder="Enter your password" required>

            <button type="submit" name="login">Sign in</button>

        </form>

        <p class="signup-text">
            Don’t have an account? <a href="signup_role.php">sign up</a>
        </p>

        <?php if($error): ?>
            <div class="alert-danger"><?= $error ?></div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
