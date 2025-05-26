<?php

function ec_register_event_cpt() {
    $labels = [
        'name' => 'Мероприятия',
        'singular_name' => 'Мероприятие',
        'add_new' => 'Добавить мероприятие',
        'add_new_item' => 'Новое мероприятие',
        'edit_item' => 'Редактировать мероприятие',
        'new_item' => 'Новое мероприятие',
        'view_item' => 'Смотреть мероприятие',
        'search_items' => 'Искать мероприятия',
        'not_found' => 'Не найдено',
        'not_found_in_trash' => 'В корзине не найдено',
    ];

    $args = [
        'labels' => $labels,
        'public' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'menu_position' => 5,
        'supports' => ['title', 'editor'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'events'],
        'show_in_rest' => true,
        'taxonomies' => ['ec_event_type', 'ec_organizer', 'ec_location'],
    ];

    register_post_type('ec_event', $args);
}

add_action('init', 'ec_register_event_cpt');
