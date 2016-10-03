#!/bin/bash
set -e
WORKDIR=`pwd`
sudo mkdir -p /consul/data/raft && sudo chmod 777 -R /consul
docker-compose up -d consul
sleep 30
cd /development/infra/ansible

if [ -f /home/bamboo/vault_password ]; then
    ansible-playbook --vault-password-file=/home/bamboo/vault_password --extra-vars env=local consul_seed_persona.yml
else
    ansible-playbook --ask-vault-pass --extra-vars env=local consul_seed_persona.yml
fi
ansible-playbook --extra-vars env=local consul_seed_echo.yml
ansible-playbook --extra-vars env=local consul_seed_global.yml

cd $WORKDIR
docker-compose up --timeout 120 -d haproxy

sleep 10

docker-compose run persona_seed
