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
  <!-- aici pui câmpurile ascunse -->
  <input type="hidden" id="note-id" name="id" value="<?= $initialNote['id'] ?>">
<input type="hidden" id="note-slug" name="slug" value="<?= htmlspecialchars($initialNote['slug']) ?>">
<input type="hidden" id="note-title-input" name="title" value="<?= htmlspecialchars($initialNote['title']) ?>">
<input type="hidden" id="local-id" name="localId">

  <button type="button" class="panel-btn save-note-btn">Save to account</button>
  <button type="button" class="panel-btn save-local-btn">Save to LocalStorage</button>
  <button type="button" id="share-btn" class="panel-btn">Share</button>
  <button type="button" id="delete-btn" class="panel-btn delete-btn" style="background-color:#e74c3c; color:#fff; margin-left:0.5rem">
    Delete
  </button>

  <textarea name="content" class="editor-input" placeholder="Type your note here…" required></textarea>
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
          <button type="button" id="share-cancel" class="panel-btn">Cancel</button><button type="submit" id="share-confirm" class="panel-btn">Share</button>

        </div>
          <!-- … restul share-modal … -->
  <div id="send-to-friend" style="margin-top:1.5rem; border-top:1px solid #ccc; padding-top:1rem;">
    <h4>SEND TO FRIEND</h4>
    <div id="send-friends-list" style="max-height:200px; overflow:auto;"></div>
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
  // ─── Toate elementele ─────────────────────────────────────────
  const sidebar       = document.querySelector('.sidebar');
  const ham           = document.getElementById('hamburger');
  const newNoteBtn    = document.getElementById('new-note');
  const editorInput   = document.querySelector('.editor-input');
  const titleDisplay  = document.getElementById('note-title-display');
  const titleInput    = document.getElementById('note-title-input');
  const slugInput     = document.getElementById('note-slug');
  const idInput       = document.getElementById('note-id');
  const saveBtn       = document.querySelector('.save-note-btn');
  const saveLocalBtn  = document.querySelector('.save-local-btn');
  const shareBtn      = document.getElementById('share-btn');
  const deleteBtn     = document.getElementById('delete-btn');
  const notesList     = document.getElementById('notes-list');
  const notifBtn      = document.getElementById('notif-btn');
  const notifPanel    = document.getElementById('notif-panel');
  const addFriendBtn  = document.getElementById('add-friend-btn');
  const friendsList   = document.querySelector('.friends-list');
  const chatPanel     = document.getElementById('chat-panel');
  const chatTitle     = document.getElementById('chat-with');
  const chatClose     = document.getElementById('chat-close-btn');
  const chatBody      = document.getElementById('chat-body');
  const chatInput     = document.getElementById('chat-input');
  const chatSend      = document.getElementById('chat-send');

  // ─── Demo inject initialNote ──────────────────────────────────
  if (window.initialNote) {
    editorInput.value        = initialNote.full;
    titleDisplay.textContent = initialNote.title;
    titleInput.value         = initialNote.title;
    slugInput.value          = initialNote.slug;
    idInput.value            = initialNote.id || '';
  }

  // ─── DELETE handler ───────────────────────────────────────────
  deleteBtn.addEventListener('click', () => {
    const noteId = idInput.value.trim();
    const slug   = slugInput.value.trim();
    if (!noteId && !slug) {
      return alert('Nu există nicio notă de șters.');
    }
    if (!confirm('Ești sigur că vrei să ștergi această notă?')) {
      return;
    }
    const body = noteId
      ? `id=${encodeURIComponent(noteId)}`
      : `slug=${encodeURIComponent(slug)}`;
    fetch('delete_note.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        alert('Notă ștearsă cu succes.');
        editorInput.value = '';
        titleDisplay.textContent = 'Untitled note';
        titleInput.value = '';
        slugInput.value = '';
        idInput.value   = '';
        location.reload();
      } else {
        alert('Eroare la ștergere: ' + (json.error||''));
      }
    })
    .catch(() => alert('Eroare de rețea la ștergere.'));
  });

  // ─── CHAT SEND handler ────────────────────────────────────────
  chatSend.addEventListener('click', () => {
    const text = chatInput.value.trim();
    const toId = chatTitle.dataset.userId;
    if (!text || !toId) return;
    fetch('send_message.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `to=${encodeURIComponent(toId)}&content=${encodeURIComponent(text)}`
    })
    .then(r => r.json())
    .then(json => {
      if (!json.success) return alert(json.error||'Eroare la trimitere');
      const bubble = document.createElement('div');
      bubble.className = 'chat-message-outgoing';
      bubble.textContent = text;
      chatBody.appendChild(bubble);
      chatBody.scrollTop = chatBody.scrollHeight;
      chatInput.value = '';
    })
    .catch(() => alert('Eroare de rețea'));
  });
  chatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      chatSend.click();
    }
  }); 
  const idInput     = document.getElementById('note-id');
  const slugInput   = document.getElementById('note-slug');
  const editorInput = document.querySelector('.editor-input');
  const titleDisplay= document.getElementById('note-title-display');
  const titleInput  = document.getElementById('note-title-input');

  // … aici urmează toate celelalte handler-e pe care le-ai adăugat deja …

  // ───── handler pentru DELETE ─────
  deleteBtn.addEventListener('click', () => {
    const noteId = idInput.value.trim();
    const slug   = slugInput.value.trim();
    if (!noteId && !slug) {
      return alert('Nu există nicio notă de şters.');
    }
    if (!confirm('Eşti sigur că vrei să ştergi această notă?')) {
      return;
    }
    const body = noteId
      ? `id=${encodeURIComponent(noteId)}`
      : `slug=${encodeURIComponent(slug)}`;
    fetch('delete_note.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        alert('Notă ștearsă cu succes.');
        editorInput.value = '';
        titleDisplay.textContent = 'Untitled note';
        titleInput.value = '';
        slugInput.value = '';
        idInput.value   = '';
        location.reload();
      } else {
        alert('Eroare la ştergere: ' + (json.error||''));
      }
    })
    .catch(() => alert('Eroare de rețea la ștergere.'));
  });

  // ───── handler pentru SEND în chat ─────
  const chatSend  = document.getElementById('chat-send');
  const chatInput = document.getElementById('chat-input');
  const chatWith  = document.getElementById('chat-with');
  const chatBody  = document.getElementById('chat-body');
  chatSend.addEventListener('click', () => {
    const text = chatInput.value.trim();
    const toId = chatWith.dataset.userId;
    if (!text || !toId) return;
    fetch('send_message.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `to=${encodeURIComponent(toId)}&content=${encodeURIComponent(text)}`
    })
    .then(r => r.json())
    .then(json => {
      if (!json.success) return alert(json.error||'Eroare la trimitere');
      const bubble = document.createElement('div');
      bubble.className = 'chat-message-outgoing';
      bubble.textContent = text;
      chatBody.appendChild(bubble);
      chatBody.scrollTop = chatBody.scrollHeight;
      chatInput.value = '';
    })
    .catch(() => alert('Eroare de rețea'));
  });
  chatInput.addEventListener('keydown', e => {
    if (e.key==='Enter' && !e.shiftKey) {
      e.preventDefault();
      chatSend.click();
    }
  });

  // … restul listener-elor (share, friend-request, sidebar, etc.) …
});

</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const notesList    = document.getElementById('notes-list'); 
  const localIdInput = document.getElementById('local-id');
  const idInput      = document.getElementById('note-id');
  const slugInput    = document.getElementById('note-slug');
  const titleDisplay = document.getElementById('note-title-display');
  const titleInput   = document.getElementById('note-title-input');
  const editorInput  = document.querySelector('.editor-input');
  const saveLocalBtn = document.querySelector('.save-local-btn');

  function loadLocalNotes() {
    document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
    const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
    arr.forEach(item => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'panel-btn local-note-btn';
      b.textContent = item.title;
      b.dataset.lid = item.id;
      notesList.appendChild(b);
      b.addEventListener('click', () => {
        localIdInput.value = item.id;
        idInput.value = '';
        slugInput.value = '';
        editorInput.value = item.content;
        titleDisplay.textContent = item.title;
        titleInput.value = item.title;
      });
    });
  }
  loadLocalNotes();

  saveLocalBtn.addEventListener('click', () => {
    localIdInput.value = '';
  });

  deleteBtn.addEventListener('click', () => {
    const srvId = idInput.value.trim();
    const slug  = slugInput.value.trim();
    const locId = localIdInput.value.trim();

    // pe server
    if (srvId || slug) {
      if (!confirm('Ștergi nota de pe server?')) return;
      const body = srvId
        ? `id=${encodeURIComponent(srvId)}`
        : `slug=${encodeURIComponent(slug)}`;
      fetch('delete_note.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body
      })
      .then(r => r.json())
      .then(j => {
        if (j.success) location.reload();
        else alert('Eroare: '+(j.error||''));
      })
      .catch(()=>alert('Eroare de rețea'));
      return;
    }

    // localStorage
    if (locId) {
      if (!confirm('Ștergi nota locală?')) return;
      let arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
      arr = arr.filter(n => String(n.id)!==locId);
      localStorage.setItem('localNotes', JSON.stringify(arr));
      alert('Notă locală ștearsă.');
      editorInput.value = '';
      titleDisplay.textContent = 'Untitled note';
      titleInput.value = '';
      localIdInput.value = '';
      loadLocalNotes();
      return;
    }

    alert('Nu există nicio notă de șters.');
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // referinţe
  const notesList    = document.getElementById('notes-list');
  const deleteBtn    = document.getElementById('delete-btn');
  const saveLocalBtn = document.querySelector('.save-local-btn');
  const localIdInput = document.getElementById('local-id');
  const idInput      = document.getElementById('note-id');
  const slugInput    = document.getElementById('note-slug');
  const titleDisplay = document.getElementById('note-title-display');
  const titleInput   = document.getElementById('note-title-input');
  const editorInput  = document.querySelector('.editor-input');

  // încărcare butoane localStorage
  function loadLocalNotes() {
    document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
    const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
    arr.forEach(item => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'panel-btn local-note-btn';
      b.textContent = item.title; b.dataset.lid = item.id;
      notesList.appendChild(b);
      b.addEventListener('click', () => {
        localIdInput.value    = item.id;
        idInput.value         = '';
        slugInput.value       = '';
        editorInput.value     = item.content;
        titleDisplay.textContent = item.title;
        titleInput.value         = item.title;
      });
    });
  }
  loadLocalNotes();

  // dacă porneşti salvarea local, resetăm contextul
  saveLocalBtn.addEventListener('click', () => {
    localIdInput.value = '';
  });

  // DELETE (fie pe server, fie din localStorage)
  deleteBtn.addEventListener('click', () => {
    const srvId = idInput.value.trim();
    const slug  = slugInput.value.trim();
    const locId = localIdInput.value.trim();

    // 1) DELETE pe server
    if (srvId || slug) {
      if (!confirm('Ștergi nota de pe server?')) return;
      const body = srvId
        ? `id=${encodeURIComponent(srvId)}`
        : `slug=${encodeURIComponent(slug)}`;
      fetch('delete_note.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body
      })
      .then(r => r.json())
      .then(j => {
        if (j.success) location.reload();
        else alert('Eroare server: '+(j.error||''));
      })
      .catch(()=>alert('Eroare de rețea'));
      return;
    }

    // 2) DELETE din localStorage
    if (locId) {
      if (!confirm('Ștergi nota locală?')) return;
      let arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
      arr = arr.filter(n => String(n.id)!==locId);
      localStorage.setItem('localNotes', JSON.stringify(arr));
      alert('Notă locală ștearsă.');
      editorInput.value        = '';
      titleDisplay.textContent = 'Untitled note';
      titleInput.value         = '';
      localIdInput.value       = '';
      loadLocalNotes();
      return;
    }

    // 3) nimic de şters
    alert('Nu există nicio notă de șters.');
  });

  // SHARE modal
  const shareBtn     = document.getElementById('share-btn');
  const shareCancel  = document.getElementById('share-cancel');
  const shareConfirm = document.getElementById('share-confirm');
  const shareModal   = document.getElementById('share-modal');

  shareBtn.addEventListener('click', () => {
    shareModal.style.display = 'flex';
  });
  shareCancel.addEventListener('click', () => {
    shareModal.style.display = 'none';
  });
  shareConfirm.addEventListener('click', e => {
    e.preventDefault();
    // aici pui logica ta de AJAX pentru share…
  });

  // … alte handler-e (chat, notifications etc.) …
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const shareBtn     = document.getElementById('share-btn');
  const shareModal   = document.getElementById('share-modal');
  const sendList     = document.getElementById('send-friends-list');
  const friends      = window.friendsList || [];  // vine din PHP: window.friendsList = [...]

  shareBtn.addEventListener('click', () => {
    // 1) golește orice intrare veche
    sendList.innerHTML = '';

    // 2) pentru fiecare prieten, creează un card
    friends.forEach(f => {
      const card = document.createElement('div');
      card.className    = 'friend-card';
      card.dataset.userId = f.id;
      card.innerHTML    = `
        <span>@${f.username}</span>
        <button type="button" class="send-to-friend-btn">SEND</button>
      `;
      sendList.appendChild(card);
    });

    // 3) arată modalul
    shareModal.style.display = 'flex';
  });

  // Aici poți atașa și handler-ul pentru .send-to-friend-btn
  sendList.addEventListener('click', e => {
    if (!e.target.matches('.send-to-friend-btn')) return;
    // … restul trimiterii notificării …
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const notifBtn   = document.getElementById('notif-btn');
  const notifPanel = document.getElementById('notif-panel');

  // Ascunde panel-ul la început (dacă nu e deja)
  notifPanel.style.display = notifPanel.style.display === 'block' ? 'block' : 'none';

  // Clic pe clopoțel → arată/ascunde notificările
  notifBtn.addEventListener('click', () => {
    notifPanel.style.display =
      notifPanel.style.display === 'block'
        ? 'none'
        : 'block';
  });
});
</script>


   <script src="script.js"></script> 
</body>
</html>
