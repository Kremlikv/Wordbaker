<?php
require_once 'db.php';
require_once 'session.php';

$PIXABAY_API_KEY = '51629627-a41f1d96812d8b351d3f25867';

$table = $_GET['table'] ?? '';
if (!$table) {
    die("No table specified.");
}

$conn->set_charset("utf8mb4");

/* --- Save updates --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['image_url'] as $id => $url) {
        $id = intval($id);
        $url = trim($url);

        // Handle file upload
        if (isset($_FILES['image_file']['name'][$id]) && $_FILES['image_file']['error'][$id] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . "/uploads/quiz_images/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $tmpName = $_FILES['image_file']['tmp_name'][$id];
            $ext = strtolower(pathinfo($_FILES['image_file']['name'][$id], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $newName = "quiz_" . $id . "_" . uniqid() . "." . $ext;
                move_uploaded_file($tmpName, $uploadDir . $newName);
                $url = "uploads/quiz_images/" . $newName;
            }
        }

        $stmt = $conn->prepare("UPDATE `$table` SET image_url=? WHERE id=?");
        $stmt->bind_param("si", $url, $id);
        $stmt->execute();
        $stmt->close();
    }

    echo "<div style='text-align:center; color:green; font-weight:bold;'>✅ Images saved successfully!</div>";
}

/* --- Fetch questions --- */
$res = $conn->query("SELECT id, question, image_url FROM `$table` ORDER BY id ASC");
if (!$res) {
    die("Table not found.");
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Images to Quiz</title>
<style>
body {
    font-family: Arial, sans-serif;
    margin: 10px;
}
.question-block {
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 8px;
}
.question-text {
    font-weight: bold;
    margin-bottom: 8px;
}
.image-strip {
    display: flex;
    overflow-x: auto;
    gap: 10px;
    padding-bottom: 5px;
}
.image-strip img {
    max-height: 100px;
    cursor: pointer;
    border: 3px solid transparent;
    border-radius: 4px;
}
.image-strip img.selected {
    border-color: blue;
}
.upload-section, .search-section {
    margin-top: 5px;
}
button {
    padding: 5px 10px;
    cursor: pointer;
}
.save-btn {
    margin-top: 20px;
    padding: 10px 20px;
}
@media (max-width: 600px) {
    .question-block {
        font-size: 14px;
    }
    .image-strip img {
        max-height: 80px;
    }
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

<h2>🖼 Add Images to: <code><?php echo htmlspecialchars($table); ?></code></h2>
<a href="generate_quiz_questions.php">⬅ Back to Quiz Editor</a>

<form method="POST" enctype="multipart/form-data">
<?php while ($row = $res->fetch_assoc()): ?>
<div class="question-block">
    <div class="question-text"><?php echo htmlspecialchars($row['question']); ?></div>
    <input type="hidden" name="image_url[<?php echo $row['id']; ?>]" id="image_url_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['image_url']); ?>">

    <div class="upload-section">
        <label>Upload from PC:</label>
        <input type="file" name="image_file[<?php echo $row['id']; ?>]">
    </div>

    <div class="search-section">
        <input type="text" id="search_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['question']); ?>" style="width:70%;">
        <button type="button" onclick="searchImages(<?php echo $row['id']; ?>)">Search</button>
    </div>

    <div class="image-strip" id="images_<?php echo $row['id']; ?>">
        <?php if (!empty($row['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($row['image_url']); ?>" class="selected">
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>

<div style="text-align:center;">
    <button type="submit" class="save-btn">💾 Save All Images</button>
</div>
</form>

</body>
</html>
