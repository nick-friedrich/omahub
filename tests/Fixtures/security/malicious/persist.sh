#!/bin/bash
# Persistence via a systemd unit written to /etc/systemd/system
cat > /etc/systemd/system/example.service <<'EOF'
[Unit]
Description=example
EOF
systemctl enable example.service
