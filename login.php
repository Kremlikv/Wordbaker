<?php
session_start();
require_once 'db_users.php'; // Ensure this uses mysqli, not PDO
include 'styling.php';

// ✅ Set charset to match utf8mb4_czech_ci
$conn->set_charset("utf8mb4");

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                $error = "❌ User not found.";
            } else {
                $stmt->bind_result($user_id, $hash);
                $stmt->fetch();

                if (password_verify($password, $hash)) {
                    $_SESSION["user_id"] = $user_id;
                    $_SESSION["username"] = $username;

                    // ✅ Flush session to disk and redirect

                    echo "<!-- Redirecting to main.php -->";
                    session_write_close();
                    header("Location: main.php");                  
                    echo "<script>window.location.href = 'main.php';</script>";
                    echo "If you are not redirected, <a href='main.php'>click here</a>.";
                    exit();               

                } else {
                    $error = "❌ Invalid username or password.";
                }
            }

            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    } else {
        $error = "❗ Please fill in both fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>
<div class='content'>
    <h2>Login</h2>

    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="POST">
        Username: <input name="username" required><br>
        Password: <input type="password" name="password" required><br>
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
