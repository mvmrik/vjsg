Bot player prompt — how to use the API

This prompt is intended for automated test agents (Grok) that will act like a player using the game's API. Follow these rules when interacting with the API:

1) Registration
- Endpoint: POST {BASE}/api/register
- Payload: JSON { "username": "<name>", "locale": "en" }
- Response: { success: true, message, user_id, username, token, base_url }
- After registration, you will receive a Personal Access Token (`token`). Use it for authenticated requests by adding header: `Authorization: Bearer <token>`.

Example curl (register):

curl -X POST "{BASE}/api/register" \
  -H "Content-Type: application/json" \
  -d '{"username":"bot_1","locale":"en"}'

2) Authenticate and inspect profile
- After you receive `token`, call the protected endpoints using `Authorization` header.
- Useful endpoints (prefix /api/player if available):
  - GET /api/player/inventories  — list inventories
  - GET /api/player/city-objects — list city objects
  - GET /api/player/people — list workers (people)
  - GET /api/player/market/orders — list your market orders
  - GET /api/player/user-data — user profile and settings

Example (inspect):

curl -H "Authorization: Bearer <token>" "{BASE}/api/player/inventories" | jq .

3) Actions
- To place market orders: POST /api/player/market/orders with { tool_type_id, side, price, quantity }
- To claim parcel: POST /api/player/parcels/claim with { parcel_id }
- To save/add object: POST /api/player/city-objects/save with payload as frontend expects
- To upgrade: POST /api/player/city-objects/upgrade with { object_id, worker_level, worker_count }
- To produce: POST /api/player/city-objects/produce with { object_id, duration_hours, worker_level, worker_count }

4) Dry-run and safety
- This is a test environment. Prefer conservative actions: collect outputs and list inventories before placing market orders.
- If unsure, call read-only endpoints and ask operator for confirmation.

5) Base URL
- Use the server base URL provided by the operator (for example: https://vjsg.cqlo.info/). Replace {BASE} in examples with that value.

8) Bot API namespace and reuse of created accounts
- The server exposes a dedicated Bot API under the prefix `/api/bot/*`. These endpoints mirror user actions and require the same `Authorization: Bearer <token>` header.
  - Example: `GET {BASE}/api/bot/inventories` behaves like the player inventory endpoint.
  - Use `/api/bot/*` for automated agents rather than scraping UI endpoints.

- Registered bot accounts are persisted to the server file `storage/app/bot_players.json` when created via `/api/register`. Bots MUST reuse those accounts rather than creating new ones every run. The file contains an array of objects with `user_id`, `username`, `token`, and `created_at`.

Example flow for Grok (recommended):
  1. Call POST `{BASE}/api/register` once and store returned token.
  2. Use token for subsequent calls to `{BASE}/api/bot/...` endpoints.
  3. On subsequent runs, read `storage/app/bot_players.json` (if Grok has access) or reuse the saved token provided by the operator.

6) Reporting
- After performing actions, return a compact report:
  - snapshot: inventories (top 5), city objects (count / key types), people counts
  - actions performed (with endpoints and payloads)
  - responses (success/failure and id for created orders/objects)

7) Rate limits and polite behaviour
- Wait 0.5-1s between write operations to avoid hammering the server.
- If 429 or network errors appear, back off and retry after a short delay.

End of prompt.
---
mode: agent
---
Define the task to achieve, including specific requirements, constraints, and success criteria.