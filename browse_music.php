<?php
$apiKey = 'YOUR_PIXABAY_API_KEY';

// If user selects a mood or genre, apply it
$mood = $_GET['mood'] ?? '';
$genre = $_GET['genre'] ?? '';

$queryParams = [
    'key' => $apiKey,
    'per_page' => 20,
];

if ($mood !== '') $queryParams['mood'] = $mood;
if ($genre !== '') $queryParams['genre'] = $genre;

// Build final URL
$url = "https://pixabay.com/api/music/?" . http_build_query($queryParams);

$response = @file_get_contents($url);
$data = json_decode($response, true);
$tracks = $data['hits'] ?? [];
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Browse Pixabay Music</title>
  <style>
    body {
      font-family: sans-serif;
      padding: 20px;
      background: #f4f4f4;
    }
    h2 {
      margin-top: 0;
    }
    .track {
      background: white;
      margin-bottom: 16px;
      padding: 12px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .track-title {
      font-weight: bold;
    }
    .use-btn {
      background: #4CAF50;
      color: white;
      padding: 6px 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }
    select {
      padding: 8px;
      font-size: 1em;
      margin-right: 10px;
    }
  </style>
</head>
<body>

<h2>🎵 Browse Royalty-Free Music from Pixabay</h2>

<form method="get">
  <label for="genre">Genre:</label>
  <select name="genre" id="genre">
    <option value="">-- All --</option>
    <option value="jazz" <?= $genre === 'jazz' ? 'selected' : '' ?>>Jazz</option>
    <option value="ambient" <?= $genre === 'ambient' ? 'selected' : '' ?>>Ambient</option>
    <option value="pop" <?= $genre === 'pop' ? 'selected' : '' ?>>Pop</option>
    <option value="classical" <?= $genre === 'classical' ? 'selected' : '' ?>>Classical</option>
    <option value="hip-hop" <?= $genre === 'hip-hop' ? 'selected' : '' ?>>Hip-Hop</option>
  </select>

  <label for="mood">Mood:</label>
  <select name="mood" id="mood">
    <option value="">-- All --</option>
    <option value="happy" <?= $mood === 'happy' ? 'selected' : '' ?>>Happy</option>
    <option value="calm" <?= $mood === 'calm' ? 'selected' : '' ?>>Calm</option>
    <option value="dramatic" <?= $mood === 'dramatic' ? 'selected' : '' ?>>Dramatic</option>
    <option value="uplifting" <?= $mood === 'uplifting' ? 'selected' : '' ?>>Uplifting</option>
  </select>

  <button type="submit">Search</button>
</form>

<hr>

<?php if (empty($tracks)): ?>
  <p>⚠️ No tracks found. Try a different genre or mood.</p>
<?php else: ?>
  <?php foreach ($tracks as $track): ?>
    <div class="track">
      <div class="track-title"><?= htmlspecialchars($track['name']) ?></div>
      <div>Mood: <?= htmlspecialchars($track['mood'] ?? '—') ?> | Genre: <?= htmlspecialchars($track['genre'] ?? '—') ?> | Duration: <?= htmlspecialchars($track['duration']) ?> sec</div>
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
