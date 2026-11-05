<?php
/**
 * Private wp-admin screens for the guide.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbgc_admin_menu()
{
    add_menu_page(
        'Breast Pump Insurance Guide',
        'Breast Pump Insurance Guide',
        'bbgc_view_reports',
        'bbgc-guide',
        'bbgc_admin_overview',
        'dashicons-heart',
        58
    );
    add_submenu_page('bbgc-guide', 'Overview', 'Overview', 'bbgc_view_reports', 'bbgc-guide', 'bbgc_admin_overview');
    add_submenu_page('bbgc-guide', 'Insurance Company List', 'Insurance Company List', 'bbgc_edit_directory', 'bbgc-insurers', 'bbgc_admin_insurers');
    add_submenu_page('bbgc-guide', 'Responses', 'Responses', 'bbgc_view_reports', 'bbgc-responses', 'bbgc_admin_responses');
    add_submenu_page('bbgc-guide', 'New categories', 'New categories', 'bbgc_edit_directory', 'bbgc-categories', 'bbgc_admin_categories');
    add_submenu_page('bbgc-guide', 'Audit log', 'Audit log', 'bbgc_view_reports', 'bbgc-audit', 'bbgc_admin_audit');
    add_submenu_page('bbgc-guide', 'Settings', 'Settings', 'bbgc_edit_directory', 'bbgc-settings', 'bbgc_admin_settings');
}

add_action('admin_menu', 'bbgc_admin_menu');

function bbgc_admin_filters()
{
    return [
        'from' => isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : gmdate('Y-m-d', time() - (30 * DAY_IN_SECONDS)),
        'to' => isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : gmdate('Y-m-d'),
        'source' => isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : '',
        'campaign' => isset($_GET['campaign']) ? sanitize_text_field(wp_unslash($_GET['campaign'])) : '',
        'insurer' => isset($_GET['insurer']) ? sanitize_key(wp_unslash($_GET['insurer'])) : '',
        'version' => isset($_GET['version']) ? absint($_GET['version']) : 0,
    ];
}

function bbgc_admin_filter_form($filters)
{
    global $wpdb;

    $insurers = $wpdb->get_results('SELECT code, name FROM ' . bbgc_table('insurers') . ' ORDER BY sort_order ASC, id ASC');
    echo '<form method="get" style="margin:12px 0 18px">';
    echo '<input type="hidden" name="page" value="' . esc_attr(isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbgc-guide') . '">';
    echo '<label>From <input type="date" name="from" value="' . esc_attr($filters['from']) . '"></label> ';
    echo '<label>To <input type="date" name="to" value="' . esc_attr($filters['to']) . '"></label> ';
    echo '<label>Source <select name="source"><option value="">All</option>';
    foreach (['bbgc_registration' => 'Registration', 'qr' => 'QR', 'direct' => 'Direct'] as $code => $label) {
        echo '<option value="' . esc_attr($code) . '"' . selected($filters['source'], $code, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>Campaign <input type="text" name="campaign" value="' . esc_attr($filters['campaign']) . '" maxlength="40"></label> ';
    echo '<label>Insurer <select name="insurer"><option value="">All</option>';
    foreach ($insurers as $insurer) {
        echo '<option value="' . esc_attr($insurer->code) . '"' . selected($filters['insurer'], $insurer->code, false) . '>' . esc_html($insurer->name) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>Directory version <input type="number" min="0" name="version" value="' . esc_attr((string) $filters['version']) . '" style="width:70px"></label> ';
    submit_button('Filter', 'secondary', '', false);
    echo '</form>';
}

function bbgc_filter_sql($filters, $dateAlias, $dateColumn, $versionAlias = '')
{
    global $wpdb;

    if ($versionAlias === '') {
        $versionAlias = $dateAlias;
    }
    $where = ['1=1'];
    $args = [];
    if ($filters['from'] !== '') {
        $where[] = "{$dateAlias}.{$dateColumn} >= %s";
        $args[] = $filters['from'] . ' 00:00:00';
    }
    if ($filters['to'] !== '') {
        $where[] = "{$dateAlias}.{$dateColumn} <= %s";
        $args[] = $filters['to'] . ' 23:59:59';
    }
    if ($filters['source'] !== '') {
        $where[] = 'j.source = %s';
        $args[] = $filters['source'];
    }
    if ($filters['campaign'] !== '') {
        $where[] = 'j.campaign_code = %s';
        $args[] = $filters['campaign'];
    }
    if ($filters['insurer'] !== '') {
        $where[] = 'i.code = %s';
        $args[] = $filters['insurer'];
    }
    if ($filters['version'] > 0) {
        $where[] = "{$versionAlias}.directory_version = %d";
        $args[] = $filters['version'];
    }
    $sql = implode(' AND ', $where);

    return $args ? $wpdb->prepare($sql, $args) : $sql;
}

function bbgc_admin_overview()
{
    if (!current_user_can('bbgc_view_reports')) {
        wp_die(esc_html__('You cannot view these reports.', 'breast-pump-insurance-plugin'));
    }
    global $wpdb;

    $filters = bbgc_admin_filters();
    $journeyWhere = bbgc_filter_sql($filters, 'j', 'created_at');
    $journeys = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . bbgc_table('journeys') . " j LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = j.insurer_id WHERE {$journeyWhere}");
    $page2 = (int) $wpdb->get_var("SELECT COUNT(DISTINCT e.journey_id) FROM " . bbgc_table('events') . " e INNER JOIN " . bbgc_table('journeys') . " j ON j.id = e.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = j.insurer_id WHERE e.event_type = 'step_viewed' AND e.step = 2 AND {$journeyWhere}");
    $clicks = (int) $wpdb->get_var("SELECT COUNT(DISTINCT e.journey_id) FROM " . bbgc_table('events') . " e INNER JOIN " . bbgc_table('journeys') . " j ON j.id = e.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = j.insurer_id WHERE e.event_type = 'insurer_website_clicked' AND {$journeyWhere}");
    $results = (int) $wpdb->get_var("SELECT COUNT(DISTINCT e.journey_id) FROM " . bbgc_table('events') . " e INNER JOIN " . bbgc_table('journeys') . " j ON j.id = e.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = j.insurer_id WHERE e.event_type = 'results_started' AND {$journeyWhere}");
    $responseJoin = bbgc_table('responses') . " r INNER JOIN " . bbgc_table('journeys') . " j ON j.id = r.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = j.insurer_id";
    $submitted = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$responseJoin} WHERE {$journeyWhere}");
    $yes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$responseJoin} WHERE r.coverage_status = 'yes' AND {$journeyWhere}");
    $no = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$responseJoin} WHERE r.coverage_status = 'no' AND {$journeyWhere}");
    $unclear = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$responseJoin} WHERE r.coverage_status = 'unclear' AND {$journeyWhere}");
    $known = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$responseJoin} WHERE r.amount_cad IS NOT NULL AND {$journeyWhere}");
    $percentOnly = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$responseJoin} WHERE r.amount_not_found = 1 AND r.reimbursement_percent IS NOT NULL AND {$journeyWhere}");
    $completion = $journeys > 0 ? round(($submitted / $journeys) * 100, 1) : 0;
    $engagement = $page2 > 0 ? round(($clicks / $page2) * 100, 1) : 0;
    $positive = $submitted > 0 ? round(($yes / $submitted) * 100, 1) : 0;

    echo '<div class="wrap"><h1>Breast pump insurance guide</h1>';
    echo '<p>Counts are journeys or submitted responses, not unique people. A link click is intent to open the insurer site, not proof of a login.</p>';
    bbgc_admin_filter_form($filters);
    echo '<table class="widefat striped" style="max-width:760px"><tbody>';
    $rows = [
        'Guide journeys' => $journeys,
        'Reached page 2' => $page2,
        'Insurer-link clicks (journeys)' => $clicks,
        'Results started' => $results,
        'Submitted responses' => $submitted,
        'Coverage yes / no / could not confirm' => $yes . ' / ' . $no . ' / ' . $unclear,
        'Known dollar amount' => $known,
        'Percentage only, dollar unknown' => $percentOnly,
        'Completion rate (submitted / journeys)' => $completion . '%',
        'Link engagement (clicks / page 2)' => $engagement . '%',
        'Positive coverage (yes / submitted)' => $positive . '%',
    ];
    foreach ($rows as $label => $value) {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
    }
    echo '</tbody></table>';

    $min = max(1, (int) get_option('bbgc_min_cell', 5));
    echo '<h2>Dollar amounts by meaning</h2><p>Shared balances are not averaged with breast-pump limits. A median is hidden until the group has at least ' . esc_html((string) $min) . ' responses.</p>';
    $meanings = $wpdb->get_results("SELECT r.amount_meaning, COUNT(*) AS samples, MIN(r.amount_cad) AS low_amount, MAX(r.amount_cad) AS high_amount FROM {$responseJoin} WHERE r.amount_cad IS NOT NULL AND {$journeyWhere} GROUP BY r.amount_meaning");
    echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>Meaning</th><th>Responses</th><th>Low</th><th>High</th><th>Median</th></tr></thead><tbody>';
    if (!$meanings) {
        echo '<tr><td colspan="5">No dollar amounts in this filter.</td></tr>';
    }
    foreach ($meanings as $meaning) {
        $median = 'Below minimum';
        if ((int) $meaning->samples >= $min && in_array($meaning->amount_meaning, ['remaining', 'reimbursement_limit', 'eligible_expense_limit', 'shared_balance', 'unknown'], true)) {
            $values = $wpdb->get_col("SELECT r.amount_cad FROM {$responseJoin} WHERE r.amount_cad IS NOT NULL AND r.amount_meaning = '" . esc_sql($meaning->amount_meaning) . "' AND {$journeyWhere} ORDER BY r.amount_cad + 0 ASC");
            $median = $values ? $values[(int) floor((count($values) - 1) / 2)] : '';
        }
        echo '<tr><td>' . esc_html($meaning->amount_meaning) . '</td><td>' . esc_html($meaning->samples) . '</td><td>' . esc_html($meaning->low_amount) . '</td><td>' . esc_html($meaning->high_amount) . '</td><td>' . esc_html((string) $median) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function bbgc_admin_insurers()
{
    if (!current_user_can('bbgc_edit_directory')) {
        wp_die(esc_html__('You cannot edit the insurer directory.', 'breast-pump-insurance-plugin'));
    }
    if (isset($_GET['edit'])) {
        bbgc_admin_insurer_edit(absint($_GET['edit']));
        return;
    }
    global $wpdb;
    $rows = $wpdb->get_results('SELECT * FROM ' . bbgc_table('insurers') . ' ORDER BY sort_order ASC, id ASC');
    echo '<div class="wrap"><h1>Insurance Company List, Login Links &amp; Instructions</h1>';
    echo '<p>Published links are the member destinations parents open. Deactivating an insurer hides it from new visits and keeps older responses.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=bbgc-insurers&edit=0')) . '">Add insurer</a></p>';
    echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Name</th><th>Code</th><th>Active</th><th>Version</th><th>Login</th><th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $host = $row->login_url ? wp_parse_url($row->login_url, PHP_URL_HOST) : 'No public link';
        echo '<tr><td>' . esc_html($row->sort_order) . '</td><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->code) . '</td><td>' . ($row->active ? 'Yes' : 'No') . '</td><td>' . esc_html($row->version) . '</td><td>' . esc_html((string) $host) . '</td><td><a href="' . esc_url(admin_url('admin.php?page=bbgc-insurers&edit=' . $row->id)) . '">Edit</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

function bbgc_admin_insurer_edit($id)
{
    global $wpdb;

    $row = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('insurers') . ' WHERE id = %d', $id)) : null;
    $areas = $row ? $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . bbgc_table('insurer_areas') . ' WHERE insurer_id = %d ORDER BY sort_order ASC, id ASC', $row->id)) : [];
    $isNew = !$row;
    echo '<div class="wrap"><h1>' . ($isNew ? 'Add insurer' : 'Edit insurer') . '</h1>';
    if ($isNew) {
        echo '<p>Stable code and display name are required.</p>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('bbgc_save_insurer');
    echo '<input type="hidden" name="action" value="bbgc_save_insurer"><input type="hidden" name="id" value="' . esc_attr((string) $id) . '">';
    echo '<table class="form-table"><tbody>';
    $fields = [
        'code' => ['Stable code', true],
        'name' => ['Display name', true],
        'name_fr' => ['French display name', false],
        'sort_order' => ['Sort order', false],
        'login_url' => ['Member login URL', false],
        'fallback_url' => ['Official fallback URL', false],
        'registration_url' => ['Registration URL', false],
        'help_url' => ['Coverage help URL', false],
        'audience' => ['Audience note', false],
        'audience_fr' => ['French audience note', false],
        'navigation_text' => ['Website navigation', false],
        'navigation_text_fr' => ['French website navigation', false],
    ];
    foreach ($fields as $key => $meta) {
        $value = $row && isset($row->$key) ? $row->$key : '';
        $readonly = $key === 'code' && $row ? ' readonly' : '';
        $required = $isNew && $meta[1];
        $star = $required ? ' <span class="required" aria-hidden="true">*</span>' : '';
        $attr = $required ? ' required' : '';
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($meta[0]) . $star . '</label></th><td><input class="regular-text" type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '"' . $readonly . $attr . '></td></tr>';
    }
    foreach (['existing_help' => 'Existing user help', 'new_help' => 'New user help', 'recovery_help' => 'Recovery help', 'areas_note' => 'Areas note', 'existing_help_fr' => 'French existing user help', 'new_help_fr' => 'French new user help', 'recovery_help_fr' => 'French recovery help', 'areas_note_fr' => 'French areas note'] as $key => $label) {
        $value = $row && isset($row->$key) ? $row->$key : '';
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><textarea class="large-text" rows="3" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">' . esc_textarea((string) $value) . '</textarea></td></tr>';
    }
    echo '<tr><th>Areas heading</th><td><input class="regular-text" name="areas_heading" value="' . esc_attr($row ? $row->areas_heading : '') . '"></td></tr>';
    echo '<tr><th>French areas heading</th><td><input class="regular-text" name="areas_heading_fr" value="' . esc_attr($row ? $row->areas_heading_fr : '') . '"></td></tr>';
    echo '<tr><th>Active</th><td><label><input type="checkbox" name="active" value="1"' . checked(!$row || $row->active, true, false) . '> Show in the guide</label></td></tr>';
    echo '<tr><th>Sun Life route</th><td><label><input type="checkbox" name="show_route" value="1"' . checked($row && $row->show_route, true, false) . '> Show the on-website route</label></td></tr>';
    echo '</tbody></table>';
    echo '<h2>Places to check and response categories</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Page 2 title</th><th>Page 3 label</th><th>Kind</th><th>Instructions</th><th>French title / label / instructions</th><th>Remove</th></tr></thead><tbody>';
    $areas[] = null;
    foreach ($areas as $area) {
        $prefix = $area ? 'area[' . (int) $area->id . ']' : 'new_area';
        echo '<tr>';
        echo '<td><input name="' . esc_attr($prefix) . '[sort_order]" value="' . esc_attr($area ? $area->sort_order : '') . '" style="width:60px"></td>';
        echo '<td><input name="' . esc_attr($prefix) . '[title]" value="' . esc_attr($area ? $area->title : '') . '"></td>';
        echo '<td><input name="' . esc_attr($prefix) . '[response_label]" value="' . esc_attr($area ? $area->response_label : '') . '"></td>';
        echo '<td><select name="' . esc_attr($prefix) . '[kind]">';
        foreach (['search', 'booklet', 'medical', 'spending_account', 'other'] as $kind) {
            echo '<option value="' . esc_attr($kind) . '"' . selected($area ? $area->kind : 'other', $kind, false) . '>' . esc_html($kind) . '</option>';
        }
        echo '</select></td>';
        echo '<td><textarea name="' . esc_attr($prefix) . '[instructions]" rows="2">' . esc_textarea($area ? (string) $area->instructions : '') . '</textarea></td>';
        echo '<td><input name="' . esc_attr($prefix) . '[title_fr]" value="' . esc_attr($area ? $area->title_fr : '') . '" placeholder="French title"><br><input name="' . esc_attr($prefix) . '[response_label_fr]" value="' . esc_attr($area ? $area->response_label_fr : '') . '" placeholder="French label"><br><textarea name="' . esc_attr($prefix) . '[instructions_fr]" rows="2">' . esc_textarea($area ? (string) $area->instructions_fr : '') . '</textarea></td>';
        echo '<td>' . ($area ? '<label><input type="checkbox" name="remove_area[]" value="' . esc_attr((string) $area->id) . '"> Remove</label>' : 'New') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    submit_button('Save insurer');
    echo '</form></div>';
}

function bbgc_https_url($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $url = esc_url_raw($value);
    if (!$url || stripos($url, 'https://') !== 0) {
        return false;
    }

    return $url;
}

function bbgc_save_insurer()
{
    if (!current_user_can('bbgc_edit_directory')) {
        wp_die(esc_html__('You cannot edit the insurer directory.', 'breast-pump-insurance-plugin'));
    }
    check_admin_referer('bbgc_save_insurer');
    global $wpdb;

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $code = isset($_POST['code']) ? sanitize_key(wp_unslash($_POST['code'])) : '';
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    if ($code === '' || $name === '') {
        wp_die(esc_html__('A stable insurer code and a display name are required.', 'breast-pump-insurance-plugin'));
    }
    if (!$id) {
        $taken = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . bbgc_table('insurers') . ' WHERE code = %s', $code));
        if ($taken) {
            wp_die(esc_html__('That stable code is already used. Choose a different code.', 'breast-pump-insurance-plugin'));
        }
    }
    $urls = [];
    foreach (['login_url', 'fallback_url', 'registration_url', 'help_url'] as $key) {
        $urls[$key] = bbgc_https_url(isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '');
        if ($urls[$key] === false) {
            wp_die(esc_html__('Insurer links must be https URLs.', 'breast-pump-insurance-plugin'));
        }
    }
    $now = bbgc_now();
    $data = [
        'name' => $name,
        'name_fr' => sanitize_text_field(wp_unslash($_POST['name_fr'] ?? '')),
        'active' => empty($_POST['active']) ? 0 : 1,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'login_url' => $urls['login_url'],
        'fallback_url' => $urls['fallback_url'],
        'registration_url' => $urls['registration_url'],
        'help_url' => $urls['help_url'],
        'login_kind' => $urls['login_url'] ? 'member_login' : 'none',
        'audience' => sanitize_text_field(wp_unslash($_POST['audience'] ?? '')),
        'audience_fr' => sanitize_text_field(wp_unslash($_POST['audience_fr'] ?? '')),
        'navigation_text' => sanitize_textarea_field(wp_unslash($_POST['navigation_text'] ?? '')),
        'navigation_text_fr' => sanitize_textarea_field(wp_unslash($_POST['navigation_text_fr'] ?? '')),
        'existing_help' => sanitize_textarea_field(wp_unslash($_POST['existing_help'] ?? '')),
        'existing_help_fr' => sanitize_textarea_field(wp_unslash($_POST['existing_help_fr'] ?? '')),
        'new_help' => sanitize_textarea_field(wp_unslash($_POST['new_help'] ?? '')),
        'new_help_fr' => sanitize_textarea_field(wp_unslash($_POST['new_help_fr'] ?? '')),
        'recovery_help' => sanitize_textarea_field(wp_unslash($_POST['recovery_help'] ?? '')),
        'recovery_help_fr' => sanitize_textarea_field(wp_unslash($_POST['recovery_help_fr'] ?? '')),
        'areas_heading' => sanitize_text_field(wp_unslash($_POST['areas_heading'] ?? '')),
        'areas_heading_fr' => sanitize_text_field(wp_unslash($_POST['areas_heading_fr'] ?? '')),
        'areas_note' => sanitize_textarea_field(wp_unslash($_POST['areas_note'] ?? '')),
        'areas_note_fr' => sanitize_textarea_field(wp_unslash($_POST['areas_note_fr'] ?? '')),
        'show_route' => empty($_POST['show_route']) ? 0 : 1,
        'guidance_status' => 'published',
        'reviewed_at' => $now,
        'reviewed_by' => get_current_user_id(),
        'updated_at' => $now,
    ];
    if ($id) {
        $current = $wpdb->get_row($wpdb->prepare('SELECT version FROM ' . bbgc_table('insurers') . ' WHERE id = %d', $id));
        $data['version'] = $current ? ((int) $current->version + 1) : 1;
        $wpdb->update(bbgc_table('insurers'), $data, ['id' => $id]);
    } else {
        $data['code'] = $code;
        $data['version'] = 1;
        $data['created_at'] = $now;
        $wpdb->insert(bbgc_table('insurers'), $data);
        $id = (int) $wpdb->insert_id;
    }

    $remove = isset($_POST['remove_area']) ? array_map('absint', (array) $_POST['remove_area']) : [];
    foreach ($remove as $areaId) {
        $wpdb->delete(bbgc_table('insurer_areas'), ['id' => $areaId, 'insurer_id' => $id]);
    }
    $posted = isset($_POST['area']) && is_array($_POST['area']) ? wp_unslash($_POST['area']) : [];
    foreach ($posted as $areaId => $area) {
        if (in_array((int) $areaId, $remove, true) || !is_array($area)) {
            continue;
        }
        bbgc_save_area($id, absint($areaId), $area, $now);
    }
    if (isset($_POST['new_area']) && is_array($_POST['new_area'])) {
        bbgc_save_area($id, 0, wp_unslash($_POST['new_area']), $now);
    }
    bbgc_audit('directory_saved', 'insurer', $id, ['code' => $code]);
    wp_safe_redirect(admin_url('admin.php?page=bbgc-insurers&edit=' . $id . '&updated=1'));
    exit;
}

function bbgc_save_area($insurerId, $areaId, $area, $now)
{
    global $wpdb;

    $title = sanitize_text_field($area['title'] ?? '');
    $label = sanitize_text_field($area['response_label'] ?? '');
    if ($title === '' && $label === '') {
        return;
    }
    $kind = sanitize_key($area['kind'] ?? 'other');
    if (!in_array($kind, ['search', 'booklet', 'medical', 'spending_account', 'other'], true)) {
        $kind = 'other';
    }
    $data = [
        'title' => $title,
        'title_fr' => sanitize_text_field($area['title_fr'] ?? ''),
        'response_label' => $label !== '' ? $label : $title,
        'response_label_fr' => sanitize_text_field($area['response_label_fr'] ?? ''),
        'instructions' => sanitize_textarea_field($area['instructions'] ?? ''),
        'instructions_fr' => sanitize_textarea_field($area['instructions_fr'] ?? ''),
        'kind' => $kind,
        'selectable' => 1,
        'active' => 1,
        'sort_order' => (int) ($area['sort_order'] ?? 0),
        'updated_at' => $now,
    ];
    if ($areaId) {
        $wpdb->update(bbgc_table('insurer_areas'), $data, ['id' => $areaId, 'insurer_id' => $insurerId]);
        return;
    }
    $data['insurer_id'] = $insurerId;
    $data['code'] = substr(sanitize_title($data['response_label']) . '-' . wp_generate_password(4, false, false), 0, 60);
    $data['verification_status'] = 'published_navigation';
    $data['reviewed_at'] = $now;
    $wpdb->insert(bbgc_table('insurer_areas'), $data);
}

add_action('admin_post_bbgc_save_insurer', 'bbgc_save_insurer');

function bbgc_admin_responses()
{
    if (!current_user_can('bbgc_view_reports')) {
        wp_die(esc_html__('You cannot view responses.', 'breast-pump-insurance-plugin'));
    }
    if (isset($_GET['response'])) {
        bbgc_admin_response_detail(absint($_GET['response']));
        return;
    }
    global $wpdb;
    $filters = bbgc_admin_filters();
    $where = bbgc_filter_sql($filters, 'r', 'submitted_at');
    $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . bbgc_table('responses') . " r LEFT JOIN " . bbgc_table('journeys') . " j ON j.id = r.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = r.insurer_id WHERE {$where}");
    $limit = (int) $perPage;
    $start = (int) $offset;
    $rows = $wpdb->get_results("SELECT r.*, i.name AS insurer_name, j.public_id, j.source, j.campaign_code FROM " . bbgc_table('responses') . " r LEFT JOIN " . bbgc_table('journeys') . " j ON j.id = r.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = r.insurer_id WHERE {$where} ORDER BY r.submitted_at DESC LIMIT {$limit} OFFSET {$start}");
    $brandsByResponse = [];
    if ($rows) {
        $ids = array_map('intval', wp_list_pluck($rows, 'id'));
        $idList = implode(',', $ids);
        $brandRows = $wpdb->get_results("SELECT response_id, brand_code FROM " . bbgc_table('response_brands') . " WHERE response_id IN ({$idList})");
        foreach ($brandRows as $brandRow) {
            $brandsByResponse[(int) $brandRow->response_id][] = bbgc_brand_label((string) $brandRow->brand_code);
        }
    }
    echo '<div class="wrap"><h1>Responses</h1><p>These are self-reported answers. They are not verified benefits.</p>';
    bbgc_admin_filter_form($filters);
    if (current_user_can('bbgc_export_responses')) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:12px">';
        wp_nonce_field('bbgc_export');
        echo '<input type="hidden" name="action" value="bbgc_export">';
        foreach ($filters as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        submit_button('Export CSV for this filter', 'secondary', '', false);
        echo '</form>';
    }
    echo '<style>.bbgc-responses-scroll{width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #c3c4c7;background:#fff}.bbgc-responses-table{width:max-content;min-width:100%;margin:0;border:0;box-shadow:none}.bbgc-responses-table th,.bbgc-responses-table td{white-space:nowrap;padding:8px 10px;vertical-align:top}.bbgc-responses-table td.bbgc-wrap{white-space:normal;max-width:200px;word-break:break-word}</style>';
    echo '<div class="bbgc-responses-scroll"><table class="widefat striped bbgc-responses-table"><thead><tr>';
    $headers = ['Submitted', 'Insurer', 'Email', 'Coverage', 'Amount', 'Percent', 'Meaning', 'Prescription', 'Category', 'Source', 'Campaign', 'Retailer', 'Timing', 'Brands', 'Ease', ''];
    foreach ($headers as $header) {
        echo '<th>' . esc_html($header) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="' . count($headers) . '">No responses in this filter.</td></tr>';
    }
    foreach ($rows as $row) {
        $amount = $row->amount_not_found ? 'Not found' : ($row->amount_cad === null ? 'Blank' : $row->amount_cad);
        $email = isset($row->email) ? (string) $row->email : '';
        $category = $row->category_other ? $row->category_other : $row->category_label_snapshot;
        $brands = isset($brandsByResponse[(int) $row->id]) ? implode(', ', $brandsByResponse[(int) $row->id]) : '';
        $retailer = bbgc_retailer_label((string) $row->retailer_code);
        if ($row->retailer_code === 'other_retailer' && $row->retailer_other) {
            $retailer = $row->retailer_other;
        }
        echo '<tr>';
        echo '<td>' . esc_html(get_date_from_gmt($row->submitted_at, 'Y-m-d H:i')) . '</td>';
        echo '<td>' . esc_html((string) $row->insurer_name) . '</td>';
        echo '<td class="bbgc-wrap">' . esc_html($email) . '</td>';
        echo '<td>' . esc_html($row->coverage_status) . '</td>';
        echo '<td>' . esc_html((string) $amount) . '</td>';
        echo '<td>' . esc_html($row->reimbursement_percent === null ? '' : (string) $row->reimbursement_percent) . '</td>';
        echo '<td>' . esc_html((string) $row->amount_meaning) . '</td>';
        echo '<td>' . esc_html((string) $row->prescription_required) . '</td>';
        echo '<td class="bbgc-wrap">' . esc_html((string) $category) . '</td>';
        echo '<td>' . esc_html((string) $row->source) . '</td>';
        echo '<td>' . esc_html((string) $row->campaign_code) . '</td>';
        echo '<td>' . esc_html($retailer) . '</td>';
        echo '<td>' . esc_html(bbgc_timing_label((string) $row->purchase_timing)) . '</td>';
        echo '<td class="bbgc-wrap">' . esc_html($brands) . '</td>';
        echo '<td>' . esc_html((string) $row->feedback_ease) . '</td>';
        echo '<td><a href="' . esc_url(admin_url('admin.php?page=bbgc-responses&response=' . $row->id)) . '">View</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    $pages = (int) ceil($total / $perPage);
    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= $pages; $i++) {
            $url = add_query_arg(array_merge($filters, ['page' => 'bbgc-responses', 'paged' => $i]), admin_url('admin.php'));
            echo $i === $page ? '<strong>' . $i . '</strong> ' : '<a href="' . esc_url($url) . '">' . $i . '</a> ';
        }
        echo '</p>';
    }
    echo '</div>';
}

function bbgc_admin_response_detail($id)
{
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT r.*, i.name AS insurer_name, j.public_id, j.source, j.campaign_code FROM ' . bbgc_table('responses') . ' r LEFT JOIN ' . bbgc_table('journeys') . ' j ON j.id = r.journey_id LEFT JOIN ' . bbgc_table('insurers') . ' i ON i.id = r.insurer_id WHERE r.id = %d',
        $id
    ));
    if (!$row) {
        wp_die(esc_html__('That response was not found.', 'breast-pump-insurance-plugin'));
    }
    $brands = $wpdb->get_col($wpdb->prepare('SELECT brand_code FROM ' . bbgc_table('response_brands') . ' WHERE response_id = %d', $row->id));
    echo '<div class="wrap"><h1>Response ' . esc_html((string) $row->id) . '</h1>';
    echo '<style>.bbgc-response-detail{width:auto;max-width:520px}.bbgc-response-detail th{width:1%;white-space:nowrap;padding:8px 10px;vertical-align:top}.bbgc-response-detail td{padding:8px 10px;word-break:break-word}</style>';
    echo '<table class="widefat striped bbgc-response-detail"><tbody>';
    $pairs = [
        'Journey reference' => $row->public_id,
        'Insurer' => $row->insurer_name,
        'Source' => $row->source,
        'Campaign' => $row->campaign_code,
        'Coverage' => $row->coverage_status,
        'Email' => isset($row->email) ? $row->email : '',
        'Amount CAD' => $row->amount_not_found ? 'Could not find a dollar amount' : $row->amount_cad,
        'Meaning' => $row->amount_meaning,
        'Percent' => $row->reimbursement_percent,
        'Prescription' => $row->prescription_required,
        'Category shown' => $row->category_label_snapshot,
        'Other category' => $row->category_other,
        'Ease' => $row->feedback_ease,
        'Comment' => $row->feedback_comment,
        'Brands' => implode(', ', array_map('bbgc_brand_label', $brands)),
        'Other brand' => $row->other_brand,
        'Retailer' => bbgc_retailer_label((string) $row->retailer_code),
        'Timing' => bbgc_timing_label((string) $row->purchase_timing),
        'Directory version' => $row->directory_version,
        'Notice version' => $row->collection_notice_version,
        'Submitted' => get_date_from_gmt($row->submitted_at, 'Y-m-d H:i'),
        'Updated' => get_date_from_gmt($row->updated_at, 'Y-m-d H:i'),
    ];
    foreach ($pairs as $label => $value) {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
    }
    echo '</tbody></table>';
    if (current_user_can('bbgc_delete_responses')) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:16px">';
        wp_nonce_field('bbgc_delete_response_' . $row->id);
        echo '<input type="hidden" name="action" value="bbgc_delete_response"><input type="hidden" name="id" value="' . esc_attr((string) $row->id) . '">';
        submit_button('Delete this response', 'delete');
        echo '</form>';
    }
    echo '</div>';
}

function bbgc_csv_cell($value)
{
    $value = (string) $value;
    if (preg_match('/^[=+\-@]/', $value)) {
        $value = "'" . $value;
    }

    return $value;
}

function bbgc_export_responses()
{
    if (!current_user_can('bbgc_export_responses')) {
        wp_die(esc_html__('You cannot export responses.', 'breast-pump-insurance-plugin'));
    }
    check_admin_referer('bbgc_export');
    global $wpdb;

    $filters = [
        'from' => sanitize_text_field(wp_unslash($_POST['from'] ?? '')),
        'to' => sanitize_text_field(wp_unslash($_POST['to'] ?? '')),
        'source' => sanitize_key(wp_unslash($_POST['source'] ?? '')),
        'campaign' => sanitize_text_field(wp_unslash($_POST['campaign'] ?? '')),
        'insurer' => sanitize_key(wp_unslash($_POST['insurer'] ?? '')),
        'version' => absint($_POST['version'] ?? 0),
    ];
    $where = bbgc_filter_sql($filters, 'r', 'submitted_at');
    $rows = $wpdb->get_results("SELECT r.*, i.name AS insurer_name, j.public_id, j.source, j.campaign_code FROM " . bbgc_table('responses') . " r LEFT JOIN " . bbgc_table('journeys') . " j ON j.id = r.journey_id LEFT JOIN " . bbgc_table('insurers') . " i ON i.id = r.insurer_id WHERE {$where} ORDER BY r.submitted_at DESC");
    bbgc_audit('export', 'responses', 0, ['count' => count($rows), 'filters' => $filters]);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bbgc-responses.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['submitted_at', 'journey', 'insurer', 'source', 'campaign', 'coverage', 'email', 'amount_cad', 'amount_not_found', 'amount_meaning', 'percent', 'prescription', 'category', 'category_other', 'ease', 'comment', 'retailer', 'timing', 'directory_version']);
    foreach ($rows as $row) {
        fputcsv($out, array_map('bbgc_csv_cell', [
            $row->submitted_at,
            $row->public_id,
            $row->insurer_name,
            $row->source,
            $row->campaign_code,
            $row->coverage_status,
            isset($row->email) ? $row->email : '',
            $row->amount_cad,
            $row->amount_not_found ? '1' : '0',
            $row->amount_meaning,
            $row->reimbursement_percent,
            $row->prescription_required,
            $row->category_label_snapshot,
            $row->category_other,
            $row->feedback_ease,
            $row->feedback_comment,
            $row->retailer_code,
            $row->purchase_timing,
            $row->directory_version,
        ]));
    }
    fclose($out);
    exit;
}

add_action('admin_post_bbgc_export', 'bbgc_export_responses');

function bbgc_delete_response()
{
    if (!current_user_can('bbgc_delete_responses')) {
        wp_die(esc_html__('You cannot delete responses.', 'breast-pump-insurance-plugin'));
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    check_admin_referer('bbgc_delete_response_' . $id);
    global $wpdb;

    $wpdb->delete(bbgc_table('response_brands'), ['response_id' => $id]);
    $wpdb->delete(bbgc_table('category_suggestions'), ['response_id' => $id]);
    $wpdb->delete(bbgc_table('responses'), ['id' => $id]);
    bbgc_audit('response_deleted', 'response', $id, []);
    wp_safe_redirect(admin_url('admin.php?page=bbgc-responses&deleted=1'));
    exit;
}

add_action('admin_post_bbgc_delete_response', 'bbgc_delete_response');

function bbgc_admin_categories()
{
    if (!current_user_can('bbgc_edit_directory')) {
        wp_die(esc_html__('You cannot review categories.', 'breast-pump-insurance-plugin'));
    }
    global $wpdb;
    $rows = $wpdb->get_results('SELECT s.*, i.name AS insurer_name FROM ' . bbgc_table('category_suggestions') . ' s LEFT JOIN ' . bbgc_table('insurers') . ' i ON i.id = s.insurer_id ORDER BY s.created_at DESC LIMIT 200');
    echo '<div class="wrap"><h1>New categories</h1><p>A parent\'s wording stays pending until you map it to a reviewed area or reject it. Accepting does not publish it as a benefit rule by itself.</p>';
    echo '<table class="widefat striped"><thead><tr><th>When</th><th>Insurer</th><th>Proposed label</th><th>Status</th><th></th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="5">No suggestions yet.</td></tr>';
    }
    foreach ($rows as $row) {
        echo '<tr><td>' . esc_html(get_date_from_gmt($row->created_at, 'Y-m-d H:i')) . '</td><td>' . esc_html((string) $row->insurer_name) . '</td><td>' . esc_html($row->proposed_label) . '</td><td>' . esc_html($row->status) . '</td><td>';
        if ($row->status === 'pending') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('bbgc_moderate_' . $row->id);
            echo '<input type="hidden" name="action" value="bbgc_moderate"><input type="hidden" name="id" value="' . esc_attr((string) $row->id) . '">';
            echo '<input type="text" name="review_note" placeholder="Review note" maxlength="300"> ';
            echo '<button class="button" name="decision" value="accepted">Accept and add area</button> ';
            echo '<button class="button" name="decision" value="rejected">Reject</button>';
            echo '</form>';
        } else {
            echo esc_html($row->review_note);
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function bbgc_moderate_category()
{
    if (!current_user_can('bbgc_edit_directory')) {
        wp_die(esc_html__('You cannot review categories.', 'breast-pump-insurance-plugin'));
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    check_admin_referer('bbgc_moderate_' . $id);
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('category_suggestions') . ' WHERE id = %d', $id));
    if (!$row || $row->status !== 'pending') {
        wp_safe_redirect(admin_url('admin.php?page=bbgc-categories'));
        exit;
    }
    $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : 'rejected';
    if (!in_array($decision, ['accepted', 'rejected'], true)) {
        $decision = 'rejected';
    }
    $note = sanitize_text_field(wp_unslash($_POST['review_note'] ?? ''));
    $note = substr($note, 0, 300);
    $mapped = null;
    if ($decision === 'accepted') {
        $now = bbgc_now();
        $wpdb->insert(bbgc_table('insurer_areas'), [
            'insurer_id' => (int) $row->insurer_id,
            'code' => substr(sanitize_title($row->proposed_label) . '-' . wp_generate_password(4, false, false), 0, 60),
            'title' => $row->proposed_label,
            'response_label' => $row->proposed_label,
            'instructions' => 'Added from a parent suggestion. Confirm the heading on the insurer site before relying on it.',
            'kind' => 'other',
            'selectable' => 1,
            'active' => 1,
            'sort_order' => 50,
            'verification_status' => 'editor_added',
            'reviewed_at' => $now,
            'updated_at' => $now,
        ]);
        $mapped = (int) $wpdb->insert_id;
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . bbgc_table('insurers') . ' SET version = version + 1, updated_at = %s WHERE id = %d',
            $now,
            $row->insurer_id
        ));
    }
    $wpdb->update(bbgc_table('category_suggestions'), [
        'status' => $decision,
        'mapped_area_id' => $mapped,
        'reviewer_user_id' => get_current_user_id(),
        'reviewed_at' => bbgc_now(),
        'review_note' => $note,
    ], ['id' => $id]);
    bbgc_audit('category_' . $decision, 'suggestion', $id, ['mapped_area_id' => $mapped]);
    wp_safe_redirect(admin_url('admin.php?page=bbgc-categories&updated=1'));
    exit;
}

add_action('admin_post_bbgc_moderate', 'bbgc_moderate_category');

function bbgc_admin_audit()
{
    if (!current_user_can('bbgc_view_reports')) {
        wp_die(esc_html__('You cannot view the audit log.', 'breast-pump-insurance-plugin'));
    }
    global $wpdb;
    $rows = $wpdb->get_results('SELECT * FROM ' . bbgc_table('audit_log') . ' ORDER BY occurred_at DESC LIMIT 200');
    echo '<div class="wrap"><h1>Audit log</h1><table class="widefat striped"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Object</th><th>Detail</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="5">No admin actions yet.</td></tr>';
    }
    foreach ($rows as $row) {
        $user = $row->actor_user_id ? get_userdata((int) $row->actor_user_id) : null;
        echo '<tr><td>' . esc_html(get_date_from_gmt($row->occurred_at, 'Y-m-d H:i')) . '</td><td>' . esc_html($user ? $user->user_login : 'system') . '</td><td>' . esc_html($row->action) . '</td><td>' . esc_html($row->object_type . ' ' . $row->object_id) . '</td><td>' . esc_html((string) $row->metadata) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function bbgc_admin_settings()
{
    if (!current_user_can('bbgc_edit_directory')) {
        wp_die(esc_html__('You cannot change these settings.', 'breast-pump-insurance-plugin'));
    }
    echo '<div class="wrap"><h1>Guide settings</h1>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('bbgc_save_settings');
    echo '<input type="hidden" name="action" value="bbgc_save_settings">';
    echo '<table class="form-table"><tbody>';
    $fields = [
        'bbgc_return_url' => ['Return to BabyBrands URL', 'url'],
        'bbgc_session_hours' => ['Session length in hours', 'number'],
        'bbgc_unsubmitted_days' => ['Delete unsubmitted journeys after days', 'number'],
        'bbgc_event_days' => ['Delete activity events after days', 'number'],
        'bbgc_response_days' => ['Delete submitted responses after days', 'number'],
        'bbgc_min_cell' => ['Minimum group size before showing a median', 'number'],
    ];
    foreach ($fields as $key => $meta) {
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($meta[0]) . '</label></th><td><input class="regular-text" type="' . esc_attr($meta[1]) . '" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr((string) get_option($key, '')) . '"></td></tr>';
    }
    echo '<tr><th>Uninstall</th><td><label><input type="checkbox" name="bbgc_delete_data_on_uninstall" value="1"' . checked((int) get_option('bbgc_delete_data_on_uninstall', 0), 1, false) . '> Delete guide tables when this plugin is deleted</label><p class="description">Leave this off unless you have a backup and mean to remove the responses.</p></td></tr>';
    echo '</tbody></table>';
    submit_button('Save settings');
    echo '</form><p>Retention also runs daily through WordPress cron. On a quiet site, call <code>bbgc_run_retention</code> from a server scheduler as well.</p></div>';
}

function bbgc_save_settings()
{
    if (!current_user_can('bbgc_edit_directory')) {
        wp_die(esc_html__('You cannot change these settings.', 'breast-pump-insurance-plugin'));
    }
    check_admin_referer('bbgc_save_settings');
    $return = esc_url_raw(wp_unslash($_POST['bbgc_return_url'] ?? ''));
    if ($return !== '' && stripos($return, 'https://') !== 0 && stripos($return, home_url()) !== 0) {
        wp_die(esc_html__('The return URL must be an https address or a page on this site.', 'breast-pump-insurance-plugin'));
    }
    update_option('bbgc_return_url', $return, false);
    foreach (['bbgc_session_hours', 'bbgc_unsubmitted_days', 'bbgc_event_days', 'bbgc_response_days', 'bbgc_min_cell'] as $key) {
        update_option($key, max(1, absint($_POST[$key] ?? 1)), false);
    }
    update_option('bbgc_delete_data_on_uninstall', empty($_POST['bbgc_delete_data_on_uninstall']) ? 0 : 1, false);
    bbgc_audit('settings_saved', 'settings', 0, []);
    wp_safe_redirect(admin_url('admin.php?page=bbgc-settings&updated=1'));
    exit;
}

add_action('admin_post_bbgc_save_settings', 'bbgc_save_settings');
