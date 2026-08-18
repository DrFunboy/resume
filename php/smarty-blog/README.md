Запуск окружения
```
docker compose up -d
```
Для переустановки без кэша
```
docker compose -f docker-compose.yml up -d --build --remove-orphans --force-recreate
```
Сидер
```
php sql/seed.php
```
scss
```
sass public/assets/scss/style.scss public/assets/css/style.css
```

P.S. Картинки логичнее перенести в public