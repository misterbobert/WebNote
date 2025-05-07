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
  <link rel="manifest"  href="/manifest.json"><!-- Quill CSS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

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
     <!-- Notifications -->
<button id="notif-btn" class="notif-btn" title="Notifications">🔔</button>
<div id="notif-panel" class="notif-panel" style="display:none;">
  <?php if (empty($friendRequests)): ?>
    <p class="notif-empty">No notifications.</p>
  <?php endif; ?>
  <?php foreach ($friendRequests as $fr): ?>
    <div class="notif-item">
      <img src="<?= $fr['image_url'] ?: 'avatar_default.png' ?>" class="notif-avatar" alt="">
      <div class="notif-text">
        @<?= htmlspecialchars($fr['username']) ?> has invited you to be friends!
      </div>
      <div class="notif-actions">
        <button class="notif-reject btn" data-fr-id="<?= $fr['fr_id'] ?>">Reject</button>
        <button class="notif-accept btn" data-fr-id="<?= $fr['fr_id'] ?>">Accept</button>
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
        data-id="<?= $n['id'] ?>"
        data-slug="<?= htmlspecialchars($n['slug']) ?>">
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
  <input type="hidden" id="note-id" name="id" value="<?= $initialNote['id'] ?? '' ?>">
<input type="hidden" id="note-slug" name="slug" value="<?= htmlspecialchars($initialNote['slug'] ?? '') ?>">
<input type="hidden" id="note-title-input" name="title" value="<?= htmlspecialchars($initialNote['title'] ?? '') ?>">
<input type="hidden" id="local-id" name="localId">
<button type="button" class="panel-btn save-to-account" data-note-id="">Save to account</button>



  <button type="button" id="share-btn" class="panel-btn">Share</butston>
  <button type="button" id="delete-btn" class="panel-btn delete-btn" style="background-color:#e74c3c; color:#fff; margin-left:0.5rem">
    Delete
  </button>
  <button type="button" id="change-title-btn" class="panel-btn" style="background-color:#3498db; color:#fff; margin-left:0.5rem">
  Change Title
</button>

  <div id="quill-editor" style="height:100%;"></div>
<input type="hidden" name="content" id="hidden-content">
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

 
 
  <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>let quill;
let autosaveTimeout;
// Autosave Function
// Autosave Function
// Autosave Function
// Autosave Function
// Autosave Function
// Autosave Function
// Autosave Function
function setupAutosave() {
  quill.on('text-change', () => {
    const titleInput = document.getElementById('note-title-input');
    const localIdInput = document.getElementById('local-id');

    const content = quill.root.innerHTML.trim();
    let title = titleInput.value.trim();
    let localId = localIdInput.value.trim();

    clearTimeout(autosaveTimeout);
    autosaveNote();
    function autosaveNote() {
  const titleInput = document.getElementById('note-title-input');
  const localIdInput = document.getElementById('local-id');

  const content = quill.root.innerHTML.trim();
  let title = titleInput.value.trim();
  let localId = localIdInput.value.trim();

  let notes = JSON.parse(localStorage.getItem('localNotes') || '[]');

  if (!localId) {
    localId = 'draft-' + Date.now();
    localIdInput.value = localId;
    notes.push({ id: localId, title: title || 'Untitled note', content });
    console.log('✅ New draft autosaved:', localId);
  } else {
    const index = notes.findIndex(note => note.id === localId);

    if (index !== -1) {
      notes[index].title = title || 'Untitled note';
      notes[index].content = content;
      console.log('✅ Draft updated:', localId);
    } else {
      notes.push({ id: localId, title: title || 'Untitled note', content });
      console.log('✅ New draft added:', localId);
    }
  }

  localStorage.setItem('localNotes', JSON.stringify(notes));
  showLocalNotes();
}

  });
}



// Inițializare Quill
function initializeQuill() {
  if (!quill) {
    quill = new Quill('#quill-editor', {
      theme: 'snow',
      placeholder: 'Scrie notița aici...',
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'font': [] }, { 'size': [] }],
          [{ 'color': [] }, { 'background': [] }],
          [{ 'list': 'ordered' }, { 'list': 'bullet' }],
          [{ 'align': [] }],
          ['clean']
        ]
      }
    });
    console.log('✅ Quill initialized.');
    setupAutosave();
  }
}



// Creare Notiță Nouă
// Creare Notiță Nouă
// Creare Notiță Nouă// Creare Notiță Nouă
// Creare Notiță Nouă
// Creare Notiță Nouă
function createNewNote(forceNew = false) {
  const notes = JSON.parse(localStorage.getItem('localNotes') || '[]');

  const newId = 'draft-' + Date.now();
  document.getElementById('local-id').value = newId;
  document.querySelector('.save-to-account').dataset.noteId = newId;

  notes.push({ id: newId, title: 'Untitled note', content: '' });
  localStorage.setItem('localNotes', JSON.stringify(notes));

  quill.root.innerHTML = '';
  document.getElementById('note-title-display').innerText = 'Untitled note';
  document.getElementById('note-title-input').value = 'Untitled note';

  console.log('✅ New note created and draft initialized:', newId);
  showLocalNotes();
}


// Afișare Notițe Locale
// Afișare Notițe Locale
// Afișare Notițe Locale
// Afișare Notițe Locale
// Afișare Notițe Locale
function showLocalNotes() {
  const notes = JSON.parse(localStorage.getItem('localNotes') || '[]');
  const container = document.getElementById('notes-list');
  container.innerHTML = '';

  notes.forEach(note => {
    const btn = document.createElement('button');
    btn.className = 'panel-btn note-btn';
    btn.textContent = note.title || 'Untitled note';
    btn.dataset.noteId = note.id;

    btn.addEventListener('click', () => {
      quill.root.innerHTML = note.content;
      document.getElementById('note-title-display').innerText = note.title;
      document.getElementById('note-title-input').value = note.title;
      document.getElementById('local-id').value = note.id;

      // Actualizăm `data-note-id` al butonului „Save to Account”
      document.querySelector('.save-to-account').dataset.noteId = note.id;

      console.log('✅ Note loaded:', note.id);
    });

    container.appendChild(btn);
  });
}





// Salvare în Cont
// Salvare în Cont
function saveNoteToAccount(noteId) {
    const notes = JSON.parse(localStorage.getItem('localNotes') || '[]');
    const note = notes.find(note => note.id === noteId);

    if (!note) {
        alert('Note not found in local storage.');
        return;
    }

    fetch('save_note.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            noteId: note.id,
            title: note.title,
            content: note.content
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Note saved to account successfully!');
        } else {
            alert('Failed to save note to account.');
        }
    })
    .catch(error => console.error('Error:', error));
}

// Event Listener pentru butonul „Save to Account”
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('save-to-account')) {
        const noteId = document.getElementById('local-id').value.trim();
        if (!noteId) {
            alert('No note selected to save.');
            return;
        }
        saveNoteToAccount(noteId);
    }
});





// Ștergere Notiță
// Ștergere Notiță
// Ștergere Notiță
function deleteNote() {
  if (confirm('Sigur vrei să ștergi această notiță?')) {
    const localId = document.getElementById('local-id').value.trim();
    let notes = JSON.parse(localStorage.getItem('localNotes') || '[]');

    notes = notes.filter(note => note.id !== localId);
    localStorage.setItem('localNotes', JSON.stringify(notes));

    console.log('✅ Note deleted:', localId);

    // Resetare editor
    quill.root.innerHTML = '';
    document.getElementById('note-title-display').innerText = 'Untitled note';
    document.getElementById('note-title-input').value = '';
    document.getElementById('local-id').value = '';

    showLocalNotes();
  }
}


// Event Listeners
// Actualizează această funcție
// Event Listeners
function setupEventListeners() {
  const newNoteBtn = document.getElementById('new-note');
  const deleteBtn = document.getElementById('delete-btn');

  if (newNoteBtn) newNoteBtn.addEventListener('click', () => createNewNote(true)); // Force New Note
  if (deleteBtn) deleteBtn.addEventListener('click', deleteNote);
}
function changeNoteTitle() {
  const localId = document.getElementById('local-id').value.trim();
  let notes = JSON.parse(localStorage.getItem('localNotes') || '[]');

  if (!localId) {
    alert("No active note to rename.");
    return;
  }

  const newTitle = prompt("Enter the new title:");
  if (!newTitle) return;

  // Actualizăm titlul în editor
  document.getElementById('note-title-display').innerText = newTitle;
  document.getElementById('note-title-input').value = newTitle;

  // Actualizăm titlul în localStorage
  const index = notes.findIndex(note => note.id === localId);
  if (index !== -1) {
    notes[index].title = newTitle;
  } else {
    // Dacă nota nu există în localStorage, o adăugăm
    notes.push({ id: localId, title: newTitle, content: quill.root.innerHTML });
  }

  localStorage.setItem('localNotes', JSON.stringify(notes));
  console.log('✅ Title updated for note:', localId);
  showLocalNotes();
}


// DOM Ready
// DOM Ready
// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
  initializeQuill();
  setupEventListeners();
  showLocalNotes();

  const notes = JSON.parse(localStorage.getItem('localNotes') || '[]');
  const draftNote = notes.find(note => note.id.startsWith('draft-'));
  const changeTitleBtn = document.getElementById('change-title-btn'); 
if (changeTitleBtn) changeTitleBtn.addEventListener('click', changeNoteTitle);
 



  if (draftNote) {
    quill.root.innerHTML = draftNote.content;
    document.getElementById('note-title-input').value = draftNote.title;
    document.getElementById('note-title-display').innerText = draftNote.title || 'Untitled note';
    document.getElementById('local-id').value = draftNote.id;
    console.log('✅ Draft loaded:', draftNote.id);
  } else {
    console.log('ℹ️ No draft found.');
  }
});

// Event listener pentru butoanele Reject și Accept
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('notif-reject') || e.target.classList.contains('notif-accept')) {
        const frId = e.target.dataset.frId;
        const action = e.target.classList.contains('notif-reject') ? 'reject' : 'accept';
        handleFriendRequest(frId, action);
    }
});

// Funcția de gestionare a cererilor de prietenie
function handleFriendRequest(frId, action) {
    fetch('friend_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            frId: frId,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`Friend request ${action}ed successfully.`);
            // Eliminăm cererea de prietenie din UI
            document.querySelector(`.notif-reject[data-fr-id="${frId}"]`).closest('.notif-item').remove();
        } else {
            console.error('Error:', data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}




</script>

   <script src="script.js"></script> 
</body>
</html>
