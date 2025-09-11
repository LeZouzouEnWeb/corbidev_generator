<?php
$isResults = ($_SERVER['REQUEST_METHOD'] === 'POST');
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
  <head>
    <meta charset="UTF-8">
    <title>CertKit — Générateur Certificats & Clés</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css?v=4.1" rel="stylesheet">
  </head>
  <body>
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="text-primary m-0">🔐 CertKit — certificats, clés et jetons <span class="badge badge-ui">UI v4.1</span></h1>
        <div class="d-flex gap-2 align-items-center">
          <button id="themeBtn" class="btn btn-outline-secondary btn-sm" type="button">🌓 Thème</button>
        </div>
      </div>

      <section id="widgetsScreen" class="<?= $isResults ? 'd-none' : '' ?>">
        <section id="tileHub" class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 m-0">🧩 Modules</h2>
            <div class="d-flex gap-2">
              <button id="resetBtn" type="button" class="btn btn-outline-secondary btn-sm">↺ Réinitialiser</button>
            </div>
          </div>
          <div class="tile-grid">
            <button type="button" class="tile" data-bs-target="#modalCA"><div class="tile-emoji">📜</div><div class="tile-title">CA</div><div class="tile-desc">Autorité de Certification</div></button>
            <button type="button" class="tile" data-bs-target="#modalServer"><div class="tile-emoji">🌐</div><div class="tile-title">Serveur</div><div class="tile-desc">Certificat & clé</div></button>
            <button type="button" class="tile" data-bs-target="#modalClient"><div class="tile-emoji">👤</div><div class="tile-title">Client</div><div class="tile-desc">Certificat utilisateur</div></button>
            <button type="button" class="tile" data-bs-target="#modalSSH"><div class="tile-emoji">🧩</div><div class="tile-title">SSH</div><div class="tile-desc">Clé Ed25519</div></button>
            <button type="button" class="tile" data-bs-target="#modalOther"><div class="tile-emoji">🔑</div><div class="tile-title">Jeton & UUID</div><div class="tile-desc">Tokens Base64, UUID v4</div></button>
          </div>
        </section>

        <div id="actionBar" class="d-flex flex-wrap gap-2 mb-3">
          <button id="generateBtn" type="submit" form="certForm" class="btn btn-primary">⚙️ Générer</button>
          <button id="zipOnlyBtn" type="button" class="btn btn-outline-primary">⬇️ Télécharger (ZIP)</button>
        </div>
      </section>

      <section id="resultsScreen" class="<?= $isResults ? '' : 'd-none' ?>">
        <div class="card mb-4" id="summaryCard">
          <div class="card-header"><strong>Récapitulatif</strong></div>
          <div class="card-body" id="summaryBody"></div>
        </div>
        <div class="card" id="resultsCard">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>📦 Résultats</strong>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="backToTiles">← Retour</button>
          </div>
          <div class="card-body">
            <a id="section-results"></a>
            <div id="result" aria-live="polite"></div>
          </div>
        </div>
      </section>

      <!-- Le formulaire englobe tous les modals pour garantir l'envoi POST de tous les champs -->
      <form id="certForm" method="POST">
        <input type="hidden" name="zip_only" id="zip_only" value="">
        <!-- Modals -->
        <div class="modal fade" id="modalCA" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">📜 CA — Paramètres</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-12"><div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="opt_ca" name="generate_option[]" value="ca">
                  <label class="form-check-label" for="opt_ca"><strong>Inclure l’Autorité de Certification (CA)</strong></label>
                </div><hr/></div>
                <div class="col-md-6"><label class="form-label">Nom commun (CN)</label><input type="text" class="form-control" name="ca_cn" placeholder="Ex. My Root CA"></div>
                <div class="col-md-3"><label class="form-label">Validité (jours)</label><input type="number" class="form-control" name="ca_days" value="3650" min="1"></div>
                <div class="col-md-3"><label class="form-label">Taille clé</label><select class="form-select" name="ca_key_bits"><option value="2048">RSA 2048</option><option value="3072">RSA 3072</option><option value="4096" selected>RSA 4096</option></select></div>
                <div class="col-md-6"><label class="form-label">Organisation (O)</label><input type="text" class="form-control" name="ca_org" placeholder="Ex. ACME Corp."></div>
                <div class="col-md-6"><label class="form-label">Pays (C)</label><input type="text" class="form-control" name="ca_c" placeholder="FR"></div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button class="btn btn-primary" data-bs-dismiss="modal">Valider</button></div>
          </div></div>
        </div>

        <div class="modal fade" id="modalServer" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">🌐 Serveur — Paramètres</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-12"><div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="opt_server" name="generate_option[]" value="server">
                  <label class="form-check-label" for="opt_server"><strong>Inclure un certificat serveur</strong></label>
                </div><hr/></div>
                <div class="col-md-6"><label class="form-label">Nom commun (CN)</label><input type="text" class="form-control" name="server_cn" placeholder="Ex. server.example.com"></div>
                <div class="col-md-6"><label class="form-label">SANs (DNS, séparés par des virgules)</label><input type="text" class="form-control" name="server_sans" placeholder="www.example.com,api.example.com"></div>
                <div class="col-md-3"><label class="form-label">Validité (jours)</label><input type="number" class="form-control" name="server_days" value="825" min="1"></div>
                <div class="col-md-3"><label class="form-label">Taille clé</label><select class="form-select" name="server_key_bits"><option value="2048" selected>RSA 2048</option><option value="3072">RSA 3072</option><option value="4096">RSA 4096</option></select></div>
                <div class="col-md-6"><label class="form-label">Organisation (O)</label><input type="text" class="form-control" name="server_org" placeholder="Ex. ACME Corp."></div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button class="btn btn-primary" data-bs-dismiss="modal">Valider</button></div>
          </div></div>
        </div>

        <div class="modal fade" id="modalClient" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">👤 Client — Paramètres</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-12"><div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="opt_client" name="generate_option[]" value="client">
                  <label class="form-check-label" for="opt_client"><strong>Inclure un certificat client</strong></label>
                </div><hr/></div>
                <div class="col-md-6"><label class="form-label">Nom complet</label><input type="text" class="form-control" name="client_name" placeholder="Ex. Jean Dupont"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="client_email" placeholder="jean.dupont@example.com"></div>
                <div class="col-md-3"><label class="form-label">Validité (jours)</label><input type="number" class="form-control" name="client_days" value="730" min="1"></div>
                <div class="col-md-3"><label class="form-label">Taille clé</label><select class="form-select" name="client_key_bits"><option value="2048" selected>RSA 2048</option><option value="3072">RSA 3072</option><option value="4096">RSA 4096</option></select></div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button class="btn btn-primary" data-bs-dismiss="modal">Valider</button></div>
          </div></div>
        </div>

        <div class="modal fade" id="modalSSH" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">🧩 SSH — Clé Ed25519</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-12"><div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="opt_ssh" name="generate_option[]" value="ssh_ed25519">
                  <label class="form-check-label" for="opt_ssh"><strong>Inclure une paire SSH (Ed25519)</strong></label>
                </div><hr/></div>
                <div class="col-md-8"><label class="form-label">Commentaire</label><input type="text" class="form-control" name="ssh_comment" placeholder="Ex. user@host"></div>
                <div class="col-md-4"><label class="form-label">Passphrase (optionnel)</label><input type="password" class="form-control" name="ssh_passphrase" autocomplete="new-password"></div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button class="btn btn-primary" data-bs-dismiss="modal">Valider</button></div>
          </div></div>
        </div>

        <div class="modal fade" id="modalOther" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">🔑 Jeton & UUID</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-12"><div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="opt_token" name="generate_option[]" value="token_uuid">
                  <label class="form-check-label" for="opt_token"><strong>Inclure Jeton Base64 et/ou UUID v4</strong></label>
                </div><hr/></div>
                <div class="col-md-6"><label class="form-label">Jeton Base64 — octets bruts</label><input type="number" class="form-control" name="token_bytes" value="32" min="4" max="512"></div>
                <div class="col-md-6"><label class="form-label">UUIDs à générer</label><input type="number" class="form-control" name="uuid_count" value="1" min="1" max="50"></div>
                <div class="col-md-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="token_urlsafe" id="token_urlsafe" value="1" checked><label class="form-check-label" for="token_urlsafe">Utiliser Base64 URL-safe</label></div></div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button class="btn btn-primary" data-bs-dismiss="modal">Valider</button></div>
          </div></div>
        </div>
      </form>

    </div><!-- /container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js?v=4.1"></script>
    <script src="assets/ui.js?v=4.1"></script>
  </body>
</html>
