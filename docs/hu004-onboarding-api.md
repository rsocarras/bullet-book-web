# HU-004 Onboarding API (Backend)

MVP rule implemented:
- `POST /api/v1/user/setup` only accepts system templates (`is_system=1`).

Compatibility layer notes:
- If `bb_user_setup` or `bb_user_template` are missing, setup still works for bullet activation/cloning and logs a TODO warning.
- If `bb_bullet.source_bullet_id` is missing, idempotency fallback matches by `(user_id, name, bullet_type, input_type)`.
- If `bb_bullet.sort_order` is missing, sort is preserved via `bb_template_bullet.sort_order` at read time (TODO documented in service).

## Endpoints

### 1) List system templates

```bash
curl -X GET /api/v1/templates?system=1 \
  -H "Authorization: Bearer <TOKEN>"
```

### 2) Template details + bullets preview

```bash
curl -X GET /api/v1/templates/1 \
  -H "Authorization: Bearer <TOKEN>"
```

### 3) Complete onboarding

```bash
curl -X POST /api/v1/user/setup \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "template_ids": [1,2,3],
    "timezone": "America/Bogota"
  }'
```

Success response example:

```json
{
  "success": true,
  "message": "Onboarding completed",
  "data": {
    "onboarded_at": "2026-03-01 04:00:00.000000",
    "selected_template_ids": [1, 2, 3],
    "activated_bullets_count": 8,
    "server_time": "2026-03-01 04:00:00.000000"
  }
}
```

Validation error example (422):

```json
{
  "success": false,
  "errors": {
    "template_ids": ["template_ids is required and must contain at least one template id."]
  }
}
```
