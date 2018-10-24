# AEI Craft 3 site

### To get local development up and running:

- install fabric (`brew install fabric` if on Mac) and run `fab devsetup`
- edit `.env` and update with mysql settings
- point webserver to `aei-craft/web` directory as root for local domain, e.g. aei-craft.localhost
- `npx gulp watch` will fire up scss/js watching and browser-sync
- open http://aei-craft.localhost:3000/ for live updates

### Deploying:

- `fab assets` will run `npx gulp --production` to create versioned production assets
- commit new `rev-manifest.json` and all new/deleted versioned assets and push to repo
- `fab deploy` will push up changes to staging server
- `fab production deploy` pushes to production

Currently only uses master branch but can easily change this in `fabfile.py` to specify different branches for staging/production.

Expects composer to be available in `~/bin/composer.phar` on server, and uses WebFaction `php72` command to utilize PHP7.2. Also easily editable in `fabfile.py` if moving servers.
