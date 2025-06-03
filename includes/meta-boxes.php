<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Добавление метабокса для событий
 */
function ec_add_event_meta_boxes() {
    add_meta_box(
        'ec_event_details',
        'Детали мероприятия',
        'ec_render_event_meta_box',
        'ec_event',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'ec_add_event_meta_boxes' );

/**
 * HTML-содержимое метабокса
 */
function ec_render_event_meta_box( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'ec_event_nonce' );

    // Получаем текущие сохранённые данные (из post_meta)
    $start   = get_post_meta( $post->ID, 'ec_event_start', true );
    $end     = get_post_meta( $post->ID, 'ec_event_end', true );
    $address = get_post_meta( $post->ID, 'ec_event_address', true );
    $region  = get_post_meta( $post->ID, 'ec_event_region', true );
    $city    = get_post_meta( $post->ID, 'ec_event_city', true );
    $all_day = get_post_meta( $post->ID, 'ec_event_all_day', true );

    // Получаем метаданные из таксономии "Место проведения" (ec_location)
    $locs = get_the_terms( $post->ID, 'ec_location' );
    $loc_region         = '';
    $loc_city           = '';
    $loc_address_from   = '';
    if ( $locs && ! is_wp_error( $locs ) ) {
        $term_id       = $locs[0]->term_id;
        $loc_region    = get_term_meta( $term_id, 'ec_location_region', true );
        $loc_city      = get_term_meta( $term_id, 'ec_location_city', true );
        $loc_address_from = get_term_meta( $term_id, 'ec_location_address', true );
    }

    // Если у события поля пустые, заполняем их из места проведения
    if ( empty( $region ) && ! empty( $loc_region ) ) {
        $region = $loc_region;
    }
    if ( empty( $city ) && ! empty( $loc_city ) ) {
        $city = $loc_city;
    }
    if ( empty( $address ) && ! empty( $loc_address_from ) ) {
        $address = $loc_address_from;
    }
    ?>

    <style>
        .ec-datetime-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .ec-datetime-row label {
            width: 130px;
        }
        .ec-datetime-row input {
            width: 250px;
            max-width: 100%;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Переключение типов полей «дата/время» при выборе «Весь день»
            const checkbox = document.getElementById('ec_event_all_day');
            const startInput = document.getElementById('ec_event_start');
            const endInput   = document.getElementById('ec_event_end');

            function toggleDatetimeInputs() {
                const type = checkbox.checked ? 'date' : 'datetime-local';
                startInput.type = type;
                endInput.type   = type;
            }

            if (checkbox && startInput && endInput) {
                checkbox.addEventListener('change', toggleDatetimeInputs);
                toggleDatetimeInputs();
            }

            // Автоматическое заполнение «Регион», «Город», «Адрес (улица, дом)» из выбранного "Места проведения"
            const locSelect = document.getElementById('ec_location_select');
            if (locSelect) {
                locSelect.addEventListener('change', function() {
                    const locationId = this.value;
                    if (!locationId) return;

                    const data = new FormData();
                    data.append('action', 'ec_get_location_details');
                    data.append('location_id', locationId);

                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: data
                    })
                    .then(response => response.json())
                    .then(res => {
                        if (res.success) {
                            // Заполняем поля формы
                            const regionInput  = document.getElementById('ec_event_region');
                            const cityInput    = document.getElementById('ec_event_city');
                            const addrInput    = document.getElementById('ec_event_address');

                            if (regionInput) regionInput.value = res.data.region || '';
                            if (cityInput)   cityInput.value   = res.data.city   || '';
                            if (addrInput)   addrInput.value   = res.data.address || '';
                        }
                    });
                });
            }
        });
    </script>

    <p>
        <label>
            <input type="checkbox" name="ec_event_all_day" id="ec_event_all_day" value="1" <?php checked( $all_day, '1' ); ?> />
            Весь день
        </label>
    </p>

    <div class="ec-datetime-row">
        <label for="ec_event_start">Дата начала:</label>
        <input type="datetime-local" name="ec_event_start" id="ec_event_start" value="<?php echo esc_attr( $start ); ?>" />
    </div>

    <div class="ec-datetime-row">
        <label for="ec_event_end">Дата окончания:</label>
        <input type="datetime-local" name="ec_event_end" id="ec_event_end" value="<?php echo esc_attr( $end ); ?>" />
    </div>

    <hr>

    <p>
        <label for="ec_event_region"><strong>Регион:</strong></label><br>
        <input type="text" name="ec_event_region" id="ec_event_region" value="<?php echo esc_attr( $region ); ?>" style="width:100%;" />
    </p>
    <p>
        <label for="ec_event_city"><strong>Город:</strong></label><br>
        <input type="text" name="ec_event_city" id="ec_event_city" value="<?php echo esc_attr( $city ); ?>" style="width:100%;" />
    </p>
    <p>
        <label for="ec_event_address"><strong>Адрес (улица, дом):</strong></label><br>
        <input type="text" name="ec_event_address" id="ec_event_address" value="<?php echo esc_attr( $address ); ?>" style="width:100%;" />
    </p>
    <?php
}

/**
 * Сохраняет метаданные мероприятия
 */
function ec_save_event_meta( $post_id ) {
    if ( ! isset( $_POST['ec_event_nonce'] ) || ! wp_verify_nonce( $_POST['ec_event_nonce'], basename( __FILE__ ) ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( get_post_type( $post_id ) !== 'ec_event' ) return;

    $fields = [
        'ec_event_start',
        'ec_event_end',
        'ec_event_region',
        'ec_event_city',
        'ec_event_address',
    ];

    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
        }
    }

    $all_day = isset( $_POST['ec_event_all_day'] ) ? '1' : '0';
    update_post_meta( $post_id, 'ec_event_all_day', $all_day );
}
add_action('save_post', 'ec_save_event_meta' );

/**
 * При сохранении поста — подставляет адрес из таксономии ec_location (если он есть)
 */
add_action( 'save_post_ec_event', 'ec_save_address_from_location_to_postmeta' );
function ec_save_address_from_location_to_postmeta( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $terms = get_the_terms( $post_id, 'ec_location' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        $address = get_term_meta( $terms[0]->term_id, 'ec_location_address', true );
        update_post_meta( $post_id, 'ec_event_address', sanitize_text_field( $address ) );
    }
}
