<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$PIXABAY_API_KEY = '51629627-a41f1d96812d8b351d3f25867'; // Replace with your Pixabay key

function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (stripos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts = explode('_', $suffix, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $folder = $parts[0];
                $file = $parts[1];
            } else {
                $folder = 'Uncategorized';
                $file = $suffix;
            }
            $allTables[$folder][] = [
                'table_name' => $table,
                'display_name' => $file
            ];
        }
    }
    return $allTables;
}

$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
$folders = getUserFoldersAndTables($conn, $username);
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table' => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}

$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';

/* Save edits */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $saveTable = $conn->real_escape_string($_POST['save_table']);
    $editedRows = $_POST['edited_rows'] ?? [];
    $deleteRows = $_POST['delete_rows'] ?? [];

    $uploadDir = __DIR__ . "/uploads/quiz_images/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach ($editedRows as $id => $row) {
        if (in_array($id, $deleteRows)) {
            $conn->query("DELETE FROM `$saveTable` WHERE id=" . intval($id));
            continue;
        }

        $imageUrl = trim($row['image_url']);

        if (isset($_FILES['image_file']['name'][$id]) && $_FILES['image_file']['error'][$id] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image_file']['tmp_name'][$id];
            $fileSize = $_FILES['image_file']['size'][$id];
            $ext = strtolower(pathinfo($_FILES['image_file']['name'][$id], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && $fileSize <= 2 * 1024 * 1024) {
                $newName = "quiz_" . intval($id) . "_" . uniqid() . "." . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                    $imageUrl = "uploads/quiz_images/" . $newName;
                }
            }
        }

        $stmt = $conn->prepare("UPDATE `$saveTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=?, image_url=? WHERE id=?");
        $stmt->bind_param("sssssi", $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $imageUrl, $id);
        $stmt->execute();
        $stmt->close();
    }
}

/* Display */
echo "<div class='content'>👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Edit Quiz Choices</h2>";

include 'file_explorer.php';

if (!empty($selectedTable)) {
    $res = $conn->query("SELECT * FROM `$selectedTable`");
    echo "<form method='POST' enctype='multipart/form-data' style='text-align:center;'>
            <input type='hidden' name='save_table' value='" . htmlspecialchars($selectedTable) . "'>
            <table border='1' cellpadding='5' cellspacing='0' style='margin:auto;'>
                <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Image</th><th>Upload File</th><th>Preview</th><th>Delete</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>
                <td>" . htmlspecialchars($row['question']) . "</td>
                <td><textarea name='edited_rows[$id][correct]' oninput='autoResize(this)'>" . htmlspecialchars($row['correct_answer']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong1]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong1']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong2]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong2']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong3]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong3']) . "</textarea></td>
                <td>
                    <input type='hidden' name='edited_rows[$id][image_url]' id='image_url_$id' value='" . htmlspecialchars($row['image_url']) . "'>
                    <button type='button' onclick='openPixabaySearch($id)'>Search Pixabay</button>
                </td>
                <td><input type='file' name='image_file[$id]'></td>
                <td id='preview_$id'>" . (!empty($row['image_url']) ? "<img src='" . htmlspecialchars($row['image_url']) . "' style='max-height:50px;'>" : "") . "</td>
                <td><input type='checkbox' name='delete_rows[]' value='" . intval($id) . "'></td>
              </tr>";
    }
    echo "</table><br><button type='submit'>📂 Save Changes</button></form><br>";
}
?>

<style>
#pixabayModal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.7);
}
#pixabayModalContent {
    background: white;
    margin: 5% auto;
    padding: 20px;
    width: 80%;
    max-width: 800px;
    border-radius: 8px;
}
#pixabayResults {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    max-height: 400px;
    overflow-y: auto;
}
#pixabayResults img {
    cursor: pointer;
    max-width: 150px;
    border: 2px solid transparent;
}
#pixabayResults img:hover {
    border: 2px solid blue;
}
</style>

<div id="pixabayModal">
    <div id="pixabayModalContent">
        <h3>Search Pixabay</h3>
        <input type="text" id="pixabaySearch" placeholder="Enter search term" style="width:70%;">
        <button onclick="searchPixabay()">Search</button>
        <div id="pixabayResults"></div>
        <button onclick="closePixabayModal()" style="margin-top:10px;">Close</button>
    </div>
</div>

<script>
let currentRowId = null;
const PIXABAY_KEY = <?= json_encode($PIXABAY_API_KEY) ?>;

function openPixabaySearch(rowId) {
    currentRowId = rowId;
    document.getElementById('pixabayModal').style.display = 'block';
    document.getElementById('pixabayResults').innerHTML = '';
    document.getElementById('pixabaySearch').value = '';
}

function closePixabayModal() {
    document.getElementById('pixabayModal').style.display = 'none';
}

function searchPixabay() {
    const term = document.getElementById('pixabaySearch').value.trim();
    if (!term) return;
    const url = `https://pixabay.com/api/?key=${PIXABAY_KEY}&q=${encodeURIComponent(term)}&image_type=photo&per_page=20&safe_search=true`;
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('pixabayResults');
            container.innerHTML = '';
            if (data.hits && data.hits.length > 0) {
                data.hits.forEach(hit => {
                    const img = document.createElement('img');
                    img.src = hit.previewURL;
                    img.title = hit.tags;
                    img.onclick = () => selectPixabayImage(hit.largeImageURL);
                    container.appendChild(img);
                });
            } else {
                container.innerHTML = '<p>No results found.</p>';
            }
        });
}

function selectPixabayImage(url) {
    if (!currentRowId) return;
    document.getElementById(`image_url_${currentRowId}`).value = url;
    document.getElementById(`preview_${currentRowId}`).innerHTML = `<img src="${url}" style="max-height:50px;">`;
    closePixabayModal();
}

function autoResize(el) {
    el.style.height = "auto";
    el.style.height = (el.scrollHeight) + "px";
}
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("textarea").forEach(el => {
        autoResize(el);
        el.addEventListener("input", () => autoResize(el));
    });
});
</script>
