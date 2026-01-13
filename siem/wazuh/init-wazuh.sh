#!/bin/bash

# Create necessary directories
mkdir -p /var/ossec/logs/alerts/2026/Jan
mkdir -p /var/ossec/logs/archives/2026/Jan
mkdir -p /var/ossec/logs/firewall/2026/Jan
mkdir -p /var/ossec/logs/fts/2026/Jan
mkdir -p /var/ossec/etc/shared

# Create required files
touch /var/ossec/etc/shared/ar.conf

# Fix permissions
chown -R wazuh:wazuh /var/ossec/logs/alerts /var/ossec/logs/archives /var/ossec/logs/firewall /var/ossec/logs/fts /var/ossec/etc/shared

# Remove stale lock if exists
rm -rf /var/ossec/var/start-script-lock

# Start Wazuh
/var/ossec/bin/wazuh-control start