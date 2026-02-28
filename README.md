# Portfolio app на Yii2 + MySQL + Docker

Приложение переписано с Python на **PHP (Yii2)** и использует **MySQL**.

## Стек
- PHP 8.2
- Yii2
- MySQL 8
- Docker / Docker Compose

## Быстрый старт
```bash
docker compose up --build
```

После старта приложение будет доступно по адресу:
- http://localhost:8080

MySQL будет доступен на порту `3307`:
- host: `127.0.0.1`
- db: `portfolio`
- user: `portfolio`
- password: `portfolio`

## Что происходит при запуске
Контейнер `app` автоматически:
1. Выполняет `composer install` (если не установлен `vendor`).
2. Запускает миграции Yii2 (`php yii migrate/up --interactive=0`).
3. Стартует Apache.

## API (совместим с текущим фронтендом)
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/me`
- `GET /api/projects`
- `POST /api/projects`
- `PUT /api/integrations/jira`

Авторизация — Bearer Token (`Authorization: Bearer <token>`).
