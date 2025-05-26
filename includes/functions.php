<?php

// 🗓️ Шорткод календаря
if (!function_exists('ec_calendar_shortcode')) {
    function ec_calendar_shortcode() {
        ob_start(); ?>
        <form id="ec-filter-form">
            <!-- Форма фильтра -->
        </form>
        <div id="ec-calendar"></div>
        <?php return ob_get_clean();
    }
    add_shortcode('event_calendar', 'ec_calendar_shortcode');
}

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

    $selected = $_GET['rsvp_event_filter'] ?? '';
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

add_filter('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') === 'ec_rsvp' && isset($_GET['rsvp_event_filter'])) {
        $query->set('meta_query', [[
            'key' => 'rsvp_event_id',
            'value' => intval($_GET['rsvp_event_filter']),
        ]]);
    }
});

// ✏️ Комментарий администратора к заявке
add_action('add_meta_boxes', function() {
    add_meta_box('ec_rsvp_comment_metabox', 'Комментарий администратора', 'ec_render_rsvp_comment_metabox', 'ec_rsvp');
});

function ec_render_rsvp_comment_metabox($post) {
    $comment = get_post_meta($post->ID, 'rsvp_comment', true);
    echo '<textarea name="rsvp_comment" rows="5" style="width:100%;">' . esc_textarea($comment) . '</textarea>';
}

add_action('save_post', function($post_id) {
    if (get_post_type($post_id) !== 'ec_rsvp' || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['rsvp_comment'])) {
        update_post_meta($post_id, 'rsvp_comment', sanitize_textarea_field($_POST['rsvp_comment']));
    }
});

add_filter('manage_ec_rsvp_posts_columns', function($cols) {
    $cols['rsvp_comment'] = 'Комментарий';
    return $cols;
});

add_action('manage_ec_rsvp_posts_custom_column', function($col, $post_id) {
    if ($col === 'rsvp_comment') {
        $comment = get_post_meta($post_id, 'rsvp_comment', true);
        echo $comment ? '<div style="background:#fffbe6;border-left:4px solid #ffcc00;padding:5px;">' . esc_html($comment) . '</div>' : '—';
    }
}, 10, 2);

// 📅 Экспорт .ics и форма RSVP
add_filter('the_content', 'ec_render_single_event_page', 10);
function ec_render_single_event_page($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) return $content;

    ob_start();
    $start_date = get_post_meta(get_the_ID(), 'ec_event_start_date', true);
    $location = get_post_meta(get_the_ID(), 'ec_event_location', true);
    $ics_link = get_post_meta(get_the_ID(), 'ec_event_ics_link', true);
    ?>
    <div class="ec-single-event">
        <h1 class="ec-title"><?php the_title(); ?></h1>
        <div class="ec-meta">
            <?php if ($start_date): ?><p><strong>Дата:</strong> <?= esc_html($start_date); ?></p><?php endif; ?>
            <?php if ($location): ?><p><strong>Место:</strong> <?= esc_html($location); ?></p><?php endif; ?>
        </div>
        <?php if ($ics_link): ?>
            <div class="ec-actions">
                <a class="ec-export" href="<?= esc_url($ics_link); ?>">📅 Экспорт в календарь (.ics)</a>
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

// 📍 Информация об организаторе
add_filter('the_content', 'ec_append_organizer_info_to_event', 20);
function ec_append_organizer_info_to_event($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) return $content;

    $organizers = get_the_terms(get_the_ID(), 'ec_organizer');
    if (empty($organizers) || is_wp_error($organizers)) return $content;

    $html = '<div class="ec-organizer-block">';
    foreach ($organizers as $org) {
        $name  = esc_html($org->name);
        $html .= "<h3>Организатор: {$name}</h3>";
            }
    $html .= '</div>';
    return $content . $html;
}

// 📍 Информация о месте проведения
add_filter('the_content', 'ec_append_location_info_to_event', 30);
function ec_append_location_info_to_event($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) return $content;

    $locations = get_the_terms(get_the_ID(), 'ec_location');
    if (empty($locations) || is_wp_error($locations)) return $content;

    $html = '<div class="ec-location-block">';
    foreach ($locations as $loc) {
        $name = esc_html($loc->name);
        $desc = get_term_meta($loc->term_id, 'ec_location_description', true);
        $html .= "<h3>Место проведения: {$name}</h3>";
        if ($desc) $html .= "<p><em>{$desc}</em></p>";
        $html .= '<iframe width="100%" height="300" style="border:0;margin-top:1rem" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>';
    }
    $html .= '</div>';
    return $content . $html;
}

// 🧭 Хлебные крошки
add_filter('the_content', 'ec_add_breadcrumbs_to_event_page', 15);
function ec_add_breadcrumbs_to_event_page($content) {
    if (!is_singular('ec_event') || !in_the_loop() || !is_main_query()) return $content;

    $breadcrumbs = '<nav class="ec-breadcrumbs" style="margin-bottom: 1rem;">';
    $breadcrumbs .= '<a href="' . home_url() . '">Главная</a> » ';
    $breadcrumbs .= '<a href="' . get_post_type_archive_link('ec_event') . '">Календарь мероприятий</a> » ';
    $breadcrumbs .= '<span>' . get_the_title() . '</span>';
    $breadcrumbs .= '</nav>';

    return $breadcrumbs . $content;
}

// 💾 Сохранение описания места
add_action('edited_ec_location', 'ec_save_location_meta');
add_action('create_ec_location', 'ec_save_location_meta');
function ec_save_location_meta($term_id) {
    if (isset($_POST['ec_location_description'])) {
        update_term_meta($term_id, 'ec_location_description', sanitize_textarea_field($_POST['ec_location_description']));
    }
}

// 📁 Замена шаблона архива
add_filter('template_include', 'ec_override_archive_template');
function ec_override_archive_template($template) {
    if (is_post_type_archive('ec_event')) {
        return plugin_dir_path(__FILE__) . '../templates/archive-ec_event.php';
    }
    return $template;
}

// 🧹 Скрытие "Родительской рубрики"
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen->post_type === 'ec_event') {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            const selectors = document.querySelectorAll("select[name=\'parent\']");
            selectors.forEach(select => {
                const wrapper = select.closest("p, div");
                if (wrapper) wrapper.style.display = "none";
            });
        });
        </script>';
    }
});


// 🧩 Настройка отображения чекбоксов
add_filter('wp_terms_checklist_args', function($args) {
    $args['checked_ontop'] = false;
    return $args;
});