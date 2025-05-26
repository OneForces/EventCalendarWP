<?php
function ec_add_export_menu_item() {
    add_submenu_page(
        'edit.php?post_type=ec_event',
        'Экспорт мероприятий',
        'Экспорт',
        'manage_options',
        'ec-export',
        'ec_render_export_page'
    );
}
add_action('admin_menu', 'ec_add_export_menu_item');

function ec_render_export_page() {
    ?>
    <div class="wrap">
        <h1>Экспорт мероприятий</h1>
        <form method="post">
            <input type="submit" name="ec_export_csv" class="button button-primary" value="Экспорт в CSV" />
        </form>
    </div>
    <?php

    if (isset($_POST['ec_export_csv'])) {
        ec_export_events_to_csv();
        exit;
    }
}

function ec_export_events_to_csv() {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=events.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Название', 'Дата начала', 'Дата окончания', 'Организатор', 'Место', 'Тип']);

    $query = new WP_Query([
        'post_type' => 'ec_event',
        'posts_per_page' => -1,
    ]);

    while ($query->have_posts()) {
        $query->the_post();

        $start = get_post_meta(get_the_ID(), 'ec_event_start', true);
        $end = get_post_meta(get_the_ID(), 'ec_event_end', true);

        $organizer = wp_get_post_terms(get_the_ID(), 'ec_organizer', ['fields' => 'names']);
        $location = wp_get_post_terms(get_the_ID(), 'ec_location', ['fields' => 'names']);
        $type = wp_get_post_terms(get_the_ID(), 'ec_event_type', ['fields' => 'names']);

        fputcsv($output, [
            get_the_title(),
            $start,
            $end,
            implode(', ', $organizer),
            implode(', ', $location),
            implode(', ', $type),
        ]);
    }

    wp_reset_postdata();
    fclose($output);
}