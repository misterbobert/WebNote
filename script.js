document.addEventListener('DOMContentLoaded', () => {
  // ───────── grab all our elements ─────────
  const sidebar       = document.querySelector('.sidebar');
  const ham           = document.getElementById('hamburger');
  const newNoteBtn    = document.getElementById('new-note');const editorInput = document.getElementById('hidden-content'); 

  const titleDisplay  = document.getElementById('note-title-display');
  const titleInput    = document.getElementById('note-title-input');
  const slugInput     = document.getElementById('note-slug');
  const idInput       = document.getElementById('note-id');
  const saveBtn       = document.querySelector('.save-note-btn');
  const saveLocalBtn  = document.querySelector('.save-local-btn');
  const shareBtn      = document.getElementById('share-btn');
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

  // share modal
  const shareModal    = document.getElementById('share-modal');
  const shareSlug     = document.getElementById('share-slug');
  const shareContent  = document.getElementById('share-content');
  const shareTitle    = document.getElementById('share-title');
  const shareEditable = document.getElementById('share-editable');
  const shareCancel   = document.getElementById('share-cancel');
  const shareConfirm  = document.getElementById('share-confirm');

  // save-to-account modal
  const saModal       = document.getElementById('save-account-modal');
  const saInput       = document.getElementById('save-account-title');
  const saCancel      = document.getElementById('save-account-cancel');
  const saConfirm     = document.getElementById('save-account-confirm');

  // save-to-local modal
  const slModal       = document.getElementById('save-local-modal');
  const slInput       = document.getElementById('save-local-title');
  const slCancel      = document.getElementById('save-local-cancel');
  const slConfirm     = document.getElementById('save-local-confirm');

  // add-friend modal (ensure you have this markup)
  const afModal       = document.getElementById('add-friend-modal');
  const afInput       = document.getElementById('add-friend-input');
  const afCancel      = document.getElementById('add-friend-cancel');
  const afConfirm     = document.getElementById('add-friend-confirm');

  let currentChatUserId = null;

  // ───────── inject loaded note ─────────
  if (window.initialNote) {
    editorInput.value        = initialNote.full;
    titleDisplay.textContent = initialNote.title;
    titleInput.value         = initialNote.title;
    slugInput.value          = initialNote.slug;
    idInput.value            = initialNote.id || '';
  }

  // ───────── build a lookup map for noteData ─────────
  const noteMap = {};
  noteData.forEach(n => noteMap[n.id] = n);

  // ───────── sidebar toggle ─────────
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

  // ───────── new note ─────────
  newNoteBtn.addEventListener('click', () => {
    editorInput.value        = '';
    titleDisplay.textContent = 'Untitled note';
    titleInput.value         = '';
    slugInput.value          = '';
    idInput.value            = '';
  });

  // ───────── load existing notes ─────────
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

  // ───────── auto-save draft ─────────
 // ───────── auto-save draft ─────────
// ───────── localStorage notes ─────────
function loadLocalNotes() {
  document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
  const arr = JSON.parse(localStorage.getItem('localNotes') || '[]');

  arr.forEach(item => {
    const b = document.createElement('button');
    b.type = 'button'; 
    b.className = 'panel-btn local-note-btn';
    b.textContent = item.title; 
    b.dataset.lid = item.id;
    notesList.appendChild(b);

    b.addEventListener('click', () => {
      editorInput.value = item.content;
      titleDisplay.textContent = item.title;
      titleInput.value = item.title;
      slugInput.value = '';
      idInput.value = item.id;
      
      // Șterge draft-ul când se deschide o notiță existentă
      localStorage.removeItem('draftContent');
      localStorage.removeItem('draftTitle');
    });
  });
}

  // ───────── localStorage notes ─────────
  function loadLocalNotes() {
    document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
    const arr = JSON.parse(localStorage.getItem('localNotes') || '[]');
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

  // ───────── save to localStorage modal ─────────
 

  // ───────── save to account modal ─────────
  saveBtn.addEventListener('click', () => {
    if (!isLogged) return window.location='login.php';
    saInput.value = titleDisplay.textContent==='Untitled note'?'':titleDisplay.textContent;
    saModal.style.display='flex';
  });
  saCancel.addEventListener('click', () => saModal.style.display='none');
  saConfirm.addEventListener('click', () => {
    const t = saInput.value.trim();
    if (!t) return alert('Trebuie un titlu.');
    titleInput.value = t;
    titleDisplay.textContent = t;
    saModal.style.display='none';
    document.getElementById('editor-form').submit();
  });

  // ───────── share/save toggle ─────────
  function openShareModal() {
    shareSlug.value       = titleDisplay.textContent.replace(/\s+/g,'');
    shareEditable.checked = false;
    shareContent.value    = editorInput.value;
    shareTitle.value      = titleInput.value||titleDisplay.textContent;
    shareModal.style.display='flex';
  }
  if (initialNote && initialNote.slug) {
    if (initialNote.editable) {
      shareBtn.textContent='Save';
      shareBtn.addEventListener('click', () => {
        fetch('update_shared_note.php',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`slug=${encodeURIComponent(initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        }).then(r=>{ if(!r.ok)throw Error(r.statusText) })
          .catch(e=>alert('Eroare la salvare: '+e));
      });
    } else {
      shareBtn.style.display='none';
    }
  } else {
    if (shareBtn) {
  shareBtn.addEventListener('click', openShareModal);
}
  }
  shareCancel.addEventListener('click', () => shareModal.style.display='none');
  shareConfirm.addEventListener('click', () => {
    const slug = shareSlug.value.trim();
    const ed   = shareEditable.checked?1:0;
    if (!slug) return alert('Trebuie un nume de link.');
    shareModal.style.display='none';
    const params = new URLSearchParams({
      content: editorInput.value,
      title:   titleInput.value||titleDisplay.textContent,
      slug, editable: ed
    });
    fetch('share_note.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: params.toString()
    }).then(r=>r.json())
      .then(j=>{
        if (j.link) {
          prompt('Copy link:', j.link);
          window.location.href = j.link;
        } else alert('Eroare la share: '+(j.error||''));
      })
      .catch(()=>alert('Network error.'));
  });

  // ───────── friend‐request dropdown ─────────
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
    if (!e.target.matches('.notif-accept, .notif-reject')) return;
    const item   = e.target.closest('.notif-item');
    const action = e.target.matches('.notif-accept')?'accept':'reject';
    const fr_id  = item.dataset.frId;
    fetch('respond_friend_request.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`fr_id=${encodeURIComponent(fr_id)}&action=${action}`
    }).then(r=>r.json())
      .then(j=>{
        if (!j.success) return alert(j.error||'Eroare');
        if (action==='accept') {
          const div = document.createElement('div');
          div.className='friend-item';
          div.dataset.userId = j.user_id;
          div.innerHTML = `
            <img src="${j.avatar}" class="friend-avatar">
            <span class="friend-name">@${j.username}</span>
            <button class="chat-icon btn" data-user="@${j.username}">💬</button>`;
          friendsList.appendChild(div);
          attachChatHandler(div.querySelector('.chat-icon'));
        }
        item.remove();
        updateBell();
      }).catch(()=>alert('Network error.'));
  });

  // ───────── add‐friend modal ─────────
  addFriendBtn.addEventListener('click', () => {
    if (addFriendBtn) {
      addFriendBtn.addEventListener('click', () => {
        const handle = prompt('Friend handle (@username):','@');
        if (!handle || handle[0] !== '@') {
          return alert('Invalid handle. Trebuie să înceapă cu @');
        }
        // trimiți cererea
        fetch('send_friend_request.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: `handle=${encodeURIComponent(handle)}`
        })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            alert('Request sent!');
          } else {
            alert('Error: ' + (json.error||''));
          }
        })
        .catch(() => alert('Network error.'));
      });
    }
    
  });
 
  // ───────── chat pop-up ─────────
  function attachChatHandler(btn) {
    btn.addEventListener('click', () => {
      const uid = btn.closest('.friend-item').dataset.userId;
      currentChatUserId = uid;
      chatTitle.textContent   = btn.dataset.user;
      chatTitle.dataset.userId= uid;
      chatBody.innerHTML      = '';
      chatPanel.classList.add('open');
      fetch(`load_messages.php?with=${uid}`)
        .then(r=>r.json())
        .then(msgs=>{
          msgs.forEach(m=>{
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
    fetch('send_message.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`to=${encodeURIComponent(toId)}&content=${encodeURIComponent(text)}`
    }).then(r=>r.json())
      .then(j=>{
        if (!j.success) return alert(j.error||'Eroare');
        const d = document.createElement('div');
        d.className='chat-message-outgoing';
        d.textContent = text;
        chatBody.appendChild(d);
        chatBody.scrollTop = chatBody.scrollHeight;
        chatInput.value = '';
      }).catch(()=>alert('Network error.'));
  });
  if (chatInput) {
    chatInput.addEventListener('keydown', e => {
      if (e.key==='Enter' && !e.shiftKey) {
        e.preventDefault();
        chatSend.click();
      }
    });
  }
  

  // ───────── live‐save shared note ─────────
  if (!isLogged && initialNote && initialNote.editable) {
    let db;
    editorInput.addEventListener('input', () => {
      clearTimeout(db);
      db = setTimeout(() => {
        fetch('update_shared_note.php',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`slug=${encodeURIComponent(initialNote.slug)}&content=${encodeURIComponent(editorInput.value)}`
        });
      },800);
    });
  }

  // ───────── register service worker ─────────
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
      .catch(()=>{/*ignore*/});
  }
});// ─── Toggling Notifications Panel ────────────────────────────
const notifBtn = document.getElementById('notif-btn');
const notifPanel = document.getElementById('notif-panel');

if (notifBtn && notifPanel) {
  notifBtn.addEventListener('click', () => {
    notifPanel.style.display = notifPanel.style.display === 'block' ? 'none' : 'block';
  });
}

// ─── Chat Open / Close ────────────────────────────────────────
// Deschizi chat-ul când apeși pe oricare buton .chat-icon
document.querySelectorAll('.chat-icon').forEach(btn => {
  btn.addEventListener('click', () => {
    const userItem = btn.closest('.friend-item');
    const otherId  = userItem.dataset.userId;
    const userHandle = btn.dataset.user;
    // Populezi titlul și data-userId
    const chatWith = document.getElementById('chat-with');
    chatWith.textContent     = userHandle;
    chatWith.dataset.userId  = otherId;
    // Golești și arăți corpul
    const body = document.getElementById('chat-body');
    body.innerHTML = '';
    document.getElementById('chat-panel').classList.add('open');
    // Aici poți relua fetch-ul de mesaje
    fetch(`load_messages.php?with=${otherId}`)
      .then(r => r.json())
      .then(msgs => {
        msgs.forEach(m => {
          const d = document.createElement('div');
          d.className = m.sender_id == window.myId
                      ? 'chat-message-outgoing'
                      : 'chat-message-incoming';
          d.textContent = m.content;
          body.appendChild(d);
        });
        body.scrollTop = body.scrollHeight;
      });
  });
});
// Închizi chat-ul
document.getElementById('chat-close-btn').addEventListener('click', () => {
  document.getElementById('chat-panel').classList.remove('open');
});

// ─── Add Friend Button ────────────────────────────────────────
document.getElementById('add-friend-btn').addEventListener('click', () => {
  const handle = prompt('Friend handle (@username):','@');
  if (!handle || handle[0] !== '@') {
    return alert('Invalid handle. Trebuie să înceapă cu @');
  }
  // trimiți cererea
  fetch('send_friend_request.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `handle=${encodeURIComponent(handle)}`
  })
  .then(r => r.json())
  .then(json => {
    if (json.success) {
      alert('Request sent!');
    } else {
      alert('Error: ' + (json.error||''));
    }
  })
  .catch(() => alert('Network error.'));
});
