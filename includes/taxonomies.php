<?php
/**
 * taxonomies.php
 *
 * Регистрация таксономий для CPT «ec_event» и кастомные метабоксы,
 * которые позволяют:
 *  1) Добавлять новый термин прямо из записи (input + кнопка «Добавить»).
 *  2) Выбирать уже существующий термин в компактном <select>.
 *  3) Убрать стандартные чекбоксы и «Родительская рубрика» 
 *     как из редактора записи, так и со страниц терминов в админке.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1) Регистрируем таксономии «ec_event_type», «ec_organizer», «ec_location».
 *    При этом:
 *      – 'show_ui' => true  → чтобы появились пункты меню «Мероприятия → Тип мероприятия» и т. д.
 *      – 'show_in_rest' => false  → чтобы WP не рендерил штатную панель Gutenberg.
 */
add_action( 'init', 'ec_register_event_taxonomies' );
function ec_register_event_taxonomies() {
    register_taxonomy(
        'ec_event_type',
        'ec_event',
        array(
            'label'             => 'Тип мероприятия',
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => false,
            'show_admin_column' => false,
            'rewrite'           => array(
                'slug'       => 'event-type',
                'with_front' => false,
            ),
        )
    );

    register_taxonomy(
        'ec_organizer',
        'ec_event',
        array(
            'label'             => 'Организатор',
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'meta_box_cb'       => false, // 🔧 отключает дублирующий метабокс
            'show_in_rest'      => false,
            'show_admin_column' => false,
            'rewrite'           => array(
                'slug'       => 'organizer',
                'with_front' => false,
            ),
        )
    );


    register_taxonomy(
        'ec_location',
        'ec_event',
        array(
            'label'             => 'Место проведения',
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => false,
            'show_admin_column' => false,
            'rewrite'           => array(
                'slug'       => 'location',
                'with_front' => false,
            ),
        )
    );
}

/**
 * 2) Удаляем штатные метабоксы таксономий в редакторе записи CPT «ec_event»,
 *    чтобы не было дубликатов и стандартных чекбоксов/родительских селектов.
 */
add_action( 'add_meta_boxes', 'ec_remove_default_taxonomy_metaboxes', 1 );
function ec_remove_default_taxonomy_metaboxes() {
    $post_type = 'ec_event';

    // «ec_event_type»: удаляем both “typediv” and “tagsdiv-ec_event_type”
    remove_meta_box( 'ec_event_typediv', $post_type, 'side' );
    remove_meta_box( 'tagsdiv-ec_event_type', $post_type, 'side' );

    // «ec_organizer»
    remove_meta_box( 'ec_organizertdiv', $post_type, 'side' );
    remove_meta_box( 'tagsdiv-ec_organizer', $post_type, 'side' );

    // «ec_location»
    remove_meta_box( 'ec_locationdiv', $post_type, 'side' );
    remove_meta_box( 'tagsdiv-ec_location', $post_type, 'side' );
}

/**
 * 3) Добавляем собственные метабоксы с input + кнопка «Добавить» + компактный <select>.
 */
add_action( 'add_meta_boxes', 'ec_add_custom_taxonomy_metaboxes' );
function ec_add_custom_taxonomy_metaboxes() {
    $post_type = 'ec_event';

    // «Тип мероприятия»
    add_meta_box(
        'ec_event_type_dropdown',
        'Тип мероприятия',
        'ec_render_event_type_metabox',
        $post_type,
        'side',
        'default'
    );

    // «Организатор»
    add_meta_box(
        'ec_organizer_dropdown',
        'Организатор',
        'ec_render_organizer_metabox',
        $post_type,
        'side',
        'default'
    );

    // «Место проведения»
    add_meta_box(
        'ec_location_dropdown',
        'Место проведения',
        'ec_render_location_metabox',
        $post_type,
        'side',
        'default'
    );
}

/**
 * 4) Callback: рендерим метабокс «Тип мероприятия» (input + button + select).
 */
function ec_render_event_type_metabox( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'ec_tax_nonce' );

    // 4.1) Если пользователь ввёл новое название и нажал кнопку «Добавить»:
    if ( isset( $_POST['ec_event_type_new'] ) && check_admin_referer( basename( __FILE__ ), 'ec_tax_nonce' ) ) {
        $new_name = sanitize_text_field( $_POST['ec_event_type_new'] );
        if ( $new_name !== '' ) {
            if ( ! term_exists( $new_name, 'ec_event_type' ) ) {
                wp_insert_term( $new_name, 'ec_event_type' );
            }
            // Очищаем input после перезагрузки
            echo '<script>document.getElementById("ec_event_type_new").value = "";</script>';
        }
    }

    // 4.2) Получаем все термины таксономии «ec_event_type»
    $terms = get_terms( array(
        'taxonomy'   => 'ec_event_type',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );

    // 4.3) Текущий термин записи (берём только первый из списка)
    $current = wp_get_object_terms( $post->ID, 'ec_event_type', array( 'fields' => 'ids' ) );
    $current_id = ( ! empty( $current ) ) ? intval( $current[0] ) : 0;

    // 4.4) Поле для добавления нового термина
    echo '<p><label for="ec_event_type_new"><strong>Добавить новый тип:</strong></label><br>';
    echo '<input type="text" id="ec_event_type_new" name="ec_event_type_new" placeholder="Новое название" style="width:100%; margin-bottom:4px;">';
    echo '<button type="submit" class="button" name="ec_event_type_add" value="1">Добавить</button></p>';

    // 4.5) Компактный <select> со всеми терминами (ширина 80%, но не больше 160px)
    echo '<p><label for="ec_event_type_select"><strong>Выберите тип:</strong></label><br>';
    echo '<select name="ec_event_type_select" id="ec_event_type_select" style="width:80%; max-width:160px; margin-top:4px;">';
    echo '<option value="0">— Не задан —</option>';
    foreach ( $terms as $term ) {
        $sel = ( $term->term_id === $current_id ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $sel . '>' . esc_html( $term->name ) . '</option>';
    }
    echo '</select></p>';
}

/**
 * 5) Callback: рендерим метабокс «Организатор».
 */
function ec_render_organizer_metabox( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'ec_tax_nonce' );

    if ( isset( $_POST['ec_organizer_new'] ) && check_admin_referer( basename( __FILE__ ), 'ec_tax_nonce' ) ) {
        $new_name = sanitize_text_field( $_POST['ec_organizer_new'] );
        if ( $new_name !== '' ) {
            if ( ! term_exists( $new_name, 'ec_organizer' ) ) {
                wp_insert_term( $new_name, 'ec_organizer' );
            }
            echo '<script>document.getElementById("ec_organizer_new").value = "";</script>';
        }
    }

    $terms = get_terms( array(
        'taxonomy'   => 'ec_organizer',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );
    $current = wp_get_object_terms( $post->ID, 'ec_organizer', array( 'fields' => 'ids' ) );
    $current_id = ( ! empty( $current ) ) ? intval( $current[0] ) : 0;

    echo '<p><label for="ec_organizer_new"><strong>Добавить нового организатора:</strong></label><br>';
    echo '<input type="text" id="ec_organizer_new" name="ec_organizer_new" placeholder="Новое название" style="width:100%; margin-bottom:4px;">';
    echo '<button type="submit" class="button" name="ec_organizer_add" value="1">Добавить</button></p>';

    echo '<p><label for="ec_organizer_select"><strong>Выберите организатора:</strong></label><br>';
    echo '<select name="ec_organizer_select" id="ec_organizer_select" style="width:80%; max-width:160px; margin-top:4px;">';
    echo '<option value="0">— Не задан —</option>';
    foreach ( $terms as $term ) {
        $sel = ( $term->term_id === $current_id ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $sel . '>' . esc_html( $term->name ) . '</option>';
    }
    echo '</select></p>';
}

/**
 * 6) Callback: рендерим метабокс «Место проведения».
 */
function ec_render_location_metabox( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'ec_tax_nonce' );

    if ( isset( $_POST['ec_location_new'] ) && check_admin_referer( basename( __FILE__ ), 'ec_tax_nonce' ) ) {
        $new_name = sanitize_text_field( $_POST['ec_location_new'] );
        if ( $new_name !== '' ) {
            if ( ! term_exists( $new_name, 'ec_location' ) ) {
                wp_insert_term( $new_name, 'ec_location' );
            }
            echo '<script>document.getElementById("ec_location_new").value = "";</script>';
        }
    }

    $terms = get_terms( array(
        'taxonomy'   => 'ec_location',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );
    $current = wp_get_object_terms( $post->ID, 'ec_location', array( 'fields' => 'ids' ) );
    $current_id = ( ! empty( $current ) ) ? intval( $current[0] ) : 0;

    echo '<p><label for="ec_location_new"><strong>Добавить новое место:</strong></label><br>';
    echo '<input type="text" id="ec_location_new" name="ec_location_new" placeholder="Новое название" style="width:100%; margin-bottom:4px;">';
    echo '<button type="submit" class="button" name="ec_location_add" value="1">Добавить</button></p>';

    echo '<p><label for="ec_location_select"><strong>Выберите место:</strong></label><br>';
    echo '<select name="ec_location_select" id="ec_location_select" style="width:80%; max-width:160px; margin-top:4px;">';
    echo '<option value="0">— Не задано —</option>';
    foreach ( $terms as $term ) {
        $sel = ( $term->term_id === $current_id ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $sel . '>' . esc_html( $term->name ) . '</option>';
    }
    echo '</select></p>';
}

/**
 * 7) Сохраняем выбранные/добавленные термины при сохранении записи ec_event.
 */
add_action( 'save_post', 'ec_save_event_taxonomies', 10, 2 );
function ec_save_event_taxonomies( $post_id, $post ) {
    // Только для CPT ec_event
    if ( $post->post_type !== 'ec_event' ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Проверяем nonce
    if ( ! isset( $_POST['ec_tax_nonce'] ) || ! wp_verify_nonce( $_POST['ec_tax_nonce'], basename( __FILE__ ) ) ) {
        return;
    }

    // 7.1) «Тип мероприятия»
    if ( isset( $_POST['ec_event_type_new'] ) && sanitize_text_field( $_POST['ec_event_type_new'] ) !== '' ) {
        $new_name = sanitize_text_field( $_POST['ec_event_type_new'] );
        if ( ! term_exists( $new_name, 'ec_event_type' ) ) {
            $inserted = wp_insert_term( $new_name, 'ec_event_type' );
            if ( ! is_wp_error( $inserted ) ) {
                $term_id = intval( $inserted['term_id'] );
                wp_set_object_terms( $post_id, array( $term_id ), 'ec_event_type', false );
            }
        }
    } elseif ( isset( $_POST['ec_event_type_select'] ) ) {
        $term_id = intval( $_POST['ec_event_type_select'] );
        if ( $term_id > 0 ) {
            wp_set_object_terms( $post_id, array( $term_id ), 'ec_event_type', false );
        } else {
            wp_set_object_terms( $post_id, array(), 'ec_event_type', false );
        }
    }

    // 7.2) «Организатор»
    if ( isset( $_POST['ec_organizer_new'] ) && sanitize_text_field( $_POST['ec_organizer_new'] ) !== '' ) {
        $new_name = sanitize_text_field( $_POST['ec_organizer_new'] );
        if ( ! term_exists( $new_name, 'ec_organizer' ) ) {
            $inserted = wp_insert_term( $new_name, 'ec_organizer' );
            if ( ! is_wp_error( $inserted ) ) {
                $term_id = intval( $inserted['term_id'] );
                wp_set_object_terms( $post_id, array( $term_id ), 'ec_organizer', false );
            }
        }
    } elseif ( isset( $_POST['ec_organizer_select'] ) ) {
        $term_id = intval( $_POST['ec_organizer_select'] );
        if ( $term_id > 0 ) {
            wp_set_object_terms( $post_id, array( $term_id ), 'ec_organizer', false );
        } else {
            wp_set_object_terms( $post_id, array(), 'ec_organizer', false );
        }
    }

    // 7.3) «Место проведения»
    if ( isset( $_POST['ec_location_new'] ) && sanitize_text_field( $_POST['ec_location_new'] ) !== '' ) {
        $new_name = sanitize_text_field( $_POST['ec_location_new'] );
        if ( ! term_exists( $new_name, 'ec_location' ) ) {
            $inserted = wp_insert_term( $new_name, 'ec_location' );
            if ( ! is_wp_error( $inserted ) ) {
                $term_id = intval( $inserted['term_id'] );
                wp_set_object_terms( $post_id, array( $term_id ), 'ec_location', false );
            }
        }
    } elseif ( isset( $_POST['ec_location_select'] ) ) {
        $term_id = intval( $_POST['ec_location_select'] );
        if ( $term_id > 0 ) {
            wp_set_object_terms( $post_id, array( $term_id ), 'ec_location', false );
        } else {
            wp_set_object_terms( $post_id, array(), 'ec_location', false );
        }
    }
}

/**
 * 8) Скрываем поле «Родительская рубрика» на страницах управления терминами
 *    «Мероприятия → Тип мероприятия/Организатор/Место проведения».
 */
add_action( 'admin_head-edit-tags.php', 'ec_hide_parent_fields_on_term_pages' );
function ec_hide_parent_fields_on_term_pages() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->taxonomy === '' ) {
        return;
    }

    // Прячем родительский контрол только для наших таксономий
    if ( in_array( $screen->taxonomy, array( 'ec_event_type', 'ec_organizer', 'ec_location' ), true ) ) {
        echo '<style>
            /* Скрываем блок «Родительская рубрика» в форме «Добавить термин» */
            .form-field.term-parent-wrap { display: none !important; }
            /* Скрываем на странице редактирования термина */
            .edit-tags-php .form-field.term-parent-wrap { display: none !important; }
        </style>';
    }
}

/**
 * 9) Добавляем адрес к термину "Место проведения"
 */
add_action( 'ec_location_add_form_fields', 'ec_location_add_address_field' );
add_action( 'ec_location_edit_form_fields', 'ec_location_edit_address_field', 10, 2 );
add_action( 'create_ec_location', 'ec_location_save_address' );
add_action( 'edited_ec_location', 'ec_location_save_address' );

function ec_location_add_address_field() {
    ?>
    <div class="form-field term-group">
        <label for="ec_location_address">Адрес</label>
        <input type="text" id="ec_location_address" name="ec_location_address" value="" />
    </div>
    <?php
}

function ec_location_edit_address_field( $term ) {
    $address = get_term_meta( $term->term_id, 'ec_location_address', true );
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="ec_location_address">Адрес</label></th>
        <td><input type="text" id="ec_location_address" name="ec_location_address" value="<?php echo esc_attr( $address ); ?>" /></td>
    </tr>
    <?php
}

function ec_location_save_address( $term_id ) {
    if ( isset( $_POST['ec_location_address'] ) ) {
        update_term_meta( $term_id, 'ec_location_address', sanitize_text_field( $_POST['ec_location_address'] ) );
    }
}


// Дополнительные поля к месту проведения
add_action('ec_location_edit_form_fields', function($term) {
    $fields = [
        'region' => 'Регион',
        'city' => 'Город'
    ];
    foreach ($fields as $key => $label) {
        $value = get_term_meta($term->term_id, 'ec_location_' . $key, true);
        echo '<tr class="form-field term-group-wrap">';
        echo '<th scope="row"><label for="ec_location_' . $key . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="text" name="ec_location_' . $key . '" id="ec_location_' . $key . '" value="' . esc_attr($value) . '" /></td>';
        echo '</tr>';
    }
});

add_action('edited_ec_location', function($term_id) {
    foreach (['region', 'city'] as $key) {
        if (isset($_POST['ec_location_' . $key])) {
            update_term_meta($term_id, 'ec_location_' . $key, sanitize_text_field($_POST['ec_location_' . $key]));
        }
    }
});

// ✅ Добавим мета-поля к организатору
add_action('ec_organizer_add_form_fields', 'ec_add_organizer_meta_fields');
add_action('ec_organizer_edit_form_fields', 'ec_edit_organizer_meta_fields', 10, 2);

function ec_add_organizer_meta_fields($taxonomy) {
    ?>
    <div class="form-field">
        <label for="ec_organizer_phone">Телефон</label>
        <input type="text" name="ec_organizer_phone" id="ec_organizer_phone" />
    </div>
    <div class="form-field">
        <label for="ec_organizer_email">Email</label>
        <input type="email" name="ec_organizer_email" id="ec_organizer_email" />
    </div>
    <div class="form-field">
        <label for="ec_organizer_website">Веб-сайт</label>
        <input type="url" name="ec_organizer_website" id="ec_organizer_website" />
    </div>
    <?php
}

function ec_edit_organizer_meta_fields($term, $taxonomy) {
    $phone   = get_term_meta($term->term_id, 'ec_organizer_phone', true);
    $email   = get_term_meta($term->term_id, 'ec_organizer_email', true);
    $website = get_term_meta($term->term_id, 'ec_organizer_website', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="ec_organizer_phone">Телефон</label></th>
        <td><input type="text" name="ec_organizer_phone" id="ec_organizer_phone" value="<?php echo esc_attr($phone); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="ec_organizer_email">Email</label></th>
        <td><input type="email" name="ec_organizer_email" id="ec_organizer_email" value="<?php echo esc_attr($email); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="ec_organizer_website">Веб-сайт</label></th>
        <td><input type="url" name="ec_organizer_website" id="ec_organizer_website" value="<?php echo esc_attr($website); ?>" /></td>
    </tr>
    <?php
}

// ✅ Сохраняем мета-поля при сохранении термина
add_action('created_ec_organizer', 'ec_save_organizer_meta_fields', 10, 2);
add_action('edited_ec_organizer', 'ec_save_organizer_meta_fields', 10, 2);

function ec_save_organizer_meta_fields($term_id) {
    if (isset($_POST['ec_organizer_phone'])) {
        update_term_meta($term_id, 'ec_organizer_phone', sanitize_text_field($_POST['ec_organizer_phone']));
    }
    if (isset($_POST['ec_organizer_email'])) {
        update_term_meta($term_id, 'ec_organizer_email', sanitize_email($_POST['ec_organizer_email']));
    }
    if (isset($_POST['ec_organizer_website'])) {
        update_term_meta($term_id, 'ec_organizer_website', esc_url_raw($_POST['ec_organizer_website']));
    }
}
