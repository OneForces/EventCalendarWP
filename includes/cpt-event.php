<?php

function ec_register_event_cpt() {
    $labels = [
        'name' => 'Календарь мероприятий',
        'singular_name' => 'Мероприятие',
        'add_new' => 'Добавить мероприятие',
        'add_new_item' => 'Добавить новое мероприятие',
        'edit_item' => 'Редактировать мероприятие',
        'new_item' => 'Новое мероприятие',
        'view_item' => 'Просмотреть мероприятие',
        'search_items' => 'Искать мероприятия',
        'not_found' => 'Не найдено',
        'not_found_in_trash' => 'В корзине не найдено',
        'menu_name' => 'Календарь мероприятий'
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
        'taxonomies' => ['ec_event_type'],
    ];

    register_post_type('ec_event', $args);
}



add_action('init', 'ec_register_event_cpt');


