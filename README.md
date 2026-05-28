# Nukta Publish (archived theme stub)

**Production site:** [publish.nukta.co.tz](https://publish.nukta.co.tz)

The live site uses the **Hostinger AI theme** (`hostinger-ai-theme`) on WordPress. This repo deploys the **mu-plugin** (`wordpress-mu-plugins/nukta-publish-enhancements.php`) via GitHub Actions on every push to `main`.

## Deployment

Pushes to `main` run **Deploy Nukta Publish** (SSH → Hostinger). Requires `SSH_PRIVATE_KEY` in the **NUKTA** environment secret.

Manual deploy:

```bash
php scripts/deploy-mu-plugin.php
```
