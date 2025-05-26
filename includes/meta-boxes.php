<?php

add_action('add_meta_boxes', function () {
    add_meta_box('ec_event_details', 'Детали мероприятия', 'ec_event_details_box', 'ec_event', 'side', 'default');
    add_meta_box('ec_repeat_metabox', 'Повторение события', 'ec_render_repeat_metabox', 'ec_event', 'side', 'default');
});

function ec_event_details_box($post) {
    $start    = get_post_meta($post->ID, 'ec_event_start', true);
    $end      = get_post_meta($post->ID, 'ec_event_end', true);
    $all_day  = get_post_meta($post->ID, 'ec_event_all_day', true);
    $address  = get_post_meta($post->ID, 'ec_event_address', true);

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

function ec_render_repeat_metabox($post) {
    $type  = get_post_meta($post->ID, 'ec_event_repeat_type', true) ?: 'none';
    $until_raw = get_post_meta($post->ID, 'ec_event_repeat_until', true);

    $until = '';
    if ($until_raw) {
        $timestamp = strtotime($until_raw);
        $until = date('Y-m-d\TH:i', $timestamp);
    }

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
        <input type="datetime-local" name="ec_event_repeat_until" value="<?= esc_attr($until); ?>" style="width:100%;">
    </label></p>
    <?php
}

add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'ec_event') return;

    $start_raw = $_POST['ec_event_start'] ?? '';
    $end_raw   = $_POST['ec_event_end'] ?? '';
    $start_ts  = strtotime($start_raw);
    $end_ts    = strtotime($end_raw);

    // Проверка: окончание >= начало
    if ($start_ts && $end_ts && $end_ts < $start_ts) {
        return; // Не сохраняем, если даты некорректны
    }

    update_post_meta($post_id, 'ec_event_start', $start_ts ? date('Y-m-d H:i:s', $start_ts) : '');
    update_post_meta($post_id, 'ec_event_end', $end_ts ? date('Y-m-d H:i:s', $end_ts) : '');
    update_post_meta($post_id, 'ec_event_all_day', isset($_POST['ec_event_all_day']) ? '1' : '');
    update_post_meta($post_id, 'ec_event_address', sanitize_text_field($_POST['ec_event_address'] ?? ''));

    if (!isset($_POST['ec_repeat_nonce']) || !wp_verify_nonce($_POST['ec_repeat_nonce'], 'ec_repeat_nonce_action')) return;

    update_post_meta($post_id, 'ec_event_repeat_type', sanitize_text_field($_POST['ec_event_repeat_type'] ?? 'none'));
    update_post_meta($post_id, 'ec_event_repeat_until', sanitize_text_field($_POST['ec_event_repeat_until'] ?? ''));
});

// 🔧 Смена типа поля даты при выборе "весь день"
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen->post_type === 'ec_event') {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
                const checkbox = document.querySelector("[name=\\"ec_event_all_day\\"]");
                if (!checkbox) return;
                checkbox.addEventListener("change", function () {
                    const startInput = document.querySelector("[name=\\"ec_event_start\\"]");
                    const endInput = document.querySelector("[name=\\"ec_event_end\\"]");
                    const type = this.checked ? "date" : "datetime-local";
                    if (startInput) startInput.type = type;
                    if (endInput) endInput.type = type;
                });
            });
            </script>';
    }
});