<?php
// index.php — pro-plus7: FIX JS order (Bootstrap loaded BEFORE custom), replace btn-group by flex-wrap containers,
// stronger tooltip init, and responsive button containers to prevent overflow. 
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<title>CertKit — Générateur Certificats & Clés</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { padding: 30px; background-color: var(--bs-body-bg); }
.card { margin-bottom: 16px; }
textarea { width: 100%; height: 150px; margin-bottom: 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace; }
h1 { margin-bottom: 20px; }
.section h2 { font-size: 1.1rem; display:flex; align-items:center; gap:.5rem; margin:0; }
.section .card-header { color: #fff; }
.header-ca { background: #0d6efd; }       /* primary */
.header-server { background: #198754; }   /* success */
.header-client { background: #6f42c1; }   /* purple */
.header-other { background: #495057; }    /* secondary */
.badge-req { margin-left: .5rem; }
.small-muted { font-size: .9rem; color: var(--bs-secondary); }
.tooltip-inner { max-width: 300px; text-align: left; }
.actions { display:flex; flex-wrap: wrap; gap:.5rem; }
.row-title { display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap:.5rem; }
</style>

<!-- Load Bootstrap JS bundle FIRST -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="text-primary">🔐 CertKit — certificats, clés et jetons</h1>
    <div class="actions">
      <button id="themeBtn" class="btn btn-outline-secondary btn-sm" type="button">🌓 Thème</button>
      <button id="zipOnlyBtn" class="btn btn-warning btn-sm" type="button">📦 Générer & Télécharger ZIP</button>
      <a id="zipBtn" class="btn btn-success btn-sm disabled" href="#" role="button" aria-disabled="true">📦 Télécharger tout (ZIP)</a>
    </div>
  </div>

  <form id="certForm" class="mb-4">
    <!-- CA SECTION -->
    <div class="card section">
      <div class="card-header header-ca d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <input class="form-check-input" type="checkbox" name="generate_option[]" value="ca" id="opt_ca" checked>
          <h2>📜 CA (Autorité de Certification)</h2>
        </div>
        <span class="small">Utilisée pour signer Serveur/Client</span>
      </div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">Algorithme CA <span data-bs-toggle="tooltip" title="RSA: compatible partout, tailles 2048–4096. EC: clés plus courtes, perfs supérieures.">❔</span></label>
          <select name="ca_key_alg" id="ca_key_alg" class="form-select">
            <option value="RSA" selected>RSA</option>
            <option value="EC">EC (Elliptic Curve)</option>
          </select>
        </div>
        <div class="col-md-4" id="ca_rsa_opts">
          <label class="form-label">Taille RSA CA <span data-bs-toggle="tooltip" title="2048: standard. 3072: sécurité renforcée. 4096: plus lourd mais plus robuste.">❔</span></label>
          <select name="ca_rsa_bits" class="form-select">
            <option value="2048" selected>2048</option>
            <option value="3072">3072</option>
            <option value="4096">4096</option>
          </select>
        </div>
        <div class="col-md-4 d-none" id="ca_ec_opts">
          <label class="form-label">Courbe EC CA <span data-bs-toggle="tooltip" title="prime256v1 (P-256): très courant. secp384r1: plus robuste. secp521r1: très robuste, plus lourd.">❔</span></label>
          <select name="ca_ec_curve" class="form-select">
            <option value="prime256v1" selected>prime256v1 (P-256)</option>
            <option value="secp384r1">secp384r1 (P-384)</option>
            <option value="secp521r1">secp521r1 (P-521)</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Validité CA (jours)</label>
          <input type="number" class="form-control" name="days_ca" value="3650" min="1">
        </div>
      </div>
    </div>

    <!-- SERVER SECTION -->
    <div class="card section">
      <div class="card-header header-server d-flex align-items-center gap-2">
        <input class="form-check-input me-1 need-cn-toggle" data-target="server_cn" data-badge="server_badge" data-sandep="server_san" type="checkbox" name="generate_option[]" value="server" id="opt_server">
        <h2>🌐 Serveur</h2>
      </div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Nom serveur (CN)</label>
          <div class="input-group">
            <input type="text" name="server_cn" id="server_cn" placeholder="localhost" class="form-control" disabled>
            <span id="server_badge" class="input-group-text d-none text-bg-warning">Requis</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">SAN (DNS/IP)</label>
          <input type="text" name="server_san" class="form-control" placeholder="DNS:localhost,IP:127.0.0.1" id="server_san" disabled>
          <div class="form-text">Entrées séparées par virgules : <code>DNS:nom</code>, <code>IP:adresse</code></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Algorithme Serveur <span data-bs-toggle="tooltip" title="RSA: standard. EC: plus rapide, clés plus courtes.">❔</span></label>
          <select name="srv_key_alg" id="srv_key_alg" class="form-select">
            <option value="RSA" selected>RSA</option>
            <option value="EC">EC (Elliptic Curve)</option>
          </select>
        </div>
        <div class="col-md-4" id="srv_rsa_opts">
          <label class="form-label">Taille RSA Serveur</label>
          <select name="srv_rsa_bits" class="form-select">
            <option value="2048" selected>2048</option>
            <option value="3072">3072</option>
            <option value="4096">4096</option>
          </select>
        </div>
        <div class="col-md-4 d-none" id="srv_ec_opts">
          <label class="form-label">Courbe EC Serveur</label>
          <select name="srv_ec_curve" class="form-select">
            <option value="prime256v1" selected>prime256v1 (P-256)</option>
            <option value="secp384r1">secp384r1 (P-384)</option>
            <option value="secp521r1">secp521r1 (P-521)</option>
          </select>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_p12_server" value="1" id="opt_p12_srv">
            <label class="form-check-label" for="opt_p12_srv">Exporter PKCS#12 (.p12) Serveur</label>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Mot de passe .p12 Serveur</label>
          <input type="password" class="form-control" name="p12_password_server" placeholder="">
        </div>
        <div class="col-md-4">
          <label class="form-label">Validité Serveur (jours)</label>
          <input type="number" class="form-control" name="days_server" value="825" min="1">
        </div>
      </div>
    </div>

    <!-- CLIENT SECTION -->
    <div class="card section">
      <div class="card-header header-client d-flex align-items-center gap-2">
        <input class="form-check-input me-1 need-cn-toggle" data-target="client_cn" data-badge="client_badge" type="checkbox" name="generate_option[]" value="client" id="opt_client">
        <h2>👤 Client</h2>
      </div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Nom client (CN)</label>
          <div class="input-group">
            <input type="text" name="client_cn" id="client_cn" placeholder="client" class="form-control" disabled>
            <span id="client_badge" class="input-group-text d-none text-bg-warning">Requis</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Algorithme Client</label>
          <select name="cli_key_alg" id="cli_key_alg" class="form-select">
            <option value="RSA" selected>RSA</option>
            <option value="EC">EC (Elliptic Curve)</option>
          </select>
        </div>
        <div class="col-md-6" id="cli_rsa_opts">
          <label class="form-label">Taille RSA Client</label>
          <select name="cli_rsa_bits" class="form-select">
            <option value="2048" selected>2048</option>
            <option value="3072">3072</option>
            <option value="4096">4096</option>
          </select>
        </div>
        <div class="col-md-6 d-none" id="cli_ec_opts">
          <label class="form-label">Courbe EC Client</label>
          <select name="cli_ec_curve" class="form-select">
            <option value="prime256v1" selected>prime256v1 (P-256)</option>
            <option value="secp384r1">secp384r1 (P-384)</option>
            <option value="secp521r1">secp521r1 (P-521)</option>
          </select>
        </div>
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_p12_client" value="1" id="opt_p12_cli">
            <label class="form-check-label" for="opt_p12_cli">Exporter PKCS#12 (.p12) Client</label>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Mot de passe .p12 Client</label>
          <input type="password" class="form-control" name="p12_password_client" placeholder="">
        </div>
        <div class="col-md-4">
          <label class="form-label">Validité Client (jours)</label>
          <input type="number" class="form-control" name="days_client" value="825" min="1">
        </div>
      </div>
    </div>

    <!-- OTHER SECTION -->
    <div class="card section">
      <div class="card-header header-other d-flex align-items-center justify-content-between">
        <h2>⚙️ Autres</h2>
        <span class="small">Indépendants des CN</span>
      </div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="generate_option[]" value="keypair" id="opt_keypair">
            <label class="form-check-label" for="opt_keypair">Paire de clés (RSA/EC)</label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="generate_option[]" value="token_uuid" id="opt_token" checked>
            <label class="form-check-label" for="opt_token">Jeton & UUID</label>
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Longueur jeton</label>
              <input type="number" class="form-control" name="token_length" value="32" min="8" max="256">
            </div>
            <div class="col-md-8">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="token_include_special" value="1" id="token_special">
                <label class="form-check-label" for="token_special">Inclure caractères spéciaux</label>
              </div>
              <div class="form-text">Autorisés: <code>!@#$%^&*()-_=+[]{};:,.?/</code></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
      <button id="generateBtn" type="submit" class="btn btn-primary">🚀 Générer (afficher résultats)</button>
    </div>
  </form>

  <div id="alerts"></div>

  <div id="summary" class="card d-none">
    <div class="card-header">🧾 Récapitulatif</div>
    <div class="card-body" id="summaryBody"></div>
  </div>

  <div id="result"></div>
</div>

<script>
const alerts = document.getElementById('alerts');
const zipBtn = document.getElementById('zipBtn');
const zipOnlyBtn = document.getElementById('zipOnlyBtn');
function showAlert(type, msg){
  alerts.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
    ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>`;
}
document.getElementById('themeBtn').addEventListener('click', () => {
  const html = document.documentElement;
  html.setAttribute('data-bs-theme', html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
});

// Tooltips guarded
function initTooltips(){
  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }
}
document.addEventListener('DOMContentLoaded', initTooltips);
window.addEventListener('load', initTooltips);

function toggleAlgSections(prefix) {
  const sel = document.getElementById(prefix + '_key_alg');
  const rsa = document.getElementById(prefix + '_rsa_opts');
  const ec  = document.getElementById(prefix + '_ec_opts');
  if (!sel || !rsa || !ec) return;
  if (sel.value === 'EC') { ec.classList.remove('d-none'); rsa.classList.add('d-none'); }
  else { rsa.classList.remove('d-none'); ec.classList.add('d-none'); }
}

['ca','srv','cli'].forEach(prefix => {
  const el = document.getElementById(prefix + '_key_alg');
  if (el) {
    el.addEventListener('change', () => toggleAlgSections(prefix));
    toggleAlgSections(prefix);
  }
});

function updateCnState() {
  const srvChecked = document.getElementById('opt_server').checked;
  const cliChecked = document.getElementById('opt_client').checked;
  const srvInput = document.getElementById('server_cn');
  const cliInput = document.getElementById('client_cn');
  const srvBadge = document.getElementById('server_badge');
  const cliBadge = document.getElementById('client_badge');
  const san = document.getElementById('server_san');

  srvInput.disabled = !srvChecked;
  san.disabled = !srvChecked;
  cliInput.disabled = !cliChecked;

  srvBadge.classList.toggle('d-none', !(srvChecked && srvInput.value.trim()===''));
  cliBadge.classList.toggle('d-none', !(cliChecked && cliInput.value.trim()===''));
}
['opt_server','opt_client','server_cn','client_cn'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', updateCnState);
  if (el) el.addEventListener('change', updateCnState);
});
updateCnState();

async function copyText(text) {
  try { await navigator.clipboard.writeText(text); showAlert('success', 'Copié'); }
  catch(e){ showAlert('warning', 'Impossible de copier automatiquement'); }
}
function downloadRawFile(path, name) {
  const url = 'download_raw.php?file=' + encodeURIComponent(path) + '&name=' + encodeURIComponent(name);
  window.open(url, '_blank');
}
function sectionCard(id, title, innerHtml, copyAllText = '') {
  const copyBtn = copyAllText ? `<button class="btn btn-outline-secondary btn-sm" data-copyall="${id}">📋 Tout copier</button>` : '';
  return `<div class="section card"><div class="card-body" id="${id}">
    <div class="row-title mb-2">
      <h2 class="m-0">${title}</h2>
      <div class="actions">${copyBtn}</div>
    </div>
    ${innerHtml}
  </div></div>`;
}
function textarea(label, value, filename, path) {
  const id = 'ta_' + Math.random().toString(36).slice(2);
  const dl = path ? `<button class="btn btn-outline-success btn-sm" data-dl="${path}" data-name="${filename}">⬇️ Télécharger</button>` : '';
  const copyBtn = value ? `<button class="btn btn-outline-secondary btn-sm" data-copy="#${id}">📋 Copier</button>` : '';
  return `<div class="mb-3">
    <div class="row-title">
      <label class="form-label me-2">${label}</label>
      <div class="actions">${copyBtn}${dl}</div>
    </div>
    <textarea id="${id}" readonly class="form-control">${value || ''}</textarea>
  </div>`;
}

let lastFilesMap = {};
document.getElementById('result').addEventListener('click', (ev) => {
  const t = ev.target;
  if (t.dataset.copy) {
    const ta = document.querySelector(t.dataset.copy);
    if (ta) copyText(ta.value);
  }
  if (t.dataset.copyall) {
    const sec = document.getElementById(t.dataset.copyall);
    if (sec) {
      const texts = Array.from(sec.querySelectorAll('textarea')).map(e => e.value).join('\n\n');
      copyText(texts);
    }
  }
  if (t.dataset.dl) {
    downloadRawFile(t.dataset.dl, t.dataset.name || 'download.txt');
  }
});

function buildSummary(formData) {
  const has = (v) => formData.getAll('generate_option[]').includes(v);
  const fmtAlg = (alg, bits, curve) => alg === 'RSA' ? `RSA-${bits}` : `EC ${curve}`;
  let html = '<ul class="mb-0">';
  if (has('ca')) {
    html += `<li><strong>CA:</strong> ${fmtAlg(formData.get('ca_key_alg'), formData.get('ca_rsa_bits'), formData.get('ca_ec_curve'))}, ${formData.get('days_ca')} jours</li>`;
  }
  if (has('server')) {
    html += `<li><strong>Serveur:</strong> CN=${formData.get('server_cn') || '(non défini)'}; Algo ${fmtAlg(formData.get('srv_key_alg'), formData.get('srv_rsa_bits'), formData.get('srv_ec_curve'))}, ${formData.get('days_server')} jours</li>`;
  }
  if (has('client')) {
    html += `<li><strong>Client:</strong> CN=${formData.get('client_cn') || '(non défini)'}; Algo ${fmtAlg(formData.get('cli_key_alg'), formData.get('cli_rsa_bits'), formData.get('cli_ec_curve'))}, ${formData.get('days_client')} jours</li>`;
  }
  if (has('token_uuid')) {
    html += `<li><strong>Jeton:</strong> longueur=${formData.get('token_length') || 32}, spéciaux=${formData.get('token_include_special') ? 'oui' : 'non'}</li>`;
  }
  html += '</ul>';
  const summary = document.getElementById('summary');
  const body = document.getElementById('summaryBody');
  body.innerHTML = html;
  summary.classList.remove('d-none');
}

async function submitAndRender(formData, {zipOnly=false} = {}) {
  try {
    const resp = await fetch('cert_generator.php', {method:'POST', body:formData});
    if (!resp.ok) {
      const txt = await resp.text();
      throw new Error('HTTP ' + resp.status + (txt ? ' — ' + txt : ''));
    }
    const data = await resp.json();
    lastFilesMap = data.files || {};
    if (data.zip_path) {
      zipBtn.classList.remove('disabled');
      zipBtn.href = 'download.php?file=' + encodeURIComponent(data.zip_path);
      zipBtn.setAttribute('aria-disabled','false');
    } else {
      zipBtn.classList.add('disabled');
      zipBtn.href = '#';
      zipBtn.setAttribute('aria-disabled','true');
    }
    if (zipOnly && data.zip_path) {
      window.location = 'download.php?file=' + encodeURIComponent(data.zip_path);
      return;
    }
    const resultNode = document.getElementById('result');
    const options = new Set(formData.getAll('generate_option[]'));
    const res = [];

    if (options.has('ca') && data.ca_cert) {
      let inner = '';
      inner += textarea('ca_cert.pem', data.ca_cert, 'ca_cert.pem', lastFilesMap['ca_cert.pem']);
      inner += textarea('ca_key.pem', data.ca_key || '', 'ca_key.pem', lastFilesMap['ca_key.pem']);
      if (data.ca_fingerprint) inner += `<div>Empreinte SHA-256 : <code>${data.ca_fingerprint}</code></div>`;
      if (data.ca_valid_from && data.ca_valid_to) inner += `<div class="text-muted">Validité: ${data.ca_valid_from} → ${data.ca_valid_to}</div>`;
      res.push(sectionCard('sec_ca', '📜 CA', inner, (data.ca_cert||'') + '\n' + (data.ca_key||'')));
    }

    if (options.has('server') && data.server_cert) {
      let inner = '';
      inner += textarea(data.server_filename_cert || 'server_cert.pem', data.server_cert, data.server_filename_cert || 'server_cert.pem', lastFilesMap[data.server_filename_cert || 'server_cert.pem']);
      inner += textarea(data.server_filename_key  || 'server_key.pem',  data.server_key || '', data.server_filename_key || 'server_key.pem', lastFilesMap[data.server_filename_key || 'server_key.pem']);
      if (data.server_p12_b64) {
        const b64Id = 'b64_' + Math.random().toString(36).slice(2);
        const binPath = data.server_filename_p12 ? lastFilesMap[data.server_filename_p12] : null;
        const b64Path = data.server_filename_p12 ? lastFilesMap[data.server_filename_p12 + '.b64.txt'] : null;
        inner += `<div class="mb-3">
          <div class="row-title">
            <label class="form-label me-2">${data.server_filename_p12 || 'server.p12'} (Base64)</label>
            <div class="actions">
              <button class="btn btn-outline-secondary btn-sm" data-copy="#${b64Id}">📋 Copier</button>
              ${binPath ? `<button class="btn btn-outline-success btn-sm" data-dl="${binPath}" data-name="${data.server_filename_p12 || 'server.p12'}">⬇️ .p12 (binaire)</button>` : ''}
              ${b64Path ? `<button class="btn btn-outline-success btn-sm" data-dl="${b64Path}" data-name="${(data.server_filename_p12 || 'server.p12')}.b64.txt">⬇️ .p12 (Base64)</button>` : ''}
            </div>
          </div>
          <textarea id="${b64Id}" readonly class="form-control">${data.server_p12_b64 || ''}</textarea>
        </div>`;
      }
      if (data.server_fingerprint) inner += `<div>Empreinte SHA-256 : <code>${data.server_fingerprint}</code></div>`;
      if (data.server_valid_from && data.server_valid_to) inner += `<div class="text-muted">Validité: ${data.server_valid_from} → ${data.server_valid_to}</div>`;
      if (data.apache_hint) inner += textarea('Apache (extrait vhost)', data.apache_hint, 'apache_hint.txt', lastFilesMap['apache_hint.txt']);
      if (data.nginx_hint)  inner += textarea('Nginx (server block)', data.nginx_hint, 'nginx_hint.conf', lastFilesMap['nginx_hint.conf']);
      res.push(sectionCard('sec_server', '🌐 Serveur', inner, (data.server_cert||'') + '\n' + (data.server_key||'')));
    }

    if (options.has('client') && data.client_cert) {
      let inner = '';
      inner += textarea(data.client_filename_cert || 'client_cert.pem', data.client_cert, data.client_filename_cert || 'client_cert.pem', lastFilesMap[data.client_filename_cert || 'client_cert.pem']);
      inner += textarea(data.client_filename_key  || 'client_key.pem',  data.client_key || '', data.client_filename_key || 'client_key.pem', lastFilesMap[data.client_filename_key || 'client_key.pem']);
      if (data.client_p12_b64) {
        const b64Id = 'b64_' + Math.random().toString(36).slice(2);
        const binPath = data.client_filename_p12 ? lastFilesMap[data.client_filename_p12] : null;
        const b64Path = data.client_filename_p12 ? lastFilesMap[data.client_filename_p12 + '.b64.txt'] : null;
        inner += `<div class="mb-3">
          <div class="row-title">
            <label class="form-label me-2">${data.client_filename_p12 || 'client.p12'} (Base64)</label>
            <div class="actions">
              <button class="btn btn-outline-secondary btn-sm" data-copy="#${b64Id}">📋 Copier</button>
              ${binPath ? `<button class="btn btn-outline-success btn-sm" data-dl="${binPath}" data-name="${data.client_filename_p12 || 'client.p12'}">⬇️ .p12 (binaire)</button>` : ''}
              ${b64Path ? `<button class="btn btn-outline-success btn-sm" data-dl="${b64Path}" data-name="${(data.client_filename_p12 || 'client.p12')}.b64.txt">⬇️ .p12 (Base64)</button>` : ''}
            </div>
          </div>
          <textarea id="${b64Id}" readonly class="form-control">${data.client_p12_b64 || ''}</textarea>
        </div>`;
      }
      if (data.client_fingerprint) inner += `<div>Empreinte SHA-256 : <code>${data.client_fingerprint}</code></div>`;
      if (data.client_valid_from && data.client_valid_to) inner += `<div class="text-muted">Validité: ${data.client_valid_from} → ${data.client_valid_to}</div>`;
      res.push(sectionCard('sec_client', '👤 Client', inner, (data.client_cert||'') + '\n' + (data.client_key||'')));
    }

    if (options.has('keypair') && data.private_key && data.public_key) {
      let inner = '';
      inner += textarea('private_key.pem', data.private_key, 'private_key.pem', lastFilesMap['private_key.pem']);
      inner += textarea('public_key.pem', data.public_key, 'public_key.pem', lastFilesMap['public_key.pem']);
      res.push(sectionCard('sec_keys', '🗝️ Paire de clés', inner, (data.private_key||'') + '\n' + (data.public_key||'')));
    }

    if (options.has('token_uuid') && (data.uuid || data.token)) {
      let inner = '';
      inner += textarea('uuid.txt', data.uuid || '', 'uuid.txt', lastFilesMap['uuid.txt']);
      inner += textarea('token.txt', data.token || '', 'token.txt', lastFilesMap['token.txt']);
      res.push(sectionCard('sec_utit', '🔑 UUID & Jeton', inner, (data.uuid ? 'UUID: '+data.uuid+'\n' : '') + (data.token ? 'Token: '+data.token : '')));
    }

    resultNode.innerHTML = res.join('');

  } catch (err) {
    showAlert('danger', 'Échec de la génération : ' + err.message);
  }
}

// Blocking validation + submit
document.getElementById('certForm').addEventListener('submit', async e => {
  const srvChecked = document.getElementById('opt_server').checked;
  const cliChecked = document.getElementById('opt_client').checked;
  const srvCN = document.getElementById('server_cn').value.trim();
  const cliCN = document.getElementById('client_cn').value.trim();
  let errors = [];
  if (srvChecked && srvCN === '') errors.push("CN serveur est requis (case 'Certificat Serveur' cochée).");
  if (cliChecked && cliCN === '') errors.push("CN client est requis (case 'Certificat Client' cochée).");
  if (errors.length) {
    e.preventDefault();
    showAlert('warning', errors.join('<br>'));
    updateCnState();
    return;
  }
  e.preventDefault();
  const formData = new FormData(e.target);
  buildSummary(formData);
  submitAndRender(formData, {zipOnly:false});
});

// Generate & Download ZIP only (no render)
zipOnlyBtn.addEventListener('click', async () => {
  const form = document.getElementById('certForm');
  const srvChecked = document.getElementById('opt_server').checked;
  const cliChecked = document.getElementById('opt_client').checked;
  const srvCN = document.getElementById('server_cn').value.trim();
  const cliCN = document.getElementById('client_cn').value.trim();
  let errors = [];
  if (srvChecked && srvCN === '') errors.push("CN serveur est requis (case 'Certificat Serveur' cochée).");
  if (cliChecked && cliCN === '') errors.push("CN client est requis (case 'Certificat Client' cochée).");
  if (errors.length) {
    showAlert('warning', errors.join('<br>'));
    updateCnState();
    return;
  }
  const formData = new FormData(form);
  buildSummary(formData);
  await submitAndRender(formData, {zipOnly:true});
});
</script>
</body>
</html>
