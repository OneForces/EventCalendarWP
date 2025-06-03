<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Выводит страницу настроек «Календарь событий» (больше не используется напрямую)
 */
function ec_render_settings_page() {
    ?>
    <div class="wrap" id="ec-settings-container">
        <h1><?php echo esc_html__( 'Настройки календаря событий', 'event-calendar' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'ec_settings_group' );
            do_settings_sections( 'ec_settings_page' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Регистрирует настройки (опции), секции и поля на странице
 */
function ec_register_settings() {
    register_setting( 'ec_settings_group', 'ec_default_view' );
    register_setting( 'ec_settings_group', 'ec_timezone' );
    register_setting( 'ec_settings_group', 'ec_theme', array(
        'default'           => 'auto',
        'sanitize_callback' => 'ec_sanitize_theme_option',
    ) );
    register_setting( 'ec_settings_group', 'ec_max_events_per_day', array(
        'default'           => 5,
        'sanitize_callback' => 'absint',
    ) );
    register_setting( 'ec_settings_group', 'ec_event_delete_after_days', array(
        'default'           => 0,
        'sanitize_callback' => 'absint',
    ) );
    register_setting( 'ec_settings_group', 'ec_yandex_api_key', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    add_settings_section(
        'ec_main_settings',
        esc_html__( 'Основные параметры', 'event-calendar' ),
        '__return_null',
        'ec_settings_page'
    );

    add_settings_field(
        'ec_default_view',
        esc_html__( 'Вид по умолчанию', 'event-calendar' ),
        'ec_render_default_view_field',
        'ec_settings_page',
        'ec_main_settings'
    );

    add_settings_field(
        'ec_timezone',
        esc_html__( 'Часовой пояс', 'event-calendar' ),
        'ec_render_timezone_field',
        'ec_settings_page',
        'ec_main_settings'
    );

    add_settings_field(
        'ec_max_events_per_day',
        esc_html__( 'Макс. событий в день', 'event-calendar' ),
        'ec_render_max_events_field',
        'ec_settings_page',
        'ec_main_settings'
    );

    add_settings_field(
        'ec_event_delete_after_days',
        esc_html__( 'Удаление прошедших мероприятий (дней)', 'event-calendar' ),
        'ec_render_delete_after_days_field',
        'ec_settings_page',
        'ec_main_settings'
    );

    add_settings_field(
        'ec_theme',
        esc_html__( 'Тема календаря', 'event-calendar' ),
        'ec_render_theme_field',
        'ec_settings_page',
        'ec_main_settings'
    );

    add_settings_field(
        'ec_yandex_api_key',
        esc_html__( 'API ключ Яндекс.Карт', 'event-calendar' ),
        'ec_render_yandex_api_key_field',
        'ec_settings_page',
        'ec_main_settings'
    );
}
add_action( 'admin_init', 'ec_register_settings' );

/**
 * Санитизация опции «Тема календаря»
 */
function ec_sanitize_theme_option( $value ) {
    $allowed = array( 'light', 'dark', 'auto' );
    return in_array( $value, $allowed, true ) ? $value : 'auto';
}

/**
 * Рендерит поле «Вид по умолчанию»
 */
function ec_render_default_view_field() {
    $views = [
        'dayGridMonth' => 'Месяц',
        'timeGridWeek' => 'Неделя',
        'timeGridDay'  => 'День',
        'listMonth'    => 'Список',
    ];
    $current = get_option('ec_default_view', 'dayGridMonth');
    echo '<select name="ec_default_view">';
    foreach ($views as $value => $label) {
        $selected = selected($current, $value, false);
        echo "<option value=\"" . esc_attr($value) . "\" $selected>" . esc_html($label) . "</option>";
    }
    echo '</select>';
    echo '<p class="description">Выберите режим отображения календаря по умолчанию.</p>';
}

/**
 * Рендерит поле «Часовой пояс»
 */
function ec_render_timezone_field() {
    $timezones = timezone_identifiers_list();
    $current   = get_option( 'ec_timezone', 'Europe/Moscow' );
    echo '<select name="ec_timezone">';
    foreach ( $timezones as $tz ) {
        $selected = selected( $tz, $current, false );
        echo "<option value=\"" . esc_attr($tz) . "\" $selected>" . esc_html($tz) . "</option>";
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Выберите часовой пояс, который будет использоваться в календаре.', 'event-calendar' ) . '</p>';
}

/**
 * Рендерит поле «Макс. событий в день»
 */
function ec_render_max_events_field() {
    $value = get_option( 'ec_max_events_per_day', 5 );
    echo '<input type="number" name="ec_max_events_per_day" value="' . esc_attr($value) . '" class="small-text" min="1" />';
    echo '<p class="description">' . esc_html__( 'Сколько событий отображать максимум в одной ячейке календаря.', 'event-calendar' ) . '</p>';
}

/**
 * Рендерит поле «Удаление прошедших мероприятий (дней)»
 */
function ec_render_delete_after_days_field() {
    $value = get_option( 'ec_event_delete_after_days', 0 );
    echo '<input type="number" name="ec_event_delete_after_days" value="' . esc_attr($value) . '" class="small-text" min="0" />';
    echo '<p class="description">' . esc_html__( 'Сколько дней хранить прошедшее мероприятие, прежде чем оно будет удалено. 0 — удалить сразу после окончания.', 'event-calendar' ) . '</p>';
}

/**
 * Рендерит поле «Тема календаря»
 */
function ec_render_theme_field() {
    $current = get_option( 'ec_theme', 'auto' );
    ?>
    <select name="ec_theme">
        <option value="light" <?php selected( $current, 'light' ); ?>>
            <?php echo esc_html__( 'Светлая', 'event-calendar' ); ?>
        </option>
        <option value="dark" <?php selected( $current, 'dark' ); ?>>
            <?php echo esc_html__( 'Тёмная', 'event-calendar' ); ?>
        </option>
        <option value="auto" <?php selected( $current, 'auto' ); ?>>
            <?php echo esc_html__( 'Авто (по системной теме)', 'event-calendar' ); ?>
        </option>
    </select>
    <p class="description">
        <?php echo esc_html__( 'Если выбрано «Авто», календарь будет менять тему в соответствии с системными/браузерными настройками пользователя.', 'event-calendar' ); ?>
    </p>
    <?php
}

/**
 * Рендерит поле «API ключ Яндекс.Карт»
 */
function ec_render_yandex_api_key_field() {
    $value = get_option( 'ec_yandex_api_key', '' );
    echo '<input type="text" name="ec_yandex_api_key" value="' . esc_attr( $value ) . '" style="width: 400px;" />';
    echo '<p class="description">' . esc_html__( 'Введите API ключ для Яндекс.Карт. Получить можно на https://developer.tech.yandex.ru/', 'event-calendar' ) . '</p>';
}

