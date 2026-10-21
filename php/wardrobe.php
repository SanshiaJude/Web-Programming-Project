
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Only logged-in users can access their wardrobe
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = 'success';

// Handle clothing upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_clothes'])) {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $color = $_POST['color'] ?? '';

    $allowedCategories = ['Top', 'Bottom', 'Dress', 'Outerwear', 'Shoes', 'Accessories'];
    $allowedColors = [
        'White', 'Black', 'Beige', 'Brown', 'Blue',
        'Red', 'Pink', 'Green', 'Yellow', 'Grey',
        'Purple', 'Orange', 'Multicolor', 'Other'
    ];

    if (
        $name === '' ||
        !in_array($category, $allowedCategories, true) ||
        !in_array($color, $allowedColors, true)
    ) {
        $message = 'Please fill in all fields with valid values.';
        $messageType = 'error';
    } elseif (
        !isset($_FILES['clothing_image']) ||
        $_FILES['clothing_image']['error'] !== UPLOAD_ERR_OK
    ) {
        $message = 'Please select a clothing image to upload.';
        $messageType = 'error';
    } else {
        $file = $_FILES['clothing_image'];
        $maxSize = 5 * 1024 * 1024;

        $imageInfo = @getimagesize($file['tmp_name']);
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $mimeType = $imageInfo['mime'] ?? '';

        if ($file['size'] > $maxSize) {
            $message = 'Your image must be smaller than 5 MB.';
            $messageType = 'error';
        } elseif (
            $imageInfo === false ||
            !isset($allowedMimeTypes[$mimeType])
        ) {
            $message = 'Please upload a JPG, PNG, or WebP image.';
            $messageType = 'error';
        } else {
            $uploadDir = __DIR__ . '/../uploads/clothes/';

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                $message = 'Could not create the image upload folder.';
                $messageType = 'error';
            } else {
                $extension = $allowedMimeTypes[$mimeType];
                $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $uploadDir . $fileName;
                $relativePath = 'uploads/clothes/' . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $message = 'The image could not be uploaded. Please try again.';
                    $messageType = 'error';
                } else {
                    $stmt = $conn->prepare(
                        'INSERT INTO clothes (user_id, name, category, color, image_path)
                         VALUES (?, ?, ?, ?, ?)'
                    );

                    $stmt->bind_param(
                        'issss',
                        $userId,
                        $name,
                        $category,
                        $color,
                        $relativePath
                    );

                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: wardrobe.php?added=1');
                        exit;
                    } else {
                        $stmt->close();
                        if (is_file($destination)) {
                            unlink($destination);
                        }

                        $message = 'Could not save the clothing item to the database.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Your clothing item was added successfully!';
}

// Handle deleting a clothing item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_clothes'])) {
    $clothesId = filter_input(INPUT_POST, 'clothes_id', FILTER_VALIDATE_INT);

    if ($clothesId) {
        // Find the image belonging to this user
        $stmt = $conn->prepare(
            'SELECT image_path FROM clothes WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('ii', $clothesId, $userId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($item) {
            $stmt = $conn->prepare(
                'DELETE FROM clothes WHERE id = ? AND user_id = ?'
            );
            $stmt->bind_param('ii', $clothesId, $userId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();

                // Remove the image file after the database deletion
                $imageFile = realpath(
                    __DIR__ . '/../' . $item['image_path']
                );
                $imageRoot = realpath(__DIR__ . '/../uploads/clothes');

                if (
                    $imageFile !== false &&
                    $imageRoot !== false &&
                    strpos($imageFile, $imageRoot . DIRECTORY_SEPARATOR) === 0 &&
                    is_file($imageFile)
                ) {
                    unlink($imageFile);
                }

                header('Location: wardrobe.php?deleted=1');
                exit;
            }

            $stmt->close();
            $message = 'Could not delete this item.';
            $messageType = 'error';
        } else {
            $message = 'Clothing item not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid clothing item.';
        $messageType = 'error';
    }
}

if (isset($_GET['deleted'])) {
    $message = 'Clothing item deleted successfully!';
}

// Get filters
$filterCategory = $_GET['category'] ?? '';
$filterColor = $_GET['color'] ?? '';

$allowedCategories = ['Top', 'Bottom', 'Dress', 'Outerwear', 'Shoes', 'Accessories'];
$allowedColors = [
    'White', 'Black', 'Beige', 'Brown', 'Blue',
    'Red', 'Pink', 'Green', 'Yellow', 'Grey',
    'Purple', 'Orange', 'Multicolor', 'Other'
];

if (!in_array($filterCategory, $allowedCategories, true)) {
    $filterCategory = '';
}

if (!in_array($filterColor, $allowedColors, true)) {
    $filterColor = '';
}

// Retrieve only this user's clothing items
$sql = 'SELECT id, name, category, color, image_path
        FROM clothes
        WHERE user_id = ?';
$types = 'i';
$params = [$userId];

if ($filterCategory !== '') {
    $sql .= ' AND category = ?';
    $types .= 's';
    $params[] = $filterCategory;
}

if ($filterColor !== '') {
    $sql .= ' AND color = ?';
    $types .= 's';
    $params[] = $filterColor;
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$clothes = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wardrobe | StyleShelf</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="dashboard-page">

    <aside class="sidebar">
        <a class="brand" href="dashboard.php">StyleShelf</a>
        <p class="sidebar-label">YOUR WARDROBE</p>

        <nav>
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="wardrobe.php">My Wardrobe</a>
            <a class="nav-link" href="outfits.php">Mix &amp; Match</a>
        </nav>

        <a class="nav-link logout-link" href="logout.php">Log Out</a>
    </aside>

    <main class="dashboard-main">
        <header class="page-header">
            <div>
                <p class="eyebrow">YOUR PERSONAL COLLECTION</p>
                <h1>My Wardrobe</h1>
                <p>Every piece you own, all in one place.</p>
            </div>

            <a href="#add-clothes" class="primary-link">+ Add Clothes</a>
        </header>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <section class="content-card" id="add-clothes">
            <h2>Add a clothing item</h2>

            <form class="clothing-form"
                  method="POST"
                  enctype="multipart/form-data">

                <div class="form-group">
                    <label for="name">Clothing name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="e.g. White cotton shirt"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Choose category</option>
                        <option value="Top">Top</option>
                        <option value="Bottom">Bottom</option>
                        <option value="Dress">Dress</option>
                        <option value="Outerwear">Outerwear</option>
                        <option value="Shoes">Shoes</option>
                        <option value="Accessories">Accessories</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="color">Color</label>
                    <select id="color" name="color" required>
                        <option value="">Choose color</option>
                        <option value="White">White</option>
                        <option value="Black">Black</option>
                        <option value="Beige">Beige</option>
                        <option value="Brown">Brown</option>
                        <option value="Blue">Blue</option>
                        <option value="Red">Red</option>
                        <option value="Pink">Pink</option>
                        <option value="Green">Green</option>
                        <option value="Yellow">Yellow</option>
                        <option value="Grey">Grey</option>
                        <option value="Purple">Purple</option>
                        <option value="Orange">Orange</option>
                        <option value="Multicolor">Multicolor</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="clothing_image">Clothing photo</label>
                    <input
                        type="file"
                        id="clothing_image"
                        name="clothing_image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        required
                    >
                    <small class="text-muted">
                        JPG, PNG or WebP. Maximum size: 5 MB.
                    </small>
                </div>

                <div class="form-group full-width">
                    <button
                        type="submit"
                        name="add_clothes"
                        value="1"
                        class="primary-btn"
                    >
                        Add to My Wardrobe
                    </button>
                </div>
            </form>
        </section>

        <section class="recent-section">
            <div class="section-heading">
                <div>
                    <h2>Your Clothing Collection</h2>
                    <p>Filter your clothes by category and color.</p>
                </div>
            </div>

            <form method="GET" action="wardrobe.php" class="filter-bar">
                <div class="filter-group">
                    <label for="filter-category">Category</label>
                    <select id="filter-category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($allowedCategories as $category): ?>
                            <option
                                value="<?= htmlspecialchars($category) ?>"
                                <?= $filterCategory === $category ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-color">Color</label>
                    <select id="filter-color" name="color">
                        <option value="">All colors</option>
                        <?php foreach ($allowedColors as $color): ?>
                            <option
                                value="<?= htmlspecialchars($color) ?>"
                                <?= $filterColor === $color ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($color) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="primary-btn">Apply Filters</button>
                <a href="wardrobe.php" class="secondary-link">Clear Filters</a>
            </form>

            <div class="clothes-grid">
                <?php if ($clothes->num_rows > 0): ?>
                    <?php while ($item = $clothes->fetch_assoc()): ?>
                        <article class="clothing-card">
                            <img
                                src="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                            >

                            <div class="clothing-info">
                                <h3>
                                    <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
                                    &middot;
                                    <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <div class="card-actions">
                                    <form method="POST"
                                          action="wardrobe.php"
                                          onsubmit="return confirm('Delete this clothing item?');">

                                        <input
                                            type="hidden"
                                            name="clothes_id"
                                            value="<?= (int) $item['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_clothes"
                                            value="1"
                                            class="danger-btn"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No clothing items found</h3>
                        <p>
                            <?php if ($filterCategory !== '' || $filterColor !== ''): ?>
                                Try changing your filters, or add a new clothing item.
                            <?php else: ?>
                                Your wardrobe is empty. Upload your first clothing photo above!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
<?php
$stmt->close();
?>
