
<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = 'success';

$occasions = ['Casual', 'College', 'Party', 'Formal', 'Vacation', 'Other'];

// Fetch this user's clothing, including their photos.
$stmt = $conn->prepare(
    'SELECT id, name, category, color, image_path
     FROM clothes
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$wardrobe = [
    'Top' => [],
    'Bottom' => [],
    'Dress' => []
];

while ($item = $result->fetch_assoc()) {
    if (isset($wardrobe[$item['category']])) {
        $wardrobe[$item['category']][] = $item;
    }
}
$stmt->close();

// Save a new outfit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_outfit'])) {
    $name = trim($_POST['outfit_name'] ?? '');
    $occasion = $_POST['occasion'] ?? 'Casual';
    $outfitType = $_POST['outfit_type'] ?? '';
    $selectedIds = [];

    if ($outfitType === 'dress') {
        $dressId = filter_input(INPUT_POST, 'dress_id', FILTER_VALIDATE_INT);
        if ($dressId) {
            $selectedIds = [$dressId];
        }
    } elseif ($outfitType === 'separates') {
        $topId = filter_input(INPUT_POST, 'top_id', FILTER_VALIDATE_INT);
        $bottomId = filter_input(INPUT_POST, 'bottom_id', FILTER_VALIDATE_INT);

        if ($topId && $bottomId && $topId !== $bottomId) {
            $selectedIds = [$topId, $bottomId];
        }
    }

    if (
        $name === '' ||
        strlen($name) > 100 ||
        empty($selectedIds) ||
        !in_array($occasion, $occasions, true) ||
        !in_array($outfitType, ['dress', 'separates'], true)
    ) {
        $message = 'Please choose your clothing, enter an outfit name, and select an occasion.';
        $messageType = 'error';
    } else {
        // Verify every selected item belongs to this user and has
        // the expected category.
        $validItems = true;

        foreach ($selectedIds as $index => $clothesId) {
            $expectedCategory = $outfitType === 'dress'
                ? 'Dress'
                : ($index === 0 ? 'Top' : 'Bottom');

            $check = $conn->prepare(
                'SELECT id FROM clothes
                 WHERE id = ? AND user_id = ? AND category = ?'
            );
            $check->bind_param('iis', $clothesId, $userId, $expectedCategory);
            $check->execute();

            if ($check->get_result()->num_rows !== 1) {
                $validItems = false;
            }
            $check->close();
        }

        if (!$validItems) {
            $message = 'One or more selected items are invalid. Please select them again.';
            $messageType = 'error';
        } else {
            $conn->begin_transaction();

            try {
                $insert = $conn->prepare(
                    'INSERT INTO outfits (user_id, name, occasion)
                     VALUES (?, ?, ?)'
                );
                $insert->bind_param('iss', $userId, $name, $occasion);
                $insert->execute();
                $outfitId = $conn->insert_id;
                $insert->close();

                $insertItem = $conn->prepare(
                    'INSERT INTO outfit_items (outfit_id, clothes_id)
                     VALUES (?, ?)'
                );

                foreach ($selectedIds as $clothesId) {
                    $insertItem->bind_param('ii', $outfitId, $clothesId);
                    $insertItem->execute();
                }

                $insertItem->close();
                $conn->commit();

                header('Location: outfits.php?saved=1');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $message = 'Could not save your outfit. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Delete an outfit belonging to this user.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_outfit'])) {
    $outfitId = filter_input(INPUT_POST, 'outfit_id', FILTER_VALIDATE_INT);

    if ($outfitId) {
        $stmt = $conn->prepare(
            'DELETE FROM outfits WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('ii', $outfitId, $userId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if ($deleted) {
            header('Location: outfits.php?deleted=1');
            exit;
        }

        $message = 'Outfit not found.';
        $messageType = 'error';
    } else {
        $message = 'Invalid outfit.';
        $messageType = 'error';
    }
}

if (isset($_GET['saved'])) {
    $message = 'Your outfit has been saved successfully!';
}
if (isset($_GET['deleted'])) {
    $message = 'Your outfit has been deleted.';
}

// Fetch saved outfits and their clothing photos.
$stmt = $conn->prepare(
    'SELECT o.id AS outfit_id, o.name AS outfit_name, o.occasion,
            c.name AS clothes_name, c.category, c.color, c.image_path
     FROM outfits o
     LEFT JOIN outfit_items oi ON oi.outfit_id = o.id
     LEFT JOIN clothes c ON c.id = oi.clothes_id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$savedOutfits = [];

while ($row = $result->fetch_assoc()) {
    $id = $row['outfit_id'];

    if (!isset($savedOutfits[$id])) {
        $savedOutfits[$id] = [
            'id' => $id,
            'name' => $row['outfit_name'],
            'occasion' => $row['occasion'],
            'items' => []
        ];
    }

    if ($row['image_path'] !== null) {
        $savedOutfits[$id]['items'][] = [
            'name' => $row['clothes_name'],
            'category' => $row['category'],
            'color' => $row['color'],
            'image_path' => $row['image_path']
        ];
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mix &amp; Match | StyleShelf</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="dashboard-page">

<aside class="sidebar">
    <a class="brand" href="dashboard.php">StyleShelf</a>
    <p class="sidebar-label">YOUR WARDROBE</p>
    <nav>
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <a class="nav-link" href="wardrobe.php">My Wardrobe</a>
        <a class="nav-link active" href="outfits.php">Mix &amp; Match</a>
    </nav>
    <a class="nav-link logout-link" href="logout.php">Log Out</a>
</aside>

<main class="dashboard-main">
    <header class="page-header">
        <div>
            <p class="eyebrow">YOUR PERSONAL STYLING STUDIO</p>
            <h1>Mix &amp; Match</h1>
            <p>Choose your clothes by looking at them, not their names.</p>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="content-card">
        <h2>What would you like to wear?</h2>

        <form method="POST" action="outfits.php" id="outfit-form">
            <div class="outfit-mode">
                <label class="mode-option">
                    <input type="radio" name="outfit_type" value="separates" checked>
                    <span>Top + Bottom</span>
                </label>

                <label class="mode-option">
                    <input type="radio" name="outfit_type" value="dress">
                    <span>Dress</span>
                </label>
            </div>

            <div id="separates-picker">
                <h3 class="picker-heading">Choose your top</h3>

                <?php if (empty($wardrobe['Top'])): ?>
                    <p class="text-muted">
                        No tops yet. <a href="wardrobe.php">Add a top to your wardrobe.</a>
                    </p>
                <?php else: ?>
                    <div class="visual-clothing-grid">
                        <?php foreach ($wardrobe['Top'] as $item): ?>
                            <label class="visual-clothing-card">
                                <input type="radio" name="top_id"
                                       value="<?= (int)$item['id'] ?>"
                                       data-image="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <img src="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="visual-item-name">
                                    <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="visual-item-color">
                                    <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 class="picker-heading">Choose your bottom</h3>

                <?php if (empty($wardrobe['Bottom'])): ?>
                    <p class="text-muted">
                        No bottoms yet. <a href="wardrobe.php">Add a bottom to your wardrobe.</a>
                    </p>
                <?php else: ?>
                    <div class="visual-clothing-grid">
                        <?php foreach ($wardrobe['Bottom'] as $item): ?>
                            <label class="visual-clothing-card">
                                <input type="radio" name="bottom_id"
                                       value="<?= (int)$item['id'] ?>"
                                       data-image="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <img src="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="visual-item-name">
                                    <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="visual-item-color">
                                    <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="dress-picker" hidden>
                <h3 class="picker-heading">Choose your dress</h3>

                <?php if (empty($wardrobe['Dress'])): ?>
                    <p class="text-muted">
                        No dresses yet. <a href="wardrobe.php">Add a dress to your wardrobe.</a>
                    </p>
                <?php else: ?>
                    <div class="visual-clothing-grid">
                        <?php foreach ($wardrobe['Dress'] as $item): ?>
                            <label class="visual-clothing-card">
                                <input type="radio" name="dress_id"
                                       value="<?= (int)$item['id'] ?>"
                                       data-image="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <img src="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="visual-item-name">
                                    <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="visual-item-color">
                                    <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="picker-heading">Your outfit preview</h2>
            <p class="text-muted">
                Select a clothing photo above to see it in your outfit.
            </p>

            <div class="outfit-preview" id="outfit-preview">
                <div class="preview-item" id="top-preview-item">
                    <div class="preview-placeholder" id="top-placeholder">Your top</div>
                    <img id="top-preview" alt="Selected top" hidden>
                    <p id="top-preview-name"></p>
                </div>

                <div class="preview-item" id="bottom-preview-item">
                    <div class="preview-placeholder" id="bottom-placeholder">Your bottom</div>
                    <img id="bottom-preview" alt="Selected bottom" hidden>
                    <p id="bottom-preview-name"></p>
                </div>

                <div class="preview-item" id="dress-preview-item" hidden>
                    <div class="preview-placeholder" id="dress-placeholder">Your dress</div>
                    <img id="dress-preview" alt="Selected dress" hidden>
                    <p id="dress-preview-name"></p>
                </div>
            </div>

            <div class="form-group">
                <label for="outfit_name">Give your outfit a name</label>
                <input type="text" id="outfit_name" name="outfit_name"
                       maxlength="100" placeholder="e.g. My College Look" required>
            </div>

            <div class="form-group">
                <label for="occasion">Occasion</label>
                <select id="occasion" name="occasion" required>
                    <?php foreach ($occasions as $occasion): ?>
                        <option value="<?= htmlspecialchars($occasion) ?>">
                            <?= htmlspecialchars($occasion) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="save_outfit" value="1" class="primary-btn">
                Save This Outfit
            </button>
        </form>
    </section>

    <section class="recent-section">
        <div class="section-heading">
            <div>
                <h2>Your Saved Outfits</h2>
                <p>Your favourite looks, all in one place.</p>
            </div>
        </div>

        <?php if (!empty($savedOutfits)): ?>
            <div class="saved-outfits-grid">
                <?php foreach ($savedOutfits as $outfit): ?>
                    <article class="saved-outfit-card">
                        <h3><?= htmlspecialchars($outfit['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p>Occasion: <?= htmlspecialchars($outfit['occasion'], ENT_QUOTES, 'UTF-8') ?></p>

                        <div class="saved-visual-items">
                            <?php foreach ($outfit['items'] as $item): ?>
                                <div class="saved-visual-item">
                                    <img src="../<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                         loading="lazy">
                                    <p><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <form method="POST" action="outfits.php"
                              onsubmit="return confirm('Delete this saved outfit?');">
                            <input type="hidden" name="outfit_id" value="<?= (int)$outfit['id'] ?>">
                            <button type="submit" name="delete_outfit" value="1"
                                    class="danger-btn">Delete Outfit</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No saved outfits yet</h3>
                <p>Choose your clothes above and save your first look.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="../js/script.js"></script>
</body>
</html>
