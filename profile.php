<?php
session_start();
require 'config.php';

// dacă nu ești logat, du-te la login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Procesăm POST-ul de schimbare email/parolă
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['user_id'];

    // 1) preluăm hash-ul curent
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $errors[] = 'Utilizator invalid.';
    } else {
        $hash = $row['password'];

        // 2) schimbare EMAIL
        if (!empty($_POST['action']) && $_POST['action'] === 'email_change') {
            $newEmail  = trim($_POST['new_email'] ?? '');
            $currentPw = $_POST['current_password'] ?? '';

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalid.';
            } elseif (!password_verify($currentPw, $hash)) {
                $errors[] = 'Parola curentă este incorectă.';
            } else {
                $upd = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                if ($upd->execute([$newEmail, $uid])) {
                    $success = 'Email-ul a fost actualizat cu succes.';
                } else {
                    $errors[] = 'Eroare la actualizarea email-ului.';
                }
            }
        }

        // 3) schimbare PAROLĂ
        if (!empty($_POST['action']) && $_POST['action'] === 'password_change') {
            $newPw       = $_POST['new_password'] ?? '';
            $confirmPw   = $_POST['confirm_password'] ?? '';
            $currentPwPw = $_POST['current_password_pw'] ?? '';

            if ($newPw !== $confirmPw) {
                $errors[] = 'Noile parole nu coincid.';
            } elseif (!password_verify($currentPwPw, $hash)) {
                $errors[] = 'Parola curentă este incorectă.';
            } else {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $upd     = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                if ($upd->execute([$newHash, $uid])) {
                    $success = 'Parola a fost schimbată cu succes.';
                } else {
                    $errors[] = 'Eroare la schimbarea parolei.';
                }
            }
        }
    }

    // redirecționăm dacă totul a mers OK
    if ($success && empty($errors)) {
        header('Location: profile.php');
        exit;
    }
}

// 4) Preluăm detaliile utilizatorului
$stmt = $pdo->prepare('SELECT email, username, created_at, image_url FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$email     = htmlspecialchars($user['email']);
$username  = htmlspecialchars($user['username']);
$createdAt = htmlspecialchars($user['created_at']);
$imageUrl  = htmlspecialchars($user['image_url']);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Profile – Webnote</title>
  <!-- 1) Stilurile de bază (panel-btn, variabile, etc) -->
  <link rel="stylesheet" href="style.css">
  <!-- 2) Stilurile comune login/register -->
  <link rel="stylesheet" href="login.css">
  <!-- 3) Suprascrieri/ex Mic profile -->
  <link rel="stylesheet" href="profile.css">
</head>
<body>
  <header class="header">
    <button id="back" class="panel-btn back-btn">Go back</button>
  </header>

  <main class="login-page">
    <div class="profile-card-large">
      <h1>My account</h1>

      <?php if ($errors): ?>
        <ul class="errors">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="profile-content">

        <!-- DETALII + Change -->
        <div class="details">
          <p><strong>Username:</strong> @<?= $username ?></p>

          <p>
            <strong>Email:</strong> <?= $email ?>
            <button class="panel-btn change-btn" data-target="email-form">Change</button>
          </p>

          <p>
            <strong>Password:</strong> ********
            <button class="panel-btn change-btn" data-target="password-form">Change</button>
          </p>

          <p><strong>Date created:</strong> <?= $createdAt ?></p>

          <!-- FORM Schimbare EMAIL -->
          <form id="email-form" class="change-form" method="post" action="profile.php">
            <input type="hidden" name="action" value="email_change">

            <label>
              <span>New Email:</span>
              <input type="email" name="new_email" placeholder="you@example.com" required>
            </label>

            <label>
              <span>Current Password:</span>
              <input type="password" name="current_password" placeholder="••••••••" required>
            </label>

            <div class="form-buttons">
              <button type="submit"  class="panel-btn save-btn">Save</button>
              <button type="button" class="panel-btn cancel-btn">Cancel</button>
            </div>
          </form>

          <!-- FORM Schimbare PAROLĂ -->
          <form id="password-form" class="change-form" method="post" action="profile.php">
            <input type="hidden" name="action" value="password_change">

            <label>
              <span>New Password:</span>
              <input type="password" name="new_password" placeholder="••••••••" required>
            </label>

            <label>
              <span>Confirm New Password:</span>
              <input type="password" name="confirm_password" placeholder="••••••••" required>
            </label>

            <label>
              <span>Current Password:</span>
              <input type="password" name="current_password_pw" placeholder="••••••••" required>
            </label>

            <div class="form-buttons">
              <button type="submit"  class="panel-btn save-btn">Save</button>
              <button type="button" class="panel-btn cancel-btn">Cancel</button>
            </div>
          </form>
        </div>

        <!-- CARD IMAGINE -->
        <div class="image-card">
          <?php if ($imageUrl): ?>
            <img src="<?= $imageUrl ?>" alt="Profile image">
          <?php else: ?>
            <div class="placeholder">No image</div>
          <?php endif; ?>

          <form method="post" action="profile.php">
            <input
              type="url"
              name="image_url"
              placeholder="Image URL"
              value="<?= $imageUrl ?>"
              required>
            <button type="submit" class="panel-btn save-btn">Save</button>
          </form>
        </div>
      </div>

      <button id="logout" class="panel-btn submit-btn">Logout</button>
    </div>
  </main>
  <script>
document.addEventListener('DOMContentLoaded', () => {
  // 1) ascunde toate change-form la start
  document.querySelectorAll('.change-form').forEach(f => {
    f.style.display = 'none';
  });

  // 2) Change → arată formularul țintă
  document.querySelectorAll('.change-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.target;
      document.querySelectorAll('.change-form').forEach(f => f.style.display = 'none');
      document.getElementById(id).style.display = 'flex';
    });
  });

  // 3) Cancel → ascunde formularul părinte
  document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const form = btn.closest('.change-form');
      if (form) form.style.display = 'none';
    });
  });

  // 4) Go back + Logout
  document.getElementById('back').addEventListener('click', () => history.back());
  document.getElementById('logout').addEventListener('click', () => {
    fetch('logout.php', { method: 'POST' })
      .then(() => location.href = 'login.php');
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // ID-urile din profil.php pentru câmpul URL și buton
  const imgInput   = document.getElementById('profile-image-url');
  const saveBtn    = document.getElementById('save-profile-btn');

  saveBtn.addEventListener('click', () => {
    const imageUrl = imgInput.value.trim();
    if (!imageUrl) {
      return alert('Te rog introdu URL-ul imaginii de profil.');
    }

    fetch('update_profile.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `image_url=${encodeURIComponent(imageUrl)}`
    })
    .then(response => response.json())
    .then(json => {
      if (json.success) {
        alert('Imaginea de profil a fost actualizată cu succes!');
        // Dacă vrei, actualizează și avatarul din pagină:
        const avatar = document.querySelector('.profile-img');
        if (avatar) avatar.src = imageUrl;
      } else {
        alert('Eroare la actualizare: ' + (json.error || 'Unknown error'));
      }
    })
    .catch(() => {
      alert('Eroare de rețea. Încearcă din nou.');
    });
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // ──────────────────────────────────────────────────────────────
  // 1) Grab all our elements
  // ──────────────────────────────────────────────────────────────
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

  // Modals
  const shareModal    = document.getElementById('share-modal');
  const shareSlug     = document.getElementById('share-slug');
  const shareContent  = document.getElementById('share-content');
  const shareTitle    = document.getElementById('share-title');
  const shareEditable = document.getElementById('share-editable');
  const shareCancel   = document.getElementById('share-cancel');
  const shareConfirm  = document.getElementById('share-confirm');

  const saModal       = document.getElementById('save-account-modal');
  const saInput       = document.getElementById('save-account-title');
  const saCancel      = document.getElementById('save-account-cancel');
  const saConfirm     = document.getElementById('save-account-confirm');

  const slModal       = document.getElementById('save-local-modal');
  const slInput       = document.getElementById('save-local-title');
  const slCancel      = document.getElementById('save-local-cancel');
  const slConfirm     = document.getElementById('save-local-confirm');

  const afModal       = document.getElementById('add-friend-modal');
  const afInput       = document.getElementById('add-friend-input');
  const afCancel      = document.getElementById('add-friend-cancel');
  const afConfirm     = document.getElementById('add-friend-confirm');

  let currentChatUserId = null;

  // ──────────────────────────────────────────────────────────────
  // 2) Inject any loaded note (`initialNote` vine din PHP)
  // ──────────────────────────────────────────────────────────────
  if (window.initialNote) {
    editorInput.value        = initialNote.full;
    titleDisplay.textContent = initialNote.title;
    titleInput.value         = initialNote.title;
    slugInput.value          = initialNote.slug;
    idInput.value            = initialNote.id ?? '';
  }

  // Build lookup map pentru note vechi
  const noteMap = {};
  (window.noteData||[]).forEach(n => noteMap[n.id] = n);

  // ──────────────────────────────────────────────────────────────
  // 3) Sidebar toggle
  // ──────────────────────────────────────────────────────────────
  const wasOpen = localStorage.getItem('sidebarOpen') === 'true';
  sidebar.classList.toggle('open', wasOpen);
  sidebar.classList.toggle('collapsed', !wasOpen);
  ham.classList.toggle('open', wasOpen);
  ham.addEventListener('click', () => {
    const open = sidebar.classList.toggle('open');
    sidebar.classList.toggle('collapsed', !open);
    ham.classList.toggle('open', open);
    localStorage.setItem('sidebarOpen', open);
  });

  // ──────────────────────────────────────────────────────────────
  // 4) New note
  // ──────────────────────────────────────────────────────────────
  newNoteBtn.addEventListener('click', () => {
    editorInput.value = '';
    titleDisplay.textContent = 'Untitled note';
    titleInput.value = '';
    slugInput.value = '';
    idInput.value = '';
  });

  // ──────────────────────────────────────────────────────────────
  // 5) Load existing (server) notes
  // ──────────────────────────────────────────────────────────────
  document.querySelectorAll('.note-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const note = noteMap[btn.dataset.id] || { full:'', title:'', slug:'' };
      editorInput.value        = note.full;
      titleDisplay.textContent = note.title || 'Untitled note';
      titleInput.value         = note.title || '';
      slugInput.value          = note.slug  || '';
      idInput.value            = btn.dataset.id;
    });
  });

  // ──────────────────────────────────────────────────────────────
  // 6) Auto-save draft in localStorage
  // ──────────────────────────────────────────────────────────────
  editorInput.addEventListener('input', () => {
    localStorage.setItem('draftContent', editorInput.value);
    localStorage.setItem('draftTitle', titleDisplay.textContent);
  });
  const dc = localStorage.getItem('draftContent'),
        dt = localStorage.getItem('draftTitle');
  if (dc) editorInput.value = dc;
  if (dt) titleDisplay.textContent = dt;

  // ──────────────────────────────────────────────────────────────
  // 7) LocalStorage notes
  // ──────────────────────────────────────────────────────────────
  function loadLocalNotes() {
    document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
    const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
    arr.forEach(item => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'panel-btn local-note-btn';
      b.textContent = item.title; b.dataset.lid = item.id;
      notesList.appendChild(b);
      b.addEventListener('click', () => {
        editorInput.value        = item.content;
        titleDisplay.textContent = item.title;
        titleInput.value         = item.title;
        slugInput.value          = '';
        idInput.value            = '';
      });
    });
  }
  loadLocalNotes();
  saveLocalBtn.addEventListener('click', () => {
    slInput.value = titleDisplay.textContent==='Untitled note' ? '' : titleDisplay.textContent;
    slModal.style.display = 'flex';
  });
  slCancel.addEventListener('click', () => slModal.style.display='none');
  slConfirm.addEventListener('click', () => {
    const t = slInput.value.trim();
    if (!t) return alert('Trebuie un titlu.');
    titleDisplay.textContent = t;
    titleInput.value         = t;
    const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
    arr.push({ id:Date.now(), title:t, content:editorInput.value });
    localStorage.setItem('localNotes', JSON.stringify(arr));
    loadLocalNotes();
    slModal.style.display = 'none';
  });

  // ──────────────────────────────────────────────────────────────
  // 8) Save to account modal
  // ──────────────────────────────────────────────────────────────
  saveBtn.addEventListener('click', () => {
    if (!window.isLogged) return window.location='login.php';
    saInput.value = titleDisplay.textContent==='Untitled note' ? '' : titleDisplay.textContent;
    saModal.style.display = 'flex';
  });
  saCancel.addEventListener('click', () => saModal.style.display='none');
  saConfirm.addEventListener('click', () => {
    const t = saInput.value.trim();
    if (!t) return alert('Trebuie un titlu.');
    titleInput.value = t;
    titleDisplay.textContent = t;
    saModal.style.display = 'none';
    document.getElementById('editor-form').submit();
  });

  // ──────────────────────────────────────────────────────────────
  // 9) Share ↔ Save for shared notes
  // ──────────────────────────────────────────────────────────────
  function openShareModal() {
    shareSlug.value       = titleDisplay.textContent.replace(/\s+/g,'');
    shareEditable.checked = false;
    shareContent.value    = editorInput.value;
    shareTitle.value      = titleInput.value || titleDisplay.textContent;
    shareModal.style.display = 'flex';
  }

  if (initialNote && initialNote.slug) {
    if (initialNote.editable) {
      shareBtn.textContent = 'Save';
      shareBtn.addEventListener('click', () => {
        fetch('update_shared_note.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`slug=${encodeURIComponent(initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        }).then(r=>{ if(!r.ok) throw r.statusText; })
          .catch(e => alert('Eroare la salvare: '+e));
      });
    } else {
      shareBtn.style.display = 'none';
    }
  } else {
    shareBtn.addEventListener('click', openShareModal);
  }
  shareCancel.addEventListener('click', () => shareModal.style.display='none');
  shareConfirm.addEventListener('click', e => {
    e.preventDefault();
    const slug = shareSlug.value.trim();
    const ed   = shareEditable.checked?1:0;
    if (!slug) return alert('Trebuie un nume de link.');
    shareModal.style.display='none';
    const params = new URLSearchParams({
      content: editorInput.value,
      title:   titleInput.value||titleDisplay.textContent,
      slug,
      editable: ed
    });
    fetch('share_note.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: params.toString()
    })
    .then(r=>r.json())
    .then(j=>{
      if (j.link) {
        prompt('Copy link:', j.link);
        window.location.href = j.link;
      } else {
        alert('Eroare la share: '+(j.error||''));
      }
    })
    .catch(()=>alert('Network error.'));
  });

  // ──────────────────────────────────────────────────────────────
  // 10) Delete button
  // ──────────────────────────────────────────────────────────────
  deleteBtn.addEventListener('click', () => {
    const nid = idInput.value;
    const slug = slugInput.value;
    if (!nid && !slug) return alert('Nu e nimic de șters.');
    if (!confirm('Ștergi această notă?')) return;
    const body = nid ? `id=${encodeURIComponent(nid)}` : `slug=${encodeURIComponent(slug)}`;
    fetch('delete_note.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body
    })
    .then(r=>r.json())
    .then(j=>{
      if (j.success) {
        alert('Șters cu succes.');
        location.reload();
      } else {
        alert('Eroare la ștergere: '+(j.error||''));
      }
    })
    .catch(()=>alert('Network error.'));
  });

  // ──────────────────────────────────────────────────────────────
  // 11) Notifications dropdown
  // ──────────────────────────────────────────────────────────────
  function updateBell(){
    if (notifPanel.querySelectorAll('.notif-item').length)
      notifBtn.classList.add('has-notifs');
    else {
      notifBtn.classList.remove('has-notifs');
      notifPanel.innerHTML = '<p class="notif-empty">No notifications.</p>';
    }
  }
  notifBtn.addEventListener('click', () => {
    notifPanel.style.display = notifPanel.style.display==='none'?'block':'none';
  });
  updateBell();
  notifPanel.addEventListener('click', e => {
    if (!e.target.matches('.notif-accept, .notif-reject')) return;
    const item = e.target.closest('.notif-item');
    const action = e.target.matches('.notif-accept')?'accept':'reject';
    fetch('respond_friend_request.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`fr_id=${encodeURIComponent(item.dataset.frId)}&action=${action}`
    })
    .then(r=>r.json())
    .then(j=>{
      if (!j.success) return alert(j.error||'');
      if (action==='accept'){
        const div = document.createElement('div');
        div.className = 'friend-item';
        div.dataset.userId = j.user_id;
        div.innerHTML = `
          <img src="${j.avatar}" class="friend-avatar">
          <span class="friend-name">@${j.username}</span>
          <button class="chat-icon btn" data-user="@${j.username}">💬</button>`;
        friendsList.appendChild(div);
        attachChatHandler(div.querySelector('.chat-icon'));
      }
      item.remove(); updateBell();
    })
    .catch(()=>alert('Network error.'));
  });

  // ──────────────────────────────────────────────────────────────
  // 12) Add-friend modal
  // ──────────────────────────────────────────────────────────────
  addFriendBtn.addEventListener('click', () => {
    afInput.value = '@';
    afModal.style.display = 'flex';
    afInput.focus();
  });
  afCancel.addEventListener('click', () => afModal.style.display='none');
  afConfirm.addEventListener('click', () => {
    const h = afInput.value.trim();
    if (!h||h[0]!=='@') return alert('Invalid handle');
    fetch('send_friend_request.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`handle=${encodeURIComponent(h)}`
    })
    .then(r=>r.json())
    .then(j=>{
      if (j.success) { alert('Request sent!'); afModal.style.display='none'; }
      else alert('Error: '+(j.error||''));
    })
    .catch(()=>alert('Network error.'));
  });

  // ──────────────────────────────────────────────────────────────
  // 13) Chat pop-up (open, close, send)
  // ──────────────────────────────────────────────────────────────
  function attachChatHandler(btn){
    btn.addEventListener('click', () => {
      const uid = btn.closest('.friend-item').dataset.userId;
      currentChatUserId = uid;
      chatTitle.textContent = btn.dataset.user;
      chatTitle.dataset.userId = uid;
      chatBody.innerHTML = '';
      chatPanel.classList.add('open');
      fetch(`load_messages.php?with=${uid}`)
        .then(r=>r.json())
        .then(msgs=>{
          msgs.forEach(m => {
            const d = document.createElement('div');
            d.className = m.sender_id==window.myId
                        ? 'chat-message-outgoing'
                        : 'chat-message-incoming';
            d.textContent = m.content;
            chatBody.appendChild(d);
          });
          chatBody.scrollTop = chatBody.scrollHeight;
        });
    });
  }
  document.querySelectorAll('.chat-icon').forEach(attachChatHandler);
  chatClose.addEventListener('click', () => {
    chatPanel.classList.remove('open');
    currentChatUserId = null;
  });
  chatSend.addEventListener('click', () => {
    const text = chatInput.value.trim();
    const toId = chatTitle.dataset.userId;
    if (!text||!toId) return;
    fetch('send_message.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`to=${encodeURIComponent(toId)}&content=${encodeURIComponent(text)}`
    })
    .then(r=>r.json())
    .then(j=>{
      if (!j.success) return alert(j.error||'Eroare');
      const d = document.createElement('div');
      d.className = 'chat-message-outgoing';
      d.textContent = text;
      chatBody.appendChild(d);
      chatBody.scrollTop = chatBody.scrollHeight;
      chatInput.value = '';
    })
    .catch(()=>alert('Network error.'));
  });
  chatInput.addEventListener('keydown', e => {
    if (e.key==='Enter' && !e.shiftKey){
      e.preventDefault();
      chatSend.click();
    }
  });

  // ──────────────────────────────────────────────────────────────
  // 14) Live-save for shared notes
  // ──────────────────────────────────────────────────────────────
  if (!window.isLogged && initialNote && initialNote.editable){
    let db;
    editorInput.addEventListener('input',()=>{
      clearTimeout(db);
      db = setTimeout(()=>{
        fetch('update_shared_note.php',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`slug=${encodeURIComponent(initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        });
      },800);
    });
  }

  // ──────────────────────────────────────────────────────────────
  // 15) Register Service Worker
  // ──────────────────────────────────────────────────────────────
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
  }
});
</script>

</body>
</html>
