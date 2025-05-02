<?php
session_start();
require 'config.php';

// ─────────────────────────────────────────────────────────────
//  Helpers
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
//  Determine “slug” from pretty URL
// ─────────────────────────────────────────────────────────────
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script   = $_SERVER['SCRIPT_NAME'];
$basePath = rtrim(dirname($script), '/');
$slug     = ltrim(substr($uri, strlen($basePath)), '/');
if ($slug === '' || stripos($slug, 'index.php') !== false) {
    $slug = '';
}

// ─────────────────────────────────────────────────────────────
//  Current user
// ─────────────────────────────────────────────────────────────
$uid = $_SESSION['user_id'] ?? null;

// ─────────────────────────────────────────────────────────────
//  Load initial note (by slug) if any
// ─────────────────────────────────────────────────────────────
$initialNote = null;
if ($slug) {
    // private note if logged in
    if ($uid) {
        $stmt = $pdo->prepare("
          SELECT id,title,content,iv
            FROM notes
           WHERE slug = ? AND user_id = ?
           LIMIT 1
        ");
        $stmt->execute([$slug,$uid]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $initialNote = [
                'id'    => $r['id'],
                'title' => $r['title'] ?: 'Untitled note',
                'full'  => decrypt_note($r['content'],$r['iv']),
                'slug'  => $slug
            ];
        }
    }
    // public note (user_id=0) if not found above
    if (!$initialNote) {
        $stmt = $pdo->prepare("
          SELECT title,content,iv
            FROM notes
           WHERE slug = ? AND user_id = 0
           LIMIT 1
        ");
        $stmt->execute([$slug]);
        if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $initialNote = [
                'id'    => null,
                'title' => $r['title'] ?: 'Untitled note',
                'full'  => decrypt_note($r['content'],$r['iv']),
                'slug'  => $slug
            ];
        }
    }
}

// ─────────────────────────────────────────────────────────────
//  Load all user’s notes (for sidebar)
// ─────────────────────────────────────────────────────────────
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
        $full    = decrypt_note($r['content'],$r['iv']);
        $preview = mb_strimwidth($full,0,30,'…');
        $notes[] = [
            'id'      => $r['id'],
            'title'   => $r['title'],
            'preview' => $preview,
            'full'    => $full,
            'slug'    => $r['slug']
        ];
    }
}

// ─────────────────────────────────────────────────────────────
//  Load profile (if logged in)
// ─────────────────────────────────────────────────────────────
$username = null;
$imageUrl = null;
if ($uid) {
    $stmt = $pdo->prepare("
      SELECT username,image_url
        FROM users
       WHERE id = ?
    ");
    $stmt->execute([$uid]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $username = htmlspecialchars($u['username']);
        $imageUrl = $u['image_url'] ? htmlspecialchars($u['image_url']) : null;
    }
}

// ─────────────────────────────────────────────────────────────
//  Load pending friend-requests
// ─────────────────────────────────────────────────────────────
$friendRequests = [];
if ($uid) {
    $stmt = $pdo->prepare("
      SELECT f.id AS fr_id,u.id AS requester_id,u.username,u.image_url
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

// ─────────────────────────────────────────────────────────────
//  Load accepted friends
// ─────────────────────────────────────────────────────────────
$friends = [];
if ($uid) {
    $stmt = $pdo->prepare("
      SELECT u.id,u.username,u.image_url
        FROM friendships f
        JOIN users u ON (
            (f.requester_id = ? AND u.id = f.receiver_id)
         OR (f.receiver_id  = ? AND u.id = f.requester_id)
        )
       WHERE f.status = 'accepted'
    ");
    $stmt->execute([$uid,$uid]);
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
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#5C807D">
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
      <!-- Profile or Login -->
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

      <!-- Notifications bell -->
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
               alt=""
               class="notif-avatar">
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

      <!-- Notes -->
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

      <!-- Friends list + Add friend -->
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
                   alt="" class="friend-avatar">
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

    <!-- Editor area -->
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

  <!-- Chat pop-up -->
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

  <!-- All your JS in one place -->
  <script>
  document.addEventListener('DOMContentLoaded',()=>{

    //── Helpers & selectors ──────────────────────────
    const sidebar      = document.querySelector('.sidebar');
    const ham          = document.getElementById('hamburger');
    const newNoteBtn   = document.getElementById('new-note');
    const editorInput  = document.querySelector('.editor-input');
    const titleDisplay = document.getElementById('note-title-display');
    const titleInput   = document.getElementById('note-title-input');
    const idInput      = document.getElementById('note-id');
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

    //── Toggle sidebar ───────────────────────────────
    ham.addEventListener('click',()=>{
      const open = sidebar.classList.toggle('open');
      sidebar.classList.toggle('collapsed',!open);
      ham.classList.toggle('open',open);
    });

    //── New note ────────────────────────────────────
    newNoteBtn.addEventListener('click',()=>{
      editorInput.value=''; titleDisplay.textContent='Untitled note';
      titleInput.value=''; idInput.value='';
    });

    //── Load server notes ──────────────────────────
    document.querySelectorAll('.note-btn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const d = noteData[btn.dataset.id]||{full:'',title:''};
        editorInput.value=d.full;
        titleDisplay.textContent=d.title||'Untitled note';
        titleInput.value=d.title; idInput.value=d.id;
      });
    });

    //── Save to account ───────────────────────────
    saveBtn.addEventListener('click',()=>{
      if(!isLogged) return window.location='login.php';
      let t = titleInput.value.trim()||prompt('Enter note title:',titleDisplay.textContent);
      if(!t) return;
      titleInput.value=t; titleDisplay.textContent=t;
      document.getElementById('editor-form').submit();
    });

    //── Auto-save draft ────────────────────────────
    editorInput.addEventListener('input',()=>{
      localStorage.setItem('draftContent',editorInput.value);
      localStorage.setItem('draftTitle',titleDisplay.textContent);
    });
    const dc=localStorage.getItem('draftContent'),
          dt=localStorage.getItem('draftTitle');
    if(dc) editorInput.value=dc;
    if(dt) titleDisplay.textContent=dt;

    //── LocalStorage notes ──────────────────────────
    function loadLocalNotes(){
      document.querySelectorAll('.local-note-btn').forEach(b=>b.remove());
      const arr=JSON.parse(localStorage.getItem('localNotes')||'[]');
      arr.forEach(item=>{
        const b=document.createElement('button');
        b.type='button'; b.className='panel-btn local-note-btn';
        b.textContent=item.title; b.dataset.lid=item.id;
        notesList.appendChild(b);
      });
      document.querySelectorAll('.local-note-btn').forEach(b=>{
        b.addEventListener('click',()=>{
          const arr=JSON.parse(localStorage.getItem('localNotes')||'[]');
          const it=arr.find(x=>x.id==b.dataset.lid);
          if(!it) return;
          editorInput.value=it.content;
          titleDisplay.textContent=it.title;
          titleInput.value=it.title; idInput.value='';
        });
      });
    }
    loadLocalNotes();

    //── Save to LocalStorage ────────────────────────
    saveLocalBtn.addEventListener('click',()=>{
      let t = titleInput.value.trim()||prompt('Enter note title:',titleDisplay.textContent);
      if(!t) return;
      titleInput.value=t; titleDisplay.textContent=t;
      const arr=JSON.parse(localStorage.getItem('localNotes')||'[]');
      arr.push({ id:Date.now(), title:t, content:editorInput.value });
      localStorage.setItem('localNotes',JSON.stringify(arr));
      loadLocalNotes();
    });

    //── Share note ──────────────────────────────────
    shareBtn.addEventListener('click',()=>{
      const code=prompt('Share code (ex. MyNote):',titleDisplay.textContent);
      if(!code) return alert('Cancelled');
      if(!editorInput.value.trim()) return alert('Nothing to share');
      const base=location.origin+location.pathname.replace(/[^/]+$/,'');
      alert(`Your link:\n${base}${encodeURIComponent(code.trim())}`);
      const f=document.createElement('form');
      f.method='POST'; f.action='share_note.php';
      ['content','title','slug'].forEach(name=>{
        const i=document.createElement('input');
        i.type='hidden'; i.name=name;
        i.value = name==='content'
                ? editorInput.value
                : name==='title'
                  ? titleInput.value||titleDisplay.textContent
                  : code.trim();
        f.append(i);
      });
      document.body.append(f);
      f.submit();
    });

    //── Notifications ──────────────────────────────
    function updateBell(){
      if(notifPanel.querySelectorAll('.notif-item').length)
        notifBtn.classList.add('has-notifs');
      else {
        notifBtn.classList.remove('has-notifs');
        notifPanel.innerHTML='<p class="notif-empty">No notifications.</p>';
      }
    }
    notifBtn.addEventListener('click',()=>{
      notifPanel.style.display = notifPanel.style.display==='none'?'block':'none';
    });
    updateBell();

    notifPanel.addEventListener('click',e=>{
      const isAccept=e.target.classList.contains('notif-accept');
      const isReject=e.target.classList.contains('notif-reject');
      if(!isAccept && !isReject) return;
      const item=e.target.closest('.notif-item');
      const fr_id=item.dataset.frId;
      const action=isAccept?'accept':'reject';
      fetch('respond_friend_request.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`fr_id=${encodeURIComponent(fr_id)}&action=${encodeURIComponent(action)}`
      })
      .then(r=>r.json())
      .then(json=>{
        if(json.success){
          if(action==='accept'){
            const avatar=item.querySelector('.notif-avatar').src;
            const name=item.querySelector('.notif-text')
                           .textContent.match(/@(\w+)/)[1];
            const div=document.createElement('div');
            div.className='friend-item';
            div.innerHTML=`
              <img src="${avatar}" class="friend-avatar">
              <span class="friend-name">@${name}</span>
              <button class="chat-icon btn" data-user="@${name}">💬</button>
            `;
            friendsList.append(div);
          }
          item.remove();
          updateBell();
        } else {
          alert('Error: '+(json.error||''));
        }
      })
      .catch(()=>alert('Network error.'));
    });

    //── Send friend request ────────────────────────
    addFriendBtn.addEventListener('click',()=>{
      const h=prompt('Friend handle (@username):','@');
      if(!h||h[0]!=='@') return alert('Invalid handle');
      fetch('send_friend_request.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'handle='+encodeURIComponent(h)
      })
      .then(r=>r.json().then(b=>({s:r.status,b})))
      .then(o=>{
        if(o.s===200&&o.b.success) alert('Request sent!');
        else alert('Error: '+(o.b.error||''));
      })
      .catch(()=>alert('Network error.'));
    });

    //── Load initial note if slug ─────────────────
    if(initialNote){
      editorInput.value    = initialNote.full;
      titleDisplay.textContent = initialNote.title;
      titleInput.value     = initialNote.title;
      idInput.value        = initialNote.id ?? '';
    }

    //── Chat pop-up ───────────────────────────────
    document.querySelectorAll('.chat-icon').forEach(btn=>{
      btn.addEventListener('click',()=>{
        chatTitle.textContent=btn.dataset.user;
        chatBody.innerHTML='';
        chatPanel.classList.add('open');
      });
    });
    chatClose.addEventListener('click',()=>{
      chatPanel.classList.remove('open');
    });
    chatSend.addEventListener('click',()=>{
      const txt=chatInput.value.trim();
      if(!txt) return;
      const out=document.createElement('div');
      out.className='chat-message-outgoing';
      out.textContent=txt;
      chatBody.appendChild(out);
      chatBody.scrollTop=chatBody.scrollHeight;
      chatInput.value='';
      // optional auto-reply demo
      setTimeout(()=>{
        const inc=document.createElement('div');
        inc.className='chat-message-incoming';
        inc.textContent='(auto-reply) Got it!';
        chatBody.appendChild(inc);
        chatBody.scrollTop=chatBody.scrollHeight;
      },300);
    });
    chatInput.addEventListener('keydown',e=>{
      if(e.key==='Enter'&&!e.shiftKey){
        e.preventDefault(); chatSend.click();
      }
    });
  });
  </script>
</body>
</html>
