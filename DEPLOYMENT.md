# Развёртывание DevPath

Полная инструкция по развёртыванию платформы: настройка окружения, 
TLS-сертификаты, Ollama, SonarQube, почта.

---

##  Требования

- Docker Engine 29.5.3+
- Docker Compose v5.1.4+
- 16+ ГБ RAM (Ollama + SonarQube + Kafka)
- Git 2.54.0+

---

##  Пошаговое развёртывание

### 1. Клонировать репозиторий

```bash
git clone https://github.com/Kafka9092/DevPath-LMS.git
cd DevPath-LMS
```

### 2. Настроить Laravel `.env`

```bash
cp src/laravel/.env.example src/laravel/.env
```

Минимально для Docker-окружения:

```env
APP_URL=https://localhost:8089

DB_CONNECTION=pgsql
DB_HOST=app_postgres
DB_PORT=5432
DB_DATABASE=laravel_devpath
DB_USERNAME=laravel_user
DB_PASSWORD=<пароль из compose>

OLLAMA_HOST=http://nginx:80
OLLAMA_API_KEY=<ваш ключ>
OLLAMA_MODEL=qwen3-coder:480b-cloud

KAFKA_BROKERS=kafka:9092
```

### 3. TLS-сертификаты (CA + server cert)

Сертификаты лежат в `nginx/certs/` и `nginx/private/`. Если нужно пересоздать:

```bash
chmod +x nginx/certs/gen-rsa.sh
./nginx/certs/gen-rsa.sh
```

Чтобы браузер не ругался на HTTPS, установите **CA** (`nginx/certs/ca.pem`) 
как доверенный корневой сертификат:

- **Windows:** «Управление сертификатами» → Доверенные корневые → Импорт.
- **Linux:** скопировать в `/usr/local/share/ca-certificates/` и выполнить `update-ca-certificates`.
- **macOS:** добавить в Keychain Access → System → Always Trust.

### 4. Поднять контейнеры

```bash
docker compose up -d --build
```

### 5. Инициализировать приложение (в контейнере `hr-php`)

```bash
docker exec -it hr-php bash

composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Для разработки фронта:

```bash
npm run dev
```

Vite проксируется через HTTPS-шлюз (порт **8089**), отдельно открывать **5173** не нужно.

### 6. Открыть в браузере

| Сервис | URL |
|--------|-----|
| Приложение | https://localhost:8089 |
| Kafka UI | http://localhost:8080 |
| SonarQube | http://localhost:9000 |
| Grafana | http://localhost:3001 |
| Prometheus | http://localhost:9090 |
| Open WebUI | http://localhost:3000 |
| phpMyAdmin | http://localhost:8081 |
| pgAdmin | http://localhost:8082 |
| Webmail (Roundcube) | http://localhost:8083 |
| Filament Admin | https://localhost:8089/admin |

---

##  Kafka consumers

В `compose.yaml` подняты отдельные worker-контейнеры:

| Контейнер | Artisan-команда | Назначение |
|-----------|-----------------|------------|
| `hr-kafka-cat-consumer` | `kafka:cat-test-consume` | Старт CAT, следующий вопрос, финальная практика |
| `hr-kafka-lesson-consumer` | `kafka:lesson-consume` | Генерация урока, чат ментора, review практики |
| `hr-kafka-code-review-consumer` | `kafka:code-review-consume` | Анализ кода на странице Code Review |
| `hr-kafka-hr-interview-consumer` | `kafka:hr-interview-consume` | Ответы AI HR на сообщения и код |

Локальный запуск вручную (без отдельного контейнера):

```bash
php artisan kafka:lesson-consume
php artisan kafka:cat-test-consume
php artisan kafka:code-review-consume
php artisan kafka:hr-interview-consume
```

---

##  Структура проекта

```
DevPath-LMS/
├── compose.yaml          # вся инфраструктура
├── php/                  # Dockerfile PHP + rdkafka
├── sandbox/              # изолированный запуск кода студента
├── nginx.conf            # прокси к Ollama
├── prometheus/           # конфиг метрик
├── grafana/              # дашборды
└── src/laravel/          # основное приложение
    ├── app/
    │   ├── Http/Controllers/
    │   ├── Service/           # бизнес-логика + промпты
    │   └── Console/Commands/  # Kafka consumers
    ├── resources/js/          # React-страницы (Inertia)
    ├── database/seeders/      # шаблоны курсов, роли
    └── routes/web.php
```

---

##  Основные сценарии

1. **Создать курс:** `/create-course` → CAT-тест → настройки плана → onboarding → workspace
2. **Урок:** выбрать подтему → «Начать урок» → теория → практика → оценка урока ментором
3. **Code Review:** вставить код → `POST /code-review/analyze`
4. **AI HR:** выбрать направление и уровень → диалог → техническое задание
5. **Прогресс:** `/progress` — сбор статистики из БД

---

##  Разработка

```bash
# Логи приложения
docker logs -f hr-php

# Логи consumer уроков
docker logs -f hr-kafka-lesson-consumer

# Миграции
docker exec hr-php php artisan migrate

# Сидеры (шаблоны курсов обязательны для generate-plan)
docker exec hr-php php artisan db:seed

# Тесты
docker exec hr-php php artisan test
```

Отключить Kafka для отладки (синхронный режим) — в `.env`:

```env
KAFKA_LESSON_ASYNC=false
KAFKA_TEST_ASYNC=false
KAFKA_CODE_REVIEW_ASYNC=false
KAFKA_HR_INTERVIEW_ASYNC=false
```

---

##  Почта и подтверждение email

Laravel отправляет письма через **docker-mailserver**. Любой адрес получателя 
сохраняется локально и доступен в **Roundcube**.

### 1. Переменные в `src/laravel/.env`

```env
APP_URL=https://localhost:8089

MAIL_MAILER=smtp
MAIL_HOST=mailserver
MAIL_PORT=25
MAIL_FROM_ADDRESS="noreply@devpath.local"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_AUTO_VERIFY=false
MAIL_CAPTURE_INBOX=inbox@devpath.local
```

### 2. Запуск (команды в терминале)

```bash
# 1. Остановить и удалить контейнеры почты
docker compose stop mailserver webmail
docker compose rm -f mailserver webmail

# 2. Полностью очистить данные почты
sudo rm -rf mail/data
mkdir -p mail/data/{mail-data,mail-state,mail-logs,config}

# 3. Поднять только mailserver и подождать ~60 сек
docker compose up -d mailserver
sleep 60
docker ps | grep mailserver

# 4. Создать ящики (когда статус Up)
docker exec hr-mailserver setup email add noreply@devpath.local <пароль>
docker exec hr-mailserver setup email add inbox@devpath.local <пароль>

# 5. Поднять webmail
docker compose up -d webmail

# 6. Laravel
docker exec hr-php php artisan config:clear
```

> Не используйте `docker restart hr-mailserver` — из-за этого контейнер 
> часто уходит в Restarting. Если это произошло — повторите шаги 1–3.

### 3. Сценарий проверки

1. Зарегистрироваться с **любым** email, например `test@example.com`.
2. Открыть http://localhost:8083 (Roundcube).
3. Войти: `inbox@devpath.local` / `<пароль>`.
4. Открыть письмо — в поле «Кому» будет ваш реальный адрес.
5. Перейти по ссылке подтверждения.

| Ящик | Назначение |
|------|------------|
| `noreply@devpath.local` | Отправитель Laravel |
| `inbox@devpath.local` | Все письма с любым получателем |

Письма **не уходят в интернет** (Gmail их не получит) — они остаются 
на локальном сервере для проверки сценария регистрации.

При `MAIL_MAILER=log` email считается подтверждённым автоматически.

---

## Аварийные ситуации

| Ситуация | Действие |
|----------|----------|
| Сайт не открывается | Проверить `docker compose ps`. Перезапустить: `docker compose up -d --build` |
| Ошибка 504 при анализе кода | Уменьшить объём кода, перезапустить nginx и php: `docker compose restart nginx php` |
| Ошибка 401 от Ollama | `docker exec -it ollama ollama signin` |
| Недостаточно памяти | Освободить RAM, остановить необязательные сервисы |
| Почта не работает | Проверить `hr-mailserver`, повторить шаги 1–3 из раздела «Почта» |
| Ошибка 500 в приложении | Проверить логи: `docker logs -f hr-php` |