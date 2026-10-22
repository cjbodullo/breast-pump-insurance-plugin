<?php
/**
 * Data is removed only after an administrator turns that choice on.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!get_option('bbgc_delete_data_on_uninstall')) {
    return;
}

global $wpdb;

$tables = [
    'response_brands',
    'category_suggestions',
    'events',
    'responses',
    'journeys',
    'insurer_areas',
    'audit_log',
    'insurers',
];
foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'bbgc_' . $table);
}

foreach ([
    'bbgc_db_version',
    'bbgc_session_hours',
    'bbgc_unsubmitted_days',
    'bbgc_event_days',
    'bbgc_response_days',
    'bbgc_min_cell',
    'bbgc_return_url',
    'bbgc_delete_data_on_uninstall',
] as $option) {
    delete_option($option);
}

wp_clear_scheduled_hook('bbgc_retention_daily');
