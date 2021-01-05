from fabric.api import *
import os

env.hosts = ['aei-craft.firebelly.co']
env.user = 'firebelly'
env.path = '~/Sites/aei-craft'
env.remotepath = '/home/firebelly/apps/aei'
env.git_branch = 'master'
env.warn_only = True
env.forward_agent = True

def production():
  env.hosts = ['aeieng.opalstacked.com']
  env.user = 'aeieng'
  env.remotepath = '/home/aeieng/apps/aei_craft_production'
  env.git_branch = 'master'

def staging():
  env.hosts = ['aeieng.opalstacked.com']
  env.user = 'aeieng'
  env.remotepath = '/home/aeieng/apps/aei_craft_staging'
  env.git_branch = 'master'

def assets():
  local('npx gulp --production')

def devsetup():
  print "Installing composer, node and bower assets...\n"
  local('composer install')
  local('npm install')
  local('cd web/assets && bower install')
  local('npx gulp')
  local('cp .env.example .env')
  print "OK DONE! Hello? Are you still awake?\nEdit your .env file with local credentials\nRun `npx gulp watch` to run local gulp to compile & watch assets"

def deploy(composer='y'):
  update()
  if composer == 'y':
    composer_install()
  clear_cache()

def update():
  with cd(env.remotepath):
    run('git pull origin {0}'.format(env.git_branch))

def composer_install():
  with cd(env.remotepath):
    run('php74 ~/bin/composer.phar install')

def clear_cache():
  with cd(env.remotepath):
    run('./craft clear-caches/compiled-templates')
    run('./craft clear-caches/data')
