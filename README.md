# GustoLocal Staging Environment

Staging окружение для тестирования изменений перед деплоем на production.

## 🚀 Быстрый старт

1. **Прочитайте полную инструкцию:**
   ```bash
   cat STAGING-SETUP.md
   ```

2. **Создайте .env.local:**
   ```bash
   cp .env.local.example .env.local
   # Отредактируйте .env.local и заполните реальными значениями
   ```

3. **Следуйте инструкциям в STAGING-SETUP.md**

## 📋 Основные команды

```bash
# Деплой на staging
./scripts/deploy-to-staging.sh

# Подключение к staging БД
./scripts/db-connect.sh stg

# WP-CLI команды
./scripts/wp-cli.sh stg "plugin list"
./scripts/wp-cli.sh stg "option get siteurl"

# Коммит в Git
./scripts/git-commit.sh "Описание изменений"
```

## 🔗 Ссылки

- **Staging сайт**: https://staging.gustolocal.es
- **Staging Admin**: https://staging.gustolocal.es/wp-admin
- **GitHub**: https://github.com/edolohov/gustolocal_stg

## ⚠️ Важно

- Все пароли и токены хранятся в `.env.local` (НЕ в Git!)
- `wp-config.php` также не коммитится в Git
- После настройки удалите пароли из `STAGING-SETUP.md`

