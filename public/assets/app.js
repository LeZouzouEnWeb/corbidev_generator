// util: download & clipboard
async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    toast('Copié ✨');
  } catch(e) {
    alert('Impossible de copier: ' + e);
  }
}
function downloadText(filename, content) {
  const blob = new Blob([content], {type:'text/plain'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  a.click();
  URL.revokeObjectURL(a.href);
}
function toast(msg) {
  const el = document.createElement('div');
  el.className = 'toast align-items-center text-bg-dark border-0 show position-fixed bottom-0 end-0 m-3';
  el.role = 'alert';
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button></div>`;
  document.body.appendChild(el);
  setTimeout(()=> el.remove(), 2500);
}

// state of generated artifacts
const store = {
  // key -> { name, content, mime }
  items: new Map(),
};
function upsertItem(key, file) {
  store.items.set(key, file);
  renderCards();
}
function renderCards() {
  const cnRoot = document.getElementById('cards-cn');
  const freeRoot = document.getElementById('cards-free');
  cnRoot.innerHTML = '';
  freeRoot.innerHTML = '';

  const keysOrder = Array.from(store.items.keys());
  for (const key of keysOrder) {
    const file = store.items.get(key);
    const card = document.createElement('div');
    card.className = 'col';
    card.innerHTML = `
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-start mb-2">
            <h2 class="h6 mb-0">${file.title ?? file.name}</h2>
            <span class="ms-auto small-muted">${file.name}</span>
          </div>
          <div class="codebox flex-grow-1" data-key="${key}"></div>
          <div class="mt-2 d-flex justify-content-between align-items-center">
            <div class="item-actions">
              <button class="btn btn-outline-secondary btn-sm" data-action="copy" data-key="${key}">Copier</button>
              <button class="btn btn-outline-primary btn-sm" data-action="download" data-key="${key}">Télécharger</button>
            </div>
            ${file.meta ? `<span class="small-muted">${file.meta}</span>` : ''}
          </div>
        </div>
      </div>
    `;
    const box = card.querySelector('.codebox');
    // chaque réponse est dissociée (pas de "\n" concat forcé) : on affiche tel quel
    box.textContent = file.content;
    card.querySelector('[data-action="copy"]').onclick = () => copyText(file.content);
    card.querySelector('[data-action="download"]').onclick = () => downloadText(file.name, file.content);

    // Routing: éléments dépendants des CN vs indépendants
    if (file.group === 'cn') cnRoot.appendChild(card);
    else freeRoot.appendChild(card);
  }
}

// Generators — locaux
function uuidv4() {
  return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
    (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
  );
}
function randomToken(len=64) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
  let out = '';
  const buf = new Uint8Array(len);
  crypto.getRandomValues(buf);
  for (let i=0;i<len;i++) out += alphabet[buf[i]%alphabet.length];
  return out;
}
async function generateRSA() {
  const key = await crypto.subtle.generateKey(
    { name: 'RSASSA-PKCS1-v1_5', modulusLength: 2048, publicExponent: new Uint8Array([1,0,1]), hash: 'SHA-256' },
    true,
    ['sign','verify']
  );
  const [spki, pkcs8] = await Promise.all([
    crypto.subtle.exportKey('spki', key.publicKey),
    crypto.subtle.exportKey('pkcs8', key.privateKey),
  ]);
  const pubPEM = toPEM(spki, 'PUBLIC KEY');
  const privPEM = toPEM(pkcs8, 'PRIVATE KEY');
  return { pubPEM, privPEM };
}
function toPEM(buf, label) {
  const b64 = btoa(String.fromCharCode(...new Uint8Array(buf)));
  const lines = b64.match(/.{1,64}/g).join('\n');
  return `-----BEGIN ${label}-----\n${lines}\n-----END ${label}-----`;
}

// Server calls (si endpoints fournis)
async function callEndpoint(url, payload) {
  if (!url) throw new Error('Endpoint non défini');
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload||{})
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || 'Réponse invalide');
  return json; // { ok, files:[{name, content, mime}] }
}

// Hook UI
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-gen-uuid').addEventListener('click', () => {
    const val = uuidv4();
    upsertItem('uuid', { name: 'uuid.txt', content: val, mime:'text/plain', group:'free', title:'UUID v4' });
  });
  document.getElementById('btn-gen-token').addEventListener('click', () => {
    const val = randomToken(64);
    upsertItem('token', { name: 'token.txt', content: val, mime:'text/plain', group:'free', title:'Jeton (64)' });
  });
  document.getElementById('btn-gen-keypair').addEventListener('click', async () => {
    const { pubPEM, privPEM } = await generateRSA();
    upsertItem('rsa-private', { name: 'private.key', content: privPEM, mime:'application/x-pem-file', group:'free', title:'Clé privée RSA' });
    upsertItem('rsa-public',  { name: 'public.key',  content: pubPEM,  mime:'application/x-pem-file', group:'free', title:'Clé publique RSA' });
  });

  const cnServer = document.getElementById('cn-server');
  const cnClient = document.getElementById('cn-client');

  document.getElementById('btn-gen-ca').addEventListener('click', async () => {
    const cnS = cnServer.value.trim() || null;
    const cnC = cnClient.value.trim() || null;
    try {
      const data = await callEndpoint(window.ENDPOINTS.ca, { cnServer: cnS, cnClient: cnC });
      // on ajoute chaque fichier en carte indépendante
      for (const f of data.files) {
        upsertItem('ca-' + f.name, { name: f.name, content: f.content, mime: f.mime || 'text/plain', group:'cn', title: 'CA — ' + f.name });
      }
      toast('CA générée');
    } catch(e) {
      alert('Erreur CA: ' + e.message);
    }
  });

  document.getElementById('btn-gen-server').addEventListener('click', async () => {
    const cnS = cnServer.value.trim();
    if (!cnS) { alert('Le nom serveur est requis'); return; }
    try {
      const data = await callEndpoint(window.ENDPOINTS.serverCert, { cnServer: cnS });
      for (const f of data.files) {
        upsertItem('server-' + f.name, { name: f.name, content: f.content, mime: f.mime || 'text/plain', group:'cn', title: 'Serveur — ' + f.name });
      }
      toast('Certificat serveur généré');
    } catch(e) {
      alert('Erreur certificat serveur: ' + e.message);
    }
  });

  document.getElementById('btn-gen-client').addEventListener('click', async () => {
    const cnC = cnClient.value.trim();
    if (!cnC) { alert('Le nom client est requis'); return; }
    try {
      const data = await callEndpoint(window.ENDPOINTS.clientCert, { cnClient: cnC });
      for (const f of data.files) {
        upsertItem('client-' + f.name, { name: f.name, content: f.content, mime: f.mime || 'text/plain', group:'cn', title: 'Client — ' + f.name });
      }
      toast('Certificat client généré');
    } catch(e) {
      alert('Erreur certificat client: ' + e.message);
    }
  });

  document.getElementById('btn-download-all').addEventListener('click', async () => {
    if (store.items.size === 0) { alert('Rien à inclure dans le ZIP.'); return; }
    const files = Array.from(store.items.values()).map(f => ({ name: f.name, content: f.content }));
    try {
      const res = await fetch('zip.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ files })
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const blob = await res.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'gen-data.zip';
      a.click();
      URL.revokeObjectURL(a.href);
    } catch(e) {
      alert('Erreur ZIP: ' + e.message);
    }
  });
});
