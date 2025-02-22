// La încărcarea paginii, se încarcă notițele din localStorage (dacă există)
document.addEventListener('DOMContentLoaded', () => {
  const storedNotes = localStorage.getItem('notes');
  if (storedNotes) {
    document.getElementById('notes').value = storedNotes;
  }
});

// La fiecare modificare a textului, se salvează în localStorage
document.getElementById('notes').addEventListener('input', function() {
  localStorage.setItem('notes', this.value);
});

// Legarea evenimentelor pentru butoanele de export/import criptat
document.getElementById('exportBtn').addEventListener('click', exportNotes);
document.getElementById('importBtn').addEventListener('click', importNotes);

// Legarea evenimentelor pentru butoanele de export/import necriptat
document.getElementById('exportPlainBtn').addEventListener('click', exportNotesPlain);
document.getElementById('importPlainBtn').addEventListener('click', importNotesPlain);

/**
 * Funcție de criptare folosind XOR și codificare base64.
 * @param {string} text - Textul care urmează să fie criptat.
 * @param {string} key - Cheia de criptare.
 * @returns {string} - Textul criptat, codificat în base64.
 */
function encrypt(text, key) {
  let result = "";
  for (let i = 0; i < text.length; i++) {
    result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
  }
  return btoa(result);
}

/**
 * Funcție de decriptare ce inversează operația de criptare.
 * @param {string} encryptedText - Textul criptat (în base64).
 * @param {string} key - Cheia de decriptare.
 * @returns {string} - Textul decriptat.
 */
function decrypt(encryptedText, key) {
  try {
    const decoded = atob(encryptedText);
    let result = "";
    for (let i = 0; i < decoded.length; i++) {
      result += String.fromCharCode(decoded.charCodeAt(i) ^ key.charCodeAt(i % key.length));
    }
    return result;
  } catch (error) {
    alert("Cheia de decriptare este incorectă sau fișierul nu este valid!");
    return "";
  }
}

/**
 * Funcția de export criptat: solicită cheia, criptează textul și declanșează descărcarea fișierului.
 */
function exportNotes() {
  const text = document.getElementById('notes').value;
  const key = prompt("Introduceți cheia de criptare pentru export:");
  if (!key) {
    alert("Cheia de criptare este necesară pentru export.");
    return;
  }
  const encryptedText = encrypt(text, key);
  const blob = new Blob([encryptedText], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'notes_encrypted.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

/**
 * Funcția de import criptat: declanșează click-ul pe elementul de tip file dedicat importului criptat.
 */
function importNotes() {
  document.getElementById('fileInput').click();
}

// Evenimentul pentru citirea fișierului importat criptat și decriptarea conținutului
document.getElementById('fileInput').addEventListener('change', function(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const encryptedContent = e.target.result;
      const key = prompt("Introduceți cheia de decriptare pentru import:");
      if (!key) {
        alert("Cheia de decriptare este necesară pentru import.");
        return;
      }
      const decryptedText = decrypt(encryptedContent, key);
      document.getElementById('notes').value = decryptedText;
      localStorage.setItem('notes', decryptedText);
    }
    reader.readAsText(file);
  }
});

/**
 * Funcția de export necriptat: salvează textul din textarea fără a-l cripta.
 */
function exportNotesPlain() {
  const text = document.getElementById('notes').value;
  const blob = new Blob([text], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'notes.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

/**
 * Funcția de import necriptat: declanșează click-ul pe elementul de tip file dedicat importului necriptat.
 */
function importNotesPlain() {
  document.getElementById('fileInputPlain').click();
}

// Evenimentul pentru citirea fișierului importat necriptat și afișarea conținutului
document.getElementById('fileInputPlain').addEventListener('change', function(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('notes').value = e.target.result;
      localStorage.setItem('notes', e.target.result);
    }
    reader.readAsText(file);
  }
});
