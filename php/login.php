
<?php
// Display errors while developing the project
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/db.php';

$error = '';


// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, name, password FROM users WHERE email = ?'
        );

        if (!$stmt) {
            $error = 'Something went wrong. Please try again.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];

                $stmt->close();

                header('Location: dashboard.php');
                exit;
            }

            $error = 'Invalid email or password.';
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | StyleShelf</title>

    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <main class="auth-container">
        <section class="auth-card">

            <h1>StyleShelf</h1>

            <p class="subtitle">
                Your wardrobe, beautifully organized.
            </p>

            <h2>Welcome back</h2>

            <?php if (isset($_GET['registered'])): ?>
                <p class="message">
                    Account created successfully! Please log in.
                </p>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <p class="message">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="">

                <label for="email">Email address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="Enter your email"
                    autocomplete="email"
                    required
                >

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button type="submit">Log In</button>

            </form>

            <p class="auth-link">
                Don't have an account?
                <a href="register.php">Create one</a>
            </p>

        </section>
    </main>

</body>
</html>
