# Документация для code review

Полное описание устройства сервиса: компоненты, потоки данных, инварианты и осознанные компромиссы. Читается сверху вниз; пути к файлам указаны для каждого утверждения.

## 1. Общая картина

Сервис принимает запрос на массовую рассылку, раскладывает его на отдельные уведомления (по одному на получателя), отправляет их через очереди RabbitMQ мок-провайдерам и отслеживает статус каждого уведомления вплоть до подтверждения доставки.

```
POST /v1/notifications ──> NotificationBatchService ──> PostgreSQL (batch + N notifications)
        │                        │
   Idempotency-Key          dispatch N jobs ──> RabbitMQ (high | low)
                                                     │
                              worker-high / worker-low: SendNotificationJob
                                                     │
                              ProviderRegistry ──> FakeSmsProvider | FakeEmailProvider
                                                     │
                                          status: queued ──> sent
                                                     │ (отложенный DLR)
                              POST /v1/webhooks/delivery ──> DeliveryReportService
                                                     │
                                          status: sent ──> delivered | failed
```

## 2. Стандарты кода (зафиксированы в проекте)

| Стандарт | Где закреплено |
|---|---|
| Pint, пресет `laravel` + обязательный `declare(strict_types=1)` | `pint.json` |
| Larastan, level 6, paths: `app` | `phpstan.neon` |
| Int-backed enum'ы, PascalCase case'ов | `app/Enums/*` |
| Тонкие контроллеры → FormRequest → DTO → Service → Job | `app/Http`, `app/DTO`, `app/Services` |
| Тесты — Pest, классический `$this->` стиль | `tests/` |

Команды: `composer lint`, `composer analyse`, `composer test`.

## 3. Enum'ы: числа внутри, строки на границе

`app/Enums/NotificationStatus.php`, `Channel.php`, `NotificationPriority.php` — все **int-backed**:

```php
enum NotificationStatus: int
{
    case Queued = 1;     // в очереди
    case Sent = 2;       // передано шлюзу
    case Delivered = 3;  // доставка подтверждена
    case Failed = 4;     // отброшено
}
```

В БД хранятся числа (`unsignedSmallInteger`), Eloquent кастит через `$casts` в моделях. Наружу API отдаёт строковые метки — трейт `app/Enums/Concerns/HasLabel.php` даёт `label()` (имя case'а в нижнем регистре), `fromLabel()` / `tryFromLabel()` (парсинг входа API) и `labels()` (для валидационных правил `Rule::in(...)`). Таким образом строковые представления существуют ровно в трёх местах: правила валидации, API Resources и OpenAPI-аннотации — нигде в бизнес-логике строковых статусов нет.

`NotificationStatus::finalStatuses()` — единственное место, где определено множество финальных статусов; `isFinal()` и валидация webhook-отчётов (`DeliveryReportRequest`) выводятся из него.

`NotificationPriority::queue()` — маппинг приоритета на имя очереди (через `config/notifications.php`), единственная точка маршрутизации.

## 4. Схема БД

Миграции: `database/migrations/2026_06_08_00000{1,2}_*.php`.

**notification_batches** — один принятый запрос на рассылку:
- `id` uuid PK
- `idempotency_key` **unique** — якорь дедубликации запросов
- `channel`, `priority` — smallint (enum'ы)
- `text` — текст сообщения (общий для всех получателей)

**notification_messages** — одно сообщение одному получателю:
- `id` uuid PK
- `batch_id` FK → batches, cascade delete
- `recipient_id` string, **индекс** — под запрос истории
- `status` smallint, индекс
- `attempts` — счётчик попыток отправки
- `provider_message_id` nullable, **индекс** — по нему webhook находит уведомление
- `error_message`, `sent_at`, `delivered_at`, `failed_at`
- **unique (batch_id, recipient_id)** — получатель не может встретиться в batch'е дважды, даже при гонках

Инвариант жизненного цикла: `queued → sent → (delivered | failed)`, плюс `queued → failed` (отказ провайдера/исчерпание ретраев). Переходы выполняются только условными `UPDATE ... WHERE status = X` — «перепрыгнуть» или откатить статус невозможно.

## 5. Поток 1: приём рассылки

Файлы по порядку выполнения:

1. `routes/api.php` → `POST /api/v1/notifications`
2. `app/Http/Requests/SendNotificationsRequest.php`
   - `prepareForValidation()`: заголовок `Idempotency-Key` подмешивается в данные; числовые `recipient_ids` приводятся к строкам
   - правила: channel/priority через `Rule::in(Enum::labels())`, получателей 1–10 000
   - `toData()`: собирает immutable DTO `app/DTO/SendNotificationsData.php`, дедуплицирует получателей (`array_unique`), дефолтный приоритет — `Marketing`
3. `app/Http/Controllers/Api/NotificationController.php::store()` — 4 строки: сервис → ресурс → 202 (новая) или 200 (дубликат)
4. `app/Services/NotificationBatchService.php::send()` — ядро идемпотентности:

```
findExisting():  Redis (sha256 от ключа, TTL 24ч)  ──hit──> вернуть существующий batch
                 └─miss─> SELECT по idempotency_key ──hit──> вернуть существующий batch
createBatch():   транзакция: INSERT batch + чанками по 500 INSERT notifications
                 + dispatch SendNotificationJob на очередь приоритета
catch UniqueConstraintViolationException:  проигрыш гонки конкурентному
                 идентичному запросу ──> вернуть batch победителя как дубликат
```

Ключевые гарантии:
- **Redis — только ускоритель**, источник истины — unique constraint в Postgres. Сброс Redis ничего не ломает (покрыто тестом `keeps deduplicating after a redis flush`).
- **`after_commit: true`** у rabbitmq-коннекции (`config/queue.php`) — job'ы публикуются в брокер только после коммита транзакции. Воркер не может получить job для строки, которой ещё нет в БД.

## 6. Поток 2: отправка уведомления

`app/Jobs/SendNotificationJob.php` — конструктор принимает только uuid (минимальный payload в брокере).

- `$tries = 5`, `backoff() = [5, 15, 30, 60]` — экспоненциальные ретраи
- `middleware()`:
  - `RateLimited('provider-gateway')` — общий бюджет вызовов шлюза на все воркеры; лимитер объявлен в `AppServiceProvider::boot()` (Redis-backed, `PROVIDER_RATE_LIMIT_PER_SECOND`, по умолчанию 50 rps)
  - `WithoutOverlapping($notificationId)` — Redis-lock: два воркера не обрабатывают одно уведомление одновременно (защита от дублей при redelivery)

`handle()`:
1. уведомление не найдено → return (мусорное сообщение брокера не валит воркер)
2. **exactly-once guard**: `status !== Queued` → return — повторная доставка уже отправленного сообщения не дергает шлюз
3. `ProviderRegistry::for(channel)` → `send()`
4. исходы:
   - `ProviderRejectedException` (постоянная ошибка) → `$this->fail($e)` — без ретраев, срабатывает `failed()`
   - любое другое исключение (временная ошибка) → записать `attempts`/`error_message`, rethrow → брокер ретраит с backoff
   - успех → условный `UPDATE ... WHERE status = Queued SET status = Sent, provider_message_id, sent_at`

`failed()` (вызывается при `fail()` или исчерпании `$tries`): условный `UPDATE WHERE status = Queued SET Failed` — идемпотентен, финальный статус не перетирает (тест `does not let the failure callback override a final status`).

### Слои exactly-once (поверх at-least-once брокера)

| Слой | Что закрывает |
|---|---|
| 1. Проверка `status !== Queued` | redelivery после успешной обработки |
| 2. `WithoutOverlapping` | два воркера взяли одно сообщение одновременно |
| 3. Дедуп провайдера: `Cache::add("provider:accepted:{uuid}")` в `FakeProvider` | краш воркера **между** `send()` и `UPDATE`: при повторе провайдер вернёт тот же `provider_message_id`, не отправляя второй раз. Реальный шлюз дедуплицировал бы по клиентскому reference — мы передаём UUID уведомления |

## 7. Провайдеры

`app/Services/Providers/`:
- `NotificationProviderInterface` — контракт: `send(Notification): ProviderResponse`; постоянные ошибки — `ProviderRejectedException`, временные — `ProviderTemporarilyUnavailableException` (`app/Exceptions/`)
- `SmsProviderInterface` / `EmailProviderInterface` — маркерные наследники, чтобы биндить и подменять (в т.ч. в тестах) реализации каналов независимо
- `ProviderRegistry` — выбор провайдера по `Channel` (паттерн «реестр/resolver», аналог `Cache::store()`); единственная точка расширения при добавлении канала
- биндинги: `AppServiceProvider::register()` — заменить мок на реальный Twilio/SendGrid = поменять одну строку
- `Fake/FakeProvider.php` (база) + `FakeSmsProvider` / `FakeEmailProvider` — отличаются только `channel()`

Поведение мока управляется префиксом `recipient_id`:

| Префикс | Поведение | Демонстрирует |
|---|---|---|
| `fail-` | `ProviderRejectedException` сразу | постоянный отказ, без ретраев |
| `flaky-` | временная ошибка 1-й и 2-й раз (счётчик в Redis), успех с 3-го | retry с backoff |
| `undeliverable-` | принято, но DLR отрицательный | `sent → failed` |
| прочее | принято, DLR положительный | `sent → delivered` |

После «принятия» мок ставит отложенный (`DELIVERY_CALLBACK_DELAY_SECONDS`, 2 сек) `SimulateProviderCallbackJob` — имитация того, что реальный шлюз через мгновение постучится в наш webhook. Job вызывает тот же `DeliveryReportService`, что и публичный webhook — один код на оба пути.

## 8. Поток 3: отчёт о доставке (DLR)

1. `POST /api/v1/webhooks/delivery` → `DeliveryWebhookController::store()`
2. `DeliveryReportRequest` — `provider_message_id` + `status` (только метки `Delivered`/`Failed`, без строковых литералов: `Rule::in([...->label()])`); `status()` возвращает enum
3. `app/Services/DeliveryReportService.php::apply()`:
   - `match` по enum строит атрибуты перехода; не-финальный статус → `InvalidArgumentException` (защита от неправильного использования сервиса из кода)
   - условный `UPDATE WHERE provider_message_id = ? AND status = Sent`
   - 0 строк → отчёт неизвестный/повторный/запоздавший → `applied: false` + warning в лог

Webhook **идемпотентен** и отвечает 200 даже на неизвестный id — реальные провайдеры ретраят отчёты при не-2xx, отвечать им 404/409 означало бы бесконечные повторы.

## 9. Поток 4: история подписчика

`GET /api/v1/recipients/{id}/notifications` → `RecipientNotificationController::index()` → `ListRecipientNotificationsRequest::toFilters()` (DTO `NotificationHistoryFilters`) → `NotificationHistoryService::forRecipient()`:
- фильтры `status`, `channel` (валидация по `labels()`), `per_page` ≤ 100
- `with('batch')` — без N+1 (канал/приоритет/текст лежат в batch)
- сортировка: новые первыми; стандартная пагинация Laravel (`data` / `links` / `meta`)
- `NotificationResource` отдаёт все таймстампы жизненного цикла: `queued_at`, `sent_at`, `delivered_at`, `failed_at`

## 10. Конфигурация

`config/notifications.php` — все доменные ручки в одном файле:

| Ключ | Env | Default |
|---|---|---|
| `queues.high` / `queues.low` | `NOTIFICATIONS_QUEUE_HIGH/LOW` | `notifications_high/low` |
| `idempotency_ttl` | `IDEMPOTENCY_TTL` | 86400 |
| `rate_limit_per_second` | `PROVIDER_RATE_LIMIT_PER_SECOND` | 50 |
| `delivery_callback_delay` | `DELIVERY_CALLBACK_DELAY_SECONDS` | 2 |

`config/queue.php` — коннекция `rabbitmq` (драйвер `vladimir-yuldashev/laravel-queue-rabbitmq`, `after_commit: true`).

## 11. Docker

- `Dockerfile` — `php:8.4-fpm-alpine`; расширения: `pdo_pgsql`, `sockets` (нужен php-amqplib), `pcntl`, `opcache`, `bcmath`. Слой зависимостей кэшируется отдельно от кода. `touch .env` — пустой файл, чтобы phpdotenv не ругался: вся конфигурация приходит реальными env-переменными.
- `docker/entrypoint.sh` — ждёт Postgres/RabbitMQ/Redis (`nc -z`); миграции и `l5-swagger:generate` выполняет **только** контейнер с `RUN_MIGRATIONS=1` (app), чтобы воркеры не гонялись за миграциями.
- `docker-compose.yml` — YAML-якоря (`x-app-environment`, `x-app-service`) против копипасты; сервисы: `app` (fpm), `nginx` (:8080), `worker-high`, `worker-low`, `postgres`, `rabbitmq` (UI :15672), `redis`. Наружу опубликованы только 8080 и 15672 — меньше конфликтов портов. `APP_KEY` фиксированный демо-ключ (осознанно: zero-step запуск тестового задания; для прода — секрет-менеджмент).
- `docker/postgres/init.sql` — создаёт `notifications_test`, чтобы тесты не трогали демо-данные.
- `docker/nginx/default.conf` — fastcgi-прокси на `app:9000`; `SCRIPT_FILENAME` указывает путь внутри app-контейнера.

## 12. Тесты

`phpunit.xml`: все `<env force="true"/>` — **обязательно**, иначе env из docker-compose перекрыл бы тестовые значения и `RefreshDatabase` стёр бы боевую БД. Тесты идут в `notifications_test` (Postgres) и Redis db 15, очередь `sync`.

| Файл | Покрывает |
|---|---|
| `SendNotificationsApiTest` | валидация (включая обязательность `Idempotency-Key`), маршрутизация в high/low очередь, идемпотентность: дубликат → 200 + тот же batch + без повторного dispatch; выживание дедубликации после `Cache::flush()`; схлопывание повторных получателей |
| `SendNotificationJobTest` | полная цепочка `queued → sent → delivered` (DLR-job выполняется явно после завершения отправки — как в реальности); провайдер вызывается **ровно один раз** при redelivery (мок с `->once()`); постоянный отказ → `failed`; flaky: ровно 2 временные ошибки, затем `sent`; исчерпание ретраев → `failed`; `failed()` не перетирает финальный статус |
| `DeliveryWebhookTest` | положительный/отрицательный отчёт, дубликат отчёта → `applied:false`, неизвестный id → 200, отказ не-финальным статусам |
| `RecipientHistoryTest` | состав и порядок истории, фильтры, пагинация, валидация фильтров |

## 13. Известные компромиссы (на что смотреть при ревью)

Честный список того, что упрощено осознанно или является остаточным риском:

1. **Окно дублирования отправки.** Краш воркера между `provider->send()` и `UPDATE ... SET Sent` закрыт дедупом провайдера через Redis (`provider:accepted:{uuid}`, TTL 24ч). Если краш + redelivery случатся с разницей > TTL — отправка повторится. Для прода: дедуп на стороне реального шлюза по client reference (мы его уже передаём) или outbox-таблица.
2. **`attempts` не обновляется в `failed()`** — при мгновенном отказе провайдера в истории остаётся `attempts: 0`, хотя попытка была. Косметика, легко добавить.
3. **`WithoutOverlapping->releaseAfter(5)`** — release из-за занятого lock'а тоже инкрементирует счётчик попыток job'а; при экстремальной контеншн теоретически можно исчерпать `$tries` без единого вызова шлюза.
4. **Enum-значения в OpenAPI-аннотациях — строковые литералы** (`enum: ['sms', 'email']`). PHP-атрибуты принимают только константные выражения, вызвать `Channel::labels()` там нельзя. При добавлении case'а документацию нужно править руками.
5. **Нет аутентификации** ни на API, ни на webhook (реальный DLR-endpoint проверял бы подпись провайдера). Вне скоупа задания.
6. **`recipient_id` — произвольная строка**, формат телефона/email не валидируется: по заданию это «идентификаторы получателей», резолв контактов — забота другого сервиса.
7. **Dispatch N job'ов поштучно** после вставки чанками: при 10 000 получателей это 10 000 publish'ей в RabbitMQ внутри запроса. Приемлемо для задания; для прода — `Bus::batch` или отдельный «fan-out» job.
8. **`SimulateProviderCallbackJob` едет через ту же очередь приоритета**, что и уведомление. В реальности DLR приходит по HTTP и от очередей не зависит; это артефакт имитации.
9. **Фиксированный `APP_KEY` в docker-compose** — см. раздел 11.

## 14. Чек-лист ручной проверки

```bash
docker compose up -d --build          # всё поднимается одной командой
docker compose exec app php artisan test   # 24 passed

# happy path + все исходы:
curl -X POST http://localhost:8080/api/v1/notifications \
  -H 'Idempotency-Key: review-1' -H 'Content-Type: application/json' \
  -d '{"channel":"sms","priority":"transactional","text":"Code 1234",
       "recipient_ids":["42","fail-1","flaky-2","undeliverable-3"]}'
# повторить тот же запрос → 200, duplicate:true

sleep 40
curl http://localhost:8080/api/v1/recipients/42/notifications        # delivered
curl http://localhost:8080/api/v1/recipients/flaky-2/notifications   # delivered, attempts:3
curl http://localhost:8080/api/v1/recipients/fail-1/notifications    # failed
```

Swagger UI: http://localhost:8080/api/documentation. Очереди и консьюмеры: http://localhost:15672.
