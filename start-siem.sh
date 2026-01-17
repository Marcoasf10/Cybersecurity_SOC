#!/bin/bash

# 1. Limpeza total de estados anteriores
docker compose down

# 2. Sobe os containers em background
docker compose up -d

echo "Aguardar que o motor interno do Wazuh prepare o volume (30s)..."
# IMPORTANTE: O Wazuh precisa de tempo para criar a DB interna antes do restart
sleep 30 

echo "A aplicar correções de permissões e diretórios..."

# 3. Executa as tuas correções, mas sem apagar pastas vitais
docker exec wazuh bash -c "
  # Cria as pastas de logs para 2026 (se necessário para o teu análise)
  mkdir -p /var/ossec/logs/alerts/2026/Jan \
           /var/ossec/logs/archives/2026/Jan \
           /var/ossec/logs/firewall/2026/Jan \
           /var/ossec/logs/fts/2026/Jan \
           /var/ossec/etc/shared
           
  touch /var/ossec/etc/shared/ar.conf
  
  # Garante que o utilizador wazuh é dono de tudo
  chown -R wazuh:wazuh /var/ossec/logs /var/ossec/etc/shared /var/ossec/var/run
  
  # Remove o lock APENAS se ele existir para permitir o restart
  rm -f /var/ossec/var/start-script-lock
  
  # Reinicia de forma segura
  /var/ossec/bin/wazuh-control restart
"

# Força a remoção do lock se ele ainda lá estiver
docker exec -u root wazuh rm -rf /var/ossec/var/start-script-lock

echo "A verificar estado do motor de análise..."
sleep 10
docker exec wazuh /var/ossec/bin/wazuh-control status