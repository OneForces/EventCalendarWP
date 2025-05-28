<?php

add_action('init', 'ec_register_taxonomies');

function ec_register_taxonomies() {
    // Типы мероприятий
    register_taxonomy('ec_event_type', 'ec_event', [
        'label'             => 'Типы мероприятий',
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => false,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'meta_box_cb'       => 'post_categories_meta_box', // Селект справа
        'rewrite'           => ['slug' => 'event-type'],
    ]);

    // Организаторы
    register_taxonomy('ec_organizer', 'ec_event', [
        'label'             => 'Организаторы',
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => false,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'meta_box_cb'       => 'post_categories_meta_box',
        'rewrite'           => ['slug' => 'organizer'],
    ]);

    // Места проведения
    register_taxonomy('ec_location', 'ec_event', [
        'label'             => 'Места проведения',
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => false,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'meta_box_cb'       => 'post_categories_meta_box',
        'rewrite'           => ['slug' => 'location'],
    ]);
}



// Добавление полей цвета к типам мероприятий
function ec_event_type_add_meta_fields() {
    ?>
    <div class="form-field">
        <label for="ec_background_color">Цвет фона</label>
        <input type="color" name="ec_background_color" id="ec_background_color" value="#3788D8">
    </div>
    <div class="form-field">
        <label for="ec_text_color">Цвет текста</label>
        <input type="color" name="ec_text_color" id="ec_text_color" value="#ffffff">
    </div>
    <?php
}
add_action('ec_event_type_add_form_fields', 'ec_event_type_add_meta_fields');

function ec_event_type_edit_meta_fields($term) {
    $bg = get_term_meta($term->term_id, 'ec_background_color', true) ?: '#3788D8';
    $tx = get_term_meta($term->term_id, 'ec_text_color', true) ?: '#ffffff';
    ?>
    <tr class="form-field">
        <th><label for="ec_background_color">Цвет фона</label></th>
        <td><input type="color" name="ec_background_color" id="ec_background_color" value="<?php echo esc_attr($bg); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="ec_text_color">Цвет текста</label></th>
        <td><input type="color" name="ec_text_color" id="ec_text_color" value="<?php echo esc_attr($tx); ?>"></td>
    </tr>
    <?php
}
add_action('ec_event_type_edit_form_fields', 'ec_event_type_edit_meta_fields', 10, 2);

// Сохранение мета-данных
function ec_save_event_type_meta($term_id) {
    if (isset($_POST['ec_background_color'])) {
        update_term_meta($term_id, 'ec_background_color', sanitize_hex_color($_POST['ec_background_color']));
    }
    if (isset($_POST['ec_text_color'])) {
        update_term_meta($term_id, 'ec_text_color', sanitize_hex_color($_POST['ec_text_color']));
    }
}
add_action('created_ec_event_type', 'ec_save_event_type_meta');
add_action('edited_ec_event_type', 'ec_save_event_type_meta');