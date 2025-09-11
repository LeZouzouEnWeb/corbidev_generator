"use strict";
(function(){
  console.log('CertKit UI v4.2 loaded');
  const $  = (s, c=document) => c.querySelector(s);
  const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));

  // Theme toggle persist
  (function(){
    const KEY='certkit_theme'; const html=document.documentElement;
    function apply(t){ html.setAttribute('data-bs-theme', t); try{ localStorage.setItem(KEY,t);}catch(_){} }
    document.addEventListener('DOMContentLoaded', () => {
      try {
        const saved = localStorage.getItem(KEY);
        if (saved==='light'||saved==='dark') apply(saved);
      } catch(_){}
      const btn = $('#themeBtn');
      if (btn) btn.addEventListener('click', () => {
        const cur = html.getAttribute('data-bs-theme') || 'dark';
        apply(cur === 'dark' ? 'light' : 'dark');
      });
    });
  })();

  function showResults(){
    const widgets = $('#widgetsScreen');
    const results = $('#resultsScreen');
    if (widgets) widgets.classList.add('d-none');
    if (results) results.classList.remove('d-none');
  }
  function showWidgets(){
    const widgets = $('#widgetsScreen');
    const results = $('#resultsScreen');
    if (results) results.classList.add('d-none');
    if (widgets) widgets.classList.remove('d-none');
  }
  function closeAllModals(){
    // Remove stray backdrops and modal-open state
    const body = document.body;
    if (body) { body.classList.remove('modal-open'); body.style.removeProperty('paddingRight'); }
    $$('.modal-backdrop').forEach(b => { try{ b.remove(); }catch(_){ /* noop */ } });
    // If focus is trapped inside a hidden modal, blur it to avoid aria-hidden warning
    try { const ae = document.activeElement; if (ae && ae.closest && ae.closest('.modal')) ae.blur(); } catch(_){}
    // Hide any visible or half-visible modals
    $$('.modal.show, .modal[style*="display: block"]').forEach(m => {
      try { if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(m).hide(); } catch(_){ }
      // prevent focus trap warning during transition
      try { m.setAttribute('inert',''); } catch(_){}
      m.classList.remove('show');
      m.setAttribute('aria-hidden','true');
      m.style.display = 'none';
      m.removeAttribute('aria-modal');
      try { m.removeAttribute('inert'); } catch(_){}
    });
  }
  function renderResult(json){
    const result = $('#result');
    const summary = $('#summaryBody');
    if (!result || !summary) return;
    const esc = (s) => String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    const parts = [];
    if (json.ca_cert) parts.push('<li>Autorité (CA) générée</li>');
    if (json.server_cert) parts.push('<li>Certificat serveur généré</li>');
    if (json.client_cert) parts.push('<li>Certificat client généré</li>');
    if (json.uuid || json.token) parts.push('<li>Jeton / UUID générés</li>');
    if (json.id_ed25519 || json.ssh_private || json.ssh_public) parts.push('<li>Clés SSH générées</li>');
    summary.innerHTML = '<ul class="mb-0">' + parts.join('') + '</ul>';

    const files = json.files || {};
    const fileRows = Object.keys(files).map(name => {
      const path = files[name];
      const href = 'download_raw.php?file=' + encodeURIComponent(path) + '&name=' + encodeURIComponent(name);
      return '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">'
           + '<code class="text-truncate">' + esc(name) + '</code>'
           + '<a class="btn btn-sm btn-outline-primary" href="' + href + '">Télécharger</a>'
           + '</div>';
    }).join('');

    let zipBtnHtml = '';
    if (json.zip_path) {
      const z = 'download.php?file=' + encodeURIComponent(json.zip_path);
      zipBtnHtml = '<div class="mt-3"><a class="btn btn-primary" href="' + z + '">Télécharger tout (ZIP)</a></div>';
    }
    result.innerHTML = fileRows + zipBtnHtml;
  }

  function uncheckAllOptions(){
    const boxes = $$('input[name="generate_option[]"]');
    boxes.forEach(i => {
      i.autocomplete = 'off';
      i.removeAttribute('checked');
      i.checked = false;
      i.defaultChecked = false;
      i.setAttribute('aria-checked','false');
    });
    // retry sequence to beat browser restoration/BFCache
    const retries = [0, 25, 100, 300];
    retries.forEach(ms => setTimeout(() => {
      boxes.forEach(i => { i.checked = false; i.defaultChecked = false; i.setAttribute('aria-checked','false'); });
    }, ms));
    // log state for diagnostics
    try { console.debug('Unchecked options:', boxes.map(b=>({id:b.id,checked:b.checked,def:b.defaultChecked}))); } catch(_){ }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const form = $('#certForm');
    const backBtn = $('#backToTiles');
    const zipBtn = $('#zipOnlyBtn');

    // Réinitialiser formulaire et décocher toutes les options au chargement
    if (form) form.reset();
    closeAllModals();
    uncheckAllOptions();
    window.addEventListener('load', ()=>{ closeAllModals(); uncheckAllOptions(); });
    window.addEventListener('pageshow', (e)=>{ if (e && e.persisted) { closeAllModals(); uncheckAllOptions(); } });

    // Ouvrir modales via tuiles et cocher l'option correspondante
    const map = new Map([
      ['#modalCA','#opt_ca'],
      ['#modalServer','#opt_server'],
      ['#modalClient','#opt_client'],
      ['#modalSSH','#opt_ssh'],
      ['#modalOther','#opt_token']
    ]);
    $$('.tile[data-bs-target]').forEach(tile => {
      tile.addEventListener('click', ev => {
        ev.preventDefault();
        const sel = tile.getAttribute('data-bs-target');
        const modal = $(sel);
        if (modal && window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modal).show();
        }
        // Ne pas cocher automatiquement — l’utilisateur décide dans le modal
      });
    });

    async function submitGenerate(zipOnly=false){
      const selected = $$('input[name="generate_option[]"]:checked').map(i => i.value);
      if (selected.length === 0) { alert('Sélectionne au moins un module avant de générer.'); return; }

      // Validation par module
      const errors = [];
      if (selected.includes('server')) {
        const cn = $('input[name="server_cn"]');
        if (!cn || !cn.value.trim()) errors.push('Nom commun serveur (CN)');
      }
      if (selected.includes('client')) {
        const nm = $('input[name="client_name"]');
        if (!nm || !nm.value.trim()) errors.push('Nom complet du client');
      }
      if (errors.length) { alert('Champs requis manquants:\n- ' + errors.join('\n- ')); return; }

      // Construire le payload attendu par cert_generator.php
      const fd = new FormData();
      selected.forEach(v => { if (['ca','server','client','token_uuid'].includes(v)) fd.append('generate_option[]', v); });

      // CA
      const caDays = $('input[name="ca_days"]'); if (caDays) fd.append('days_ca', caDays.value);
      // Server
      const srvCN = $('input[name="server_cn"]'); if (srvCN) fd.append('server_cn', srvCN.value.trim());
      const srvDays = $('input[name="server_days"]'); if (srvDays) fd.append('days_server', srvDays.value);
      const srvSANs = $('input[name="server_sans"]'); if (srvSANs) fd.append('server_san', srvSANs.value.trim());
      // Client
      const cliName = $('input[name="client_name"]'); if (cliName) fd.append('client_cn', cliName.value.trim());
      const cliDays = $('input[name="client_days"]'); if (cliDays) fd.append('days_client', cliDays.value);
      // Token/UUID
      if (selected.includes('token_uuid')){
        const tBytes = $('input[name="token_bytes"]'); if (tBytes) fd.append('token_bytes', tBytes.value);
        const tUrl = $('#token_urlsafe'); if (tUrl && tUrl.checked) fd.append('token_urlsafe','1');
        const uCount = $('input[name="uuid_count"]'); if (uCount) fd.append('uuid_count', uCount.value);
      }
      // SSH
      let sshReq = null;
      if (selected.includes('ssh_ed25519')){
        const c = $('input[name="ssh_comment"]');
        const p = $('input[name="ssh_passphrase"]');
        const sc = c ? c.value.trim() : '';
        const sp = p ? p.value : '';
        fd.append('ssh_comment', sc);
        fd.append('ssh_passphrase', sp);
        // Préparer requête séparée pour SSH (API dédiée)
        const fdSsh = new FormData();
        if (sc) fdSsh.append('comment', sc);
        if (sp) fdSsh.append('passphrase', sp);
        sshReq = fetch('api/generate_ssh_json.php', { method:'POST', body: fdSsh }).then(async res => {
          const t = await res.text().catch(()=> '');
          let data = null; try { data = t ? JSON.parse(t) : null; } catch(_){ data = null; }
          if (!res.ok || !data || data.ok !== true) {
            throw new Error('SSH: ' + (data && data.error ? data.error : (res.status+' '+res.statusText+' '+t.substring(0,200))));
          }
          return data;
        });
      }

      try {
        const res = await fetch('cert_generator.php', { method:'POST', body: fd });
        const ctype = res.headers.get('content-type') || '';
        if (!res.ok) {
          const text = await res.text().catch(()=> '');
          throw new Error('HTTP '+res.status+' '+res.statusText+(text?': '+text.substring(0,300):''));
        }
        if (!ctype.includes('application/json')) {
          const text = await res.text().catch(()=> '');
          throw new Error('Réponse non-JSON reçue: '+text.substring(0,300));
        }
        const json = await res.json();
        renderResult(json);
        // Append SSH results if requested
        if (sshReq) {
          try {
            const ssh = await sshReq;
            const target = document.getElementById('result');
            if (target && ssh && ssh.id_ed25519 && ssh.id_ed25519_pub) {
              const makeDL = (name, content) => {
                const url = 'data:text/plain;charset=utf-8,' + encodeURIComponent(content);
                return '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">'
                  + '<code class="text-truncate">'+name+'</code>'
                  + '<a class="btn btn-sm btn-outline-primary" download="'+name+'" href="'+url+'">Télécharger</a>'
                  + '</div>';
              };
              const html = '<div class="mt-3"><strong>SSH (Ed25519)</strong></div>'
                + makeDL('id_ed25519', ssh.id_ed25519)
                + makeDL('id_ed25519.pub', ssh.id_ed25519_pub);
              target.insertAdjacentHTML('beforeend', html);
            }
          } catch (err) {
            alert('Échec SSH: ' + (err && err.message ? err.message : err));
          }
        }
        showResults();
        if (zipOnly && json.zip_path) {
          location.href = 'download.php?file=' + encodeURIComponent(json.zip_path);
        }
        const anchor = document.getElementById('section-results');
        if (anchor) anchor.scrollIntoView({behavior:'smooth'});
      } catch (err){
        alert('Échec de génération: ' + (err && err.message ? err.message : err));
      }
    }

    // Ne plus dépendre de l'événement submit du formulaire
    const genBtn = $('#generateBtn');
    if (genBtn) genBtn.addEventListener('click', (e)=>{ e.preventDefault(); submitGenerate(false); });
    if (zipBtn) zipBtn.addEventListener('click', ()=> submitGenerate(true));

    // Reset manuel
    const resetBtn = $('#resetBtn');
    if (resetBtn && form) resetBtn.addEventListener('click', ()=>{
      form.reset();
      $$('input[name="generate_option[]"]').forEach(i => { i.checked = false; });
      showWidgets();
    });

    // Bouton retour: revenir à l'écran widgets
    const backBtnEl = $('#backToTiles');
    if (backBtnEl) backBtnEl.addEventListener('click', (e)=>{ e.preventDefault(); showWidgets(); });
  });
})();
