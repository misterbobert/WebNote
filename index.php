<?php
session_start();
require 'config.php';

// ─────────────────────────────────────────────────────────────
// 0) Helpers
// ─────────────────────────────────────────────────────────────
function decrypt_note($b64cipher, $b64iv) {
    $cipher = base64_decode($b64cipher);
    $iv     = base64_decode($b64iv);
    return openssl_decrypt(
      $cipher,
      'AES-256-CBC',
      ENCRYPTION_KEY,
      OPENSSL_RAW_DATA,
      $iv
    );
}

// ─────────────────────────────────────────────────────────────
// 1) Ensure user is logged in
// ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$uid = $_SESSION['user_id'];

// ─────────────────────────────────────────────────────────────
// 2) Detect “pretty” slug from REQUEST_URI
// ─────────────────────────────────────────────────────────────
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script   = $_SERVER['SCRIPT_NAME'];       // e.g. /webnote/index.php
$basePath = rtrim(dirname($script), '/');  // e.g. /webnote
$slug     = ltrim(substr($uri, strlen($basePath)), '/');

// sanitize
if ($slug === '' || strpos($slug, 'index.php') !== false) {
    $slug = '';
}

// ─────────────────────────────────────────────────────────────
// 3) If slug, attempt to load exactly that note for this user
// ─────────────────────────────────────────────────────────────
$initialNote = null;
if ($slug) {
    $stmt = $pdo->prepare("
      SELECT id, title, content, iv
        FROM notes
       WHERE slug = ?
         AND user_id = ?
       LIMIT 1
    ");
    $stmt->execute([$slug, $uid]);
    if ($row = $stmt->fetch()) {
        $initialNote = [
          'id'    => $row['id'],
          'title' => $row['title'],
          'full'  => decrypt_note($row['content'], $row['iv']),
          'slug'  => $slug
        ];
    }
}

// ─────────────────────────────────────────────────────────────
// 4) Load *all* notes (for the sidebar) and decrypt previews
// ─────────────────────────────────────────────────────────────
$notes = [];
$stmt = $pdo->prepare("
  SELECT id, title, content, iv, slug
    FROM notes
   WHERE user_id = ?
ORDER BY created_at DESC
");
$stmt->execute([$uid]);
while ($r = $stmt->fetch()) {
    $full    = decrypt_note($r['content'], $r['iv']);
    $preview = mb_strimwidth($full, 0, 30, '…');
    $notes[] = [
      'id'      => $r['id'],
      'title'   => $r['title'],
      'preview' => $preview,
      'full'    => $full,
      'slug'    => $r['slug']
    ];
}

// ─────────────────────────────────────────────────────────────
// 5) Load user profile info
// ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT username, image_url FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user       = $stmt->fetch();
$username   = htmlspecialchars($user['username']);
$imageUrl   = $user['image_url'] ? htmlspecialchars($user['image_url']) : null;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Webnote</title>
  <link rel="stylesheet" href="style.css">
  <script>
    // Pass PHP data into JS
    const noteData = {
      <?php foreach ($notes as $n): ?>
      "<?= $n['id'] ?>": {
        id:    <?= json_encode($n['id']) ?>,
        title: <?= json_encode($n['title']) ?>,
        full:  <?= json_encode($n['full'])  ?>
      },
      <?php if ($n['slug']): ?>
      "<?= $n['slug'] ?>": {
        id:    <?= json_encode($n['id']) ?>,
        title: <?= json_encode($n['title']) ?>,
        full:  <?= json_encode($n['full'])  ?>
      },
      <?php endif; ?>
      <?php endforeach; ?>
    };
    const initialNote = <?= json_encode($initialNote) ?>;
  </script>
</head>
<body>
  <!-- Hamburger (always on top) -->
  <button id="hamburger" class="hamburger">☰</button>

  <div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar collapsed">
      <div class="profile-card">
        <div class="profile">
          <?php if ($imageUrl): ?>
            <img src="<?= $imageUrl ?>" class="profile-img" alt="Avatar">
          <?php else: ?>
            <div class="avatar"></div>
          <?php endif; ?>
          <span class="username">@<?= $username ?></span>
          <button id="manage-btn" class="manage-btn"
                  onclick="location.href='profile.php'">Manage</button>
        </div>
      </div>
      <hr>
      <div class="notes-panel">
        <button id="new-note" class="panel-btn">Create new note</button>
        <div id="notes-list" class="notes-list">
          <?php foreach ($notes as $n): ?>
            <button type="button"
                    class="panel-btn note-btn"
                    data-id="<?= $n['id'] ?>">
              <?= htmlspecialchars($n['title'] ?: $n['preview']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

    <!-- EDITOR -->
    <main class="editor">
      <div class="title-card">
        <h2 id="note-title-display">Untitled note</h2>
      </div>
      <div class="editor-card">
        <form id="editor-form" action="save_note.php" method="post">
          <input type="hidden" name="id"    id="note-id"    value="">
          <input type="hidden" name="slug"  id="note-slug"  value="">
          <input type="hidden" name="title" id="note-title-input" value="">

          <button type="button" class="panel-btn save-note-btn">Save to account</button>
          <button type="button" id="share-btn" class="panel-btn">Share</button>
          <div id="share-panel" style="display:none;flex-direction:column;margin-top:.5rem;">
            <input  id="share-slug" placeholder="Enter share code…" style="padding:.5rem;border:none;border-radius:4px">
            <button type="button" id="share-save-btn" class="panel-btn" style="margin-top:.5rem;">Save</button>
          </div>

          <textarea name="content"
                    class="editor-input"
                    placeholder="Type your note here…"
                    required></textarea>
        </form>
      </div>
    </main>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', ()=> {
    const sidebar   = document.querySelector('.sidebar');
    const ham       = document.getElementById('hamburger');
    const newBtn    = document.getElementById('new-note');
    const editorIn  = document.querySelector('.editor-input');
    const titleDisp = document.getElementById('note-title-display');
    const titleIn   = document.getElementById('note-title-input');
    const idIn      = document.getElementById('note-id');
    const slugIn    = document.getElementById('note-slug');
    const saveBtn   = document.querySelector('.save-note-btn');
    const shareBtn  = document.getElementById('share-btn');
    const shareP    = document.getElementById('share-panel');
    const shareSlug = document.getElementById('share-slug');
    const shareSv   = document.getElementById('share-save-btn');

    // A) Toggle sidebar
    ham.addEventListener('click',()=>{
      const open = sidebar.classList.toggle('open');
      sidebar.classList.toggle('collapsed', !open);
      ham.classList.toggle('open', open);
    });

    // B) New note → reset
    newBtn.addEventListener('click', ()=>{
      editorIn.value        = '';
      titleDisp.textContent = 'Untitled note';
      titleIn.value         = '';
      idIn.value            = '';
      slugIn.value          = '';
    });

    // C) Load existing
    document.querySelectorAll('.note-btn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const id   = btn.dataset.id;
        const d    = noteData[id] || { full:'', title:'' };
        editorIn.value        = d.full;
        titleDisp.textContent = d.title || 'Untitled note';
        titleIn.value         = d.title;
        idIn.value            = d.id;
        slugIn.value          = '';
      });
    });

    // D) Save to account
    saveBtn.addEventListener('click', ()=>{
      let t = titleIn.value.trim() || prompt('Enter note title:', titleDisp.textContent);
      if (!t) return;
      titleIn.value         = t;
      titleDisp.textContent = t;
      document.getElementById('editor-form').submit();
    });

    // E) Share panel
    shareBtn.addEventListener('click',()=> {
      shareP.style.display = shareP.style.display==='none'?'flex':'none';
    });
    shareSv.addEventListener('click',()=>{
      const s = shareSlug.value.trim();
      if (!s) return alert('Enter a share code.');
      slugIn.value = s;
      alert(`Share link:\n${location.origin}${window.location.pathname.replace(/[^/]+$/,'')}${encodeURIComponent(s)}`);
    });

    // F) If PHP gave us an initialNote, load it
    if (initialNote) {
      editorIn.value        = initialNote.full;
      titleDisp.textContent = initialNote.title;
      titleIn.value         = initialNote.title;
      idIn.value            = initialNote.id;
      slugIn.value          = initialNote.slug;
    }
  });
  </script>
</body>
</html>
