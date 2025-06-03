
# 📅 Event Calendar Plugin for WordPress

Мощный и гибкий плагин WordPress для создания календарей событий с поддержкой:

✅ Темной/светлой темы  
✅ Шорткодов и шаблонов  
✅ Кастомных типов записей (CPT)  
✅ Cron-очистки прошедших событий  
✅ Фильтрации по типу, организатору  
✅ Экспорта в формат .ICS  
✅ Админ-настроек и i18n

---

## 🔧 Установка

1. Скопируйте папку `event-calendar` в директорию `wp-content/plugins/`
2. Активируйте плагин через панель администратора WordPress
3. Добавьте шорткод `[event_calendar]` на нужную страницу

---

## 🧩 Доступные шорткоды

| Шорткод           | Назначение                                      |
|-------------------|-------------------------------------------------|
| `[event_calendar]`| Отображение календаря с фильтрами и стилями     |
| `[event_list]`    | Вывод блока «Предстоящие мероприятия»           |

---

## 📁 Структура плагина

```bash
event-calendar/
├── event-calendar.php           # Главный файл плагина
├── uninstall.php                # Скрипт удаления
├── single-ec_event.php          # Шаблон отдельного события
├── includes/                    # Логика CPT, мета-поля, cron, фильтры
├── templates/                   # Шаблоны вывода
├── assets/css/                  # Стили календаря (включая темную тему)
├── assets/js/                   # Скрипты FullCalendar и фильтрации
└── admin/                       # Настройки в админке WordPress
```

---

## ⚙️ Возможности

- ✅ Регистрация CPT `ec_event`
- ✅ Поддержка FullCalendar с кастомным видом
- ✅ Настройки вида (месяц, неделя, день, список)
- ✅ Выбор темы: светлая, тёмная, авто
- ✅ Поддержка тёмной темы через `prefers-color-scheme`
- ✅ Ежедневное удаление прошедших событий через `wp-cron`
- ✅ Расширяемый шаблон отдельного события (`single-ec_event.php`)
- ✅ Локализация (поддержка переводов .po/.mo)

---

## 🔁 Cron-задача

Плагин запускает задачу `ec_delete_old_events` каждый день.  
Параметр `ec_event_delete_after_days` задаёт порог удаления (по умолчанию 0).

Можно добавить логирование:

```php
file_put_contents(
  plugin_dir_path( EC_PLUGIN_FILE ) . 'cron_debug.log',
  "ec_delete_old_events запущен: " . date('Y-m-d H:i:s') . "\r\n",
  FILE_APPEND
);
```

---

## ⚙️ Админ-настройки

Перейдите в меню **Настройки → Календарь событий**, чтобы:

- Выбрать отображение по умолчанию (`month`, `week`, `list`, `day`)
- Установить часовой пояс
- Настроить тему (авто, тёмная, светлая)
- Настроить автоудаление прошедших мероприятий

---

## 🌐 Поддержка и перевод

Плагин готов для перевода с использованием `.po/.mo`.  
Загрузите локализации в `languages/` и используйте `load_plugin_textdomain`.

---

## 📜 Лицензия

Лицензия: MIT  
Автор: [GennadiyVtoroy](https://github.com/GennadiyVtoroy)  
Дата: 2025

---

## 🧠 Примеры кастомизации

**Вывод фильтра по типу события:**
```php
<select name="event_type">
  <?php foreach ( get_terms( 'ec_event_type' ) as $term ): ?>
    <option value="<?= esc_attr( $term->slug ) ?>"><?= esc_html( $term->name ) ?></option>
  <?php endforeach; ?>
</select>
```

**Интеграция с Яндекс.Картами:**
```php
<script src="https://api-maps.yandex.ru/2.1/?apikey=ВАШ_API_КЛЮЧ&lang=ru_RU" type="text/javascript"></script>
<div id="map" style="width: 100%; height: 400px;"></div>
<script>
ymaps.ready(function () {
    var map = new ymaps.Map("map", {
        center: [55.76, 37.64],
        zoom: 10
    });
});
</script>
```

---

## 📥 Экспорт в iCalendar (.ICS)

Доступен экспорт всех мероприятий по URL-параметру:

```
https://yourdomain.com/?ec_export_ics=1
```

Это создаёт `.ics` файл с предстоящими событиями для импорта в Google Calendar, Outlook и т.д.

---

## 🔒 Требования

- WordPress 5.5+
- PHP 7.4+
