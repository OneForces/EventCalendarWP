<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'template_include', 'ec_override_templates' );
function ec_override_templates( $template ) {
    if ( is_post_type_archive( 'ec_event' ) ) {
        return plugin_dir_path( __FILE__ ) . 'templates/archive-ec_event.php';
    }
    if ( is_singular( 'ec_event' ) ) {
        return plugin_dir_path( __FILE__ ) . '../single-ec_event.php';
    }
    return $template;
}
