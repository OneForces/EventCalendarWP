<?php
/**
 * export.php
 *
 * Этот файл отвечал за пункт «Экспорт» в меню «Мероприятия».
 * Мы удалили регистрацию подменю, чтобы пункт «Экспорт» полностью исчез из админки.
 * Сами функции генерации CSV при необходимости остаются, но без доступа из меню.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1) Здесь раньше была регистрация подменю «Экспорт»:
 *
 * add_action( 'admin_menu', 'ec_register_export_submenu' );
 * function ec_register_export_submenu() {
 *     add_submenu_page(
 *         'edit.php?post_type=ec_event',
 *         'Экспорт мероприятий',
 *         'Экспорт',
 *         'manage_options',
 *         'ec_export_events',
 *         'ec_render_export_page'
 *     );
 * }
 *
 * Мы удалили/закомментировали этот блок, чтобы меню «Экспорт» больше не отображалось.
 */

/**
 * 2) Остались функции для генерации CSV, если понадобятся вызовы напрямую (например,
 *    вы могли бы вызвать ec_generate_csv_export() из другого места).
 */

/**
 * Генерирует CSV-выгрузку всех мероприятий.
 * Вы можете вызывать эту функцию программно, если нужно сохранить CSV вне админки.
 */
function ec_generate_csv_export() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Недостаточно прав для экспорта.' );
    }

    // Заголовки CSV
    $headers = array(
        'ID',
        'Название',
        'Дата начала',
        'Дата окончания',
        'Тип',
        'Организатор',
        'Место проведения',
        'Адрес',
    );

    // HTTP-заголовки, чтобы браузер предложил скачать файл
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=events-export-' . date( 'Y-m-d' ) . '.csv' );
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, $headers );

    // Получаем все публикации CPT ec_event
    $args   = array(
        'post_type'      => 'ec_event',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );
    $events = get_posts( $args );

    foreach ( $events as $event ) {
        $event_id = $event->ID;
        $title    = $event->post_title;
        $date_s   = get_post_meta( $event_id, 'ec_event_start', true );
        $date_e   = get_post_meta( $event_id, 'ec_event_end',   true );

        // Форматируем дату как 'd.m.Y H:i'
        $formatted_start = $date_s ? date_i18n( 'd.m.Y H:i', strtotime( $date_s ) ) : '';
        $formatted_end   = $date_e ? date_i18n( 'd.m.Y H:i', strtotime( $date_e ) ) : '';

        // Таксономии
        $terms_type = get_the_terms( $event_id, 'ec_event_type' );
        $type_names = (! empty( $terms_type ) && ! is_wp_error( $terms_type ))
            ? implode( ', ', wp_list_pluck( $terms_type, 'name' ) )
            : '';

        $terms_org = get_the_terms( $event_id, 'ec_organizer' );
        $org_names = (! empty( $terms_org ) && ! is_wp_error( $terms_org ))
            ? implode( ', ', wp_list_pluck( $terms_org, 'name' ) )
            : '';

        $terms_loc = get_the_terms( $event_id, 'ec_location' );
        $loc_names = (! empty( $terms_loc ) && ! is_wp_error( $terms_loc ))
            ? implode( ', ', wp_list_pluck( $terms_loc, 'name' ) )
            : '';

        // Адрес
        $address = get_post_meta( $event_id, 'ec_event_address', true );

        // Собираем строку и выводим
        $row = array(
            $event_id,
            $title,
            $formatted_start,
            $formatted_end,
            $type_names,
            $org_names,
            $loc_names,
            $address,
        );

        fputcsv( $output, $row );
    }

    fclose( $output );
    exit;
}
