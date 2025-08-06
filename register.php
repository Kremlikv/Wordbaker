<?php
require_once 'db_users.php';
include 'styling_welcome.php';

$feedback = "";
$show_form = true;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    
    if (!empty($username) && !empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hash);
        
        if ($stmt->execute()) {
            $feedback = "✅ Registration successful. <a href='login.php'>Login here</a>.";
            $show_form = false;  // Hide the form
        } else {
            $feedback = "❌ Username already taken.";
        }
    } else {
        $feedback = "⚠️ Fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Register</title></head>
<body>
<div class='content'>
<h2>Register</h2>
</body>
</php>

<?php echo "<p>$feedback</p>"; ?>

<?php if ($show_form): ?>
<form method="POST">
    Username: <input name="username" required><br>
    Password: <input type="password" name="password" required><br>
    Email: <input name="email" required><br>
    <button type="submit">Register</button>
</form>
<?php endif; ?>
</div>


</body>
</html>


