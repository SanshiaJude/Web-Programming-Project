
<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Only logged-in users can access the dashboard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Fashion Lover';

// Get the number of clothing items
$stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM clothes WHERE user_id = ?'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalClothes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get the number of saved outfits
$stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM outfits WHERE user_id = ?'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalOutfits = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get recently added clothing items
$stmt = $conn->prepare(
    'SELECT id, name, category, color, image_path
     FROM clothes
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 4'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentClothes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | StyleShelf</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="dashboard-page">

    <aside class="sidebar">
        <a class="brand" href="dashboard.php">StyleShelf</a>
        <p class="sidebar-label">YOUR WARDROBE</p>

        <nav>
            <a class="nav-link active" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="wardrobe.php">My Wardrobe</a>
            <a class="nav-link" href="outfits.php">Mix &amp; Match</a>
        </nav>

        <a class="nav-link logout-link" href="logout.php">Log Out</a>
    </aside>

    <main class="dashboard-main">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">YOUR PERSONAL CLOSET</p>
                <h1>Hello, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <p>Let's put together something you love.</p>
            </div>

            <a class="primary-link" href="wardrobe.php">+ Add Clothes</a>
        </header>

        <section class="stats-grid">
            <article class="stat-card">
                <p>Wardrobe Items</p>
                <h2><?= (int) $totalClothes ?></h2>
            </article>

            <article class="stat-card">
                <p>Saved Outfits</p>
                <h2><?= (int) $totalOutfits ?></h2>
            </article>
        </section>

        <section class="recent-section">
            <div class="section-heading">
                <div>
                    <h2>Recently Added</h2>
                    <p>Your latest wardrobe additions</p>
                </div>

                <a href="wardrobe.php">View all</a>
            </div>

            <div class="clothes-grid">
                <?php if ($recentClothes->num_rows > 0): ?>
                    <?php while ($item = $recentClothes->fetch_assoc()): ?>
                        <article class="clothing-card">
                            <img
                                src="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <div class="clothing-info">
                                <h3><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p>
                                    <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
                                    ·
                                    <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>Your wardrobe is waiting!</h3>
                        <p>Add your first clothing item to get started.</p>
                        <a class="primary-link" href="wardrobe.php">Add Your First Item</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="mix-banner">
            <div>
                <p class="eyebrow">YOUR NEXT LOOK</p>
                <h2>Mix. Match. Make it yours.</h2>
                <p>Select a top and a bottom to create your outfit.</p>
            </div>

            <a class="secondary-link" href="outfits.php">Create an Outfit</a>
        </section>
    </main>

</body>
</html>

<?php
$stmt->close();
?>
