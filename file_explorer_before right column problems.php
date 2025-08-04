<?php
/**
 * file_explorer.php
 *
 * Requires:
 *   - $folders
 *   - $folderData
 *   - $selectedFullTable, $column1, $column2
 */
?>

<style>
.two-column {
    display: flex;
    align-items: flex-start;
    gap: 0;
}
.folder-panel {
    width: 220px;
    background: #444;
    padding: 0;
    max-height: 66vh;
    overflow-y: auto;
}
.folder-item {
    padding: 8px 10px;
    cursor: pointer;
    border: 1px solid #fff;
    color: #fff;
    text-align: left;
    background: #444;
}
.folder-item:hover,
.folder-item.active {
    background: #555;
}
.file-panel {
    flex: 1;
    background: #ddd;
    padding: 0;
    max-height: 66vh;
    overflow-y: auto;
}
.file-item {
    padding: 8px 10px;
    cursor: pointer;
    border: 1px solid #fff;
    text-align: left;
    background: #ddd;
}
.file-item:hover {
    background: #ccc;
}
@media (max-width: 768px) {
    .two-column {
        flex-direction: column;
    }
    .folder-panel, .file-panel {
        width: 100%;
        max-height: 40vh;
    }
}
#fileExplorer {
    display: none;
    opacity: 0;
    transition: opacity 0.4s ease;
    margin-top: 20px;
}
#fileExplorer.visible {
    display: flex;
    opacity: 1;
}
.select-file-btn {
    display: inline-block;
    padding: 10px 15px;
    background: #555;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.select-file-btn:hover {
    background: #777;
}
</style>

<div style="text-align:center;">
    <button type="button" class="select-file-btn" onclick="showFileExplorer()">Select a file</button>
</div>

<form method='POST' action='' id='tableActionForm'>
    <input type='hidden' name='table' id='selectedTableInput' value='<?php echo htmlspecialchars($selectedFullTable ?? ''); ?>'>
    <input type='hidden' name='col1' value='<?php echo htmlspecialchars($column1 ?? ''); ?>'>
    <input type='hidden' name='col2' value='<?php echo htmlspecialchars($column2 ?? ''); ?>'>

    <div style="display:flex;justify-content:center;">
        <div id="fileExplorer" class='two-column'>
            <div class='folder-panel' id='folderPanel'>
                <?php foreach ($folders as $folder => $tableList): ?>
                    <div class='folder-item' onclick="showFiles('<?php echo htmlspecialchars($folder); ?>', this)">
                        <?php echo htmlspecialchars(ucfirst($folder)); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class='file-panel' id='filePanel'>
                <em style="padding:8px;display:block;">Select a folder to view its tables</em>
            </div>
        </div>
    </div>
</form>

<div id="fileSelectedMsg" style="display:none;text-align:center;margin-top:10px;font-weight:bold;color:green;"></div>

<script>
const folderData = <?php echo json_encode($folderData, JSON_UNESCAPED_UNICODE); ?>;

function showFileExplorer() {
    const explorer = document.getElementById("fileExplorer");
    explorer.classList.add("visible");
}

function showFiles(folderName, element) {
    document.querySelectorAll(".folder-item").forEach(el => el.classList.remove("active"));
    element.classList.add("active");

    const filePanel = document.getElementById("filePanel");
    filePanel.innerHTML = "";

    if (folderData[folderName]) {
        folderData[folderName].forEach(file => {
            const div = document.createElement("div");
            div.className = "file-item";
            div.textContent = file.display;
            div.onclick = () => selectTable(file.table, file.display);
            filePanel.appendChild(div);
        });
    } else {
        filePanel.innerHTML = "<em style='padding:8px;display:block;'>No tables in this folder</em>";
    }
}

function selectTable(fullTableName, displayName) {
    // Show the selected file message instantly
    const msgEl = document.getElementById("fileSelectedMsg");
    msgEl.textContent = "File \"" + displayName + "\" selected";
    msgEl.style.display = "block";

    // Set hidden input and submit form
    document.getElementById("selectedTableInput").value = fullTableName;
    document.getElementById("tableActionForm").submit();
}
</script>
