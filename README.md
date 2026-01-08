# MATRIX Language Pack for npm

## Install (hash-linked)
- [#php-broker-api](#php-broker-api)
- [#pwa-frontend](#pwa-frontend)
- [#python-agent](#python-agent)

### PHP Broker API
1. Upload the `api/` folder and `config.php` to your hosting root.
2. Update database credentials and optional `$api_key` in `config.php`.
3. Import `schema.sql` in phpMyAdmin.
4. Verify the API at `https://YOURDOMAIN.com/api/agents.php`.

### PWA Frontend
1. Upload `index.php` (or `pwa/index.html` for static hosting) to your hosting root.
2. Ensure `config.php` has the correct `domain` and `api_base` values.
3. If you enabled `$api_key`, enter it in the PWA Broker API Key field.

### Python Agent
1. Install dependencies: `pip install -r agent/requirements.txt`.
2. Set environment variables (or use a `.env` file) for:
   - `BROKER_URL` (e.g. `https://YOURDOMAIN.com/api`)
   - `AGENT_NAME` (e.g. `Agent1`)
   - `API_KEY` (optional, if `$api_key` is set in `config.php`)
   - `LOCAL_TOKEN` (optional, for local PWA access)
3. Run the agent: `python agent/agent.py`.
4. Open the PWA and connect to `http://127.0.0.1:5001` with the matching local token if set (the agent enables CORS for browser access).
