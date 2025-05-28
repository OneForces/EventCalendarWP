<?php

// ✅ Регистрируем метабоксы
add_action('add_meta_boxes', function () {
    // Основные поля
    add_meta_box('ec_event_details', 'Детали мероприятия', 'ec_event_details_box', 'ec_event', 'side', 'default');
    add_meta_box('ec_repeat_metabox', 'Повторение события', 'ec_render_repeat_metabox', 'ec_event', 'side', 'default');

    // Удаляем стандартные таксономии
    remove_meta_box('ec_event_typediv', 'ec_event', 'side');
    remove_meta_box('ec_organizerdiv', 'ec_event', 'side');
    remove_meta_box('ec_locationdiv', 'ec_event', 'side');

    // Пользовательские селекты
    add_meta_box('ec_event_type_custom', 'Тип мероприятия', fn() => ec_render_tax_dropdown('ec_event_type'), 'ec_event', 'side');
    add_meta_box('ec_organizer_custom', 'Организатор', fn() => ec_render_tax_dropdown('ec_organizer'), 'ec_event', 'side');
    add_meta_box('ec_location_custom', 'Место проведения', fn() => ec_render_tax_dropdown('ec_location'), 'ec_event', 'side');
});

// ✅ Рендер метабокса деталей
function ec_event_details_box($post) {
    $start   = get_post_meta($post->ID, 'ec_event_start', true);
    $end     = get_post_meta($post->ID, 'ec_event_end', true);
    $all_day = get_post_meta($post->ID, 'ec_event_all_day', true);
    $address = get_post_meta($post->ID, 'ec_event_address', true);

    $start_value = $start ? date('Y-m-d\TH:i', strtotime($start)) : '';
    $end_value   = $end   ? date('Y-m-d\TH:i', strtotime($end))   : '';
    ?>
    <p><label>Дата начала:<br>
        <input type="datetime-local" name="ec_event_start" value="<?= esc_attr($start_value); ?>" required style="width:100%;"></label></p>

    <p><label>Дата окончания:<br>
        <input type="datetime-local" name="ec_event_end" value="<?= esc_attr($end_value); ?>" required style="width:100%;"></label></p>

    <p><label><input type="checkbox" name="ec_event_all_day" value="1" <?php checked($all_day, '1'); ?>> Весь день</label></p>

    <p><label>Адрес проведения:<br>
        <input type="text" name="ec_event_address" value="<?= esc_attr($address); ?>" style="width:100%;"></label></p>

    <?php if ($address): ?>
        <iframe width="100%" height="200" style="border:0" loading="lazy" allowfullscreen
            src="https://www.google.com/maps?q=<?= urlencode($address); ?>&output=embed"></iframe>
    <?php endif;
}

// ✅ Рендер метабокса повторения
function ec_render_repeat_metabox($post) {
    $type  = get_post_meta($post->ID, 'ec_event_repeat_type', true) ?: 'none';
    $until = get_post_meta($post->ID, 'ec_event_repeat_until', true);
    $until_val = $until ? date('Y-m-d\TH:i', strtotime($until)) : '';

    wp_nonce_field('ec_repeat_nonce_action', 'ec_repeat_nonce');
    ?>
    <p><label>Тип повторения:<br>
        <select name="ec_event_repeat_type" style="width:100%;">
            <option value="none"    <?php selected($type, 'none'); ?>>Нет</option>
            <option value="daily"   <?php selected($type, 'daily'); ?>>Ежедневно</option>
            <option value="weekly"  <?php selected($type, 'weekly'); ?>>Еженедельно</option>
            <option value="monthly" <?php selected($type, 'monthly'); ?>>Ежемесячно</option>
        </select>
    </label></p>

    <p><label>Повторять до:<br>
        <input type="datetime-local" name="ec_event_repeat_until" value="<?= esc_attr($until_val); ?>" style="width:100%;"></label></p>
    <?php
}

// ✅ Сохраняем значения
add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'ec_event') return;

    $start_raw = $_POST['ec_event_start'] ?? '';
    $end_raw   = $_POST['ec_event_end'] ?? '';
    $start_ts  = strtotime($start_raw);
    $end_ts    = strtotime($end_raw);

    if ($start_ts && $end_ts && $end_ts < $start_ts) return;

    update_post_meta($post_id, 'ec_event_start', $start_ts ? date('Y-m-d H:i:s', $start_ts) : '');
    update_post_meta($post_id, 'ec_event_end', $end_ts ? date('Y-m-d H:i:s', $end_ts) : '');
    update_post_meta($post_id, 'ec_event_all_day', isset($_POST['ec_event_all_day']) ? '1' : '');
    update_post_meta($post_id, 'ec_event_address', sanitize_text_field($_POST['ec_event_address'] ?? ''));

    if (!isset($_POST['ec_repeat_nonce']) || !wp_verify_nonce($_POST['ec_repeat_nonce'], 'ec_repeat_nonce_action')) return;

    update_post_meta($post_id, 'ec_event_repeat_type', sanitize_text_field($_POST['ec_event_repeat_type'] ?? 'none'));
    update_post_meta($post_id, 'ec_event_repeat_until', sanitize_text_field($_POST['ec_event_repeat_until'] ?? ''));
});

// ✅ Переключение типа поля по чекбоксу "весь день"
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen->post_type !== 'ec_event') return;

    echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            const checkbox = document.querySelector("[name=\\"ec_event_all_day\\"]");
            if (!checkbox) return;
            checkbox.addEventListener("change", function () {
                const type = this.checked ? "date" : "datetime-local";
                const start = document.querySelector("[name=\\"ec_event_start\\"]");
                const end = document.querySelector("[name=\\"ec_event_end\\"]");
                if (start) start.type = type;
                if (end) end.type = type;
            });
        });
    </script>';
});

// ✅ Выпадающие таксономии
if (!function_exists('ec_render_tax_dropdown')) {
    function ec_render_tax_dropdown($taxonomy) {
        global $post;
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $selected = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        $label = get_taxonomy($taxonomy)->labels->singular_name;

        echo '<label for="' . $taxonomy . '" style="display:block;margin-bottom:6px;font-weight:600;">Выберите ' . strtolower($label) . '</label>';
        echo '<select name="' . $taxonomy . '" id="' . $taxonomy . '" style="width:100%;">';
        echo '<option value="">-- Выберите --</option>';
        foreach ($terms as $term) {
            $is_selected = in_array($term->term_id, $selected) ? 'selected' : '';
            echo '<option value="' . $term->term_id . '" ' . $is_selected . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
    }
}
