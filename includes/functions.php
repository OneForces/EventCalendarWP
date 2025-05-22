<?php

// 📅 Стили и скрипты календаря + передача данных
if (!function_exists('ec_enqueue_calendar_assets')) {
    function ec_enqueue_calendar_assets() {
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
        wp_enqueue_style('ec-calendar-css', plugin_dir_url(__DIR__) . 'assets/css/calendar.css');

        wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', [], null, true);
        wp_enqueue_script('ec-calendar-js', plugin_dir_url(__DIR__) . 'assets/js/calendar.js', ['fullcalendar-js'], null, true);

        wp_localize_script('ec-calendar-js', 'ec_calendar_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'default_view' => 'dayGridMonth',
            'timezone' => wp_timezone_string(),
        ]);
    }
    add_action('wp_enqueue_scripts', 'ec_enqueue_calendar_assets');
}

if (!function_exists('ec_calendar_shortcode')) {
    function ec_calendar_shortcode() {
        ob_start(); ?>

        <div id="ec-filters">
            <form id="ec-filter-form" method="get">
                <select name="type">
                    <option value="">Тип мероприятия</option>
                    <?php
                    $types = get_terms(['taxonomy' => 'ec_event_type', 'hide_empty' => false]);
                    foreach ($types as $type) {
                        $selected = selected($_GET['type'] ?? '', $type->slug, false);
                        echo '<option value="' . esc_attr($type->slug) . '"' . $selected . '>' . esc_html($type->name) . '</option>';
                    }
                    ?>
                </select>

                <select name="organizer">
                    <option value="">Организатор</option>
                    <?php
                    $organizers = get_terms(['taxonomy' => 'ec_organizer', 'hide_empty' => false]);
                    foreach ($organizers as $org) {
                        $selected = selected($_GET['organizer'] ?? '', $org->slug, false);
                        echo '<option value="' . esc_attr($org->slug) . '"' . $selected . '>' . esc_html($org->name) . '</option>';
                    }
                    ?>
                </select>

                <input type="date" name="start" value="<?php echo esc_attr($_GET['start'] ?? ''); ?>">
                <input type="date" name="end" value="<?php echo esc_attr($_GET['end'] ?? ''); ?>">

                <input type="text" name="ec_search" placeholder="Поиск по названию" value="<?php echo esc_attr($_GET['ec_search'] ?? ''); ?>">

                <button type="submit">Фильтровать</button>
            </form>
        </div>

        <div id="ec-calendar"></div>

        <?php return ob_get_clean();
    }

    add_shortcode('event_calendar', 'ec_calendar_shortcode');
}


// 🧾 Форма RSVP на странице мероприятия
add_filter('the_content', 'ec_append_rsvp_form');
function ec_append_rsvp_form($content) {
    if (!is_singular('ec_event')) return $content;

    ob_start();
    ?>
    <h3>Записаться на мероприятие</h3>
    <form method="post">
        <p><input type="text" name="ec_rsvp_name" placeholder="Ваше имя" required></p>
        <p><input type="email" name="ec_rsvp_email" placeholder="Email" required></p>
        <input type="hidden" name="ec_rsvp_event_id" value="<?php echo get_the_ID(); ?>">
        <?php wp_nonce_field('ec_rsvp_submit', 'ec_rsvp_nonce'); ?>
        <p><button type="submit" name="ec_rsvp_submit">Отправить</button></p>
    </form>
    <?php
    return $content . ob_get_clean();
}

// 📩 Обработка отправки формы RSVP
add_action('init', 'ec_process_rsvp_form');
function ec_process_rsvp_form() {
    if (!isset($_POST['ec_rsvp_submit']) || !wp_verify_nonce($_POST['ec_rsvp_nonce'], 'ec_rsvp_submit')) return;

    $name = sanitize_text_field($_POST['ec_rsvp_name']);
    $email = sanitize_email($_POST['ec_rsvp_email']);
    $event_id = intval($_POST['ec_rsvp_event_id']);

    wp_insert_post([
        'post_type' => 'ec_rsvp',
        'post_title' => "$name ($email)",
        'post_status' => 'publish',
        'meta_input' => [
            'rsvp_name' => $name,
            'rsvp_email' => $email,
            'rsvp_event_id' => $event_id,
        ]
    ]);

    wp_redirect(add_query_arg('rsvp_sent', '1', get_permalink($event_id)));
    exit;
}

// 📊 Отображение заявок в админке
add_filter('manage_ec_rsvp_posts_columns', function($cols) {
    $cols['rsvp_email'] = 'Email';
    $cols['rsvp_event'] = 'Мероприятие';
    return $cols;
});

add_action('manage_ec_rsvp_posts_custom_column', function($col, $post_id) {
    if ($col === 'rsvp_email') {
        $email = get_post_meta($post_id, 'rsvp_email', true);
        echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '—';

    }
    if ($col === 'rsvp_event') {
        $event_id = get_post_meta($post_id, 'rsvp_event_id', true);
        echo $event_id ? '<a href="' . get_edit_post_link($event_id) . '">' . get_the_title($event_id) . '</a>' : '—';
    }
}, 10, 2);


// 📎 Кнопка "Экспорт CSV" в заголовке страницы заявок
add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow === 'ec_rsvp') {
        $export_url = add_query_arg(['ec_rsvp_export' => '1']);
        echo '<a href="' . esc_url($export_url) . '" class="button">Экспорт CSV</a>';
    }
});

add_action('admin_init', function() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['ec_rsvp_export'])) return;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rsvp_export_' . date('Y-m-d_H-i-s') . '.csv');

    $output = fopen('php://output', 'w');

    // Добавим колонку Комментарий
    fputcsv($output, ['Имя', 'Email', 'Мероприятие', 'Дата создания', 'Комментарий']);

    $rsvps = get_posts([
        'post_type' => 'ec_rsvp',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);

    foreach ($rsvps as $rsvp) {
        $name = get_post_meta($rsvp->ID, 'rsvp_name', true);
        $email = get_post_meta($rsvp->ID, 'rsvp_email', true);
        $event_id = get_post_meta($rsvp->ID, 'rsvp_event_id', true);
        $event_title = $event_id ? get_the_title($event_id) : '—';
        $date = get_the_date('Y-m-d H:i', $rsvp->ID);
        $comment = get_post_meta($rsvp->ID, 'rsvp_comment', true);

        fputcsv($output, [$name, $email, $event_title, $date, $comment]);
    }

    fclose($output);
    exit;
});



// 🔍 Фильтрация заявок по мероприятию в админке
add_action('restrict_manage_posts', function($post_type) {
    if ($post_type !== 'ec_rsvp') return;

    $events = get_posts([
        'post_type' => 'ec_event',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    $selected = isset($_GET['rsvp_event_filter']) ? $_GET['rsvp_event_filter'] : '';
    echo '<select name="rsvp_event_filter">';
    echo '<option value="">Все мероприятия</option>';

    foreach ($events as $event) {
        printf(
            '<option value="%d"%s>%s</option>',
            $event->ID,
            selected($selected, $event->ID, false),
            esc_html($event->post_title)
        );
    }

    echo '</select>';
});

// 🔄 Модификация запроса для применения фильтра
add_filter('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    if ($query->get('post_type') === 'ec_rsvp' && isset($_GET['rsvp_event_filter']) && $_GET['rsvp_event_filter']) {
        $query->set('meta_query', [
            [
                'key' => 'rsvp_event_id',
                'value' => intval($_GET['rsvp_event_filter']),
            ]
        ]);
    }
});



// ✏️ 1. Мета-бокс комментария в заявке
add_action('add_meta_boxes', function() {
    add_meta_box(
        'ec_rsvp_comment_metabox',
        'Комментарий администратора',
        'ec_render_rsvp_comment_metabox',
        'ec_rsvp',
        'normal',
        'default'
    );
});

function ec_render_rsvp_comment_metabox($post) {
    $comment = get_post_meta($post->ID, 'rsvp_comment', true);
    ?>
    <textarea name="rsvp_comment" rows="5" style="width:100%;"><?php echo esc_textarea($comment); ?></textarea>
    <?php
}

// 💾 2. Сохранение комментария
add_action('save_post', function($post_id) {
    if (get_post_type($post_id) !== 'ec_rsvp') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['rsvp_comment'])) {
        update_post_meta($post_id, 'rsvp_comment', sanitize_textarea_field($_POST['rsvp_comment']));
    }
});

// 📊 3. Комментарий в колонке админки
add_filter('manage_ec_rsvp_posts_columns', function($cols) {
    $cols['rsvp_comment'] = 'Комментарий';
    return $cols;
});

add_action('manage_ec_rsvp_posts_custom_column', function($col, $post_id) {
    if ($col === 'rsvp_comment') {
        $comment = get_post_meta($post_id, 'rsvp_comment', true);
    if ($comment) {
        echo '<div style="background:#fffbe6;border-left:4px solid #ffcc00;padding:5px;">' . esc_html($comment) . '</div>';
    } else {
        echo '—';
    }

    }
}, 10, 2);


add_filter('the_content', 'ec_append_map_to_event');
function ec_append_map_to_event($content) {
    if (!is_singular('ec_event')) return $content;

    $address = get_post_meta(get_the_ID(), 'ec_event_address', true);
    if (!$address) return $content;

    $map_html = '<div id="yandex-map" style="width:100%; height:400px; margin-top:20px;"></div>';
    $map_html .= "<script>window.ec_event_address = " . json_encode($address) . ";</script>";

    return $content . $map_html;
}


add_filter('the_content', 'ec_render_single_event_page');
function ec_render_single_event_page($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    // Начинаем буферизацию
    ob_start();

    // Получаем нужные данные
    $start_date = get_post_meta(get_the_ID(), 'ec_event_start_date', true);
    $location = get_post_meta(get_the_ID(), 'ec_event_location', true);
    $ics_link = get_post_meta(get_the_ID(), 'ec_event_ics_link', true);

    ?>
    <div class="ec-single-event">
        <h1 class="ec-title"><?php the_title(); ?></h1>

        <div class="ec-meta">
            <?php if ($start_date): ?>
                <p><strong>Дата:</strong> <?= esc_html($start_date); ?></p>
            <?php endif; ?>
            <?php if ($location): ?>
                <p><strong>Место:</strong> <?= esc_html($location); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($ics_link): ?>
            <div class="ec-actions">
                <a class="ec-export" href="<?= esc_url($ics_link); ?>">
                    📅 Экспорт в календарь (.ics)
                </a>
            </div>
        <?php endif; ?>

        <div class="ec-rsvp">
            <h2>Записаться на мероприятие</h2>
            <form method="post">
                <input type="text" name="ec_rsvp_name" placeholder="Ваше имя" required>
                <input type="email" name="ec_rsvp_email" placeholder="Email" required>
                <input type="submit" name="ec_rsvp_submit" value="Записаться">
            </form>
        </div>
    </div>
    <?php

    return ob_get_clean(); 
}


add_action('ec_organizer_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="ec_organizer_email">Email</label>
        <input type="email" name="ec_organizer_email" id="ec_organizer_email">
    </div>
    <div class="form-field">
        <label for="ec_organizer_phone">Телефон</label>
        <input type="text" name="ec_organizer_phone" id="ec_organizer_phone">
    </div>
    <?php
});

add_action('ec_organizer_edit_form_fields', function ($term) {
    $email = get_term_meta($term->term_id, 'ec_organizer_email', true);
    $phone = get_term_meta($term->term_id, 'ec_organizer_phone', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="ec_organizer_email">Email</label></th>
        <td><input type="email" name="ec_organizer_email" id="ec_organizer_email" value="<?= esc_attr($email); ?>"></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="ec_organizer_phone">Телефон</label></th>
        <td><input type="text" name="ec_organizer_phone" id="ec_organizer_phone" value="<?= esc_attr($phone); ?>"></td>
    </tr>
    <?php
});

add_action('created_ec_organizer', 'ec_save_organizer_meta');
add_action('edited_ec_organizer', 'ec_save_organizer_meta');
function ec_save_organizer_meta($term_id) {
    if (isset($_POST['ec_organizer_email'])) {
        update_term_meta($term_id, 'ec_organizer_email', sanitize_email($_POST['ec_organizer_email']));
    }
    if (isset($_POST['ec_organizer_phone'])) {
        update_term_meta($term_id, 'ec_organizer_phone', sanitize_text_field($_POST['ec_organizer_phone']));
    }
}

add_action('ec_location_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="ec_location_address">Адрес</label>
        <input type="text" name="ec_location_address" id="ec_location_address">
    </div>
    <div class="form-field">
        <label for="ec_location_description">Описание</label>
        <textarea name="ec_location_description" id="ec_location_description" rows="3"></textarea>
    </div>
    <?php
});

add_action('ec_location_edit_form_fields', function ($term) {
    $address = get_term_meta($term->term_id, 'ec_location_address', true);
    $desc    = get_term_meta($term->term_id, 'ec_location_description', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="ec_location_address">Адрес</label></th>
        <td><input type="text" name="ec_location_address" id="ec_location_address" value="<?= esc_attr($address); ?>" style="width: 100%;"></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="ec_location_description">Описание</label></th>
        <td><textarea name="ec_location_description" id="ec_location_description" rows="3" style="width: 100%;"><?= esc_textarea($desc); ?></textarea></td>
    </tr>
    <?php
});

add_action('created_ec_location', 'ec_save_location_meta');
add_action('edited_ec_location', 'ec_save_location_meta');
function ec_save_location_meta($term_id) {
    if (isset($_POST['ec_location_address'])) {
        update_term_meta($term_id, 'ec_location_address', sanitize_text_field($_POST['ec_location_address']));
    }
    if (isset($_POST['ec_location_description'])) {
        update_term_meta($term_id, 'ec_location_description', sanitize_textarea_field($_POST['ec_location_description']));
    }
}



add_filter('the_content', 'ec_add_breadcrumbs_to_event_page');
function ec_add_breadcrumbs_to_event_page($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    // Формируем HTML крошек
    $breadcrumbs = '<nav class="ec-breadcrumbs" style="margin-bottom: 1rem;">';
    $breadcrumbs .= '<a href="' . home_url() . '">Главная</a> » ';
    $breadcrumbs .= '<a href="' . get_post_type_archive_link('ec_event') . '">Календарь мероприятий</a> » ';
    $breadcrumbs .= '<span>' . get_the_title() . '</span>';
    $breadcrumbs .= '</nav>';

    return $breadcrumbs . $content;
}


add_filter('the_content', 'ec_append_organizer_info_to_event');
function ec_append_organizer_info_to_event($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $organizers = get_the_terms(get_the_ID(), 'ec_organizer');
    if (empty($organizers) || is_wp_error($organizers)) {
        return $content;
    }

    $html = '<div class="ec-organizer-block">';
    foreach ($organizers as $org) {
        $name  = esc_html($org->name);
        $email = get_term_meta($org->term_id, 'ec_organizer_email', true);
        $phone = get_term_meta($org->term_id, 'ec_organizer_phone', true);

        $html .= '<h3>Организатор: ' . $name . '</h3>';
        if ($email) {
            $html .= '<p>Email: <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
        }
        if ($phone) {
            $html .= '<p>Телефон: ' . esc_html($phone) . '</p>';
        }
    }
    $html .= '</div>';

    return $content . $html;
}


add_filter('the_content', 'ec_append_location_info_to_event');
function ec_append_location_info_to_event($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $locations = get_the_terms(get_the_ID(), 'ec_location');
    if (empty($locations) || is_wp_error($locations)) {
        return $content;
    }

    $html = '<div class="ec-location-block">';
    foreach ($locations as $loc) {
        $name    = esc_html($loc->name);
        $address = get_term_meta($loc->term_id, 'ec_location_address', true);
        $desc    = get_term_meta($loc->term_id, 'ec_location_description', true);

        $html .= '<h3>Место проведения: ' . $name . '</h3>';
        if ($desc) {
            $html .= '<p><em>' . esc_html($desc) . '</em></p>';
        }
        if ($address) {
            $html .= '<p><strong>Адрес:</strong> ' . esc_html($address) . '</p>';
            $html .= '<iframe
                        width="100%"
                        height="300"
                        style="border:0; margin-top:1rem"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps?q=' . urlencode($address) . '&output=embed">
                      </iframe>';
        }
    }
    $html .= '</div>';

    return $content . $html;
}
