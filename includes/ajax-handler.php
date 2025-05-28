<?php
if (!function_exists('ec_handle_get_events')) {
    add_action('wp_ajax_ec_get_events', 'ec_handle_get_events');
    add_action('wp_ajax_nopriv_ec_get_events', 'ec_handle_get_events');

    function ec_handle_get_events() {
        $events = [];

        $paged = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;

        $args = [
            'post_type'      => 'ec_event',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'paged'          => $paged,
            'orderby'        => 'meta_value',
            'meta_key'       => 'ec_event_start',
            'order'          => 'ASC',
            'meta_query'     => [],
            'tax_query'      => [],
        ];

        // 🔎 Таксономии
        if (!empty($_POST['type'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'ec_event_type',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_POST['type']),
            ];
        }

        if (!empty($_POST['organizer'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'ec_organizer',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_POST['organizer']),
            ];
        }

        // 🔎 Даты
        if (!empty($_POST['date_start']) && !empty($_POST['date_end'])) {
            $args['meta_query'][] = [
                'key'     => 'ec_event_start',
                'value'   => [
                    sanitize_text_field($_POST['date_start']),
                    sanitize_text_field($_POST['date_end'])
                ],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ];
        }

        // 🔎 Поиск
        if (!empty($_POST['search'])) {
            $args['s'] = sanitize_text_field($_POST['search']);
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $start  = get_post_meta(get_the_ID(), 'ec_event_start', true);
                $end    = get_post_meta(get_the_ID(), 'ec_event_end', true) ?: $start;
                $repeat_type  = get_post_meta(get_the_ID(), 'ec_event_repeat_type', true);
                $repeat_until = get_post_meta(get_the_ID(), 'ec_event_repeat_until', true);

                $color = '#3788D8';
                $text  = '#ffffff';

                $terms = get_the_terms(get_the_ID(), 'ec_event_type');
                if (!empty($terms) && !is_wp_error($terms)) {
                    $term_id = $terms[0]->term_id;
                    $color = get_term_meta($term_id, 'ec_background_color', true) ?: $color;
                    $text  = get_term_meta($term_id, 'ec_text_color', true) ?: $text;
                }

                if (empty($repeat_type) || $repeat_type === 'none') {
                    $events[] = [
                        'title' => get_the_title(),
                        'start' => $start,
                        'end'   => $end,
                        'url'   => get_permalink(),
                        'backgroundColor' => $color,
                        'textColor'       => $text,
                        'borderColor'     => $color,
                    ];
                    continue;
                }

                $interval_spec = match ($repeat_type) {
                    'daily'   => 'P1D',
                    'weekly'  => 'P1W',
                    'monthly' => 'P1M',
                    default   => null,
                };

                if (!$interval_spec) continue;

                $current  = new DateTime($start);
                $limit    = new DateTime($repeat_until ?: $start);
                $interval = new DateInterval($interval_spec);

                while ($current <= $limit) {
                    $date = $current->format('Y-m-d\TH:i:s');
                    $events[] = [
                        'title' => get_the_title(),
                        'start' => $date,
                        'end'   => $date,
                        'url'   => get_permalink(),
                        'backgroundColor' => $color,
                        'textColor'       => $text,
                        'borderColor'     => $color,
                    ];
                    $current->add($interval);
                }
            }
        }

        wp_reset_postdata();
        wp_send_json($events);
    }
}
