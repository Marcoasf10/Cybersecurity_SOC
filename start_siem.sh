#!/bin/bash
# 1. Start the containers
docker compose up -d

echo "Waiting for containers to stabilize..."
sleep 15

# 2. Fix the permissions and directories (Your proven fix)
docker exec wazuh mkdir -p /var/ossec/logs/alerts/2026/Jan /var/ossec/logs/archives/2026/Jan /var/ossec/logs/firewall/2026/Jan /var/ossec/logs/fts/2026/Jan /var/ossec/etc/shared
docker exec wazuh touch /var/ossec/etc/shared/ar.conf
docker exec wazuh chown -R wazuh:wazuh /var/ossec/logs /var/ossec/etc/shared

# 3. Restart Wazuh to apply the fixes
docker exec wazuh /var/ossec/bin/wazuh-control restart

echo "SIEM should be ready at http://localhost:5601"