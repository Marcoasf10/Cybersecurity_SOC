#!/bin/bash

# Start containers
docker compose up -d

echo "Waiting for containers to start..."
sleep 10

# Initialize Wazuh
docker exec wazuh bash -c "
  mkdir -p /var/ossec/logs/{alerts,archives,firewall,fts}/2026/Jan /var/ossec/etc/shared &&
  touch /var/ossec/etc/shared/ar.conf &&
  chown -R wazuh:wazuh /var/ossec/logs /var/ossec/etc/shared &&
  rm -rf /var/ossec/var/start-script-lock &&
  /var/ossec/bin/wazuh-control start
"

sleep 5
docker exec wazuh /var/ossec/bin/wazuh-control status

echo "SIEM should be ready at http://localhost:5601"
echo "Default credentials: admin / admin"