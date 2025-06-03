<?php
/**
 * Plugin Name:     Календарь событий
 * Plugin URI:      https://example.com/event-calendar
 * Description:     Календарь мероприятий с CPT «ec_event», таксономиями, FullCalendar и CSV-экспортом.
 * Version:         1.1
 * Author:          Ваше Имя
 * Author URI:      https://example.com
 * Text Domain:     event-calendar
 * Domain Path:     /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Определяем основные константы плагина
 */
define( 'EC_PLUGIN_VERSION', '1.1' );
define( 'EC_PLUGIN_FILE',    __FILE__ );
define( 'EC_PLUGIN_PATH',    plugin_dir_path( __FILE__ ) );
define( 'EC_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'EC_TEXT_DOMAIN',    'event-calendar' );

/**
 * Загрузка текста домена для перевода
 */
add_action( 'plugins_loaded', 'ec_load_textdomain' );
function ec_load_textdomain() {
    load_plugin_textdomain(
        EC_TEXT_DOMAIN,
        false,
        dirname( plugin_basename( EC_PLUGIN_FILE ) ) . '/languages'
    );
}

/**
 * Подключаем все необходимые файлы плагина
 */
require_once EC_PLUGIN_PATH . 'includes/functions.php';
require_once EC_PLUGIN_PATH . 'includes/ajax-handler.php';
require_once EC_PLUGIN_PATH . 'includes/settings.php';
require_once EC_PLUGIN_PATH . 'includes/cpt-event.php';
require_once EC_PLUGIN_PATH . 'includes/meta-boxes.php';
require_once EC_PLUGIN_PATH . 'includes/taxonomies.php';
require_once EC_PLUGIN_PATH . 'includes/export.php';
require_once EC_PLUGIN_PATH . 'includes/template-override.php';
require_once EC_PLUGIN_PATH . 'includes/calendar-shortcode.php';

/**
 * Регистрируем шорткод [event_list]
 */
add_shortcode( 'event_list', 'ec_event_list_shortcode' );

/**
 * Подключаем стили и скрипты для фронтенда
 */
add_action( 'wp_enqueue_scripts', 'ec_enqueue_front_assets' );
function ec_enqueue_front_assets() {
    // FullCalendar CSS (CDN)
    wp_enqueue_style(
        'fullcalendar-css',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css',
        array(),
        '6.1.8'
    );

    // FullCalendar JS (глобальный бандл, зависит от jQuery)
    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
        array( 'jquery' ),
        '6.1.8',
        true
    );

    // Локализация FullCalendar на русский
    wp_enqueue_script(
        'fullcalendar-locales',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/ru.global.min.js',
        array( 'fullcalendar-js' ),
        '6.1.8',
        true
    );

    // Скрипт инициализации календаря
    wp_enqueue_script(
        'ec-calendar-js',
        EC_PLUGIN_URL . 'assets/js/calendar.js',
        array( 'jquery', 'fullcalendar-js', 'fullcalendar-locales' ),
        EC_PLUGIN_VERSION,
        true
    );

    // Передаем в JS необходимые данные (AJAX URL, настройки, тема)
    wp_localize_script(
        'ec-calendar-js',
        'ec_calendar_data',
        array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'default_view' => get_option( 'ec_default_view', 'dayGridMonth' ),
            'timezone' => get_option( 'ec_timezone', 'Europe/Moscow' ),
            'home_url'     => esc_url( home_url() ),
            'plugin_url'   => EC_PLUGIN_URL,
            'nonce'        => wp_create_nonce( 'ec_get_events_nonce' ),
            'theme'        => get_option( 'ec_theme', 'auto' ),
            'max_events_per_day' => get_option( 'ec_max_events_per_day', 5 ),
        )
    );

    // Подключаем стили для фронтенда: тёмная тема, стили single-ec_event и FullCalendar
    wp_enqueue_style(
        'admin-event-calendar-css',
        plugin_dir_url( __FILE__ ) . 'assets/css/admin-event-calendar.css',
        array(),
        '1.1'
    );
}

/**
 * Подключаем стили и скрипты для админки (CPT ec_event и страница настроек)
 */
add_action( 'admin_enqueue_scripts', 'ec_enqueue_admin_assets' );
function ec_enqueue_admin_assets( $hook_suffix ) {
    // Если мы на экране создания/редактирования CPT «ec_event»
    if (
        ( $hook_suffix === 'post-new.php' || $hook_suffix === 'post.php' ) &&
        get_post_type() === 'ec_event'
    ) {
        wp_enqueue_style(
            'ec-admin-calendar',
            EC_PLUGIN_URL . 'assets/css/admin-event-calendar.css',
            array(),
            EC_PLUGIN_VERSION
        );
    }

    // Если мы на странице настроек плагина
    if ( $hook_suffix === 'settings_page_ec_settings_page' ) {
        wp_enqueue_style(
            'ec-admin-calendar',
            EC_PLUGIN_URL . 'assets/css/admin-event-calendar.css',
            array(),
            EC_PLUGIN_VERSION
        );
    }
}

/**
 * Добавляем страницу настроек в подменю CPT «ec_event»
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=ec_event',
        __('Настройки календаря', EC_TEXT_DOMAIN),
        __('Настройки', EC_TEXT_DOMAIN),
        'manage_options',
        'ec_settings_page',
        'ec_render_settings_page'
    );
});

/**
 * Регистрируем настройки
 */
add_action('admin_init', 'ec_register_settings');

// === КРОН: Регистрация / Удаление задачи при активации и деактивации ===

register_activation_hook( __FILE__, 'ec_activate_plugin' );
register_deactivation_hook( __FILE__, 'ec_deactivate_plugin' );

function ec_activate_plugin() {
    if ( ! wp_next_scheduled( 'ec_delete_old_events_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'ec_delete_old_events_cron' );
    }
}

function ec_deactivate_plugin() {
    $timestamp = wp_next_scheduled( 'ec_delete_old_events_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'ec_delete_old_events_cron' );
    }
}
