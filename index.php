<?php
session_start();
require 'config.php';

// — Helper de decriptare —
function decrypt_note(string $b64cipher, string $b64iv): string {
    $cipher = base64_decode($b64cipher);
    $iv     = base64_decode($b64iv);
    return openssl_decrypt($cipher, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
}

// — Load slug, initialNote, notes, profile, friendRequests —
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script   = $_SERVER['SCRIPT_NAME'];
$basePath = rtrim(dirname($script), '/');
$slug     = ltrim(substr($uri, strlen($basePath)), '/');
if ($slug === '' || stripos($slug, 'index.php') !== false) {
    $slug = '';
}

$uid = $_SESSION['user_id'] ?? null;
$initialNote = null;
if ($slug) {
    // note private
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
    // note public (guest, user_id = 0)
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

// load notes for sidebar
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

// load profile
$username = null;
$imageUrl = null;
if ($uid) {
    $stmt = $pdo->prepare("SELECT username, image_url FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $username = htmlspecialchars($u['username']);
        $imageUrl = $u['image_url'] ? htmlspecialchars($u['image_url']) : null;
    }
}

// load pending friend requests
$friendRequests = [];
if ($uid) {
    $stmt = $pdo->prepare("
      SELECT f.id AS fr_id, u.id AS requester_id, u.username, u.image_url
        FROM friendships f
        JOIN users u ON f.requester_id = u.id
       WHERE f.receiver_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
    ");
    $stmt->execute([$uid]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $friendRequests[] = [
            'fr_id'     => $r['fr_id'],
            'user_id'   => $r['requester_id'],
            'username'  => $r['username'],
            'image_url' => $r['image_url']
        ];
    }
}


// ─────────────────────────────────────────────
// 5) Load accepted friends for sidebar
// ─────────────────────────────────────────────
$friends = [];
if ($uid) {
    $stmt = $pdo->prepare("
      SELECT u.id, u.username, u.image_url
        FROM friendships f
        JOIN users u
          ON ( (f.requester_id = ? AND u.id = f.receiver_id)
             OR (f.receiver_id = ? AND u.id = f.requester_id)
             )
       WHERE f.status = 'accepted'
    ");
    // bind $uid twice for requester_id = ? AND receiver_id = ?
    $stmt->execute([$uid, $uid]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Webnote</title>
  <link rel="stylesheet" href="style.css">
  <!-- PWA manifest & theme -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#5C807D">

  <!-- injectăm datele în JS -->
  <script>
    window.noteData       = <?= json_encode($notes) ?>;
    window.initialNote    = <?= json_encode($initialNote) ?>;
    window.isLogged       = <?= $uid ? 'true' : 'false' ?>;
    window.friendRequests = <?= json_encode($friendRequests) ?>;
  </script>
</head>
<body>
  <!-- Hamburger -->
  <button id="hamburger" class="hamburger">☰</button>

  <div class="container">
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
            <button id="manage-btn" class="manage-btn" onclick="location.href='profile.php'">
              Manage
            </button>
          </div>
        <?php else: ?>
          <button id="login-btn" class="login-btn" onclick="location.href='login.php'">
            Login
          </button>
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
            <img src="<?= $fr['image_url'] ?: 'avatar_default.png' ?>"
                 alt="Avatar"
                 class="notif-avatar">
            <div class="notif-text">
              @<?= htmlspecialchars($fr['username']) ?>
              has invited you to be his friend!
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
          <!-- JS va injecta și notițele din LocalStorage -->
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
    <img src="<?= htmlspecialchars($f['image_url'] ?: 'avatar_default.png') ?>"
         alt="Avatar"
         class="friend-avatar">
    <span class="friend-name">@<?= htmlspecialchars($f['username']) ?></span>
    <button class="chat-btn" title="Chat with <?= htmlspecialchars($f['username']) ?>">💬</button>
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

    <!-- Editor -->
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

  <!-- toate event‐listener‐ele în one place -->
  <script>
document.addEventListener('DOMContentLoaded', () => {
  // ────────────────────────
  // ELEMENT SELECTORS
  // ────────────────────────
  const sidebar       = document.querySelector('.sidebar');
  const ham           = document.getElementById('hamburger');
  const newBtn        = document.getElementById('new-note');
  const editorIn      = document.querySelector('.editor-input');
  const titleDisp     = document.getElementById('note-title-display');
  const titleIn       = document.getElementById('note-title-input');
  const idIn          = document.getElementById('note-id');
  const saveBtn       = document.querySelector('.save-note-btn');
  const saveLocalBtn  = document.querySelector('.save-local-btn');
  const shareBtn      = document.getElementById('share-btn');
  const notesList     = document.getElementById('notes-list');
  const notifBtn      = document.getElementById('notif-btn');
  const notifPanel    = document.getElementById('notif-panel');
  const addFriendBtn  = document.getElementById('add-friend-btn');
  const friendsList   = document.querySelector('.friends-list');

  // ────────────────────────
  // DRAFT AUTO-SAVE
  // ────────────────────────
  editorIn.addEventListener('input', () => {
    localStorage.setItem('draftContent', editorIn.value);
    localStorage.setItem('draftTitle', titleDisp.textContent);
  });
  // load draft
  const draftContent = localStorage.getItem('draftContent');
  const draftTitle   = localStorage.getItem('draftTitle');
  if (draftContent) editorIn.value = draftContent;
  if (draftTitle)   titleDisp.textContent = draftTitle;

  // ────────────────────────
  // LOCALSTORAGE NOTES
  // ────────────────────────
  function loadLocalNotes() {
    // remove old
    document.querySelectorAll('.local-note-btn').forEach(b=>b.remove());
    const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
    arr.forEach(item => {
      const btn = document.createElement('button');
      btn.type        = 'button';
      btn.className   = 'panel-btn local-note-btn';
      btn.textContent = item.title;
      btn.dataset.lid = item.id;
      notesList.appendChild(btn);
    });
    // click → load
    document.querySelectorAll('.local-note-btn').forEach(b => {
      b.addEventListener('click', () => {
        const arr = JSON.parse(localStorage.getItem('localNotes')||'[]');
        const it  = arr.find(x=>x.id==b.dataset.lid);
        if (!it) return;
        editorIn.value        = it.content;
        titleDisp.textContent = it.title;
        titleIn.value         = it.title;
        idIn.value            = '';
      });
    });
  }
  loadLocalNotes();

  // ────────────────────────
  // SIDEBAR TOGGLE
  // ────────────────────────
  ham.addEventListener('click', () => {
    const open = sidebar.classList.toggle('open');
    sidebar.classList.toggle('collapsed', !open);
    ham.classList.toggle('open', open);
  });

  // ────────────────────────
  // NEW NOTE
  // ────────────────────────
  newBtn.addEventListener('click', () => {
    editorIn.value        = '';
    titleDisp.textContent = 'Untitled note';
    titleIn.value         = '';
    idIn.value            = '';
  });

  // ────────────────────────
  // SERVER-SAVED NOTES
  // ────────────────────────
  document.querySelectorAll('.note-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = noteData[btn.dataset.id] || { full:'', title:'' };
      editorIn.value        = d.full;
      titleDisp.textContent = d.title || 'Untitled note';
      titleIn.value         = d.title;
      idIn.value            = d.id;
    });
  });

  // ────────────────────────
  // SAVE TO ACCOUNT
  // ────────────────────────
  saveBtn.addEventListener('click', () => {
    if (!isLogged) {
      return window.location.href = 'login.php';
    }
    let t = titleIn.value.trim() ||
            prompt('Enter note title:', titleDisp.textContent);
    if (!t) return;
    titleIn.value         = t;
    titleDisp.textContent = t;
    document.getElementById('editor-form').submit();
  });

  // ────────────────────────
  // SAVE TO LOCALSTORAGE
  // ────────────────────────
  saveLocalBtn.addEventListener('click', () => {
    let t = titleIn.value.trim() ||
            prompt('Enter note title:', titleDisp.textContent);
    if (!t) return;
    titleIn.value         = t;
    titleDisp.textContent = t;
    const content = editorIn.value;
    const key  = 'localNotes';
    const arr  = JSON.parse(localStorage.getItem(key) || '[]');
    const id   = Date.now();
    arr.push({ id, title: t, content });
    localStorage.setItem(key, JSON.stringify(arr));
    loadLocalNotes();
  });

  // ────────────────────────
  // SHARE NOTE
  // ────────────────────────
  shareBtn.addEventListener('click', () => {
    const code = prompt(
      'Introdu numele notiței pentru share (ex. NotitaMEA):',
      titleDisp.textContent
    );
    if (!code) return alert('Share cancelled');
    if (!editorIn.value.trim()) return alert('Nothing to share');
    const base = window.location.origin +
                 window.location.pathname.replace(/[^/]+$/,'');
    alert(`Link-ul tău:\n${base}${encodeURIComponent(code.trim())}`);
    // submit hidden form to share_note.php
    const f = document.createElement('form');
    f.method = 'POST'; f.action = 'share_note.php';
    ['content','title','slug'].forEach(name => {
      const inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = name;
      inp.value = name==='content'
                ? editorIn.value
                : name==='title'
                  ? titleIn.value || titleDisp.textContent
                  : code.trim();
      f.appendChild(inp);
    });
    document.body.appendChild(f);
    f.submit();
  });

  // ────────────────────────
  // NOTIFICATIONS PANEL
  // ────────────────────────
  function updateBellDot() {
    if (notifPanel.querySelectorAll('.notif-item').length) {
      notifBtn.classList.add('has-notifs');
    } else {
      notifBtn.classList.remove('has-notifs');
      notifPanel.innerHTML = '<p class="notif-empty">No notifications.</p>';
    }
  }
  // toggle panel
  notifBtn.addEventListener('click', () => {
    notifPanel.style.display =
      notifPanel.style.display === 'none' ? 'block' : 'none';
  });
  updateBellDot();

  // accept/reject handler
  function respond(fr_id, action, itemEl) {
    fetch('respond_friend_request.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `fr_id=${encodeURIComponent(fr_id)}&action=${encodeURIComponent(action)}`
    })
    .then(r=>r.json())
    .then(json=> {
      if (json.success) {
        if (action==='accept') {
          // append to friends list
          const avatar = itemEl.querySelector('.notif-avatar').src;
          const name   = itemEl.querySelector('.notif-text')
                              .textContent.match(/@(\w+)/)[1];
          const div = document.createElement('div');
          div.className = 'friend-item';
          div.innerHTML = `
            <img src="${avatar}" class="friend-avatar">
            <span class="friend-name">@${name}</span>
          `;
          friendsList.appendChild(div);
        }
        itemEl.remove();
        updateBellDot();
      } else {
        alert('Eroare: '+json.error);
      }
    })
    .catch(()=>alert('Eroare de rețea, încearcă din nou.'));
  }

  // delegate click în panel
  notifPanel.addEventListener('click', e => {
    if (e.target.classList.contains('notif-accept') ||
        e.target.classList.contains('notif-reject')) {
      const item   = e.target.closest('.notif-item');
      const fr_id  = item.dataset.frId;
      const action = e.target.classList.contains('notif-accept')
                   ? 'accept' : 'reject';
      respond(fr_id, action, item);
    }
  });

  // ────────────────────────
  // SEND FRIEND REQUEST
  // ────────────────────────
  if (addFriendBtn) {
    addFriendBtn.addEventListener('click', () => {
      const handle = prompt('Introdu numele prietenului (începând cu @):','@');
      if (!handle || handle[0]!=='@') return alert('Handle invalid.');
      fetch('send_friend_request.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'handle='+encodeURIComponent(handle)
      })
      .then(r=>r.json().then(j=>({status:r.status,body:j})))
      .then(obj=>{
        if (obj.status===200 && obj.body.success) {
          alert('Cererea a fost trimisă!');
        } else {
          alert('Eroare: '+(obj.body.error||''));
        }
      })
      .catch(()=>alert('Eroare de rețea.'));
    });
  }

  // ────────────────────────
  // LOAD INITIAL NOTE (slug)
  // ────────────────────────
  if (initialNote) {
    editorIn.value        = initialNote.full;
    titleDisp.textContent = initialNote.title;
    titleIn.value         = initialNote.title;
    idIn.value            = initialNote.id || '';
  }
});// open chat panel
document.querySelectorAll('.chat-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const item     = btn.closest('.friend-item');
    const name     = item.querySelector('.friend-name').textContent;
    const panel    = document.getElementById('chat-panel');
    const header   = document.getElementById('chat-with');
    const messages = panel.querySelector('.chat-messages');
    header.textContent = `Chat with ${name}`;
    messages.innerHTML = '';            // clear old chat
    panel.style.display = 'flex';
  });
});

// close chat panel
document.getElementById('chat-close').addEventListener('click', () => {
  document.getElementById('chat-panel').style.display = 'none';
});

// send a message (demo only)
document.getElementById('chat-send').addEventListener('click', () => {
  const input = document.getElementById('chat-input');
  const txt   = input.value.trim();
  if (!txt) return;
  const msgEl = document.createElement('div');
  msgEl.textContent = txt;
  msgEl.style.margin = '0.5rem 0';
  msgEl.style.padding = '0.5rem';
  msgEl.style.background = 'rgba(255,255,255,0.2)';
  msgEl.style.borderRadius = '4px';
  document.querySelector('.chat-messages').appendChild(msgEl);
  document.querySelector('.chat-messages').scrollTop =
    document.querySelector('.chat-messages').scrollHeight;
  input.value = '';
});
document.addEventListener('DOMContentLoaded', ()=> {
  const chatPanel = document.getElementById('chat-panel');
  const chatTitle = document.getElementById('chat-with');
  const chatClose = document.getElementById('chat-close-btn');
  const chatSend  = document.getElementById('chat-send');
  const chatInput = document.getElementById('chat-input');
  const chatBody  = document.getElementById('chat-body');

  // deschide fereastra de chat când apeși iconița de lângă fiecare friend
  document.querySelectorAll('.chat-icon').forEach(btn => {
    btn.addEventListener('click', () => {
      const user = btn.dataset.user;      // ex. "@da"
      chatTitle.textContent = user;
      chatBody.innerHTML = '';            // golește istoricul sau îl încarci aici
      chatPanel.classList.add('open');
    });
  });

  // butonul X închide fereastra
  chatClose.addEventListener('click', () => {
    chatPanel.classList.remove('open');
  });

  // trimitere mesaj (doar local în demo)
  chatSend.addEventListener('click', () => {
    const txt = chatInput.value.trim();
    if (!txt) return;
    // adaugă mesaj la corp
    const msg = document.createElement('div');
    msg.className = 'chat-message-outgoing';
    msg.textContent = txt;
    chatBody.appendChild(msg);
    chatInput.value = '';
    chatBody.scrollTop = chatBody.scrollHeight;
    // aici poți face și fetch() spre server pentru a salva/transmite mesajul real
  });
});

</script>
<script>
document.addEventListener('DOMContentLoaded', ()=> {
  const chatPanel = document.getElementById('chat-panel');
  const chatTitle = document.getElementById('chat-with');
  const chatClose = document.getElementById('chat-close-btn');
  const chatSend  = document.getElementById('chat-send');
  const chatInput = document.getElementById('chat-input');
  const chatBody  = document.getElementById('chat-body');

  // 1) Deschide chat la click pe fiecare iconiță
  document.querySelectorAll('.chat-icon').forEach(btn => {
    btn.addEventListener('click', () => {
      const userHandle = btn.dataset.user;    // ex "@da"
      chatTitle.textContent = userHandle;
      chatBody.innerHTML = '';                // golește chatul
      chatPanel.classList.add('open');
    });
  });

  // 2) Butonul ✕ închide chat-ul
  chatClose.addEventListener('click', () => {
    chatPanel.classList.remove('open');
  });

  // 3) Trimite mesaj: creează un <div> cu clasa outgoing
  chatSend.addEventListener('click', () => {
    const text = chatInput.value.trim();
    if (!text) return;
    const out = document.createElement('div');
    out.className = 'chat-message-outgoing';
    out.textContent = text;
    chatBody.appendChild(out);
    chatBody.scrollTop = chatBody.scrollHeight;
    chatInput.value = '';

    // 4) (optional) simulăm un răspuns automat după 500ms
    setTimeout(() => {
      const incoming = document.createElement('div');
      incoming.className = 'chat-message-incoming';
      incoming.textContent = 'Salut, iată mesajul primit!';
      chatBody.appendChild(incoming);
      chatBody.scrollTop = chatBody.scrollHeight;
    }, 500);
  });

  // 5) Apasă Enter în text area → trimite mesaj
  chatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      chatSend.click();
    }
  });
});
</script>

<!-- Chat pop-up -->
<div id="chat-panel" class="chat-panel" style="display:none;">
  <div class="chat-header">
    <span id="chat-with"></span>
    <button id="chat-close" class="chat-close">✖️</button>
  </div>
  <div class="chat-messages"></div>
  <div class="chat-input-area">
    <textarea id="chat-input" placeholder="Type a message…"></textarea>
    <button id="chat-send" class="chat-send">SEND</button>
  </div>
</div>

</body>
</html>
