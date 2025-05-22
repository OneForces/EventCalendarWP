<?php

// === 1. Регистрируем страницу настроек ===
function ec_register_settings_page() {
    add_options_page(
        'Настройки календаря',
        'Настройки календаря',
        'manage_options',
        'ec-settings',
        'ec_render_settings_page'
    );
}
add_action('admin_menu', 'ec_register_settings_page');

// === 2. Шаблон страницы настроек ===
function ec_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Настройки календаря мероприятий</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ec_settings_group');
            do_settings_sections('ec-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// === 3. Регистрируем настройки ===
function ec_register_settings() {
    register_setting('ec_settings_group', 'ec_theme');
    register_setting('ec_settings_group', 'ec_default_view');
    register_setting('ec_settings_group', 'ec_max_events_per_day');
    register_setting('ec_settings_group', 'ec_timezone');
    register_setting('ec_settings_group', 'ec_auto_delete_days');

    add_settings_section('ec_main_section', '', null, 'ec-settings');

    add_settings_field('ec_theme', 'Цветовая тема', 'ec_theme_callback', 'ec-settings', 'ec_main_section');
    add_settings_field('ec_default_view', 'Вид по умолчанию', 'ec_default_view_callback', 'ec-settings', 'ec_main_section');
    add_settings_field('ec_max_events_per_day', 'Макс. мероприятий в день', 'ec_max_events_callback', 'ec-settings', 'ec_main_section');
    add_settings_field('ec_timezone', 'Часовой пояс', 'ec_timezone_callback', 'ec-settings', 'ec_main_section');
    add_settings_field('ec_auto_delete_days', 'Удаление старых событий (дней)', 'ec_delete_days_callback', 'ec-settings', 'ec_main_section');
}
add_action('admin_init', 'ec_register_settings');


// === 4. CALLBACKS ===
function ec_theme_callback() {
    $value = get_option('ec_theme', 'light');
    ?>
    <select name="ec_theme">
        <option value="light" <?php selected($value, 'light'); ?>>Светлая</option>
        <option value="dark" <?php selected($value, 'dark'); ?>>Тёмная</option>
    </select>
    <?php
}

function ec_default_view_callback() {
    $value = get_option('ec_default_view', 'dayGridMonth');
    ?>
    <select name="ec_default_view">
        <option value="dayGridMonth" <?php selected($value, 'dayGridMonth'); ?>>Месяц</option>
        <option value="timeGridWeek" <?php selected($value, 'timeGridWeek'); ?>>Неделя</option>
        <option value="timeGridDay" <?php selected($value, 'timeGridDay'); ?>>День</option>
        <option value="listWeek" <?php selected($value, 'listWeek'); ?>>Список</option>
    </select>
    <?php
}

function ec_max_events_callback() {
    $value = get_option('ec_max_events_per_day', 5);
    echo '<input type="number" name="ec_max_events_per_day" min="1" value="' . esc_attr($value) . '" />';
}

function ec_timezone_callback() {
    $value = get_option('ec_timezone', get_option('timezone_string') ?: 'Europe/Moscow');
    echo '<input type="text" name="ec_timezone" value="' . esc_attr($value) . '" placeholder="Europe/Moscow" />';
}

function ec_delete_days_callback() {
    $value = get_option('ec_auto_delete_days', 7);
    echo '<input type="number" name="ec_auto_delete_days" min="1" value="' . esc_attr($value) . '" />';
}
