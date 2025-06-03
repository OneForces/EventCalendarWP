<?php
/**
 * Код при удалении плагина
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Удаляем таблицу RSVP (если создавалась)
global $wpdb;
$table = $wpdb->prefix . 'ec_rsvps';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// Удаляем все опции плагина
delete_option( 'ec_default_view' );
delete_option( 'ec_timezone' );

// Можно добавить удаление метаполей и т.д.
