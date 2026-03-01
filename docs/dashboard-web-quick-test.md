# Dashboard Web - Quick Test

## Rutas principales

- Home inteligente (guest/auth): `GET /site/index`
- Landing pública: se muestra automáticamente si eres guest.
- Dashboard privado: se muestra automáticamente si estás autenticado.

## Endpoints AJAX web

- Guardar check-in rápido: `POST /entry/quick-save`
- Crear tarea rápida: `POST /task/quick-create`
- Actualizar tarea rápida: `PATCH /task/quick-update?id={id}`
- Configurar recordatorio diario: `POST /reminder/daily-checkin`

## Prueba rápida manual

1. Inicia sesión con un usuario válido (2amigos).
2. Entra a `/site/index`.
3. En card **Hoy**, registra un bullet y verifica toast de éxito.
4. En card **Tareas**, crea una tarea desde el modal.
5. Cambia el status de una tarea desde el dropdown.
6. En card **Recordatorios**, configura hora + timezone y guarda.

## Prueba rápida con curl (requiere sesión/cookies)

```bash
# 1) Obtener CSRF y cookie desde login page
curl -c cookies.txt -s http://localhost:8080/user/security/login > /tmp/login.html

# 2) Hacer login (ajusta _csrf y credenciales reales)
curl -b cookies.txt -c cookies.txt -X POST http://localhost:8080/user/security/login \
  -d "_csrf=<TOKEN>&LoginForm[login]=demo&LoginForm[password]=BulletBook!2026"

# 3) Guardar entry rápido
curl -b cookies.txt -X POST http://localhost:8080/entry/quick-save \
  -d "_csrf=<TOKEN>&bullet_id=1&entry_date=2026-03-01&value_int=1"
```
