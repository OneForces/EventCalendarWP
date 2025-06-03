<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX-обработчик для получения событий (JSON для FullCalendar)
 */
add_action( 'wp_ajax_nopriv_ec_get_events', 'ec_get_events' );
add_action( 'wp_ajax_ec_get_events',        'ec_get_events' );

function ec_get_events() {
    // Проверка nonce (если вы передаёте его из JS)
    if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ec_get_events_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Неверный запрос.', 'event-calendar' ) ), 400 );
    }

    // Фильтрация по таксономиям
    $tax_query = array( 'relation' => 'AND' );
    if ( ! empty( $_POST['event_type'] ) ) {
        $tax_query[] = array(
            'taxonomy' => 'ec_event_type',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( wp_unslash( $_POST['event_type'] ) ),
        );
    }
    if ( ! empty( $_POST['organizer'] ) ) {
        $tax_query[] = array(
            'taxonomy' => 'ec_organizer',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( wp_unslash( $_POST['organizer'] ) ),
        );
    }

    // Фильтрация по дате
    $meta_query = [];
    if ( ! empty( $_POST['date_start'] ) ) {
        $meta_query[] = array(
            'key'     => 'ec_event_start',
            'value'   => sanitize_text_field( wp_unslash( $_POST['date_start'] ) ),
            'compare' => '>=',
            'type'    => 'DATE',
        );
    }
    if ( ! empty( $_POST['date_end'] ) ) {
        $meta_query[] = array(
            'key'     => 'ec_event_end',
            'value'   => sanitize_text_field( wp_unslash( $_POST['date_end'] ) ),
            'compare' => '<=',
            'type'    => 'DATE',
        );
    }

    // Поиск по названию
    $search_query = '';
    if ( ! empty( $_POST['ec_search'] ) ) {
        $search_query = sanitize_text_field( wp_unslash( $_POST['ec_search'] ) );
    }

    // Основной запрос
    $args = array(
        'post_type'      => 'ec_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );

    if ( count( $tax_query ) > 1 ) {
        $args['tax_query'] = $tax_query;
    }

    if ( ! empty( $meta_query ) ) {
        $args['meta_query'] = $meta_query;
    }

    if ( $search_query ) {
        $args['s'] = $search_query;
    }

    $query = new WP_Query( $args );
    $events = [];

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id   = get_the_ID();
            $start_raw = get_post_meta( $post_id, 'ec_event_start', true );
            $end_raw   = get_post_meta( $post_id, 'ec_event_end', true );

            $start_dt = ! empty( $start_raw ) ? date( 'c', strtotime( $start_raw ) ) : '';
            $end_dt   = ! empty( $end_raw )   ? date( 'c', strtotime( $end_raw ) )   : $start_dt;

            $events[] = array(
                'id'    => $post_id,
                'title' => get_the_title(),
                'start' => $start_dt,
                'end'   => $end_dt,
                'url'   => get_permalink( $post_id ),
            );
        }
        wp_reset_postdata();
    }

    wp_send_json_success( $events );
}


/**
 * AJAX-обработчик для получения данных конкретного "Места проведения"
 */
add_action( 'wp_ajax_ec_get_location_details',    'ec_get_location_details' );
add_action( 'wp_ajax_nopriv_ec_get_location_details', 'ec_get_location_details' );

function ec_get_location_details() {
    if ( ! isset( $_POST['location_id'] ) ) {
        wp_send_json_error();
    }

    $loc_id  = intval( $_POST['location_id'] );
    $region  = get_term_meta( $loc_id, 'ec_location_region', true );
    $city    = get_term_meta( $loc_id, 'ec_location_city', true );
    $address = get_term_meta( $loc_id, 'ec_location_address', true );

    wp_send_json_success( array(
        'region'  => $region,
        'city'    => $city,
        'address' => $address,
    ) );
}
