<?php
$apiKey = '51629627-a41f1d96812d8b351d3f25867';
$query = $_GET['q'] ?? 'chill';
$url = "https://pixabay.com/api/music/?key=$apiKey&q=" . urlencode($query);

$response = file_get_contents($url);
$data = json_decode($response, true);
$tracks = $data['hits'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Browse Music</title>
<style>
  body {
    font-family: sans-serif;
    margin: 0;
    padding: 20px;
    background: #f4f4f4;
  }
  .track {
    background: white;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
  }
  .track-title {
    font-size: 1.1em;
    font-weight: bold;
  }
  .use-btn {
    margin-top: 10px;
    background: #4CAF50;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
  }
</style>
</head>
<body>

<h2>🎵 Browse Music from Pixabay</h2>
<form method="get" style="margin-bottom: 20px;">
  <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search genre or mood...">
  <button type="submit">Search</button>
</form>

<?php if (empty($tracks)): ?>
  <p>No results found.</p>
<?php else: ?>
  <?php foreach ($tracks as $track): ?>
    <div class="track">
      <div class="track-title"><?= htmlspecialchars($track['name']) ?> (<?= htmlspecialchars($track['duration']) ?> sec)</div>
      <div>Mood: <?= htmlspecialchars($track['mood'] ?? '—') ?> | Genre: <?= htmlspecialchars($track['genre'] ?? '—') ?></div>
      <audio controls src="<?= htmlspecialchars($track['audio']) ?>"></audio><br>
      <button class="use-btn" onclick="selectMusic('<?= htmlspecialchars($track['audio']) ?>')">Use this track</button>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

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
