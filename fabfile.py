from fabric.api import *
import os

env.hosts = ['aeieng.webfactional.com']
env.user = 'aeieng'
env.path = '~/Sites/aei-craft'
env.remotepath = '/home/aeieng/webapps/aei_craft_staging'
env.git_branch = 'master'
env.warn_only = True

def production():
  env.hosts = ['aeieng.com']
  env.user = 'aeieng'
  env.remotepath = '/home/aeieng/webapps/aei_craft_production'
  env.git_branch = 'master'

def assets():
  local('npx gulp --production')

def devsetup():
  print "Installing composer, node and bower assets...\n"
  local('composer install')
  local('npm install')
  local('cd public/assets && bower install')
  local('npx gulp')
  local('cp .env.example .env')
  print "OK DONE! Hello? Are you still awake?\nEdit your .env file with local credentials\nRun `npx gulp watch` to run local gulp to compile & watch assets"

def deploy(composer='y'):
  update()
  if composer == 'y':
    composer_install()

def update():
  with cd(env.remotepath):
    run('git pull origin {0}'.format(env.git_branch))

def composer_install():
  with cd(env.remotepath):
    run('php72 ~/bin/composer.phar install')
