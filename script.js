document.getElementById('exportBtn').addEventListener('click', exportNotes);
document.getElementById('importBtn').addEventListener('click', importNotes);
document.getElementById('exportPlainBtn').addEventListener('click', exportNotesPlain);
document.getElementById('importPlainBtn').addEventListener('click', importNotesPlain);

function encrypt(text, key) {
  let result = "";
  for (let i = 0; i < text.length; i++) {
    result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
  }
  return btoa(result);
}

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

function importNotes() {
  document.getElementById('fileInput').click();
}

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
    }
    reader.readAsText(file);
  }
});

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

function importNotesPlain() {
  document.getElementById('fileInputPlain').click();
}

document.getElementById('fileInputPlain').addEventListener('change', function(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('notes').value = e.target.result;
    }
    reader.readAsText(file);
  }
});
