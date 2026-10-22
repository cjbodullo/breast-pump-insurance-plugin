<?php
/**
 * Tables, capabilities, seed data and retention for the guide.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BBGC_DB_VERSION', '1');
define('BBGC_NOTICE_VERSION', '2026-10-19');

function bbgc_table($name)
{
    global $wpdb;

    return $wpdb->prefix . 'bbgc_' . $name;
}

function bbgc_now()
{
    return current_time('mysql', true);
}

function bbgc_tables_ready()
{
    global $wpdb;
    $table = bbgc_table('insurers');

    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function bbgc_install()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $insurers = bbgc_table('insurers');
    $areas = bbgc_table('insurer_areas');
    $journeys = bbgc_table('journeys');
    $responses = bbgc_table('responses');
    $brands = bbgc_table('response_brands');
    $events = bbgc_table('events');
    $suggestions = bbgc_table('category_suggestions');
    $audit = bbgc_table('audit_log');

    dbDelta("CREATE TABLE {$insurers} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        code varchar(40) NOT NULL,
        name varchar(100) NOT NULL,
        name_fr varchar(100) NOT NULL DEFAULT '',
        active tinyint(1) NOT NULL DEFAULT 1,
        sort_order smallint(6) NOT NULL DEFAULT 0,
        login_url varchar(2048) DEFAULT NULL,
        fallback_url varchar(2048) DEFAULT NULL,
        registration_url varchar(2048) DEFAULT NULL,
        help_url varchar(2048) DEFAULT NULL,
        login_kind varchar(40) NOT NULL DEFAULT '',
        audience varchar(255) NOT NULL DEFAULT '',
        audience_fr varchar(255) NOT NULL DEFAULT '',
        navigation_text text NULL,
        navigation_text_fr text NULL,
        existing_help text NULL,
        existing_help_fr text NULL,
        new_help text NULL,
        new_help_fr text NULL,
        recovery_help text NULL,
        recovery_help_fr text NULL,
        areas_heading varchar(120) NOT NULL DEFAULT '',
        areas_heading_fr varchar(120) NOT NULL DEFAULT '',
        areas_note text NULL,
        areas_note_fr text NULL,
        show_route tinyint(1) NOT NULL DEFAULT 0,
        guidance_status varchar(40) NOT NULL DEFAULT 'published',
        source_urls longtext NULL,
        version int(11) NOT NULL DEFAULT 1,
        reviewed_at datetime DEFAULT NULL,
        reviewed_by bigint(20) unsigned DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY code (code)
    ) {$charset};");

    dbDelta("CREATE TABLE {$areas} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        insurer_id bigint(20) unsigned NOT NULL,
        code varchar(60) NOT NULL,
        title varchar(120) NOT NULL DEFAULT '',
        title_fr varchar(120) NOT NULL DEFAULT '',
        response_label varchar(120) NOT NULL DEFAULT '',
        response_label_fr varchar(120) NOT NULL DEFAULT '',
        instructions text NULL,
        instructions_fr text NULL,
        kind varchar(30) NOT NULL DEFAULT 'other',
        selectable tinyint(1) NOT NULL DEFAULT 1,
        active tinyint(1) NOT NULL DEFAULT 1,
        sort_order smallint(6) NOT NULL DEFAULT 0,
        verification_status varchar(40) NOT NULL DEFAULT 'published_navigation',
        source_url varchar(2048) DEFAULT NULL,
        reviewed_at datetime DEFAULT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY insurer_code (insurer_id,code),
        KEY insurer_id (insurer_id)
    ) {$charset};");

    dbDelta("CREATE TABLE {$journeys} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        public_id char(36) NOT NULL,
        token_hash char(64) NOT NULL,
        csrf_hash char(64) NOT NULL DEFAULT '',
        source varchar(40) NOT NULL DEFAULT 'direct',
        campaign_code varchar(80) NOT NULL DEFAULT '',
        insurer_id bigint(20) unsigned DEFAULT NULL,
        insurer_other varchar(100) DEFAULT NULL,
        account_help_status varchar(10) DEFAULT NULL,
        current_step smallint(6) NOT NULL DEFAULT 1,
        directory_version int(11) NOT NULL DEFAULT 1,
        status varchar(20) NOT NULL DEFAULT 'started',
        created_at datetime NOT NULL,
        last_seen_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        submitted_at datetime DEFAULT NULL,
        row_version int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        UNIQUE KEY public_id (public_id),
        UNIQUE KEY token_hash (token_hash),
        KEY created_source_insurer (created_at,source,insurer_id)
    ) {$charset};");

    dbDelta("CREATE TABLE {$responses} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        journey_id bigint(20) unsigned NOT NULL,
        insurer_id bigint(20) unsigned DEFAULT NULL,
        coverage_status varchar(20) NOT NULL,
        amount_cad decimal(12,2) DEFAULT NULL,
        amount_not_found tinyint(1) NOT NULL DEFAULT 0,
        reimbursement_percent decimal(5,2) DEFAULT NULL,
        amount_meaning varchar(40) NOT NULL DEFAULT 'unknown',
        prescription_required varchar(10) DEFAULT NULL,
        category_id bigint(20) unsigned DEFAULT NULL,
        category_label_snapshot varchar(120) NOT NULL DEFAULT '',
        category_other varchar(120) DEFAULT NULL,
        feedback_ease varchar(20) DEFAULT NULL,
        feedback_comment text NULL,
        retailer_code varchar(50) DEFAULT NULL,
        retailer_other varchar(100) DEFAULT NULL,
        purchase_timing varchar(30) DEFAULT NULL,
        other_brand varchar(100) DEFAULT NULL,
        is_self_reported tinyint(1) NOT NULL DEFAULT 1,
        directory_version int(11) NOT NULL DEFAULT 1,
        collection_notice_version varchar(40) NOT NULL DEFAULT '',
        consent_at datetime DEFAULT NULL,
        idempotency_key char(36) NOT NULL,
        row_version int(11) NOT NULL DEFAULT 1,
        submitted_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY journey_id (journey_id),
        UNIQUE KEY idempotency_key (idempotency_key),
        KEY submitted_insurer_outcome (submitted_at,insurer_id,coverage_status)
    ) {$charset};");

    dbDelta("CREATE TABLE {$brands} (
        response_id bigint(20) unsigned NOT NULL,
        brand_code varchar(40) NOT NULL,
        selected_at datetime NOT NULL,
        PRIMARY KEY  (response_id,brand_code)
    ) {$charset};");

    dbDelta("CREATE TABLE {$events} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_uuid char(36) NOT NULL,
        journey_id bigint(20) unsigned DEFAULT NULL,
        event_type varchar(60) NOT NULL,
        step smallint(6) DEFAULT NULL,
        insurer_id bigint(20) unsigned DEFAULT NULL,
        source varchar(40) NOT NULL DEFAULT '',
        campaign_code varchar(80) NOT NULL DEFAULT '',
        directory_version int(11) NOT NULL DEFAULT 1,
        received_at datetime NOT NULL,
        client_occurred_at datetime DEFAULT NULL,
        metadata longtext NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY event_uuid (event_uuid),
        KEY received_type (received_at,event_type)
    ) {$charset};");

    dbDelta("CREATE TABLE {$suggestions} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        insurer_id bigint(20) unsigned NOT NULL,
        response_id bigint(20) unsigned DEFAULT NULL,
        proposed_label varchar(120) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        mapped_area_id bigint(20) unsigned DEFAULT NULL,
        reviewer_user_id bigint(20) unsigned DEFAULT NULL,
        reviewed_at datetime DEFAULT NULL,
        review_note varchar(300) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status_insurer (status,insurer_id)
    ) {$charset};");

    dbDelta("CREATE TABLE {$audit} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        action varchar(60) NOT NULL,
        object_type varchar(40) NOT NULL DEFAULT '',
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        occurred_at datetime NOT NULL,
        metadata longtext NULL,
        PRIMARY KEY  (id),
        KEY occurred_at (occurred_at)
    ) {$charset};");

    bbgc_add_foreign_keys();
    bbgc_seed_directory();
    bbgc_grant_capabilities();
    bbgc_schedule_retention();

    update_option('bbgc_db_version', BBGC_DB_VERSION, false);
    if (get_option('bbgc_session_hours') === false) {
        update_option('bbgc_session_hours', 24, false);
        update_option('bbgc_unsubmitted_days', 7, false);
        update_option('bbgc_event_days', 90, false);
        update_option('bbgc_response_days', 365, false);
        update_option('bbgc_min_cell', 5, false);
        update_option('bbgc_delete_data_on_uninstall', 0, false);
    }
}

function bbgc_maybe_install()
{
    if (get_option('bbgc_db_version') !== BBGC_DB_VERSION || !bbgc_tables_ready()) {
        bbgc_install();
    }
}

function bbgc_add_foreign_keys()
{
    global $wpdb;

    $statements = [
        [bbgc_table('insurer_areas'), 'bbgc_fk_area_insurer', 'insurer_id', bbgc_table('insurers'), 'id'],
        [bbgc_table('responses'), 'bbgc_fk_response_journey', 'journey_id', bbgc_table('journeys'), 'id'],
        [bbgc_table('response_brands'), 'bbgc_fk_brand_response', 'response_id', bbgc_table('responses'), 'id'],
    ];

    foreach ($statements as $fk) {
        $exists = $wpdb->get_var($wpdb->prepare(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s',
            $fk[0],
            $fk[1]
        ));
        if ($exists) {
            continue;
        }
        $wpdb->query("ALTER TABLE {$fk[0]} ADD CONSTRAINT {$fk[1]} FOREIGN KEY ({$fk[2]}) REFERENCES {$fk[3]} ({$fk[4]})");
    }
}

function bbgc_area_kind($title)
{
    $text = strtolower((string) $title);
    if (strpos($text, 'spend') !== false) {
        return 'spending_account';
    }
    if (strpos($text, 'booklet') !== false || strpos($text, 'view all coverage') !== false) {
        return 'booklet';
    }
    if (strpos($text, 'equipment') !== false || strpos($text, 'medical') !== false || strpos($text, 'extended health') !== false) {
        return 'medical';
    }
    if (strpos($text, 'search') !== false || strpos($text, 'coverage') !== false || strpos($text, 'plan detail') !== false) {
        return 'search';
    }

    return 'other';
}

function bbgc_seed_directory()
{
    global $wpdb;

    $audiences = [
        'sunlife' => 'Canadian workplace and personal plans supported by my Sun Life',
        'canadalife' => 'My Canada Life at Work is for workplace benefits. Personal plans use the official sign-in selector.',
        'manulife' => 'Canadian Manulife ID. Choose the applicable health or group-benefits product.',
        'greenshield' => 'GreenShield+ Canadian insurance members. Select the insurance profile, not an EAP-only profile.',
        'desjardins' => 'Canadian group-insurance plan members. Not banking, group savings, or home and auto login.',
        'beneva' => 'Beneva Client Centre. Select the applicable insurance product.',
        'ia' => 'My Client Space for Canadian insurance. Choose group insurance.',
        'pacific' => 'Pacific Blue Cross Member Profile. Use the member or spouse route that applies.',
        'alberta' => 'Alberta Blue Cross member site. Benefit information depends on the member role.',
    ];
    $recovery = 'On your insurer\'s sign-in page, use the account-recovery options. Do not create a second account if you already have one.';
    $now = bbgc_now();
    $order = 10;

    foreach (bbgc_insurer_directory() as $code => $entry) {
        $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . bbgc_table('insurers') . ' WHERE code = %s', $code));
        if ($exists) {
            $order += 10;
            continue;
        }

        $login = (string) $entry['login'];
        $wpdb->insert(bbgc_table('insurers'), [
            'code' => $code,
            'name' => $entry['name'],
            'active' => 1,
            'sort_order' => $order,
            'login_url' => $entry['url'] !== '' ? $entry['url'] : null,
            'fallback_url' => null,
            'registration_url' => $entry['register'] !== '' ? $entry['register'] : null,
            'help_url' => $entry['help'] !== '' ? $entry['help'] : null,
            'login_kind' => $entry['url'] !== '' ? 'member_login' : 'none',
            'audience' => isset($audiences[$code]) ? $audiences[$code] : '',
            'navigation_text' => (string) $entry['nav'],
            'existing_help' => $login,
            'new_help' => $login,
            'recovery_help' => $recovery,
            'areas_heading' => (string) $entry['areas_heading'],
            'areas_note' => (string) $entry['areas_note'],
            'show_route' => !empty($entry['show_route']) ? 1 : 0,
            'guidance_status' => 'published',
            'source_urls' => wp_json_encode(array_values(array_filter([$entry['url'], $entry['help'], $entry['register']]))),
            'version' => 1,
            'reviewed_at' => '2026-10-19 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insurerId = (int) $wpdb->insert_id;
        $categories = $entry['categories'];
        $areas = $entry['areas'];
        $count = max(count($categories), count($areas));
        for ($i = 0; $i < $count; $i++) {
            $title = isset($areas[$i][0]) ? $areas[$i][0] : (isset($categories[$i]) ? $categories[$i] : '');
            $instructions = isset($areas[$i][1]) ? $areas[$i][1] : '';
            $label = isset($categories[$i]) ? $categories[$i] : $title;
            $slug = sanitize_title($label !== '' ? $label : $title);
            if ($slug === '') {
                continue;
            }
            $wpdb->insert(bbgc_table('insurer_areas'), [
                'insurer_id' => $insurerId,
                'code' => substr($slug, 0, 60),
                'title' => $title,
                'response_label' => $label,
                'instructions' => $instructions,
                'kind' => bbgc_area_kind($label . ' ' . $title),
                'selectable' => $label !== '' ? 1 : 0,
                'active' => 1,
                'sort_order' => $i + 1,
                'verification_status' => 'published_navigation',
                'reviewed_at' => '2026-10-19 00:00:00',
                'updated_at' => $now,
            ]);
        }
        $order += 10;
    }
}

function bbgc_capabilities()
{
    return [
        'bbgc_edit_directory',
        'bbgc_view_reports',
        'bbgc_export_responses',
        'bbgc_delete_responses',
    ];
}

function bbgc_grant_capabilities()
{
    $role = get_role('administrator');
    if (!$role) {
        return;
    }
    foreach (bbgc_capabilities() as $cap) {
        $role->add_cap($cap);
    }
}

function bbgc_schedule_retention()
{
    if (!wp_next_scheduled('bbgc_retention_daily')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'bbgc_retention_daily');
    }
}

function bbgc_content_locale()
{
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

    return strpos((string) $locale, 'fr') === 0 ? 'fr' : 'en';
}

function bbgc_localized($row, $field)
{
    if (bbgc_content_locale() === 'fr') {
        $french = $field . '_fr';
        if (isset($row->$french) && trim((string) $row->$french) !== '') {
            return (string) $row->$french;
        }
    }

    return isset($row->$field) ? (string) $row->$field : '';
}

/**
 * Directory payload for the guide. Same shape the screen script already expects.
 *
 * @return array<string, array<string, mixed>>
 */
function bbgc_public_directory()
{
    global $wpdb;

    if (!bbgc_tables_ready()) {
        return bbgc_insurer_directory();
    }

    $rows = $wpdb->get_results('SELECT * FROM ' . bbgc_table('insurers') . ' WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    if (!$rows) {
        return bbgc_insurer_directory();
    }

    $directory = [];
    foreach ($rows as $row) {
        $areas = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . bbgc_table('insurer_areas') . ' WHERE insurer_id = %d AND active = 1 ORDER BY sort_order ASC, id ASC',
            $row->id
        ));
        $guide = [];
        $categories = [];
        foreach ($areas as $area) {
            $title = bbgc_localized($area, 'title');
            $label = bbgc_localized($area, 'response_label');
            if ($title !== '') {
                $guide[] = [$title, bbgc_localized($area, 'instructions')];
            }
            if ((int) $area->selectable === 1 && $label !== '') {
                $categories[] = $label;
            }
        }
        $directory[$row->code] = [
            'name' => bbgc_localized($row, 'name'),
            'url' => (string) $row->login_url,
            'help' => (string) $row->help_url,
            'register' => (string) $row->registration_url,
            'fallback' => (string) $row->fallback_url,
            'level' => (string) $row->guidance_status,
            'nav' => bbgc_localized($row, 'navigation_text'),
            'login' => bbgc_localized($row, 'existing_help'),
            'existing_help' => bbgc_localized($row, 'existing_help'),
            'new_help' => bbgc_localized($row, 'new_help'),
            'recovery_help' => bbgc_localized($row, 'recovery_help'),
            'audience' => bbgc_localized($row, 'audience'),
            'areas_heading' => bbgc_localized($row, 'areas_heading'),
            'areas_note' => bbgc_localized($row, 'areas_note'),
            'show_route' => (bool) $row->show_route,
            'categories' => $categories,
            'areas' => $guide,
            'version' => (int) $row->version,
        ];
    }

    return $directory;
}

function bbgc_audit($action, $objectType, $objectId, $metadata)
{
    global $wpdb;

    $wpdb->insert(bbgc_table('audit_log'), [
        'actor_user_id' => get_current_user_id(),
        'action' => $action,
        'object_type' => $objectType,
        'object_id' => (int) $objectId,
        'occurred_at' => bbgc_now(),
        'metadata' => wp_json_encode($metadata),
    ]);
}

function bbgc_run_retention()
{
    global $wpdb;

    $now = bbgc_now();
    $unsubmittedDays = max(1, (int) get_option('bbgc_unsubmitted_days', 7));
    $eventDays = max(1, (int) get_option('bbgc_event_days', 90));
    $responseDays = max(1, (int) get_option('bbgc_response_days', 365));

    $wpdb->query($wpdb->prepare(
        'UPDATE ' . bbgc_table('journeys') . ' SET status = %s WHERE status <> %s AND expires_at < %s AND submitted_at IS NULL',
        'expired',
        'submitted',
        $now
    ));

    $cutoff = gmdate('Y-m-d H:i:s', time() - ($unsubmittedDays * DAY_IN_SECONDS));
    $stale = $wpdb->get_col($wpdb->prepare(
        'SELECT j.id FROM ' . bbgc_table('journeys') . ' j LEFT JOIN ' . bbgc_table('responses') . ' r ON r.journey_id = j.id WHERE r.id IS NULL AND j.created_at < %s',
        $cutoff
    ));
    if ($stale) {
        $ids = implode(',', array_map('intval', $stale));
        $wpdb->query("DELETE FROM " . bbgc_table('events') . " WHERE journey_id IN ({$ids})");
        $wpdb->query("DELETE FROM " . bbgc_table('journeys') . " WHERE id IN ({$ids})");
    }

    $eventCutoff = gmdate('Y-m-d H:i:s', time() - ($eventDays * DAY_IN_SECONDS));
    $wpdb->query($wpdb->prepare('DELETE FROM ' . bbgc_table('events') . ' WHERE received_at < %s', $eventCutoff));

    $responseCutoff = gmdate('Y-m-d H:i:s', time() - ($responseDays * DAY_IN_SECONDS));
    $old = $wpdb->get_col($wpdb->prepare(
        'SELECT id FROM ' . bbgc_table('responses') . ' WHERE submitted_at < %s',
        $responseCutoff
    ));
    if ($old) {
        $ids = implode(',', array_map('intval', $old));
        $wpdb->query("DELETE FROM " . bbgc_table('response_brands') . " WHERE response_id IN ({$ids})");
        $wpdb->query("DELETE FROM " . bbgc_table('category_suggestions') . " WHERE response_id IN ({$ids})");
        $wpdb->query("DELETE FROM " . bbgc_table('responses') . " WHERE id IN ({$ids})");
    }

    bbgc_audit('retention_ran', 'retention', 0, [
        'unsubmitted_journeys' => count($stale),
        'responses' => count($old),
    ]);
}

add_action('bbgc_retention_daily', 'bbgc_run_retention');
