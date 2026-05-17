# Keycloak theme: asta-finance

This folder contains a Keycloak login theme that matches the AStA (Ruhr-Universität Bochum) branding for the FS-Finanzportal project.

Installation (copy into a running Keycloak container):

1. Download or copy the `keycloak/themes/asta-finance` folder into `/opt/keycloak/themes/` inside the Keycloak container.

Example using docker:

```bash
# from repository root
docker cp keycloak/themes/asta-finance <KEYCLOAK_CONTAINER>:/opt/keycloak/themes/
```

Or mount the theme via docker-compose (recommended for development):

```yaml
services:
  keycloak:
    image: quay.io/keycloak/keycloak:latest
    volumes:
      - ./keycloak/themes:/opt/keycloak/themes:ro
```

2. Ensure the theme files are readable by Keycloak and restart the container.

3. In Keycloak admin console, open your realm -> Themes and set `Login Theme` to `asta-finance`.

Brand tokens:
- Files: `resources/css/styles.css` contains CSS variables at the top for `--primary`, `--accent`, etc. Update these values to tune colors.

Assets:
- The current theme uses a text-based AStA mark in `login.ftl`, so it does not require a logo file to render without broken asset requests.

Compose integration:
- This repository's `compose.yaml` mounts `./keycloak/themes` into the container automatically, so the `asta-finance` theme will be available on container start. No extra copy step is required.

Notes:
- This theme is intentionally lightweight and isolated. Update `login.ftl` to adjust layout or add custom links (privacy, imprint).
- Run accessibility and contrast checks after changing colors.
