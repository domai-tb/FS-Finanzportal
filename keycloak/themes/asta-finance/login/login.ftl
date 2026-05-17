<#-- Simple Keycloak login template for the AStA-themed branding -->
<!DOCTYPE html>
<html lang="${(locale.currentLanguageTag)!'de'}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Anmelden</title>
  <link rel="stylesheet" href="${url.resourcesPath}/css/styles.css" />
</head>
<body class="asta-login">
  <div class="container">
    <aside class="brand-panel" aria-hidden="true">
      <div class="brand-logo">AStA</div>
      <p class="tagline">Deine Studierendenvertretung — Finanzportal</p>
    </aside>

    <main class="login-panel">
      <div class="card">
        <h1>Anmelden</h1>
        <#if realm.displayName?has_content>
          <p class="realm">${realm.displayName}</p>
        </#if>

        <#if message?has_content>
          <div class="note ${message.type!''}">${kcSanitize(message.summary)?no_esc}</div>
        </#if>

        <form id="kc-form-login" action="${url.loginAction}" method="post">
          <div class="form-row">
            <label for="username">Benutzername / E-Mail</label>
            <input id="username" name="username" type="text" autofocus="autofocus" autocomplete="username" value="${login.username!''}" />
          </div>

          <div class="form-row">
            <label for="password">Passwort</label>
            <div class="password-row">
              <input id="password" name="password" type="password" autocomplete="current-password" />
              <button type="button" id="show-password" aria-label="Passwort anzeigen">Anzeigen</button>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="primary">Anmelden</button>
          </div>

          <#if realm.rememberMe?? && realm.rememberMe>
          <div class="form-row">
            <input id="rememberMe" name="rememberMe" type="checkbox" <#if login.rememberMe??>checked</#if>/>
            <label for="rememberMe">Angemeldet bleiben</label>
          </div>
          </#if>

        </form>

        <#if url.loginResetCredentialsUrl?has_content>
          <p class="muted"><a href="${url.loginResetCredentialsUrl}">Passwort vergessen?</a></p>
        </#if>
      </div>
    </main>
  </div>

  <script src="${url.resourcesPath}/js/login.js"></script>
</body>
</html>
