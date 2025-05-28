<?php
/**
 * Plugin Name: Event Calendar
 * Description: Календарь мероприятий с кастомными типами событий.
 * Version: 1.0
 * Author: [Твоё Имя]
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ajax-handler.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/cpt-event.php';
require_once __DIR__ . '/includes/meta-boxes.php';
require_once __DIR__ . '/includes/taxonomies.php';

require_once plugin_dir_path(__FILE__) . 'includes/functions.php';


add_action('wp_enqueue_scripts', 'ec_enqueue_calendar_assets');

function ec_enqueue_calendar_assets() {
    // 📦 Стили
    wp_enqueue_style(
        'fullcalendar-css',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css'
    );
    wp_enqueue_style(
        'ec-calendar-css',
        plugin_dir_url(__FILE__) . 'assets/css/calendar.css'
    );

    // 📅 Скрипты FullCalendar
    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'fullcalendar-locales',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/ru.global.min.js',
        ['fullcalendar-js'],
        null,
        true
    );

    wp_enqueue_script(
        'ec-calendar-js',
        plugin_dir_url(__FILE__) . 'assets/js/calendar.js',
        ['fullcalendar-js', 'fullcalendar-locales'],
        null,
        true
    );

    wp_enqueue_script(
        'yandex-maps',
        'https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=...',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'ec-yamap',
        plugin_dir_url(__FILE__) . 'assets/js/yamap.js',
        ['yandex-maps'],
        null,
        true
    );

    // 🔧 Передача параметров в JS
    wp_localize_script('ec-calendar-js', 'ec_calendar_data', [
        'ajax_url'     => admin_url('admin-ajax.php'),
        'default_view' => get_option('ec_default_view', 'dayGridMonth'),
        'timezone'     => get_option('ec_timezone', wp_timezone_string()),
    ]);
}





add_action('plugins_loaded', 'ec_load_textdomain');
function ec_load_textdomain() {
    load_plugin_textdomain('event-calendar', false, dirname(plugin_basename(__FILE__)) . '/languages');
}




add_shortcode('event_list', 'ec_event_list_shortcode');

function ec_event_list_shortcode($atts) {
    $atts = shortcode_atts(['count' => 10], $atts);

    $query = new WP_Query([
        'post_type'      => 'ec_event',
        'posts_per_page' => intval($atts['count']),
        'post_status'    => 'publish',
        'orderby'        => 'meta_value',
        'meta_key'       => 'ec_event_start',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'ec_event_start',
                'value'   => current_time('Y-m-d H:i:s'),
                'compare' => '>=',
                'type'    => 'DATETIME',
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return '<p>Нет предстоящих мероприятий.</p>';
    }

    $html = '<ul class="ec-event-list">';
    while ($query->have_posts()) {
        $query->the_post();
        $start = get_post_meta(get_the_ID(), 'ec_event_start', true);
        $date  = $start ? date_i18n('d.m.Y H:i', strtotime($start)) : '';

        $html .= sprintf(
            '<li class="ec-event-list-item">
                <a class="ec-event-link" href="%s">%s</a>
                <span class="ec-event-date">%s</span>
            </li>',
            esc_url(get_permalink()),
            esc_html(get_the_title()),
            esc_html($date)
        );
    }
    wp_reset_postdata();
    $html .= '</ul>';

    return $html;
}



add_action('wp_ajax_ec_get_events', 'ec_handle_get_events');
add_action('wp_ajax_nopriv_ec_get_events', 'ec_handle_get_events');

add_action('wp_ajax_ec_get_events', 'ec_handle_get_events');
add_action('wp_ajax_nopriv_ec_get_events', 'ec_handle_get_events');

function ec_handle_get_events() {
    $args = [
        'post_type' => 'ec_event',
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'orderby' => 'meta_value',
        'meta_key' => 'ec_event_start',
        'order' => 'ASC',
        'meta_query' => [],
        'tax_query' => [],
    ];

    // Дата (из FullCalendar приходит через POST)
    if (!empty($_POST['start']) && !empty($_POST['end'])) {
        $args['meta_query'][] = [
            'key'     => 'ec_event_start',
            'value'   => [sanitize_text_field($_POST['start']), sanitize_text_field($_POST['end'])],
            'compare' => 'BETWEEN',
            'type'    => 'DATETIME',
        ];
    }

    // Тип
    if (!empty($_POST['type'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'ec_event_type',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_POST['type']),
        ];
    }

    // Организатор
    if (!empty($_POST['organizer'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'ec_organizer',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_POST['organizer']),
        ];
    }

    // Поиск
    if (!empty($_POST['search'])) {
        $args['s'] = sanitize_text_field($_POST['search']);
    }

    $query = new WP_Query($args);
    $events = [];

    while ($query->have_posts()) {
        $query->the_post();
        $start = get_post_meta(get_the_ID(), 'ec_event_start', true);
        $end   = get_post_meta(get_the_ID(), 'ec_event_end', true);
        $all_day = get_post_meta(get_the_ID(), 'ec_event_all_day', true);

        // Цвета из таксономии
        $color = '#3788D8';
        $text  = '#fff';
        $terms = get_the_terms(get_the_ID(), 'ec_event_type');
        if (!empty($terms) && is_array($terms)) {
            $term = $terms[0];
            $color = get_term_meta($term->term_id, 'ec_background_color', true) ?: $color;
            $text  = get_term_meta($term->term_id, 'ec_text_color', true) ?: $text;
        }

        $events[] = [
            'title' => get_the_title(),
            'start' => $start,
            'end'   => $end ?: $start,
            'allDay' => $all_day === '1',
            'url'   => get_permalink(),
            'backgroundColor' => $color,
            'textColor'       => $text,
        ];
    }

    wp_reset_postdata();
    wp_send_json($events);
}


register_activation_hook(__FILE__, 'ec_fix_datetime_format');

register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
});


register_activation_hook(__FILE__, 'ec_fix_datetime_format');

function ec_fix_datetime_format() {
    global $wpdb;

    $meta_keys = ['ec_event_start', 'ec_event_end'];

    foreach ($meta_keys as $meta_key) {
        $rows = $wpdb->get_results(
            $wpdb->prepare("
                SELECT meta_id, meta_value 
                FROM {$wpdb->postmeta}
                WHERE meta_key = %s AND meta_value LIKE '%%T%%'
            ", $meta_key)
        );

        foreach ($rows as $row) {
            $fixed = str_replace('T', ' ', $row->meta_value);
            $wpdb->update(
                $wpdb->postmeta,
                ['meta_value' => $fixed],
                ['meta_id' => $row->meta_id],
                ['%s'],
                ['%d']
            );
        }
    }
}







register_activation_hook(__FILE__, 'ec_schedule_cleanup');
function ec_schedule_cleanup() {
    if (!wp_next_scheduled('ec_daily_event_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ec_daily_event_cleanup');
    }
}

register_deactivation_hook(__FILE__, 'ec_unschedule_cleanup');
function ec_unschedule_cleanup() {
    wp_clear_scheduled_hook('ec_daily_event_cleanup');
}


add_action('ec_daily_event_cleanup', 'ec_cleanup_old_events');

function ec_cleanup_old_events() {
    $threshold_days = 7;

    $query = new WP_Query([
        'post_type'      => 'ec_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'ec_event_end',
                'value'   => date('Y-m-d H:i:s', strtotime("-{$threshold_days} days")),
                'compare' => '<',
                'type'    => 'DATETIME',
            ],
        ],
    ]);

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            wp_trash_post($post->ID);
        }
    }

    wp_reset_postdata();
}


add_action('wp_enqueue_scripts', 'ec_enqueue_theme_styles');
function ec_enqueue_theme_styles() {
    $user_theme = get_option('ec_theme', 'light');

    $style = ($user_theme === 'dark') ? 'calendar-dark.css' : 'calendar-light.css';

    wp_enqueue_style('ec-theme-css', plugin_dir_url(__FILE__) . 'assets/css/' . $style);
}


add_action('init', function () {
    add_rewrite_rule('^ec-event-ics/([0-9]+)/?$', 'index.php?ec_ics_event_id=$matches[1]', 'top');
    add_rewrite_tag('%ec_ics_event_id%', '([0-9]+)');
});


add_action('template_redirect', function () {
    $event_id = get_query_var('ec_ics_event_id');
    if (!$event_id) return;

    $post = get_post($event_id);
    if (!$post || $post->post_type !== 'ec_event') {
        wp_die('Событие не найдено');
    }

    $title = $post->post_title;
    $desc  = strip_tags($post->post_content);
    $url   = get_permalink($post->ID);

    $start = get_post_meta($post->ID, 'ec_event_start', true);
    $end   = get_post_meta($post->ID, 'ec_event_end', true) ?: $start;

    $dt_start = gmdate('Ymd\THis\Z', strtotime($start));
    $dt_end   = gmdate('Ymd\THis\Z', strtotime($end));

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//event-calendar//export//EN\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:event-" . $event_id . "@yourdomain.com\r\n";
    $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    $ics .= "DTSTART:$dt_start\r\n";
    $ics .= "DTEND:$dt_end\r\n";
    $ics .= "SUMMARY:" . esc_html($title) . "\r\n";
    $ics .= "DESCRIPTION:" . esc_html($desc) . "\\n" . $url . "\r\n";
    $location = get_post_meta($event_id, 'ec_location_address', true);
    $ics .= "LOCATION:" . esc_html($location) . "\r\n";
    $ics .= "URL:$url\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="event-' . $event_id . '.ics"');
    echo $ics;
    exit;
});

add_filter('the_content', 'ec_render_full_event_content');
function ec_render_full_event_content($content): mixed {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    ob_start();

    // Получаем данные
    $event_id = get_the_ID();
    $ics_url = home_url("/ec-event-ics/{$event_id}/");

    ?>

    <div class="ec-single-event">
        <h1 class="ec-title"><?php the_title(); ?></h1>

        <!-- Здесь можно добавить другие данные: описание, организатор, место и т.д. -->

        <div class="ec-actions">
            <p><a class="button" href="<?= esc_url($ics_url); ?>">📅 Экспорт в календарь (.ics)</a></p>
        </div>
    </div>

    <?php

    return ob_get_clean(); // возвращаем только наш шаблон без $content
}



register_activation_hook(__FILE__, 'ec_create_rsvp_table');
function ec_create_rsvp_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'ec_rsvps';

    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        event_id BIGINT NOT NULL,
        name VARCHAR(255),
        email VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}






add_action('template_redirect', 'ec_handle_rsvp_form');
function ec_handle_rsvp_form() {
    if (
        isset($_POST['ec_rsvp_submit']) &&
        isset($_POST['ec_rsvp_name']) &&
        isset($_POST['ec_rsvp_email']) &&
        is_singular('ec_event')
    ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ec_rsvps';
        $wpdb->insert($table, [
            'event_id' => get_the_ID(),
            'name'     => sanitize_text_field($_POST['ec_rsvp_name']),
            'email'    => sanitize_email($_POST['ec_rsvp_email']),
        ]);
        wp_redirect(get_permalink() . '?rsvp=1');
        exit;
    }
}


add_action('admin_menu', function () {
    add_submenu_page(
        'ec_events',
        'Заявки',
        'RSVP-заявки',
        'manage_options',
        'ec-rsvp-list',
        'ec_render_rsvp_list'
    );
});

function ec_render_rsvp_list() {
    global $wpdb;
    $table = $wpdb->prefix . 'ec_rsvps';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

    echo '<div class="wrap"><h1>RSVP-заявки</h1>';
    if (!$results) {
        echo '<p>Нет заявок.</p></div>';
        return;
    }

    echo '<table class="widefat striped"><thead><tr>
        <th>Мероприятие</th><th>Имя</th><th>Email</th><th>Дата</th>
    </tr></thead><tbody>';

    foreach ($results as $row) {
        echo '<tr>';
        echo '<td><a href="' . get_permalink($row->event_id) . '">' . get_the_title($row->event_id) . '</a></td>';
        echo '<td>' . esc_html($row->name) . '</td>';
        echo '<td>' . esc_html($row->email) . '</td>';
        echo '<td>' . esc_html($row->created_at) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

