
<?php
require_once __DIR__ . '/../config/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must contain at least 8 characters.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', $name, $email, $hashedPassword);

        if ($stmt->execute()) {
            header('Location: login.php?registered=1');
            exit;
        }

        $message = $stmt->errno === 1062
            ? 'This email is already registered.'
            : 'Registration failed. Please try again.';

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | StyleShelf</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="auth-container">
        <section class="auth-card">
            <h1>StyleShelf</h1>
            <p class="subtitle">Your wardrobe, beautifully organized.</p>

            <h2>Create your account</h2>

            <?php if ($message !== ''): ?>
                <p class="message">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required>

                <label for="email">Email address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       minlength="8" required>

                <button type="submit">Create Account</button>
            </form>

            <p class="auth-link">
                Already have an account?
                <a href="login.php">Log in</a>
            </p>
        </section>
    </main>
</body>
</html>
