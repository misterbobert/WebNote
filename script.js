// script.js

document.addEventListener('DOMContentLoaded', () => {
  const sidebar      = document.querySelector('.sidebar');
  const hamburger    = document.getElementById('hamburger');
  const newBtn       = document.getElementById('new-note');
  const editorIn     = document.querySelector('.editor-input');
  const titleDisp    = document.getElementById('note-title-display');
  const titleIn      = document.getElementById('note-title-input');
  const idIn         = document.getElementById('note-id');
  const saveLocalBtn = document.querySelector('.save-local-btn');
  const notesList    = document.getElementById('notes-list');

  const LOCAL_KEY    = 'localNotes';
  let localNotes     = JSON.parse(localStorage.getItem(LOCAL_KEY)) || [];
  let currentLocalId = null;

  // 1) Rendează notițele din localStorage
  function renderLocalNotes() {
    document.querySelectorAll('.local-note-btn').forEach(b => b.remove());
    localNotes.forEach(item => {
      const btn = document.createElement('button');
      btn.type        = 'button';
      btn.className   = 'panel-btn local-note-btn';
      btn.textContent = item.title;
      btn.dataset.lid = item.id;
      notesList.appendChild(btn);
      btn.addEventListener('click', () => {
        editorIn.value        = item.content;
        titleDisp.textContent = item.title;
        titleIn.value         = item.title;
        currentLocalId        = item.id;
      });
    });
  }

  // 2) Initializează starea sidebar din localStorage
  const wasOpen = localStorage.getItem('sidebarOpen') === 'true';
  if (wasOpen) {
    sidebar.classList.add('open');
    hamburger.classList.add('open');
  } else {
    sidebar.classList.remove('open');
    hamburger.classList.remove('open');
  }

  // 3) Toggle sidebar + salvează starea
  hamburger.addEventListener('click', () => {
    const isOpen = sidebar.classList.toggle('open');
    hamburger.classList.toggle('open', isOpen);
    localStorage.setItem('sidebarOpen', isOpen);
  });

  // 4) “Create new note” → resetează editorul
  newBtn.addEventListener('click', () => {
    editorIn.value        = '';
    titleDisp.textContent = 'Untitled note';
    titleIn.value         = '';
    currentLocalId        = null;
  });

  // 5) Save to LocalStorage (adaugă sau actualizează)
  saveLocalBtn.addEventListener('click', () => {
    let t = titleIn.value.trim() || prompt('Enter note title:', titleDisp.textContent) || '';
    if (!t) return;
    titleIn.value         = t;
    titleDisp.textContent = t;
    const content = editorIn.value;

    if (currentLocalId) {
      const idx = localNotes.findIndex(n => n.id === currentLocalId);
      if (idx > -1) {
        localNotes[idx].title   = t;
        localNotes[idx].content = content;
      }
    } else {
      const id = Date.now().toString();
      localNotes.push({ id, title: t, content });
      currentLocalId = id;
    }

    localStorage.setItem(LOCAL_KEY, JSON.stringify(localNotes));
    renderLocalNotes();
  });

  // 6) Init
  renderLocalNotes();
});
document.addEventListener('DOMContentLoaded', () => {
  const sidebar   = document.querySelector('.sidebar');
  const hamburger = document.getElementById('hamburger');

  // Restore sidebar state from localStorage:
  const wasOpen = localStorage.getItem('sidebarOpen') === 'true';
  sidebar.classList.toggle('open',   wasOpen);
  sidebar.classList.toggle('collapsed', !wasOpen);
  hamburger.classList.toggle('open', wasOpen);

  // Toggle on click:
  hamburger.addEventListener('click', () => {
    const isOpen = !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    sidebar.classList.toggle('collapsed', !isOpen);
    hamburger.classList.toggle('open', isOpen);
    localStorage.setItem('sidebarOpen', isOpen);
  });
});
