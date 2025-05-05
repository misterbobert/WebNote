// script.js
document.addEventListener('DOMContentLoaded', () => {
  // ───── Selectors ──────────────────────────────────────────
  const sidebar        = document.querySelector('.sidebar');
  const ham            = document.getElementById('hamburger');
  const newNoteBtn     = document.getElementById('new-note');
  const editorInput    = document.querySelector('.editor-input');
  const titleDisplay   = document.getElementById('note-title-display');
  const titleInput     = document.getElementById('note-title-input');
  const slugInput      = document.getElementById('note-slug');
  const notesList      = document.getElementById('notes-list');

  const saveBtn        = document.querySelector('.save-note-btn');
  const saveLocalBtn   = document.querySelector('.save-local-btn');

  const shareBtn         = document.getElementById('share-btn');
  const shareModal       = document.getElementById('share-modal');
  const shareSlugInput   = document.getElementById('share-slug');
  const shareEditableCB  = document.getElementById('share-editable');
  const shareContent     = document.getElementById('share-content');
  const shareTitle       = document.getElementById('share-title');
  const shareCancel      = document.getElementById('share-cancel');
  const shareConfirm     = document.getElementById('share-confirm');

  const notifBtn         = document.getElementById('notif-btn');
  const notifPanel       = document.getElementById('notif-panel');

  const addFriendBtn     = document.getElementById('add-friend-btn');
  const friendsList      = document.querySelector('.friends-list');

  const chatPanel        = document.getElementById('chat-panel');
  const chatTitle        = document.getElementById('chat-with');
  const chatBody         = document.getElementById('chat-body');
  const chatInput        = document.getElementById('chat-input');
  const chatSend         = document.getElementById('chat-send');
  const chatClose        = document.getElementById('chat-close-btn');

  const saModal          = document.getElementById('save-account-modal');
  const saInput          = document.getElementById('save-account-title');
  const saCancel         = document.getElementById('save-account-cancel');
  const saConfirm        = document.getElementById('save-account-confirm');

  const slModal          = document.getElementById('save-local-modal');
  const slInput          = document.getElementById('save-local-title');
  const slCancel         = document.getElementById('save-local-cancel');
  const slConfirm        = document.getElementById('save-local-confirm');

  let currentChatUserId  = null;

  // ───── Sidebar toggle + persist ────────────────────────────
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

  // ───── New note ─────────────────────────────────────────────
  newNoteBtn.addEventListener('click', () => {
    editorInput.value        = '';
    titleDisplay.textContent = 'Untitled note';
    titleInput.value         = '';
    slugInput.value          = '';
  });

  // ───── Load server notes ────────────────────────────────────
  document.querySelectorAll('.note-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = window.noteData[btn.dataset.id] || { full:'', title:'', slug:'' };
      editorInput.value        = d.full;
      titleDisplay.textContent = d.title || 'Untitled note';
      titleInput.value         = d.title;
      slugInput.value          = d.slug || '';
    });
  });

  // ───── Save to account (modal) ─────────────────────────────
  saveBtn.addEventListener('click', () => {
    if (!window.isLogged) return window.location = 'login.php';
    saInput.value = titleDisplay.textContent === 'Untitled note' ? '' : titleDisplay.textContent;
    saModal.style.display = 'flex';
  });
  saCancel.addEventListener('click', () => saModal.style.display = 'none');
  saConfirm.addEventListener('click', () => {
    const t = saInput.value.trim();
    if (!t) return alert('Trebuie un titlu.');
    titleInput.value         = t;
    titleDisplay.textContent = t;
    saModal.style.display    = 'none';
    document.getElementById('editor-form').submit();
  });

  // ───── Save to localStorage (modal) ────────────────────────
  saveLocalBtn.addEventListener('click', () => {
    slInput.value = titleDisplay.textContent === 'Untitled note' ? '' : titleDisplay.textContent;
    slModal.style.display = 'flex';
  });
  slCancel.addEventListener('click', () => slModal.style.display = 'none');
  slConfirm.addEventListener('click', () => {
    const t = slInput.value.trim();
    if (!t) return alert('Trebuie un titlu.');
    titleDisplay.textContent = t;
    titleInput.value         = t;
    const arr = JSON.parse(localStorage.getItem('localNotes') || '[]');
    arr.push({ id: Date.now(), title: t, content: editorInput.value });
    localStorage.setItem('localNotes', JSON.stringify(arr));
    // re-render local notes
    document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
    arr.forEach(item => {
      const b = document.createElement('button');
      b.type        = 'button';
      b.className   = 'panel-btn local-note-btn';
      b.textContent = item.title;
      b.dataset.lid = item.id;
      notesList.appendChild(b);
      b.addEventListener('click', () => {
        editorInput.value        = item.content;
        titleDisplay.textContent = item.title;
        titleInput.value         = item.title;
        slugInput.value          = '';
      });
    });
    slModal.style.display = 'none';
  });

  // ───── Dynamic Share/Save button ────────────────────────────
  if (window.initialNote && window.initialNote.slug) {
    // viewing a shared note
    if (window.initialNote.editable) {
      shareBtn.textContent = 'Save';
      shareBtn.addEventListener('click', () => {
        fetch('update_shared_note.php', {
          method:  'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body:    `slug=${encodeURIComponent(window.initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        })
        .then(r => { if (!r.ok) throw new Error(r.status); })
        .catch(err => alert('Eroare la salvare: ' + err));
      });
    } else {
      shareBtn.style.display = 'none';
    }
  } else {
    // new/private note: open share modal
    shareBtn.addEventListener('click', openShareModal);
  }

  function openShareModal() {
    shareSlugInput.value    = titleDisplay.textContent.replace(/\s+/g, '');
    shareEditableCB.checked = false;
    shareContent.value      = editorInput.value;
    shareTitle.value        = titleInput.value || titleDisplay.textContent;
    shareModal.style.display = 'flex';
  }
  shareCancel.addEventListener('click', () => shareModal.style.display = 'none');
  shareConfirm.addEventListener('click', () => {
    const slug     = shareSlugInput.value.trim();
    const editable = shareEditableCB.checked ? 1 : 0;
    if (!slug) return alert('Trebuie un nume de link.');
    shareModal.style.display = 'none';

    const params = new URLSearchParams({ content: editorInput.value,
                                         title:   titleInput.value||titleDisplay.textContent,
                                         slug, editable });

    fetch('share_note.php', {
      method:  'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body:    params.toString()
    })
    .then(r=>r.json())
    .then(json => {
      if (json.link) {
        prompt('Link-ul tău (copie de aici):', json.link);
        window.location = json.link;
      } else {
        alert('Eroare la share: ' + (json.error||''));
      }
    })
    .catch(()=>alert('Eroare de rețea.'));
  });

  // ───── Friend request response ──────────────────────────────
  function updateBell() {
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
    const accept = e.target.classList.contains('notif-accept');
    const reject = e.target.classList.contains('notif-reject');
    if (!accept && !reject) return;
    const item   = e.target.closest('.notif-item');
    const fr_id  = item.dataset.frId;
    const action = accept ? 'accept' : 'reject';
    fetch('respond_friend_request.php', {
      method:  'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body:    `fr_id=${encodeURIComponent(fr_id)}&action=${action}`
    })
    .then(r=>r.json())
    .then(js => {
      if (!js.success) return alert(js.error||'Eroare');
      if (accept) {
        const av = item.querySelector('.notif-avatar').src;
        const nm = item.querySelector('.notif-text').textContent.match(/@(\w+)/)[1];
        const div = document.createElement('div');
        div.className = 'friend-item';
        div.dataset.userId = js.user_id;
        div.innerHTML = `
          <img src="${av}" class="friend-avatar">
          <span class="friend-name">@${nm}</span>
          <button class="chat-icon btn" data-user="@${nm}" title="Chat">💬</button>`;
        friendsList.appendChild(div);
        attachChatHandler(div.querySelector('.chat-icon'));
      }
      item.remove();
      updateBell();
    })
    .catch(()=>alert('Network error.'));
  });

  // ───── Send friend request ───────────────────────────────────
  addFriendBtn.addEventListener('click', () => {
    const h = prompt('Friend handle (@username):','@');
    if (!h || h[0]!=='@') return alert('Invalid handle');
    fetch('send_friend_request.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:   'handle='+encodeURIComponent(h)
    })
    .then(r=>r.json())
    .then(j=> j.success ? alert('Request sent!') : alert('Error: '+(j.error||'')))
    .catch(()=>alert('Network error.'));
  });

  // ───── Auto-save shared public notes ──────────────────────────
  if (!window.isLogged && window.initialNote && window.initialNote.editable) {
    let db;
    editorInput.addEventListener('input', () => {
      clearTimeout(db);
      db = setTimeout(() => {
        fetch('update_shared_note.php', {
          method: 'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:   `slug=${encodeURIComponent(window.initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        });
      }, 800);
    });
  }

  // ───── Chat pop-up ────────────────────────────────────────────
  function attachChatHandler(btn) {
    btn.addEventListener('click', () => {
      const otherId = btn.closest('.friend-item').dataset.userId;
      chatTitle.textContent        = btn.dataset.user;
      chatTitle.dataset.userId     = otherId;
      chatBody.innerHTML           = '';
      chatPanel.classList.add('open');
      fetch(`load_messages.php?with=${otherId}`)
        .then(r=>r.json())
        .then(msgs=>{
          msgs.forEach(m => {
            const d = document.createElement('div');
            d.className   = m.sender_id == window.myId
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
    if (!text || !toId) return;
    fetch('send_message.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`to=${toId}&content=${encodeURIComponent(text)}`
    })
    .then(r=>r.json())
    .then(js => {
      if (!js.success) return alert(js.error||'Eroare');
      const out = document.createElement('div');
      out.className   = 'chat-message-outgoing';
      out.textContent = text;
      chatBody.appendChild(out);
      chatBody.scrollTop = chatBody.scrollHeight;
      chatInput.value = '';
    })
    .catch(()=>alert('Network error.'));
  });
  chatInput.addEventListener('keydown', e => {
    if (e.key==='Enter' && !e.shiftKey) {
      e.preventDefault();
      chatSend.click();
    }
  });

  // ───── Service Worker ─────────────────────────────────────────
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
      .catch(()=>{/* ignore */});
  }
});
