<?php
// browse_freepd.php - Music browser for FreePD.com

// Step 1: Define base and categories
$baseUrl = "https://freepd.com/";
$categories = [
    "epic", "comedy", "romantic", "action", "dramatic", "mystery", "horror", "relaxation", "scifi"
];

$selectedCategory = $_GET['category'] ?? '';
$tracks = [];

if (in_array($selectedCategory, $categories)) {
    // Step 2: Scrape track list from selected category page
    $html = @file_get_contents("{$baseUrl}{$selectedCategory}.php");
    if ($html) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $links = $dom->getElementsByTagName('a');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (str_starts_with($href, '/music/') && str_ends_with($href, '.mp3')) {
                $tracks[] = [
                    'name' => trim($link->nodeValue),
                    'url'  => $baseUrl . ltrim($href, '/')
                ];
            }
        }
    }
}
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Browse FreePD Music</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .track { background: white; padding: 10px; margin-bottom: 10px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .track-name { font-weight: bold; }
        audio { width: 100%; margin-top: 5px; }
        .use-btn { background: #4CAF50; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; margin-top: 8px; }
        select { padding: 6px; font-size: 1em; margin-bottom: 20px; }
    </style>
</head>
<body>

<h2>🎵 Browse FreePD Music</h2>
<form method="get">
    <label for="category">Choose category:</label>
    <select name="category" id="category" onchange="this.form.submit()">
        <option value="">-- Select --</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selectedCategory && empty($tracks)): ?>
    <p>No tracks found or failed to load.</p>
<?php endif; ?>

<?php foreach ($tracks as $track): ?>
    <div class="track">
        <div class="track-name">🎵 <?= htmlspecialchars($track['name']) ?></div>
        <audio controls src="<?= htmlspecialchars($track['url']) ?>"></audio><br>
        <button class="use-btn" onclick="selectMusic('<?= htmlspecialchars($track['url']) ?>')">Use this track</button>
    </div>
<?php endforeach; ?>

<script>
function selectMusic(url) {
    if (window.opener && window.opener.document) {
        const input = window.opener.document.querySelector('input[name="custom_music_url"]');
        if (input) {
            input.value = url;
            input.scrollIntoView({ behavior: "smooth" });
        }
        window.close();
    }
}
</script>

</body>
</html>
