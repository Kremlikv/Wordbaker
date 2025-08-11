<?php
/**
 * file_explorer.php
 *
 * Requires:
 *   - $folders
 *   - $folderData  // values like [{ table: 'user_folder_sub1_sub2_file', display: 'sub1_sub2_file' }]
 *   - $selectedFullTable, $column1, $column2
 */
?>

<style>
.two-column { display:flex; align-items:flex-start; gap:0; }
.folder-panel { width:220px; background:#444; padding:0; max-height:66vh; overflow-y:auto; }
.folder-item { padding:8px 10px; cursor:pointer; border:1px solid #fff; color:#fff; text-align:left; background:#444; user-select:none; }
.folder-item:hover, .folder-item.active { background:#555; }
.folder-item.shared { opacity:.8; cursor:default; }
.file-panel { flex:1; background:#ddd; padding:6px 0; max-height:66vh; overflow-y:auto; }

/* Tree in right pane */
.tree { padding:4px 8px; }
.tree-folder, .tree-file { padding:6px 8px; cursor:pointer; border-radius:6px; margin-left: 0; user-select:none; }
.tree-folder:hover, .tree-file:hover { background:#ccc; }
.tree-children { margin-left: 18px; }
.tree-toggle { display:inline-block; width:1em; text-align:center; margin-right:6px; }
.tree-folder > .tree-label { font-weight:600; }
.tree-file { background:#eee; }
.tree-file:hover { background:#e0e0e0; }

@media (max-width:768px){
  .two-column{flex-direction:column}
  .folder-panel,.file-panel{width:100%; max-height:40vh}
}

#fileExplorer{ display:none; opacity:0; transition:opacity .4s ease; margin-top:20px;}
#fileExplorer.visible{ display:flex; opacity:1;}
.select-file-btn{ display:inline-block; padding:10px 15px; background:#555; color:#fff; border:none; border-radius:4px; cursor:pointer;}
.select-file-btn:hover{ background:#777; }

/* Left-pane folder context menu */
.folder-context-menu{ position:absolute; display:none; z-index:9999; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 10px 20px rgba(0,0,0,.08); min-width:200px; padding:6px;}
.folder-context-menu button{ width:100%; border:0; background:transparent; text-align:left; padding:8px 10px; cursor:pointer; border-radius:6px; font-size:14px;}
.folder-context-menu button:hover{ background:#f1f5f9; }

/* Right-pane subfolder context menu */
.subfolder-context-menu{ position:absolute; display:none; z-index:9999; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 10px 20px rgba(0,0,0,.08); min-width:220px; padding:6px;}
.subfolder-context-menu button{ width:100%; border:0; background:transparent; text-align:left; padding:8px 10px; cursor:pointer; border-radius:6px; font-size:14px;}
.subfolder-context-menu button:hover{ background:#f1f5f9; }
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

<!-- Left-pane menu -->
<div id="folderMenu" class="folder-context-menu">
  <button type="button" id="shareFolderBtn">🔗 Share folder…</button>
  <button type="button" id="copyFolderLocalBtn">📄 Copy folder…</button>
  <button type="button" id="renameFolderBtn">✏️ Rename folder…</button>
  <button type="button" id="deleteFolderBtn" style="color:#b91c1c;">🗑️ Delete folder…</button>
</div>
<form id="folderActionForm" method="post" action="main.php" style="display:none;">
  <input type="hidden" name="folder_action" value="">
  <input type="hidden" name="folder_old" value="">
  <input type="hidden" name="folder_new" value="">
  <input type="hidden" name="confirm_text" value="">
  <input type="hidden" name="dest_folder" value="">
  <input type="hidden" name="overwrite" value="">
</form>

<!-- Right-pane SUBFOLDER menu + form -->
<div id="subfolderMenu" class="subfolder-context-menu">
  <div style="padding:4px 8px; font-size:12px; color:#64748b;" id="subPathHint"></div>
  <button type="button" id="shareSubBtn">🔗 Share this subfolder…</button>
  <button type="button" id="copySubBtn">📄 Copy this subfolder…</button>
  <button type="button" id="renameSubBtn">✏️ Rename this subfolder…</button>
  <button type="button" id="deleteSubBtn" style="color:#b91c1c;">🗑️ Delete this subfolder…</button>
</div>
<form id="subfolderActionForm" method="post" action="main.php" style="display:none;">
  <input type="hidden" name="sub_action" value="">
  <input type="hidden" name="root_folder" value="">
  <input type="hidden" name="subpath" value="">
  <input type="hidden" name="new_name" value="">
  <input type="hidden" name="dest_folder" value="">
  <input type="hidden" name="overwrite" value="">
  <input type="hidden" name="confirm_text" value="">
</form>

<div id="fileSelectedMsg" style="display:none;text-align:center;margin-top:10px;font-weight:bold;color:green;"></div>

<script>
const folderData = <?php echo json_encode($folderData, JSON_UNESCAPED_UNICODE); ?>;
const firstFolderName = <?php echo json_encode($firstFolderName); ?>;

let currentRootFolder = null; // left-pane folder currently open

function showFileExplorer() {
  const explorer = document.getElementById("fileExplorer");
  explorer.classList.add("visible");
  if (firstFolderName && folderData[firstFolderName]) {
    const firstFolderEl = document.querySelector(".folder-item.active");
    if (firstFolderEl) showFiles(firstFolderName, firstFolderEl);
  }
}

// Build nested tree from a folder’s file list
function buildTree(files) {
  const root = { type: 'folder', name: '', children: {}, files: [] };
  files.forEach(f => {
    const parts = (f.display || '').split('_').filter(Boolean);
    if (parts.length === 0) return;
    const fileName = parts.pop(); // last is filename
    let node = root;
    parts.forEach(seg => {
      if (!node.children[seg]) node.children[seg] = { type:'folder', name: seg, children:{}, files:[] };
      node = node.children[seg];
    });
    node.files.push({ name: fileName, table: f.table });
  });
  return root;
}

function renderTree(node, container, pathSoFar=[]) {
  const folders = Object.values(node.children).sort((a,b)=>a.name.localeCompare(b.name));
  folders.forEach(child => {
    const folderEl = document.createElement('div');
    folderEl.className = 'tree-folder';
    const label = document.createElement('span');
    label.className = 'tree-label';
    const toggle = document.createElement('span');
    toggle.className = 'tree-toggle';
    toggle.textContent = '▾';
    label.textContent = child.name;

    // annotate with root + subpath for backend
    const subpath = [...pathSoFar, child.name].join('_');
    folderEl.dataset.root = currentRootFolder || '';
    folderEl.dataset.subpath = subpath;

    const childrenWrap = document.createElement('div');
    childrenWrap.className = 'tree-children';

    folderEl.appendChild(toggle);
    folderEl.appendChild(label);
    container.appendChild(folderEl);
    container.appendChild(childrenWrap);

    // toggle expand/collapse
    folderEl.addEventListener('click', (e) => {
      if (e.target === toggle || e.target === label || e.currentTarget === folderEl) {
        const collapsed = childrenWrap.style.display === 'none';
        childrenWrap.style.display = collapsed ? '' : 'none';
        toggle.textContent = collapsed ? '▾' : '▸';
      }
      e.stopPropagation();
    });

    renderTree(child, childrenWrap, [...pathSoFar, child.name]);

    // right-click on subfolder
    folderEl.addEventListener('contextmenu', (e) => {
      e.preventDefault();
      // disable actions under left "Shared"
      if (folderEl.dataset.root === 'Shared') return;

      const menu = document.getElementById('subfolderMenu');
      const hint = document.getElementById('subPathHint');
      hint.textContent = (folderEl.dataset.root || '') + ' / ' + subpath;
      menu.style.display = 'block';
      const x = e.pageX, y = e.pageY;
      const maxX = window.scrollX + document.documentElement.clientWidth - menu.offsetWidth - 8;
      const maxY = window.scrollY + document.documentElement.clientHeight - menu.offsetHeight - 8;
      menu.style.left = Math.min(x, maxX) + 'px';
      menu.style.top  = Math.min(y, maxY) + 'px';

      // store current target
      menu.dataset.root = folderEl.dataset.root;
      menu.dataset.subpath = subpath;
    });
  });

  const files = (node.files || []).sort((a,b)=>a.name.localeCompare(b.name));
  files.forEach(f => {
    const fileEl = document.createElement('div');
    fileEl.className = 'tree-file';
    fileEl.textContent = f.name;
    fileEl.addEventListener('click', () => selectTable(f.table, f.name));
    container.appendChild(fileEl);
  });
}

function showFiles(folderName, element) {
  document.querySelectorAll(".folder-item").forEach(el => el.classList.remove("active"));
  element.classList.add("active");
  currentRootFolder = folderName;

  const filePanel = document.getElementById("filePanel");
  filePanel.innerHTML = "";

  if (folderData[folderName] && folderData[folderName].length) {
    const treeRoot = buildTree(folderData[folderName]);
    const treeWrap = document.createElement('div');
    treeWrap.className = 'tree';
    renderTree(treeRoot, treeWrap, []);
    filePanel.appendChild(treeWrap);
  } else {
    filePanel.innerHTML = "<em style='padding:8px;display:block;'>No tables in this folder</em>";
  }
}

function selectTable(fullTableName, displayName) {
  const msgEl = document.getElementById("fileSelectedMsg");
  msgEl.textContent = 'File "' + displayName + '" selected';
  msgEl.style.display = "block";
  document.getElementById("selectedTableInput").value = fullTableName;
  document.getElementById("tableActionForm").submit();
}

/* ----- Left pane folder menu (unchanged) ----- */
(function(){
  const menu = document.getElementById('folderMenu');
  const actionForm = document.getElementById('folderActionForm');
  let targetFolder = null;

  document.addEventListener('contextmenu', function(e){
    const item = e.target.closest('.folder-item');
    if (!item) return;
    const folder = item.getAttribute('data-folder');
    if (!folder || folder === 'Shared') return; // protect Shared
    e.preventDefault();
    targetFolder = folder;
    menu.style.display = 'block';
    const x=e.pageX, y=e.pageY;
    const maxX = window.scrollX + document.documentElement.clientWidth - menu.offsetWidth - 8;
    const maxY = window.scrollY + document.documentElement.clientHeight - menu.offsetHeight - 8;
    menu.style.left = Math.min(x, maxX) + 'px';
    menu.style.top  = Math.min(y, maxY) + 'px';
  });
  document.addEventListener('click', ()=> {
    document.getElementById('folderMenu').style.display='none';
    document.getElementById('subfolderMenu').style.display='none';
  });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape'){
    document.getElementById('folderMenu').style.display='none';
    document.getElementById('subfolderMenu').style.display='none';
  }});

  document.getElementById('shareFolderBtn').addEventListener('click', function(){
    if (!targetFolder) return;
    const overwrite = confirm('Share folder "'+targetFolder+'"?\nThis creates copies as shared_'+targetFolder+'_* for ALL users.\nOverwrite if existing?');
    actionForm.folder_action.value = 'share_folder';
    actionForm.folder_old.value    = targetFolder;
    actionForm.overwrite.value     = overwrite ? '1' : '';
    actionForm.submit();
  });

  document.getElementById('copyFolderLocalBtn').addEventListener('click', function(){
    if (!targetFolder) return;
    const dest = prompt('Copy folder "'+targetFolder+'" to which DESTINATION folder (same user)?', targetFolder + '_copy');
    if (!dest) return;
    if (!/^[a-z0-9_]+$/i.test(dest)) { alert('Use letters, numbers, underscores.'); return; }
    const overwrite = confirm('Overwrite destination tables if already exist?');
    actionForm.folder_action.value = 'copy_folder_local';
    actionForm.folder_old.value    = targetFolder;
    actionForm.dest_folder.value   = dest;
    actionForm.overwrite.value     = overwrite ? '1' : '';
    actionForm.submit();
  });

  document.getElementById('renameFolderBtn').addEventListener('click', function(){
    if (!targetFolder) return;
    const newName = prompt('Rename folder "'+targetFolder+'" to:', targetFolder);
    if (!newName || newName === targetFolder) return;
    if (!/^[a-z0-9_]+$/i.test(newName)) { alert('Use letters, numbers, underscores.'); return; }
    actionForm.folder_action.value = 'rename_folder';
    actionForm.folder_old.value    = targetFolder;
    actionForm.folder_new.value    = newName;
    actionForm.submit();
  });

  document.getElementById('deleteFolderBtn').addEventListener('click', function(){
    if (!targetFolder) return;
    const confirmText = prompt('Delete ALL tables in folder "'+targetFolder+'"?\n\nType the folder name to confirm:');
    if (confirmText !== targetFolder) return;
    actionForm.folder_action.value = 'delete_folder';
    actionForm.folder_old.value    = targetFolder;
    actionForm.confirm_text.value  = confirmText;
    actionForm.submit();
  });
})();

/* ----- Right pane SUBFOLDER menu ----- */
(function(){
  const menu = document.getElementById('subfolderMenu');
  const form = document.getElementById('subfolderActionForm');

  document.getElementById('shareSubBtn').addEventListener('click', function(){
    const root = menu.dataset.root || '';
    const sub  = menu.dataset.subpath || '';
    if (!root || !sub) return;
    const overwrite = confirm('Share subfolder "'+root+' / '+sub+'"?\nThis creates copies as shared_'+root+'_'+sub+'_* for ALL users.\nOverwrite if existing?');
    form.sub_action.value = 'share_subfolder';
    form.root_folder.value = root;
    form.subpath.value = sub;
    form.overwrite.value = overwrite ? '1' : '';
    form.submit();
  });

  document.getElementById('copySubBtn').addEventListener('click', function(){
    const root = menu.dataset.root || '';
    const sub  = menu.dataset.subpath || '';
    if (!root || !sub) return;
    const dest = prompt('Copy subfolder "'+root+' / '+sub+'" under which DESTINATION top-level folder (same user)?', root + '_copy');
    if (!dest) return;
    if (!/^[a-z0-9_]+$/i.test(dest)) { alert('Use letters, numbers, underscores.'); return; }
    const overwrite = confirm('Overwrite destination tables if already exist?');
    form.sub_action.value = 'copy_subfolder_local';
    form.root_folder.value = root;
    form.subpath.value = sub;
    form.dest_folder.value = dest;
    form.overwrite.value = overwrite ? '1' : '';
    form.submit();
  });

  document.getElementById('renameSubBtn').addEventListener('click', function(){
    const root = menu.dataset.root || '';
    const sub  = menu.dataset.subpath || '';
    if (!root || !sub) return;
    const parts = sub.split('_');
    const current = parts[parts.length-1];
    const newName = prompt('Rename subfolder "'+current+'" to:', current);
    if (!newName || newName === current) return;
    if (!/^[a-z0-9_]+$/i.test(newName)) { alert('Use letters, numbers, underscores.'); return; }
    form.sub_action.value = 'rename_subfolder';
    form.root_folder.value = root;
    form.subpath.value = sub;
    form.new_name.value = newName;
    form.submit();
  });

  document.getElementById('deleteSubBtn').addEventListener('click', function(){
    const root = menu.dataset.root || '';
    const sub  = menu.dataset.subpath || '';
    if (!root || !sub) return;
    const confirmText = prompt('Delete ALL tables under "'+root+' / '+sub+'"?\n\nType the subfolder path to confirm:', sub);
    if (confirmText !== sub) return;
    form.sub_action.value = 'delete_subfolder';
    form.root_folder.value = root;
    form.subpath.value = sub;
    form.confirm_text.value = confirmText;
    form.submit();
  });
})();
</script>
