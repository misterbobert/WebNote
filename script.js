// 0) Login button → login.php
const loginBtn = document.getElementById('login-btn');
if (loginBtn) {
  loginBtn.addEventListener('click', () => {
    window.location.href = 'login.php';
  });
}

// 0.1) Manage button → profile.php
const manageBtn = document.getElementById('manage-btn');
if (manageBtn) {
  manageBtn.addEventListener('click', () => {
    window.location.href = 'profile.php';
  });
}

// 1) Toggle sidebar + mută hamburger
const header    = document.querySelector('.header');
const hamburger = document.getElementById('hamburger');
const sidebar   = document.querySelector('.sidebar');

hamburger.addEventListener('click', () => {
  const opening = !sidebar.classList.contains('open');
  sidebar.classList.toggle('open', opening);
  hamburger.classList.toggle('open', opening);
  if (opening) header.appendChild(hamburger) && sidebar.appendChild(hamburger);
  else header.appendChild(hamburger);
});

// 2) Note state
const NOTES_KEY  = 'notes';
let notes        = JSON.parse(localStorage.getItem(NOTES_KEY) || '[]');
let showCount    = 4;
let expanded     = false;

// 3) Notes panel elements
const notesList  = document.getElementById('notes-list');
const toggleBtn  = document.getElementById('toggle-notes');
const toggleLbl  = document.getElementById('toggle-label');
const toggleArr  = document.getElementById('toggle-arrow');
const newNoteBtn = document.getElementById('new-note');

// 4) Render notes
function renderNotes() {
  notesList.innerHTML = '';
  const count = expanded ? notes.length : Math.min(showCount, notes.length);
  for (let i = 0; i < count; i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'panel-btn';
    btn.textContent = notes[i].title || `Note ${i+1}`;
    btn.addEventListener('click', () => {
      window.location.href = `note.html?id=${i}`;
    });
    notesList.appendChild(btn);
  }
  if (notes.length > showCount) {
    toggleBtn.style.display   = 'block';
    toggleLbl.textContent     = expanded ? 'less' : 'more';
    toggleArr.textContent     = expanded ? '∧' : '∨';
  } else {
    toggleBtn.style.display = 'none';
  }
}

// 5) Toggle more/less
toggleBtn.addEventListener('click', () => {
  expanded = !expanded;
  renderNotes();
});

// 6) Stub create
newNoteBtn.addEventListener('click', () => {
  console.log('Create new note – funcționalitate viitoare');
});

// 7) Init
renderNotes();
