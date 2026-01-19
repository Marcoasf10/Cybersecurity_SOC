Este módulo é responsável pela monitorização de segurança, análise de logs em tempo real e deteção de incidentes do ecossistema Laravel. A infraestrutura baseia-se no Wazuh Manager para correlação de eventos e no OpenSearch para indexação e visualização de alertas.

## Arquitetura de Monitorização

O SIEM processa logs estruturados em formato JSON gerados pela aplicação Laravel. O fluxo de dados segue este percurso:
1. A aplicação Laravel escreve logs de segurança em `backend/storage/logs/soc.json`.
2. O contentor Wazuh mapeia este diretório e consome os eventos através do `ossec.conf`.
3. O motor de análise (`wazuh-analysisd`) aplica as regras de segurança definidas no ficheiro `laravel_rules.xml`.
4. Os alertas gerados são enviados pelo Filebeat para o OpenSearch e visualizados no Wazuh Dashboard.



## Componentes da Infraestrutura

* **Wazuh Manager:** Servidor central que realiza a descodificação de logs e execução do motor de regras.
* **OpenSearch:** Base de dados NoSQL para armazenamento persistente de alertas e dashboards.
* **Wazuh Dashboard:** Interface Web para visualização de eventos (Porta 5601).
* **Filebeat:** Agente de transporte que encaminha os alertas do Manager para o Indexador.

## Regras de Deteção e Casos de Uso

As regras estão configuradas no ficheiro `siem/wazuh/rules/laravel_rules.xml` e cobrem os seguintes cenários:

### Segurança de Autenticação
* **ID 100101 (Brute Force):** Deteta 3 falhas de login do mesmo IP num intervalo de 60 segundos.
* **ID 210001 (Account Takeover - ATO):** Identifica um login com sucesso vindo de um IP que anteriormente disparou um alerta de Brute Force.
* **ID 110110 (Excessive Requests):** Deteta abuso do endpoint `api/login` com mais de 5 pedidos por minuto.

### Ataques Aplicacionais (Web)
* **ID 100103 (SQL Injection):** Utiliza expressões regulares para detetar padrões como `1=1`, `UNION SELECT` ou caracteres de escape no username ou URL.
* **ID 100102 (Access Denied):** Regista tentativas de acesso a recursos proibidos (HTTP 403).

### Fraude Financeira
* **ID 110141 (High-Value Transfer):** Identifica transferências bancárias com valores superiores a 250€.
* **ID 210002 (Financial Fraud):** Alerta de nível máximo para transferências realizadas após um cenário de Account Takeover.
* **ID 110142 (Rapid Transfers):** Deteta a criação de 3 ou mais transações no espaço de 2 minutos pelo mesmo IP.

## Guia de Operação

### Inicialização
O sistema deve ser iniciado através do script de automação para garantir a configuração correta de permissões e o arranque sequencial: ./start-siem.sh