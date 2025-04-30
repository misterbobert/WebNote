<?php
session_start();
require 'config.php';

// ─────────────────────────────────────────────────────────────
// Helper: decriptează o notiță AES-256-CBC
// ─────────────────────────────────────────────────────────────
function decrypt_note(string $b64cipher, string $b64iv): string {
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
// 1) Detectăm “slug” pretty în URL
// ─────────────────────────────────────────────────────────────
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script   = $_SERVER['SCRIPT_NAME'];
$basePath = rtrim(dirname($script), '/');
$slug     = ltrim(substr($uri, strlen($basePath)), '/');
if ($slug === '' || stripos($slug, 'index.php') !== false) {
    $slug = '';
}

// ─────────────────────────────────────────────────────────────
// 2) Încarcă notița inițială dacă slug există
// ─────────────────────────────────────────────────────────────
$initialNote = null;
$uid = $_SESSION['user_id'] ?? null;

if ($slug) {
    // pri­vată (dacă ești logat)
    if ($uid) {
        $stmt = $pdo->prepare("
            SELECT id, title, content, iv
              FROM notes
             WHERE slug = ? AND user_id = ?
             LIMIT 1
        ");
        $stmt->execute([$slug, $uid]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $initialNote = [
                'id'    => $r['id'],
                'title' => $r['title'] ?: 'Untitled note',
                'full'  => decrypt_note($r['content'], $r['iv']),
                'slug'  => $slug,
            ];
        }
    }
    // publică (guest)
    if (!$initialNote) {
        $stmt = $pdo->prepare("
            SELECT title, content, iv
              FROM notes
             WHERE slug = ? AND user_id = 0
             LIMIT 1
        ");
        $stmt->execute([$slug]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $initialNote = [
                'id'    => null,
                'title' => $r['title'] ?: 'Untitled note',
                'full'  => decrypt_note($r['content'], $r['iv']),
                'slug'  => $slug,
            ];
        }
    }
}

// ─────────────────────────────────────────────────────────────
// 3) Încarcă notițele private în sidebar (dacă ești logat)
// ─────────────────────────────────────────────────────────────
$notes = [];
if ($uid) {
    $stmt = $pdo->prepare("
        SELECT id, title, content, iv, slug
          FROM notes
         WHERE user_id = ?
      ORDER BY created_at DESC
    ");
    $stmt->execute([$uid]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $full    = decrypt_note($r['content'], $r['iv']);
        $preview = mb_strimwidth($full, 0, 30, '…');
        $notes[] = [
            'id'      => $r['id'],
            'title'   => $r['title'],
            'preview' => $preview,
            'full'    => $full,
            'slug'    => $r['slug'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────
// 4) Încarcă profil (dacă ești logat)
// ─────────────────────────────────────────────────────────────
$username = null;
$imageUrl = null;
if ($uid) {
    $stmt = $pdo->prepare("SELECT username, image_url FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $username = htmlspecialchars($u['username']);
        $imageUrl = $u['image_url'] ? htmlspecialchars($u['image_url']) : null;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Webnote</title>
  <link rel="stylesheet" href="style.css">
  <script>
    // server-side notes for JS
    const noteData = {
      <?php foreach ($notes as $n): ?>
      "<?= $n['id'] ?>": {
        id:    <?= json_encode($n['id']) ?>,
        title: <?= json_encode($n['title']   ?: '') ?>,
        full:  <?= json_encode($n['full'])  ?>
      },
      <?php endforeach; ?>
    };
    const initialNote = <?= json_encode($initialNote) ?>;
    const isLogged    = <?= $uid ? 'true' : 'false' ?>;
  </script> 
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#5C807D">
 
 
</head>
<body>
  <!-- Hamburger on top -->
  <button id="hamburger" class="hamburger">☰</button>

  <div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar collapsed">
      <div class="profile-card">
        <?php if ($username): ?>
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
        <?php else: ?>
          <button id="login-btn" class="login-btn"
                  onclick="location.href='login.php'">Login</button>
        <?php endif; ?>
      </div>
      <hr>

      <!-- Create & list notes ALWAYS -->
      <div class="notes-panel">
        <button id="new-note" class="panel-btn">Create new note</button>
        <div id="notes-list" class="notes-list">
          <!-- server-side notes (if any) -->
          <?php foreach ($notes as $n): ?>
            <button type="button" class="panel-btn note-btn" data-id="<?= $n['id'] ?>">
              <?= htmlspecialchars($n['title'] ?: $n['preview']) ?>
            </button>
          <?php endforeach; ?>
          <!-- localStorage notes will be injected by JS -->
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
          <button type="button" class="panel-btn save-local-btn">Save to LocalStorage</button>
          <button type="button" id="share-btn" class="panel-btn">Share</button>

          <textarea name="content"
                    class="editor-input"
                    placeholder="Type your note here…"
                    required></textarea>
        </form>
      </div>
    </main>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const sidebar    = document.querySelector('.sidebar');
    const ham        = document.getElementById('hamburger');
    const newBtn     = document.getElementById('new-note');
    const editorIn   = document.querySelector('.editor-input');
    const titleDisp  = document.getElementById('note-title-display');
    const titleIn    = document.getElementById('note-title-input');
    const idIn       = document.getElementById('note-id');
    const saveBtn    = document.querySelector('.save-note-btn');
    const saveLocal  = document.querySelector('.save-local-btn');
    const shareBtn   = document.getElementById('share-btn');
    const notesList  = document.getElementById('notes-list');

    // --- Live auto‐save draft in localStorage ---
    editorIn.addEventListener('input', () => {
      localStorage.setItem('draftContent', editorIn.value);
      localStorage.setItem('draftTitle', titleDisp.textContent);
    });

    // --- Load draft if any ---
    const draftContent = localStorage.getItem('draftContent');
    const draftTitle   = localStorage.getItem('draftTitle');
    if (draftContent) editorIn.value = draftContent;
    if (draftTitle)   titleDisp.textContent = draftTitle;

    // --- Inject localStorage notes into sidebar ---
    function loadLocalNotes() {
      const raw = localStorage.getItem('localNotes');
      if (!raw) return;
      const arr = JSON.parse(raw);
      arr.forEach(item => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'panel-btn local-note-btn';
        btn.textContent = item.title;
        btn.dataset.lid = item.id;
        notesList.appendChild(btn);
      });
      // attach click handlers
      document.querySelectorAll('.local-note-btn').forEach(b => {
        b.addEventListener('click', () => {
          const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
          const it  = arr.find(x=>x.id==b.dataset.lid);
          if (!it) return;
          editorIn.value = it.content;
          titleDisp.textContent = it.title;
          titleIn.value = it.title;
          idIn.value = '';
        });
      });
    }
    loadLocalNotes();

    // --- Toggle sidebar ---
    ham.addEventListener('click', () => {
      const open = sidebar.classList.toggle('open');
      sidebar.classList.toggle('collapsed', !open);
      ham.classList.toggle('open', open);
    });

    // --- Create new note ---
    newBtn.addEventListener('click', () => {
      editorIn.value        = '';
      titleDisp.textContent = 'Untitled note';
      titleIn.value         = '';
      idIn.value            = '';
    });

    // --- Load server notes ---
    document.querySelectorAll('.note-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const d = noteData[ btn.dataset.id ] || { full:'', title:'' };
        editorIn.value        = d.full;
        titleDisp.textContent = d.title || 'Untitled note';
        titleIn.value         = d.title;
        idIn.value            = d.id;
      });
    });

    // --- Save to account (or redirect if guest) ---
    saveBtn.addEventListener('click', () => {
      if (!isLogged) {
        return window.location.href = 'login.php';
      }
      let t = titleIn.value.trim()
            || prompt('Enter note title:', titleDisp.textContent);
      if (!t) return;
      titleIn.value         = t;
      titleDisp.textContent = t;
      document.getElementById('editor-form').submit();
    });

    // --- Save to LocalStorage ---
    saveLocal.addEventListener('click', () => {
      let t = titleIn.value.trim()
            || prompt('Enter note title:', titleDisp.textContent);
      if (!t) return;
      titleIn.value         = t;
      titleDisp.textContent = t;
      const content = editorIn.value;
      // pull existing array
      const key  = 'localNotes';
      const raw  = localStorage.getItem(key);
      const arr  = raw ? JSON.parse(raw) : [];
      const id   = Date.now();  // simple unique
      arr.push({ id, title: t, content });
      localStorage.setItem(key, JSON.stringify(arr));
      // reload menu
      loadLocalNotes();
    });

    // --- Share (guest+user) ---
    shareBtn.addEventListener('click', () => {
      const code = prompt(
        'Introdu numele notiței pentru share (ex. NotitaMEA):',
        titleDisp.textContent
      );
      if (!code) return alert('Share cancelled');
      // ensure draft saved
      if (!editorIn.value.trim()) return alert('Nothing to share');
      // show link
      const base = window.location.origin +
                   window.location.pathname.replace(/[^/]+$/,'');
      alert(`Link-ul tău:\n${base}${encodeURIComponent(code.trim())}`);
      // now trigger backend save with user_id=0:
      // (we create a hidden form for share)
      const f = document.createElement('form');
      f.method = 'POST'; f.action = 'share_note.php';
      ['content','title','slug'].forEach(name => {
        const i = document.createElement('input');
        i.type  = 'hidden';
        i.name  = name;
        i.value = name==='content'
                ? editorIn.value
                : name==='title'
                  ? titleIn.value || titleDisp.textContent
                  : code.trim();
        f.appendChild(i);
      });
      document.body.appendChild(f);
      f.submit();
    });

    // --- Load initialNote if provided ---
    if (initialNote) {
      editorIn.value        = initialNote.full;
      titleDisp.textContent = initialNote.title;
      titleIn.value         = initialNote.title;
      idIn.value            = initialNote.id || '';
    }
  });
  </script>  <script src="script.js"></script>
</body>
</html>
