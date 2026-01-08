# MATRIX - RUNTIME GUI CLI PHP MATH ENGINE

## Install (hash-linked)
- [#php-broker-api](#php-broker-api)
- [#pwa-frontend](#pwa-frontend)
- [#python-agent](#python-agent)

### PHP Broker API
1. Upload the `api/` folder and `config.php` to your hosting root.
2. Update database credentials in `config.php`.
3. Import `schema.sql` in phpMyAdmin.
4. Verify the API at `https://YOURDOMAIN.com/api/agents.php`.

### PWA Frontend
1. Upload `index.php` to your hosting root.
2. Ensure `config.php` has the correct `domain` and `api_base` values.

### Python Agent
1. Copy `agent.py` and `agent_config.json` to the agent machine.
2. Update `agent_config.json` with your broker URL and agent name.
3. Run `python agent.py`.
