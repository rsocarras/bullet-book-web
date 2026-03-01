# Bullet Book API MVP (Yii2)

## 1) Ejecutar migraciones

```bash
cd /usr/local/var/www/app_akumajaa/backend-bullet
# Primero: tablas base de 2amigos/yii2-usuario (user, profile, token, etc.)
php yii migrate/up --migrationNamespaces=Da\\User\\Migration --interactive=0

# Luego: tablas MVP de Bullet Book
php yii migrate --migrationPath=@app/migrations
```

## 2) Configuración recomendada

Variables de entorno para RabbitMQ (yii2-queue AMQP):

```bash
export RABBITMQ_HOST=127.0.0.1
export RABBITMQ_PORT=5672
export RABBITMQ_USER=guest
export RABBITMQ_PASSWORD=guest
export RABBITMQ_QUEUE=bullet_book_queue
export RABBITMQ_EXCHANGE=bullet_book_exchange
export RABBITMQ_ROUTING_KEY=bullet.book
```

Notas:
- Para AMQP instala dependencias: `composer require yiisoft/yii2-queue enqueue/amqp-lib`.
- Para procesar la cola: `php yii queue/listen`.

## 3) Probar login y endpoints con curl

### Login

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "demo@example.com",
    "password": "demo-password",
    "device": {
      "platform": "android",
      "device_uid": "pixel8-001",
      "push_token": "token_fcm_abc"
    }
  }'
```

Respuesta esperada:

```json
{
  "success": true,
  "data": {
    "access_token": "<TOKEN>",
    "token_type": "Bearer",
    "expires_at": "2026-03-30 12:00:00.123456",
    "user": {
      "id": 1,
      "username": "demo",
      "email": "demo@example.com"
    }
  }
}
```

### Crear bullet

```bash
curl -X POST http://localhost:8080/api/v1/bullets \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mood",
    "bullet_type": "feeling",
    "input_type": "scale",
    "scale_min": 1,
    "scale_max": 5,
    "scale_labels": {"1":"very bad","5":"very good"},
    "is_active": 1
  }'
```

### Upsert entry

```bash
curl -X POST http://localhost:8080/api/v1/entries \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "bullet_id": 10,
    "entry_date": "2026-02-28",
    "value_int": 4,
    "note": "Buen día"
  }'
```

## 4) Sync API

### Pull

`GET /api/v1/sync/pull?since=YYYY-MM-DD%20HH:MM:SS.ffffff`

Respuesta:

```json
{
  "success": true,
  "data": {
    "server_time": "2026-02-28 23:00:00.123456",
    "since": "2026-02-20 00:00:00.000000",
    "changes": {
      "bullets": [],
      "templates": [],
      "template_bullets": [],
      "entries": [],
      "projects": [],
      "tasks": [],
      "labels": [],
      "task_labels": [],
      "reminders": []
    },
    "deletions": {
      "bullets": [1, 2],
      "templates": [],
      "template_bullets": [],
      "entries": [],
      "projects": [],
      "tasks": [],
      "labels": [],
      "task_labels": [],
      "reminders": []
    }
  }
}
```

### Push

`POST /api/v1/sync/push`

Body:

```json
{
  "since": "2026-02-20 00:00:00.000000",
  "changes": {
    "bullets": [{"id": 10, "name": "Mood", "bullet_type": "feeling", "input_type": "scale", "scale_min": 1, "scale_max": 5, "updated_at": "2026-02-28 10:00:00.111111"}],
    "entries": [{"id": 20, "bullet_id": 10, "entry_date": "2026-02-28", "value_int": 4, "updated_at": "2026-02-28 10:05:00.111111"}],
    "tasks": []
  },
  "deletions": {
    "tasks": [99]
  }
}
```

Respuesta:

```json
{
  "success": true,
  "data": {
    "server_time": "2026-02-28 23:01:00.555555",
    "applied": {
      "changes": 2,
      "deletions": 1
    },
    "diff": {
      "server_time": "2026-02-28 23:01:00.555555",
      "since": "2026-02-20 00:00:00.000000",
      "changes": {},
      "deletions": {}
    }
  }
}
```

## 5) Scheduler/jobs

- Encolar reminders vencidos:

```bash
php yii scheduler/enqueue-due-reminders
```

- Encolar resumen semanal (todos o un usuario):

```bash
php yii scheduler/enqueue-weekly-summary
php yii scheduler/enqueue-weekly-summary 123
```

## 6) Convenciones implementadas

- Soft delete: `deleted_at`.
- Timestamps: `created_at`/`updated_at` con `UTC_TIMESTAMP(6)`.
- Sync incremental por `updated_at` y devolución de IDs borrados por entidad.
- Last-write-wins en `sync/push` comparando `updated_at` cliente vs servidor.
