<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Регистрируем CPT "ec_event"
 */
function ec_register_event_cpt() {
    $labels = array(
        'name'                  => 'Мероприятия',
        'singular_name'         => 'Мероприятие',
        'add_new'               => 'Добавить мероприятие',
        'add_new_item'          => 'Новое мероприятие',
        'edit_item'             => 'Редактировать мероприятие',
        'new_item'              => 'Новое мероприятие',
        'view_item'             => 'Смотреть мероприятие',
        'search_items'          => 'Искать мероприятия',
        'not_found'             => 'Не найдено',
        'not_found_in_trash'    => 'В корзине не найдено',
        'menu_name'             => 'Мероприятия',
    );

    $args = array(
        'labels'         => $labels,
        'public'         => true,
        'show_ui'        => true,
        'show_in_menu'   => true,
        'menu_icon'      => 'dashicons-calendar-alt',
        'menu_position'  => 5,
        'supports'       => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'has_archive'    => true,
        'rewrite'        => array( 'slug' => 'events', 'with_front' => false ),
        'show_in_rest'   => true,
        'taxonomies'     => array( 'ec_event_type', 'ec_organizer', 'ec_location' ),
    );

    register_post_type( 'ec_event', $args );
}
add_action( 'init', 'ec_register_event_cpt' );
