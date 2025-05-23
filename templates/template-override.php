<?php
add_filter('template_include', 'ec_override_archive_template');
function ec_override_archive_template($template) {
    if (is_post_type_archive('ec_event')) {
        return plugin_dir_path(__FILE__) . '../templates/archive-ec_event.php';
    }
    return $template;
}
