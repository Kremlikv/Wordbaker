<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

// $PIXABAY_API_KEY = '51629627-a41f1d96812d8b351d3f25867';

$table = $_GET['table'] ?? '';
$msg = $_GET['msg'] ?? '';

if (!$table) {
    die("<p style='color:red;font-weight:bold;'>❌ No table specified.</p>");
}

// Validate table exists
$tableEsc = $conn->real_escape_string($table);
$resCheck = $conn->query("SHOW TABLES LIKE '$tableEsc'");
if (!$resCheck || $resCheck->num_rows === 0) {
    die("<p style='color:red;font-weight:bold;'>❌ Table '$table' does not exist in the database.</p>");
}

$conn->set_charset("utf8mb4");

// ✅ Bulk set default images if requested
if (isset($_GET['set_default']) && $_GET['set_default'] == '1') {
    $stmt = $conn->prepare("UPDATE `$table` SET image_url = 'quiz_logo.png'");
    $stmt->execute();
    $stmt->close();
    $msg = "✅ All questions now use the default image.";
}

/* Save updates */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['image_url'] as $id => $url) {
        $id = intval($id);
        $url = trim($url);

        // Check if a file was uploaded
       $uploadDir = __DIR__ . "/uploads/quiz_images/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Case 1: Uploaded from PC
        if (isset($_FILES['image_file']['name'][$id]) && $_FILES['image_file']['error'][$id] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image_file']['tmp_name'][$id];
            $ext = strtolower(pathinfo($_FILES['image_file']['name'][$id], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $newName = "quiz_" . $id . "_" . uniqid() . "." . $ext;
                move_uploaded_file($tmpName, $uploadDir . $newName);
                $url = "uploads/quiz_images/" . $newName;
            }
        }

        // Case 2: Selected from Pixabay (starts with https://pixabay.com or cdn.pixabay.com)
        elseif (filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https://(cdn\.)?pixabay\.com/#', $url)) {
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $newName = "quiz_" . $id . "_" . uniqid() . "." . $ext;
                $imgData = file_get_contents($url);
                if ($imgData !== false) {
                    file_put_contents($uploadDir . $newName, $imgData);
                    $url = "uploads/quiz_images/" . $newName;
                }
            }
        }


        // ✅ Set default if still empty
        if ($url === '' || $url === null) {
            $url = 'quiz_logo.png';
        }

        $stmt = $conn->prepare("UPDATE `$table` SET image_url=? WHERE id=?");
        $stmt->bind_param("si", $url, $id);
        $stmt->execute();
        $stmt->close();
    }
    $msg = "✅ Images saved successfully!";
}

$res = $conn->query("SELECT id, question, image_url FROM `$table` ORDER BY id ASC");
if (!$res) die("Table not found.");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Images</title>
<style>
body { font-family: Arial; margin: 0px; }
.msg { color: green; font-weight: bold; margin-bottom: 10px; }
.question-block { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; border-radius: 8px; }
.question-text { font-weight: bold; margin-bottom: 8px; }
.image-strip { display: flex; overflow-x: auto; gap: 10px; padding-bottom: 5px; }
.image-strip img { max-height: 100px; cursor: pointer; border: 3px solid transparent; border-radius: 4px; }
.image-strip img.selected { border-color: blue; }
@media (max-width: 600px) {
    .question-block { font-size: 14px; }
    .image-strip img { max-height: 80px; }
}
</style>
<script>
const PIXABAY_KEY = <?php echo json_encode($PIXABAY_API_KEY); ?>;
function searchImages(qid) {
    let query = document.getElementById('search_' + qid).value.trim();
    if (!query) return;
    let container = document.getElementById('images_' + qid);
    container.innerHTML = '<em>Loading...</em>';
    fetch(`https://pixabay.com/api/?key=${PIXABAY_KEY}&q=${encodeURIComponent(query)}&image_type=photo&per_page=10&safe_search=true`)
        .then(r => r.json())
        .then(data => {
            container.innerHTML = '';
            if (data.hits && data.hits.length) {
                data.hits.forEach(hit => {
                    let img = document.createElement('img');
                    img.src = hit.previewURL;
                    img.onclick = () => selectImage(qid, hit.largeImageURL, img);
                    container.appendChild(img);
                });
            } else {
                container.innerHTML = '<em>No results</em>';
            }
        });
}
function selectImage(qid, url, imgElement) {
    document.getElementById('image_url_' + qid).value = url;
    document.querySelectorAll('#images_' + qid + ' img').forEach(el => el.classList.remove('selected'));
    imgElement.classList.add('selected');
}
</script>
</head>
<body>

<div class='content'>
<p>👤 Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> | <a href='logout.php'>Logout</a></p>
</div>

<?php if ($msg): ?>
<div class="msg"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div style="margin-bottom:15px;">
    <a href="?table=<?= urlencode($table) ?>&set_default=1"><button>🚫 I do not want any pictures (Use default)</button></a>
    <a href="generate_quiz_choices.php?table=<?= urlencode(preg_replace('/^quiz_choices_/', '', $table)) ?>"><button>⬅ Back</button></a>

</div>

<form method="POST" enctype="multipart/form-data">
<?php while ($row = $res->fetch_assoc()):
    $currentImage = (isset($row['image_url']) && trim($row['image_url']) !== '') ? $row['image_url'] : 'quiz_logo.png';
?>
<div class="question-block">
    <div class="question-text"><?php echo htmlspecialchars($row['question']); ?></div>
    <input type="hidden" name="image_url[<?php echo $row['id']; ?>]" id="image_url_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($currentImage); ?>">
    <div>
        <label>Upload from PC:</label>
        <input type="file" name="image_file[<?php echo $row['id']; ?>]">
    </div>
    <div>
        <input type="text" id="search_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['question']); ?>" style="width:70%;">
        <button type="button" onclick="searchImages(<?php echo $row['id']; ?>)">Search Pixabay</button>
    </div>
    <div class="image-strip" id="images_<?php echo $row['id']; ?>">
        <img src="<?php echo htmlspecialchars($currentImage); ?>" class="selected">
    </div>
</div>
<?php endwhile; ?>
<div style="text-align:center;">
    <button type="submit">💾 Save All Images</button>
</div>
</form>

</body>
</html>
