<?php
/**
 * functions.php
 * 
 * Служебные функции плагина Event Calendar:
 * – Шорткод [event_list]
 * – Экспорт событий в iCal/ICS
 * – Автоудаление прошедших мероприятий по WP-Cron
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* === 1. Шорткод: Список ближайших событий [event_list] === */
function ec_event_list_shortcode( $atts ) {
    ob_start();
    $now = current_time( 'mysql' );
    $args = [
        'post_type'      => 'ec_event',
        'posts_per_page' => 5,
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'ec_event_end',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ],
            [
                'key'     => 'ec_event_start',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ],
        ],
        'orderby'   => 'meta_value',
        'meta_key'  => 'ec_event_start',
        'order'     => 'ASC',
    ];

    $events = new WP_Query( $args );
    if ( $events->have_posts() ) {
        echo '<ul class="ec-upcoming-events">';
        while ( $events->have_posts() ) {
            $events->the_post();
            $start = get_post_meta( get_the_ID(), 'ec_event_start', true );
            $end   = get_post_meta( get_the_ID(), 'ec_event_end',   true );
            echo '<li>';
            echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a> ';
            echo '<span style="color: #666;">' . date( 'j.m.Y', strtotime( $start ) );
            if ( $end && $end !== $start ) {
                echo ' – ' . date( 'j.m.Y', strtotime( $end ) );
            }
            echo '</span>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Нет предстоящих мероприятий.</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'event_list', 'ec_event_list_shortcode' );

/* === 2. Экспорт всех событий в iCal/ICS === */
// 2.1. Регистрируем query_var для ?ec_export_ics=1
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ec_export_ics';
    return $vars;
});

// 2.2. Проверяем URL-параметр и, если он установлен, генерируем .ics
add_action( 'template_redirect', function() {
    $export_flag = get_query_var( 'ec_export_ics' );
    if ( $export_flag && intval( $export_flag ) === 1 ) {
        ec_generate_ics_export();
        exit;
    }
});

// 2.3. Собираем события и генерируем ICS-файл
function ec_generate_ics_export() {
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="events-calendar-' . date( 'Ymd' ) . '.ics"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//" . esc_attr( parse_url( home_url(), PHP_URL_HOST ) ) . "//Event Calendar//RU\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:PUBLISH\r\n";

    $events = get_posts([
        'post_type'      => 'ec_event',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    foreach ( $events as $event ) {
        $event_id  = $event->ID;
        $title     = get_the_title( $event_id );
        $permalink = get_permalink( $event_id );
        $date_start = get_post_meta( $event_id, 'ec_event_start', true );
        $date_end   = get_post_meta( $event_id, 'ec_event_end',   true );
        if ( ! $date_start ) continue;

        $dtstart = gmdate( 'Ymd\THis\Z', strtotime( $date_start ) );
        $dtend   = $date_end ? gmdate( 'Ymd\THis\Z', strtotime( $date_end ) )
                             : gmdate( 'Ymd\THis\Z', strtotime( '+1 hour', strtotime( $date_start ) ) );

        $description = wp_strip_all_tags( get_post_field( 'post_content', $event_id ) );
        $description = str_replace( [ "\r", "\n" ], '\\n', $description );
        $host = parse_url( home_url(), PHP_URL_HOST );
        $uid  = 'event-' . $event_id . '@' . $host;

        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . esc_attr( $uid ) . "\r\n";
        echo "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
        echo "DTSTART:" . esc_attr( $dtstart ) . "\r\n";
        echo "DTEND:" . esc_attr( $dtend ) . "\r\n";
        echo "SUMMARY:" . esc_attr( $title ) . "\r\n";
        echo "DESCRIPTION:" . esc_attr( $description ) . "\r\n";
        echo "URL:" . esc_url( $permalink ) . "\r\n";
        echo "END:VEVENT\r\n";
    }
    echo "END:VCALENDAR\r\n";
}

/* === 3. Автоудаление прошедших мероприятий по WP-Cron === */

// 3.1 Регистрация и удаление cron-задачи при активации/деактивации
register_activation_hook(__FILE__, 'ec_activate_plugin_with_cron');
register_deactivation_hook(__FILE__, 'ec_deactivate_plugin_with_cron');

function ec_activate_plugin_with_cron() {
    ec_register_event_cpt();
    if ( ! wp_next_scheduled( 'ec_delete_old_events_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'ec_delete_old_events_cron' );
    }
}
function ec_deactivate_plugin_with_cron() {
    $timestamp = wp_next_scheduled( 'ec_delete_old_events_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'ec_delete_old_events_cron' );
    }
}

// 3.2 Привязка cron-хука к функции-удалялке
add_action( 'ec_delete_old_events_cron', 'ec_delete_old_events' );

// 3.3 Функция удаления старых мероприятий
function ec_delete_old_events() {
    // Логируем факт вызова
    file_put_contents(
        plugin_dir_path( EC_PLUGIN_FILE ) . 'cron_debug.log',
        "ec_delete_old_events запущен: " . date( 'Y-m-d H:i:s' ) . "\r\n",
        FILE_APPEND
    );

    $days = absint( get_option( 'ec_event_delete_after_days', 0 ) );
    $threshold_date = date(
        'Y-m-d H:i:s',
        strtotime( current_time( 'mysql' ) . " -{$days} days" )
    );

    $args = [
        'post_type'      => 'ec_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'ec_event_end',
                'value'   => $threshold_date,
                'compare' => '<',
                'type'    => 'DATETIME',
            ],
        ],
        'fields' => 'ids',
    ];

    $old_events = get_posts( $args );

    if ( ! empty( $old_events ) ) {
        foreach ( $old_events as $post_id ) {
            file_put_contents(
                plugin_dir_path( EC_PLUGIN_FILE ) . 'cron_debug.log',
                "  → удаляем событие ID={$post_id}, ec_event_end=" . get_post_meta( $post_id, 'ec_event_end', true ) . "\r\n",
                FILE_APPEND
            );
            wp_delete_post( $post_id, true );
        }
    }
}
add_action('ec_delete_old_events_cron', 'ec_delete_old_events');
