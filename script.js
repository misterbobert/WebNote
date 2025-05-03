document.addEventListener('DOMContentLoaded', () => {
  // … celelalte inițializări …

  // –– FRIEND REQUEST RESPONSE ––
  const notifPanel = document.getElementById('notif-panel');
  notifPanel.addEventListener('click', e => {
    if (!e.target.matches('.notif-accept, .notif-reject')) return;
    const item   = e.target.closest('.notif-item');
    const frId   = item.dataset.frId;
    const action = e.target.matches('.notif-accept')
                   ? 'accept' : 'decline';
    // trimitem form-urlencoded, nu JSON
    fetch('respond_friend_request.php', {
      method: 'POST',
      headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
      body: `fr_id=${frId}&action=${action}`
    })
    .then(r => r.json())
    .then(json => {
      if (!json.success) return alert(json.error||'Eroare');
      item.remove();
      // dacă accept, adăugăm în friends-list:
      if (action==='accept') {
        const fl = document.getElementById('friends-list');
        const div = document.createElement('div');
        div.className = 'friend-item';
        div.dataset.userId = json.user_id;       // PHP poate returna user_id
        div.innerHTML = `
          <img src="${json.avatar}" class="friend-avatar">
          <span class="friend-name">@${json.username}</span>
          <button class="chat-icon btn"
                  data-user="@${json.username}"
                  title="Chat">💬</button>
        `;
        fl.appendChild(div);
        attachChatHandler(div.querySelector('.chat-icon'));
      }
    });
  });

  // –– CHAT HANDLER ––
  function attachChatHandler(btn) {
    btn.addEventListener('click', () => {
      const otherId = btn.closest('.friend-item').dataset.userId;
      const chatWith = document.getElementById('chat-with');
      chatWith.textContent = btn.dataset.user;
      chatWith.dataset.userId  = otherId;
      const body = document.getElementById('chat-body');
      body.innerHTML = '';
      document.getElementById('chat-panel').classList.add('open');
      fetch(`load_messages.php?with=${otherId}`)
        .then(r=>r.json())
        .then(msgs => {
          msgs.forEach(m => {
            const d = document.createElement('div');
            d.textContent = m.content;
            d.className = m.sender_id==window.myId
                        ? 'chat-message-outgoing'
                        : 'chat-message-incoming';
            body.appendChild(d);
          });
          body.scrollTop = body.scrollHeight;
        });
    });
  }
  // atașează la toate din start
  document.querySelectorAll('.chat-icon').forEach(attachChatHandler);

  // –– SEND MESSAGE ––
  document.getElementById('chat-send').addEventListener('click', () => {
    const input   = document.getElementById('chat-input');
    const text    = input.value.trim();
    const chatWith = document.getElementById('chat-with');
    const toId    = chatWith.dataset.userId;
    if (!text || !toId) return;
    fetch('send_message.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`to=${toId}&content=${encodeURIComponent(text)}`
    })
    .then(r=>r.json())
    .then(json=>{
      if (!json.success) return alert(json.error||'Eroare');
      const body = document.getElementById('chat-body');
      const out  = document.createElement('div');
      out.textContent = text;
      out.className   = 'chat-message-outgoing';
      body.appendChild(out);
      body.scrollTop = body.scrollHeight;
      input.value = '';
    });
  });

  // –– CLOSE CHAT ––
  document.getElementById('chat-close-btn')
    .addEventListener('click', ()=>{
      document.getElementById('chat-panel')
              .classList.remove('open');
    });

  // … restul codului (sidebar, localNotes, SW etc.) …
});
btn.addEventListener('click', async () => {
  const userHandle = btn.dataset.user;
  const otherId    = btn.dataset.userId;             // <— aici
  chatTitle.textContent = `Chat cu ${userHandle}`;
  chatTitle.dataset.userId = otherId;                // salvezi receptorul

  const body = chatBody;
  body.innerHTML = '';                               // golești

  //  FETCH a istoricului:
  try {
    const resp = await fetch(`load_messages.php?with=${otherId}`);
    const msgs = await resp.json();
    msgs.forEach(m => {
      const div = document.createElement('div');
      div.className = m.sender_id == window.myId
                    ? 'chat-message-outgoing'
                    : 'chat-message-incoming';
      div.textContent = m.content;
      body.appendChild(div);
    });
    body.scrollTop = body.scrollHeight;
  } catch(e) {
    console.error(e);
    alert('Nu am putut încărca mesajele.');
  }

  chatPanel.classList.add('open');
});
shareConfirm.addEventListener('click', () => {
  const slug     = shareSlugInput.value.trim();
  const editable = shareEditableCB.checked ? 1 : 0;
  if (!slug) return alert('Trebuie un nume de link.');
  shareModal.style.display = 'none';

  const params = new URLSearchParams({
    content: editorInput.value,
    title:   titleInput.value || titleDisplay.textContent,
    slug,
    editable
  });

  fetch('share_note.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/x-www-form-urlencoded' },
    body:   params.toString()
  })
  .then(r => r.json())
  .then(json => {
    if (json.link) {
      // either show and copy:
      prompt('Link-ul tău de share (copie de aici):', json.link);
      // **and** auto-navigate**:
      window.location.href = json.link;
    } else {
      alert('Eroare la share: '+(json.error||''));
    }
  })
  .catch(() => alert('Eroare de rețea.'));
});
