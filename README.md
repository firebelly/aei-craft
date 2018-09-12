## AEI Craft 3 site

To get local development up and running:

- install fabric (brew install fabric if on Mac) and run `fab devsetup`
- edit `.env` and update with mysql settings
- point webserver to `aei-craft/web` directory as root for local domain, e.g. aei-craft.localhost
- `npx gulp watch` will fire up scss/js watching and browser-sync
- open http://aei-craft.localhost:3000/ for live updates

To deploy: `fab deploy` will push up changes to staging server, and `fab production deploy` pushes to production.

Currently only uses master branch but can easily change this in `fabfile.py` to specify branches for staging/production.
