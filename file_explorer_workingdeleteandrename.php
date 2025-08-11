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
    user-select: none;
}
.folder-item:hover,
.folder-item.active {
    background: #555;
}
.folder-item.shared { opacity: 0.8; cursor: default; }
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

/* Right-click menu */
.folder-context-menu {
    position: absolute; display: none; z-index: 9999;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.08);
    min-width: 190px; padding: 6px;
}
.folder-context-menu button {
    width: 100%; border: 0; background: transparent; text-align: left;
    padding: 8px 10px; cursor: pointer; border-radius: 6px; font-size: 14px;
}
.folder-context-menu button:hover { background: #f1f5f9; }
</style>

<div style="text-align:center;">
    <button type="button" class="select-file-btn" onclick="showFileExplorer()">Select a file</button>
</div>

<form method='POST' action='' id='tableActionForm'>
    <input type='hidden' name='table' id='selectedTableInput' value='<?php echo htmlspecialchars($selectedFullTable ?? ''); ?>'>
    <input type='hidden' name='col1' value='<?php echo htmlspecialchars($column1 ?? ''); ?>'>
    <input type='hidden' name='col2' value='<?php echo htmlspecialchars($column2 ?? ''); ?>'>

    <div style="display:flex;justify-content:center; position: relative;">
        <div id="fileExplorer" class='two-column'>
            <div class='folder-panel' id='folderPanel'>
                <?php
                $firstFolderName = '';
                $isFirst = true;
                foreach ($folders as $folder => $tableList):
                    if ($isFirst) { $firstFolderName = $folder; }
                    $isShared = ($folder === 'Shared');
                ?>
                    <div class='folder-item<?php echo $isFirst ? " active" : ""; ?><?php echo $isShared ? " shared" : ""; ?>'
                         data-folder="<?php echo htmlspecialchars($folder); ?>"
                         onclick="showFiles('<?php echo htmlspecialchars($folder); ?>', this)">
                        <?php echo htmlspecialchars(ucfirst($folder)); ?>
                    </div>
                <?php
                    $isFirst = false;
                endforeach;
                ?>
            </div>
            <div class='file-panel' id='filePanel'>
                <em style="padding:8px;display:block;">Select a folder to view its tables</em>
            </div>
        </div>
    </div>
</form>

<!-- Right-click menu + hidden form for folder actions -->
<div id="folderMenu" class="folder-context-menu">
  <button type="button" id="renameFolderBtn">✏️ Rename folder…</button>
  <button type="button" id="deleteFolderBtn" style="color:#b91c1c;">🗑️ Delete folder…</button>
</div>

<form id="folderActionForm" method="post" action="main.php" style="display:none;">
  <input type="hidden" name="folder_action" value="">
  <input type="hidden" name="folder_old" value="">
  <input type="hidden" name="folder_new" value="">
  <input type="hidden" name="confirm_text" value="">
</form>

<div id="fileSelectedMsg" style="display:none;text-align:center;margin-top:10px;font-weight:bold;color:green;"></div>

<script>
const folderData = <?php echo json_encode($folderData, JSON_UNESCAPED_UNICODE); ?>;
const firstFolderName = <?php echo json_encode($firstFolderName); ?>;

function showFileExplorer() {
    const explorer = document.getElementById("fileExplorer");
    explorer.classList.add("visible");
    // Automatically open first folder if exists
    if (firstFolderName && folderData[firstFolderName]) {
        const firstFolderEl = document.querySelector(".folder-item.active");
        if (firstFolderEl) {
            showFiles(firstFolderName, firstFolderEl);
        }
    }
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
    const msgEl = document.getElementById("fileSelectedMsg");
    msgEl.textContent = "File \"" + displayName + "\" selected";
    msgEl.style.display = "block";

    document.getElementById("selectedTableInput").value = fullTableName;
    document.getElementById("tableActionForm").submit();
}

// ---- Right-click (context menu) on folders ----
(function () {
  const menu = document.getElementById('folderMenu');
  const actionForm = document.getElementById('folderActionForm');
  let targetFolder = null;

  // Open menu if right-clicking a folder (except "Shared")
  document.addEventListener('contextmenu', function (e) {
    const item = e.target.closest('.folder-item');
    if (!item) return;

    const folder = item.getAttribute('data-folder');
    if (!folder || folder === 'Shared') return;

    e.preventDefault();
    targetFolder = folder;

    // Position menu near cursor
    menu.style.display = 'block';
    const x = e.pageX, y = e.pageY;
    // prevent menu from going off-screen
    const maxX = window.scrollX + document.documentElement.clientWidth - menu.offsetWidth - 8;
    const maxY = window.scrollY + document.documentElement.clientHeight - menu.offsetHeight - 8;
    menu.style.left = Math.min(x, maxX) + 'px';
    menu.style.top  = Math.min(y, maxY) + 'px';
  });

  // Hide menu on click elsewhere or Escape
  document.addEventListener('click', () => { menu.style.display = 'none'; });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') menu.style.display = 'none'; });

  // Rename folder
  document.getElementById('renameFolderBtn').addEventListener('click', function () {
    if (!targetFolder) return;
    const newName = prompt('Rename folder "' + targetFolder + '" to:', targetFolder);
    if (!newName || newName === targetFolder) return;
    if (!/^[a-z0-9_]+$/i.test(newName)) { alert('Use letters, numbers, and underscores only.'); return; }

    actionForm.folder_action.value = 'rename_folder';
    actionForm.folder_old.value    = targetFolder;
    actionForm.folder_new.value    = newName;
    actionForm.submit();
  });

  // Delete folder
  document.getElementById('deleteFolderBtn').addEventListener('click', function () {
    if (!targetFolder) return;
    const confirmText = prompt(
      'Delete ALL tables in folder "' + targetFolder + '"?\n\nType the folder name to confirm:'
    );
    if (confirmText !== targetFolder) return;

    actionForm.folder_action.value = 'delete_folder';
    actionForm.folder_old.value    = targetFolder;
    actionForm.confirm_text.value  = confirmText;
    actionForm.submit();
  });
})();
</script>
