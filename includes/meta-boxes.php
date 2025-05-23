<?php

add_action('add_meta_boxes', 'ec_event_meta_boxes');
function ec_event_meta_boxes() {
    add_meta_box('ec_event_details', 'Детали мероприятия', 'ec_event_details_box', 'ec_event', 'normal', 'high');
    add_meta_box('ec_repeat_metabox', 'Повторение события', 'ec_render_repeat_metabox', 'ec_event', 'side', 'default');
}

function ec_event_details_box($post) {
    $start    = get_post_meta($post->ID, 'ec_event_start', true);
    $end      = get_post_meta($post->ID, 'ec_event_end', true);
    $all_day  = get_post_meta($post->ID, 'ec_event_all_day', true);
    $address  = get_post_meta($post->ID, 'ec_event_address', true);
    $org_id   = $org_term[0] ?? 0;



    $start_value = $start ? date('Y-m-d\TH:i', strtotime($start)) : '';
    $end_value   = $end   ? date('Y-m-d\TH:i', strtotime($end))   : '';
    ?>

    <p><label>Дата начала:<br>
        <input type="datetime-local" name="ec_event_start" value="<?= esc_attr($start_value); ?>" style="width:100%;"></label></p>

    <p><label>Дата окончания:<br>
        <input type="datetime-local" name="ec_event_end" value="<?= esc_attr($end_value); ?>" style="width:100%;"></label></p>

    <p><label><input type="checkbox" name="ec_event_all_day" value="1" <?php checked($all_day, '1'); ?>> Весь день</label></p>


    <?php if ($address): ?>
        <iframe width="100%" height="300" style="border:0" loading="lazy" allowfullscreen
            src="https://www.google.com/maps?q=<?= urlencode($address); ?>&output=embed"></iframe>
    <?php endif; ?>

    <hr>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('ec_event_organizer');
        const email = document.querySelector('input[name="ec_event_organizer_email"]');
        const phone = document.querySelector('input[name="ec_event_organizer_phone"]');

        select.addEventListener('change', () => {
            const id = select.value;
            fetch(ajaxurl + '?action=ec_get_organizer_info&term_id=' + id)
                .then(r => r.json())
                .then(data => {
                    email.value = data.email || '';
                    phone.value = data.phone || '';
                });
        });
    });
    </script>

    <?php
}




function ec_render_repeat_metabox($post) {
    $type  = get_post_meta($post->ID, 'ec_event_repeat_type', true) ?: 'none';
    $until_raw = get_post_meta($post->ID, 'ec_event_repeat_until', true);

    // Приводим к формату datetime-local (если есть)
    $until = '';
    if ($until_raw) {
        $timestamp = strtotime($until_raw);
        $until = date('Y-m-d\TH:i', $timestamp); // Пример: 2025-06-01T14:30
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


add_action('save_post', 'ec_save_event_meta');
function ec_save_event_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'ec_event') return;

    if (!empty($_POST['ec_event_start'])) {
        update_post_meta($post_id, 'ec_event_start', date('Y-m-d H:i:s', strtotime($_POST['ec_event_start'])));
    }

    if (!empty($_POST['ec_event_end'])) {
        update_post_meta($post_id, 'ec_event_end', date('Y-m-d H:i:s', strtotime($_POST['ec_event_end'])));
    }

    update_post_meta($post_id, 'ec_event_all_day', isset($_POST['ec_event_all_day']) ? '1' : '');
    update_post_meta($post_id, 'ec_event_address', sanitize_text_field($_POST['ec_event_address'] ?? ''));

    if (!empty($_POST['ec_event_organizer'])) {
        wp_set_object_terms($post_id, [(int)$_POST['ec_event_organizer']], 'ec_organizer', false);
    }

    if (!isset($_POST['ec_repeat_nonce']) || !wp_verify_nonce($_POST['ec_repeat_nonce'], 'ec_repeat_nonce_action')) return;
    update_post_meta($post_id, 'ec_event_repeat_type', sanitize_text_field($_POST['ec_event_repeat_type'] ?? 'none'));
    update_post_meta($post_id, 'ec_event_repeat_until', sanitize_text_field($_POST['ec_event_repeat_until'] ?? ''));
}
