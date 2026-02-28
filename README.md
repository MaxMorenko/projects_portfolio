# Projects Portfolio

Минимальное веб-приложение для проектного менеджера:
- регистрация и авторизация пользователя;
- настройка интеграции с Jira (base URL, email, API token);
- ведение собственного портфеля проектов (добавление и просмотр).

## Запуск

```bash
python3 app.py
```

Приложение будет доступно на `http://localhost:3000`.

## API

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/me`
- `PUT /api/integrations/jira`
- `GET /api/projects`
- `POST /api/projects`

Для защищенных методов используйте заголовок:

`Authorization: Bearer <token>`
