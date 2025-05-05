<?php
session_start();
require 'config.php';

// ── 1) Pretty‐URL slug detection ───────────────────────────────
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/'); // e.g. "WebNote"
if ($base !== '' && strpos($path, $base) === 0) {
    $path = ltrim(substr($path, strlen($base)), '/');
}
$slug = ($path === '' || $path === 'index.php') ? '' : $path;

// ── 2) AES‐256‐CBC decrypt helper ──────────────────────────────
function decrypt_note(string $b64cipher, string $b64iv): string {
    $cipher = base64_decode($b64cipher);
    $iv     = base64_decode($b64iv);
    return openssl_decrypt(
        $cipher, 'AES-256-CBC',
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
}

// ── 3) Who’s logged in? ────────────────────────────────────────
$uid = $_SESSION['user_id'] ?? null;

// ── 4) Load initialNote (private if you, else public slug) ────
$initialNote = null;
if ($slug) {
    // private
    if ($uid) {
        $stmt = $pdo->prepare("
          SELECT id,title,content,iv,editable
            FROM notes
           WHERE slug = ? AND user_id = ?
           LIMIT 1
        ");
        $stmt->execute([$slug, $uid]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $initialNote = [
                'id'       => $r['id'],
                'title'    => $r['title']    ?: 'Untitled note',
                'full'     => decrypt_note($r['content'], $r['iv']),
                'slug'     => $slug,
                'editable' => (int)($r['editable'] ?? 0),
            ];
        }
    }
    // public (user_id = 0)
    if (!$initialNote) {
        $stmt = $pdo->prepare("
          SELECT title,content,iv,editable
            FROM notes
           WHERE slug = ? AND user_id = 0
           LIMIT 1
        ");
        $stmt->execute([$slug]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $initialNote = [
                'id'       => null,
                'title'    => $r['title']    ?: 'Untitled note',
                'full'     => decrypt_note($r['content'], $r['iv']),
                'slug'     => $slug,
                'editable' => (int)($r['editable'] ?? 0),
            ];
        }
    }
}

// ── 5) Load your private notes for the sidebar ────────────────
$notes = [];
if ($uid) {
    $stmt = $pdo->prepare("
      SELECT id,title,content,iv,slug
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

// ── 6) Load profile, friendRequests, friends ──────────────────
$username       = null;
$imageUrl       = null;
$friendRequests = [];
$friends        = [];

if ($uid) {
    // profile
    $u = $pdo->prepare("SELECT username,image_url FROM users WHERE id = ?");
    $u->execute([$uid]);
    if ($row = $u->fetch(PDO::FETCH_ASSOC)) {
        $username = htmlspecialchars($row['username']);
        $imageUrl = $row['image_url'] ? htmlspecialchars($row['image_url']) : null;
    }

    // pending friend requests
    $fr = $pdo->prepare("
      SELECT f.id AS fr_id,u.id AS requester_id,u.username,u.image_url
        FROM friendships f
        JOIN users u ON f.requester_id = u.id
       WHERE f.receiver_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
    ");
    $fr->execute([$uid]);
    while ($r = $fr->fetch(PDO::FETCH_ASSOC)) {
        $friendRequests[] = [
            'fr_id'     => $r['fr_id'],
            'user_id'   => $r['requester_id'],
            'username'  => $r['username'],
            'image_url' => $r['image_url'],
        ];
    }

    // accepted friends
    $f2 = $pdo->prepare("
      SELECT u.id,u.username,u.image_url
        FROM friendships f
        JOIN users u ON (
               (f.requester_id = ? AND u.id = f.receiver_id)
            OR (f.receiver_id  = ? AND u.id = f.requester_id)
        )
       WHERE f.status = 'accepted'
    ");
    $f2->execute([$uid, $uid]);
    $friends = $f2->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Webnote</title>
  <link rel="stylesheet" href="style.css">
  <link rel="manifest"  href="/manifest.json">
  <meta name="theme-color" content="#5C807D">

  <script>
    // expose to JS
    window.myId           = <?= json_encode($uid) ?>;
    window.noteData       = <?= json_encode($notes) ?>;
    window.initialNote    = <?= json_encode($initialNote) ?>;
    window.isLogged       = <?= $uid ? 'true' : 'false' ?>;
    window.friendRequests = <?= json_encode($friendRequests) ?>;
    window.friendsList    = <?= json_encode($friends) ?>;
  </script>
</head>

<body>
  <!-- Hamburger -->
  <button id="hamburger" class="hamburger">☰</button>

  <div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar collapsed">
      <!-- Profile / Login -->
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

      <!-- Notifications -->
      <button id="notif-btn" class="notif-btn" title="Notifications">🔔</button>
      <div id="notif-panel" class="notif-panel" style="display:none;">
        <?php if (empty($friendRequests)): ?>
          <p class="notif-empty">No notifications.</p>
        <?php endif; ?>
        <?php foreach ($friendRequests as $fr): ?>
          <div class="notif-item"
               data-fr-id="<?= $fr['fr_id'] ?>"
               data-user-id="<?= $fr['user_id'] ?>">
            <img src="<?= $fr['image_url']?:'avatar_default.png' ?>"
                 class="notif-avatar" alt="">
            <div class="notif-text">
              @<?= htmlspecialchars($fr['username']) ?> has invited you to be friends!
            </div>
            <div class="notif-actions">
              <button class="notif-reject btn">Reject</button>
              <button class="notif-accept btn">Accept</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <hr>

      <!-- Notes panel -->
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

      <!-- Friends panel -->
      <div class="friends-panel">
        <h4 class="friends-title">FRIENDS</h4>
        <hr class="friends-separator">
        <div class="friends-list">
          <?php if (empty($friends)): ?>
            <p class="friends-empty">You have no friends yet.</p>
          <?php else: ?>
            <?php foreach ($friends as $f): ?>
            <div class="friend-item" data-user-id="<?= $f['id'] ?>">
              <img src="<?= htmlspecialchars($f['image_url']?:'avatar_default.png') ?>"
                   class="friend-avatar" alt="">
              <span class="friend-name">@<?= htmlspecialchars($f['username']) ?></span>
              <button class="chat-icon btn"
                      data-user="@<?= htmlspecialchars($f['username']) ?>"
                      title="Chat">💬</button>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <hr class="friends-divider">
        <button id="add-friend-btn" class="panel-btn add-friend-btn">
          ADD FRIENDS
        </button>
      </div>
    </aside>

    <!-- EDITOR -->
    <main class="editor">
      <div class="title-card">
        <h2 id="note-title-display">Untitled note</h2>
      </div>
      <div class="editor-card">
        <form id="editor-form" action="save_note.php" method="post">
          <input type="hidden" id="note-id"    name="id">
          <input type="hidden" id="note-slug"  name="slug">
          <input type="hidden" id="note-title-input" name="title">
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

  <!-- CHAT POP-UP -->
  <div id="chat-panel" class="chat-panel">
    <div class="chat-header">
      <span id="chat-with"></span>
      <button id="chat-close-btn" class="chat-close">✖️</button>
    </div>
    <div id="chat-body" class="chat-body"></div>
    <div class="chat-input-area">
      <textarea id="chat-input" placeholder="Type a message…"></textarea>
      <button id="chat-send" class="chat-send">SEND</button>
    </div>
  </div>

  <!-- SHARE MODAL -->
  <div id="share-modal" class="modal" style="display:none;">
    <div class="modal-content">
      <h3>Share Note</h3>
      <form id="share-form" action="share_note.php" method="post">
        <label>
          Link name:
          <input type="text" name="slug" id="share-slug" class="panel-input" placeholder="NotitaMEA">
        </label>
        <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;">
          <input type="checkbox" name="editable" id="share-editable">
          Oricine cu link-ul poate modifica
        </label>
        <input type="hidden" name="content" id="share-content">
        <input type="hidden" name="title"   id="share-title">
        <div class="modal-actions" style="margin-top:1rem;">
          <button type="button" id="share-cancel" class="panel-btn">Cancel</button>
          <button type="submit"          class="panel-btn">Share</button>
        </div>
      </form>
    </div>
  </div>

  <!-- SAVE TO ACCOUNT MODAL -->
  <div id="save-account-modal" class="modal" style="display:none;">
    <div class="modal-content">
      <h3>Save to account</h3>
      <label>
        Title:
        <input type="text" id="save-account-title" class="panel-input" placeholder="Titlul notiței">
      </label>
      <div class="modal-actions" style="margin-top:1rem;">
        <button type="button" id="save-account-cancel" class="panel-btn">Cancel</button>
        <button type="button" id="save-account-confirm" class="panel-btn">Save</button>
      </div>
    </div>
  </div>

  <!-- SAVE TO LOCALSTORAGE MODAL -->
  <div id="save-local-modal" class="modal" style="display:none;">
    <div class="modal-content">
      <h3>Save to LocalStorage</h3>
      <label>
        Title:
        <input type="text" id="save-local-title" class="panel-input" placeholder="Titlul notiței">
      </label>
      <div class="modal-actions" style="margin-top:1rem;">
        <button type="button" id="save-local-cancel" class="panel-btn">Cancel</button>
        <button type="button" id="save-local-confirm" class="panel-btn">Save</button>
      </div>
    </div>
  </div>

  <script>
document.addEventListener('DOMContentLoaded', () => {
  // ──────────────────────────────────────────────────────────────
  // grab your elements
  // ──────────────────────────────────────────────────────────────
  const sidebar      = document.querySelector('.sidebar');
  const ham          = document.getElementById('hamburger');
  const newNoteBtn   = document.getElementById('new-note');
  const editorInput  = document.querySelector('.editor-input');
  const titleDisplay = document.getElementById('note-title-display');
  const titleInput   = document.getElementById('note-title-input');
  const slugInput    = document.getElementById('note-slug');
  const saveBtn      = document.querySelector('.save-note-btn');
  const saveLocalBtn = document.querySelector('.save-local-btn');
  const shareBtn     = document.getElementById('share-btn');
  const notesList    = document.getElementById('notes-list');
  const notifBtn     = document.getElementById('notif-btn');
  const notifPanel   = document.getElementById('notif-panel');
  const addFriendBtn = document.getElementById('add-friend-btn');
  const friendsList  = document.querySelector('.friends-list');
  const chatPanel    = document.getElementById('chat-panel');
  const chatTitle    = document.getElementById('chat-with');
  const chatClose    = document.getElementById('chat-close-btn');
  const chatBody     = document.getElementById('chat-body');
  const chatInput    = document.getElementById('chat-input');
  const chatSend     = document.getElementById('chat-send');

  // share modal fields
  const shareModal    = document.getElementById('share-modal');
  const shareSlug     = document.getElementById('share-slug');
  const shareContent  = document.getElementById('share-content');
  const shareTitle    = document.getElementById('share-title');
  const shareEditable = document.getElementById('share-editable');
  const shareCancel   = document.getElementById('share-cancel');
  const shareConfirm  = document.getElementById('share-confirm');

  // save‐to‐account modal
  const saModal       = document.getElementById('save-account-modal');
  const saInput       = document.getElementById('save-account-title');
  const saCancel      = document.getElementById('save-account-cancel');
  const saConfirm     = document.getElementById('save-account-confirm');

  // save‐to‐local modal
  const slModal       = document.getElementById('save-local-modal');
  const slInput       = document.getElementById('save-local-title');
  const slCancel      = document.getElementById('save-local-cancel');
  const slConfirm     = document.getElementById('save-local-confirm');

  let currentChatUserId = null;

  // ──────────────────────────────────────────────────────────────
  // inject any loaded note into the editor
  // ──────────────────────────────────────────────────────────────
  if (initialNote) {
    editorInput.value        = initialNote.full;
    titleDisplay.textContent = initialNote.title;
    titleInput.value         = initialNote.title;
    slugInput.value          = initialNote.slug;
  }

  // ──────────────────────────────────────────────────────────────
  // dynamic Share ↔ Save button
  // ──────────────────────────────────────────────────────────────
  if (initialNote && initialNote.slug) {
    if (initialNote.editable) {
      // turn “Share” into “Save”
      shareBtn.textContent = 'Save';
      shareBtn.addEventListener('click', () => {
        fetch('update_shared_note.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: `slug=${encodeURIComponent(initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        })
        .then(r => {
          if (!r.ok) throw new Error(r.statusText);
          // you could flash “Saved!” here
        })
        .catch(err => alert('Eroare la salvare: ' + err));
      });
    } else {
      // read‐only: hide the button
      shareBtn.style.display = 'none';
    }
  } else {
    // normal private/new note → open share modal
    shareBtn.addEventListener('click', openShareModal);
  }

  function openShareModal() {
    shareSlug.value        = titleDisplay.textContent.replace(/\s+/g,'');
    shareEditable.checked  = false;
    shareContent.value     = editorInput.value;
    shareTitle.value       = titleInput.value || titleDisplay.textContent;
    shareModal.style.display = 'flex';
  }
  shareCancel.addEventListener('click', () => shareModal.style.display = 'none');
  shareConfirm.addEventListener('click', () => {
    const slug     = shareSlug.value.trim();
    const editable = shareEditable.checked ? 1 : 0;
    if (!slug) return alert('Trebuie un nume de link.');
    shareModal.style.display = 'none';
    const params = new URLSearchParams({content: editorInput.value, title: titleInput.value||titleDisplay.textContent, slug, editable});
    fetch('share_note.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: params.toString()
    })
    .then(r=>r.json())
    .then(json=>{
      if (json.link) {
        prompt('Link-ul tău (copie de aici):', json.link);
        window.location.href = json.link;
      } else {
        alert('Eroare la share: '+(json.error||''));
      }
    })
    .catch(()=>alert('Eroare de rețea.'));
  });

  // ──────────────────────────────────────────────────────────────
  // (rest of your existing handlers: sidebar toggle, new‐note,
  // load‐note buttons, save‐to‐account modal, save‐to‐local modal,
  // friend‐requests, chat pop‐up, service worker…)
  // ──────────────────────────────────────────────────────────────
  // … copy/paste all your other listeners here unchanged …

});// grab sidebar & hamburger
const sidebar = document.querySelector('.sidebar');
const ham     = document.getElementById('hamburger');

// restore persisted state on load
const wasOpen = localStorage.getItem('sidebarOpen') === 'true';
sidebar.classList.toggle('open', wasOpen);
sidebar.classList.toggle('collapsed', !wasOpen);
ham.classList.toggle('open', wasOpen);

// attach click handler
ham.addEventListener('click', () => {
  const open = sidebar.classList.toggle('open');
  sidebar.classList.toggle('collapsed', !open);
  ham.classList.toggle('open', open);
  localStorage.setItem('sidebarOpen', open);
});

</script>

<script>
  // at the very bottom, re-publish initialNote into JS:
  window.initialNote = <?= json_encode([
    'slug'     => $initialNote['slug']     ?? null,
    'full'     => $initialNote['full']     ?? '',
    'title'    => $initialNote['title']    ?? '',
    'editable' => (int)($initialNote['editable'] ?? 0),
  ], JSON_HEX_TAG) ?>;
</script>


</body>
</html>
