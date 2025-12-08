# Структура доступов для Staging окружения

**ВАЖНО:** Все реальные пароли и токены хранятся в `.env.local` (НЕ в git!)

## Файлы с credentials

### `.env.local` (локальный файл, НЕ коммитится)
Содержит все пароли, токены и секретные данные:
- GitHub токен
- SFTP/SSH доступы
- Пароли баз данных (staging и production)
- WordPress admin пароль

### `wp-config.php` (локальный файл, НЕ коммитится)
Содержит пароли БД для staging окружения.

## Структура доступов

### GitHub
- **Репозиторий:** `edolohov/gustolocal_stg`
- **Токен:** хранится в `.env.local` (GITHUB_TOKEN)
- **Ветка:** `main`

### SFTP/SSH
- **Хост:** `82.29.185.42`
- **Порт:** `65002`
- **Пользователь:** `u850527203`
- **Пароль:** хранится в `.env.local` (SFTP_PASS или используется общий пароль)
- **Путь на сервере:** `/home/u850527203/domains/gustolocal.es/public_html/staging`

### База данных - Staging
- **Имя БД:** `u850527203_stg`
- **Пользователь:** `u850527203_stg`
- **Пароль:** хранится в `.env.local` (DB_STG_PASS)
- **Хост:** `localhost`
- **Префикс таблиц:** `staging_`

### База данных - Production (для справки)
- **Имя БД:** `u850527203_5vYEq`
- **Пользователь:** `u850527203_ZmKMJ`
- **Пароль:** хранится в `.env.local` (DB_PROD_PASS)
- **Хост:** `localhost`

### WordPress Admin
- **URL:** `https://staging.gustolocal.es/wp-admin`
- **Логин:** `admin`
- **Пароль:** хранится в `.env.local` (WP_ADMIN_PASS)

## Как загрузить credentials

Используйте скрипт `scripts/load-env.sh`:
```bash
source scripts/load-env.sh
```

Или вручную:
```bash
export $(cat .env.local | grep -v '^#' | grep -v '^$' | xargs)
```

## Безопасность

- ✅ `.env.local` в `.gitignore` - никогда не коммитится
- ✅ `wp-config.php` в `.gitignore` - никогда не коммитится
- ✅ Все пароли хранятся только локально
- ✅ В git коммитятся только скрипты и документация (без паролей)

