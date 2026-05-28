# Nukta Publish

WordPress theme for [publish.nukta.co.tz](https://publish.nukta.co.tz) — landing page for creators to register or log in.

## Live site

[https://publish.nukta.co.tz](https://publish.nukta.co.tz)

## Project structure

| Path | Purpose |
|------|---------|
| `style.css` | Theme metadata and styles |
| `index.php` | Front-page hero |
| `header.php` / `footer.php` | Theme shell |
| `functions.php` | Enqueues and theme support |
| `scripts/deploy.php` | Zip + SCP deploy to Hostinger |
| `.github/workflows/main.yml` | Auto-deploy on push |

Remote theme path:

`/home/u620189679/domains/publish.nukta.co.tz/public_html/wp-content/themes/nukta-publish`

## Deployment

Deployment mirrors [nukta-habari](https://github.com/karimkawambwa/nukta-habari): every push triggers GitHub Actions. This site uses SSH/SCP on Hostinger (same pattern as [nukta-tech](https://github.com/karimkawambwa/nukta-tech)), not FTP.

### GitHub setup

1. Create the repo and push `main`:

   ```bash
   cd ../nukta-publish
   git init
   git add .
   git commit -m "Initial Nukta Publish theme and deploy workflow"
   gh repo create nukta-publish --private --source=. --remote=origin --push
   ```

2. In **Settings → Secrets and variables → Actions**, add `SSH_PRIVATE_KEY` (same deploy key as nukta-tech / nukta-ai).

3. Ensure the **NUKTA** environment exists (or rename `environment:` in the workflow to match your org).

### Manual deploy

```bash
php scripts/deploy.php
```

Requires SSH access to `u620189679@82.198.227.109` on port `65002`.

### WordPress

Activate **Nukta Publish** under **Appearance → Themes** on the publish.nukta.co.tz WordPress install.
