from fabric import task, Connection
from invoke import run as local

remote_path = "/home/aeieng/apps/aei_craft_staging"
remote_hosts = ["aeieng@aeieng.opalstacked.com"]
php_command = "php74"

# set to production
@task
def production(c):
    global remote_hosts, remote_path
    remote_hosts = ["aeieng@aeieng.com"]
    remote_path = "/home/aeieng/apps/aei_craft_production"

# deploy
@task(hosts=remote_hosts)
def deploy(c):
    update(c)
    composer_update(c)
    clear_cache(c)

def update(c):
    c.run("cd {} && git pull".format(remote_path))

def composer_update(c):
    c.run("cd {} && {} ~/bin/composer.phar install".format(remote_path, php_command))

def clear_cache(c):
    c.run("cd {} && ./craft clear-caches/compiled-templates".format(remote_path))
    c.run("cd {} && ./craft clear-caches/data".format(remote_path))

# local commands
@task
def assets(c):
    local("npx gulp --production")
