<?php
/**
 * Visitor session and /wp-json/bbgc/v1.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbgc_cookie_name()
{
    return 'bbgc_session';
}

function bbgc_session_hours()
{
    return max(1, (int) get_option('bbgc_session_hours', 24));
}

function bbgc_set_session_cookie($token, $expires)
{
    if (headers_sent()) {
        return;
    }
    setcookie(bbgc_cookie_name(), $token, [
        'expires' => $expires,
        'path' => COOKIEPATH ? COOKIEPATH : '/',
        'domain' => COOKIE_DOMAIN,
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[bbgc_cookie_name()] = $token;
}

function bbgc_clear_session_cookie()
{
    if (!headers_sent()) {
        setcookie(bbgc_cookie_name(), '', [
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE[bbgc_cookie_name()]);
}

function bbgc_origin_allowed()
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
    if ($origin === '') {
        return true;
    }
    $home = wp_parse_url(home_url('/'));
    $sent = wp_parse_url($origin);
    if (!$home || !$sent) {
        return false;
    }

    return strtolower((string) $home['host']) === strtolower((string) $sent['host']);
}

function bbgc_rate_limit($bucket, $limit)
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = 'bbgc_rl_' . md5($bucket . '|' . $ip);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        return false;
    }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);

    return true;
}

function bbgc_rest_error($code, $message, $status)
{
    $response = new WP_REST_Response([
        'code' => $code,
        'message' => $message,
    ], $status);
    $response->header('Cache-Control', 'no-store');

    return $response;
}

function bbgc_rest_ok($data, $status)
{
    $response = new WP_REST_Response($data, $status);
    $response->header('Cache-Control', 'no-store');

    return $response;
}

function bbgc_issue_csrf($journeyId)
{
    global $wpdb;

    $token = bin2hex(random_bytes(32));
    $wpdb->update(bbgc_table('journeys'), [
        'csrf_hash' => hash('sha256', $token),
    ], ['id' => $journeyId]);

    return $token;
}

function bbgc_current_journey()
{
    global $wpdb;

    $token = isset($_COOKIE[bbgc_cookie_name()]) ? (string) wp_unslash($_COOKIE[bbgc_cookie_name()]) : '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . bbgc_table('journeys') . ' WHERE token_hash = %s',
        hash('sha256', $token)
    ));
    if (!$row) {
        return null;
    }
    if ($row->expires_at < bbgc_now() && $row->submitted_at === null) {
        return 'expired';
    }

    return $row;
}

function bbgc_require_journey($request)
{
    if (!bbgc_origin_allowed()) {
        return bbgc_rest_error('origin', 'This request was not accepted.', 403);
    }
    $journey = bbgc_current_journey();
    if ($journey === 'expired') {
        bbgc_clear_session_cookie();

        return bbgc_rest_error('expired', 'This guide session has expired. Start again to save answers.', 401);
    }
    if (!$journey) {
        return bbgc_rest_error('unauthorized', 'Start the guide again to continue.', 401);
    }
    $csrf = (string) $request->get_header('x-bbgc-csrf');
    if ($csrf === '' || !hash_equals((string) $journey->csrf_hash, hash('sha256', $csrf))) {
        return bbgc_rest_error('csrf', 'The guide could not confirm this request. Refresh the page and try again.', 403);
    }

    return $journey;
}

function bbgc_touch_journey($journey)
{
    global $wpdb;

    $wpdb->update(bbgc_table('journeys'), [
        'last_seen_at' => bbgc_now(),
    ], ['id' => $journey->id]);
}

function bbgc_journey_payload($journey, $csrf)
{
    global $wpdb;

    $insurer = null;
    if ($journey->insurer_id) {
        $insurer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('insurers') . ' WHERE id = %d', $journey->insurer_id));
    }
    $response = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('responses') . ' WHERE journey_id = %d', $journey->id));
    $brands = [];
    if ($response) {
        $brands = $wpdb->get_col($wpdb->prepare(
            'SELECT brand_code FROM ' . bbgc_table('response_brands') . ' WHERE response_id = %d',
            $response->id
        ));
    }

    return [
        'publicId' => $journey->public_id,
        'csrf' => $csrf,
        'rowVersion' => (int) $journey->row_version,
        'status' => $journey->status,
        'step' => (int) $journey->current_step,
        'expiresAt' => $journey->expires_at,
        'source' => $journey->source,
        'campaign' => $journey->campaign_code,
        'insurer' => $insurer ? $insurer->code : '',
        'insurerOther' => (string) $journey->insurer_other,
        'account' => (string) $journey->account_help_status,
        'directoryVersion' => (int) $journey->directory_version,
        'response' => $response ? bbgc_response_payload($response, $brands) : null,
    ];
}

function bbgc_brand_label($code)
{
    $map = [
        'medela' => 'Medela',
        'momcozy' => 'Momcozy',
        'spectra' => 'Spectra',
        'philips_avent' => 'Philips Avent',
        'lansinoh' => 'Lansinoh',
        'elvie' => 'Elvie',
        'willow' => 'Willow',
        'other' => 'Other',
        'undecided' => 'I haven\'t decided yet',
    ];

    return isset($map[$code]) ? $map[$code] : $code;
}

function bbgc_retailer_label($code)
{
    $map = [
        'snuggle_bugz' => 'Snuggle Bugz',
        'west_coast_kids' => 'West Coast Kids',
        'walmart' => 'Walmart',
        'amazon_ca' => 'Amazon.ca',
        'well_ca' => 'Well.ca',
        'shoppers_drug_mart' => 'Shoppers Drug Mart',
        'rexall' => 'Rexall',
        'clement' => 'Clément',
        'london_drugs' => 'London Drugs',
        'best_buy' => 'Best Buy',
        'other_retailer' => 'Other',
        // Legacy codes kept for older saved responses.
        'baby_specialty' => 'Baby specialty store',
        'pharmacy_medical' => 'Pharmacy / medical supply store',
        'manufacturer' => 'Directly from the manufacturer',
        'undecided' => 'I haven\'t decided yet',
        'already_purchased' => 'I already purchased a breast pump',
    ];

    return isset($map[$code]) ? $map[$code] : '';
}

function bbgc_timing_label($code)
{
    $map = [
        'within_30_days' => 'Within 30 days',
        'one_to_three_months' => 'Within 1-3 months',
        'over_three_months' => 'More than 3 months from now',
        'already_purchased' => 'I already purchased one',
        'undecided' => 'I haven\'t decided',
    ];

    return isset($map[$code]) ? $map[$code] : '';
}

function bbgc_response_payload($response, $brands)
{
    global $wpdb;

    $category = (string) $response->category_label_snapshot;
    if ($response->category_other) {
        $category = 'Other - please specify';
    }

    return [
        'id' => (int) $response->id,
        'rowVersion' => (int) $response->row_version,
        'coverage' => $response->coverage_status,
        'email' => isset($response->email) ? (string) $response->email : '',
        'amount' => $response->amount_cad === null ? '' : (string) $response->amount_cad,
        'noAmount' => (bool) $response->amount_not_found,
        'percent' => $response->reimbursement_percent === null ? '' : (string) $response->reimbursement_percent,
        'amountMeaning' => $response->amount_meaning === 'unknown' ? '' : $response->amount_meaning,
        'prescription' => (string) $response->prescription_required,
        'category' => $category,
        'otherCategory' => (string) $response->category_other,
        'ease' => (string) $response->feedback_ease,
        'comment' => (string) $response->feedback_comment,
        'brands' => array_map('bbgc_brand_label', $brands),
        'otherBrand' => (string) $response->other_brand,
        'retailer' => bbgc_retailer_label((string) $response->retailer_code),
        'otherRetailer' => (string) $response->retailer_other,
        'timing' => bbgc_timing_label((string) $response->purchase_timing),
        'directoryVersion' => (int) $response->directory_version,
        'submittedAt' => $response->submitted_at,
    ];
}

function bbgc_code_map($allowed)
{
    return $allowed;
}

function bbgc_map_choice($value, $map)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return isset($map[$value]) ? $map[$value] : null;
}

function bbgc_record_event($journey, $type, $step, $metadata)
{
    global $wpdb;

    $wpdb->insert(bbgc_table('events'), [
        'event_uuid' => wp_generate_uuid4(),
        'journey_id' => $journey ? (int) $journey->id : null,
        'event_type' => $type,
        'step' => $step,
        'insurer_id' => $journey && $journey->insurer_id ? (int) $journey->insurer_id : null,
        'source' => $journey ? $journey->source : '',
        'campaign_code' => $journey ? $journey->campaign_code : '',
        'directory_version' => $journey ? (int) $journey->directory_version : 1,
        'received_at' => bbgc_now(),
        'metadata' => $metadata ? wp_json_encode($metadata) : null,
    ]);
}

function bbgc_create_journey_row($source, $campaign)
{
    global $wpdb;

    $secret = random_bytes(32);
    $token = bin2hex($secret);
    $csrf = bin2hex(random_bytes(32));
    $now = bbgc_now();
    $hours = bbgc_session_hours();
    $expires = gmdate('Y-m-d H:i:s', time() + ($hours * HOUR_IN_SECONDS));
    $wpdb->insert(bbgc_table('journeys'), [
        'public_id' => wp_generate_uuid4(),
        'token_hash' => hash('sha256', $token),
        'csrf_hash' => hash('sha256', $csrf),
        'source' => $source,
        'campaign_code' => $campaign,
        'current_step' => 1,
        'directory_version' => 1,
        'status' => 'started',
        'created_at' => $now,
        'last_seen_at' => $now,
        'expires_at' => $expires,
        'row_version' => 1,
    ]);
    $id = (int) $wpdb->insert_id;
    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('journeys') . ' WHERE id = %d', $id));
    bbgc_set_session_cookie($token, time() + ($hours * HOUR_IN_SECONDS));
    bbgc_record_event($row, 'guide_opened', 1, null);

    return [$row, $csrf, true];
}

function bbgc_rest_insurers()
{
    $directory = [];
    foreach (bbgc_public_directory() as $code => $entry) {
        $directory[$code] = [
            'name' => $entry['name'],
            'url' => $entry['url'],
            'help' => $entry['help'],
            'register' => $entry['register'],
            'fallback' => $entry['fallback'],
            'nav' => $entry['nav'],
            'audience' => $entry['audience'],
            'existingHelp' => $entry['existing_help'],
            'newHelp' => $entry['new_help'],
            'recoveryHelp' => $entry['recovery_help'],
            'areasHeading' => $entry['areas_heading'],
            'areasNote' => $entry['areas_note'],
            'showRoute' => $entry['show_route'],
            'categories' => $entry['categories'],
            'areas' => $entry['areas'],
            'version' => $entry['version'],
        ];
    }
    $version = 1;
    foreach ($directory as $entry) {
        $version = max($version, (int) $entry['version']);
    }
    $response = new WP_REST_Response($directory, 200);
    $response->header('Cache-Control', 'public, max-age=300');
    $response->header('ETag', '"bbgc-' . $version . '"');

    return $response;
}

function bbgc_rest_open_journey($request)
{
    if (!bbgc_origin_allowed()) {
        return bbgc_rest_error('origin', 'This request was not accepted.', 403);
    }
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }
    $force = !empty($params['forceNew']);
    $existing = bbgc_current_journey();
    if ($existing && $existing !== 'expired' && !$force) {
        $csrf = bbgc_issue_csrf((int) $existing->id);
        bbgc_touch_journey($existing);
        $existing->csrf_hash = hash('sha256', $csrf);

        return bbgc_rest_ok(bbgc_journey_payload($existing, $csrf), 200);
    }
    if (!$force && !bbgc_rate_limit('journey', 30)) {
        return bbgc_rest_error('rate_limit', 'Please wait a moment and try again.', 429);
    }
    $source = isset($params['source']) ? sanitize_key($params['source']) : 'direct';
    if (!in_array($source, ['bbgc_registration', 'qr', 'direct'], true)) {
        $source = 'direct';
    }
    $campaign = isset($params['campaign']) ? sanitize_text_field($params['campaign']) : '';
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $campaign)) {
        $campaign = '';
    }
    list($row, $csrf) = bbgc_create_journey_row($source, $campaign);

    return bbgc_rest_ok(bbgc_journey_payload($row, $csrf), 201);
}

function bbgc_rest_get_journey()
{
    $journey = bbgc_current_journey();
    if ($journey === 'expired') {
        bbgc_clear_session_cookie();

        return bbgc_rest_error('expired', 'This guide session has expired. Start again to save answers.', 401);
    }
    if (!$journey) {
        return bbgc_rest_error('unauthorized', 'Start the guide again to continue.', 401);
    }
    $csrf = bbgc_issue_csrf((int) $journey->id);
    bbgc_touch_journey($journey);

    return bbgc_rest_ok(bbgc_journey_payload($journey, $csrf), 200);
}

function bbgc_rest_patch_journey($request)
{
    global $wpdb;

    $journey = bbgc_require_journey($request);
    if ($journey instanceof WP_REST_Response) {
        return $journey;
    }
    $params = $request->get_json_params();
    if (!is_array($params)) {
        return bbgc_rest_error('invalid', 'The guide could not read this update.', 400);
    }
    $sent = isset($params['rowVersion']) ? (int) $params['rowVersion'] : 0;
    if ($sent !== (int) $journey->row_version) {
        return bbgc_rest_error('conflict', 'This guide was updated somewhere else. Refresh and try again.', 409);
    }

    $code = isset($params['insurer']) ? sanitize_key($params['insurer']) : '';
    $insurer = null;
    if ($code !== '') {
        $insurer = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . bbgc_table('insurers') . ' WHERE code = %s AND active = 1',
            $code
        ));
        if (!$insurer) {
            return bbgc_rest_error('insurer', 'Choose an insurance company from the list.', 400);
        }
    }
    $activeCsrf = (string) $request->get_header('x-bbgc-csrf');
    if ($journey->submitted_at && $insurer && (int) $insurer->id !== (int) $journey->insurer_id) {
        list($row, $activeCsrf) = bbgc_create_journey_row($journey->source, $journey->campaign_code);
        $journey = $row;
    }

    $account = isset($params['account']) ? sanitize_key($params['account']) : '';
    if ($account !== '' && !in_array($account, ['yes', 'no', 'unsure'], true)) {
        return bbgc_rest_error('account', 'Choose one of the account options.', 400);
    }
    $step = isset($params['step']) ? (int) $params['step'] : (int) $journey->current_step;
    if ($step < 1 || $step > 3) {
        $step = 1;
    }
    $other = isset($params['insurerOther']) ? sanitize_text_field($params['insurerOther']) : '';
    if (strlen($other) > 100) {
        return bbgc_rest_error('insurer_other', 'The insurance company name is too long.', 400);
    }
    $changed = $insurer && (int) $journey->insurer_id !== (int) $insurer->id;
    $status = $journey->status === 'started' && ($insurer || $step > 1) ? 'in_progress' : $journey->status;
    if ($journey->submitted_at) {
        $status = 'submitted';
    }
    $wpdb->update(bbgc_table('journeys'), [
        'insurer_id' => $insurer ? (int) $insurer->id : $journey->insurer_id,
        'insurer_other' => $other !== '' ? $other : $journey->insurer_other,
        'account_help_status' => $account !== '' ? $account : $journey->account_help_status,
        'current_step' => $step,
        'directory_version' => $insurer ? (int) $insurer->version : (int) $journey->directory_version,
        'status' => $status,
        'last_seen_at' => bbgc_now(),
        'row_version' => (int) $journey->row_version + 1,
    ], ['id' => $journey->id]);
    if ($changed) {
        $event = $journey->insurer_id ? 'insurer_changed' : 'insurer_selected';
        $fresh = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('journeys') . ' WHERE id = %d', $journey->id));
        bbgc_record_event($fresh, $event, $step, ['version' => (int) $insurer->version]);
        $journey = $fresh;
    } else {
        $journey = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('journeys') . ' WHERE id = %d', $journey->id));
    }

    return bbgc_rest_ok(bbgc_journey_payload($journey, $activeCsrf), 200);
}

function bbgc_allowed_events()
{
    return [
        'step_viewed',
        'insurer_website_clicked',
        'help_opened',
        'help_clicked',
        'help_link_clicked',
        'registration_help_clicked',
        'results_started',
        'coverage_not_found_selected',
        'return_to_babybrands_clicked',
        'validation_failed',
    ];
}

function bbgc_rest_events($request)
{
    global $wpdb;

    $journey = bbgc_require_journey($request);
    if ($journey instanceof WP_REST_Response) {
        return $journey;
    }
    if (!bbgc_rate_limit('events', 120)) {
        return bbgc_rest_error('rate_limit', 'Please wait a moment and try again.', 429);
    }
    $params = $request->get_json_params();
    $events = isset($params['events']) && is_array($params['events']) ? $params['events'] : [];
    if (count($events) > 20) {
        return bbgc_rest_error('payload', 'Too many events were sent at once.', 400);
    }
    $allowed = bbgc_allowed_events();
    $saved = 0;
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $type = isset($event['type']) ? sanitize_key($event['type']) : '';
        $uuid = isset($event['uuid']) ? sanitize_text_field($event['uuid']) : '';
        if (!in_array($type, $allowed, true) || !wp_is_uuid($uuid)) {
            continue;
        }
        $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . bbgc_table('events') . ' WHERE event_uuid = %s', $uuid));
        if ($exists) {
            continue;
        }
        $step = isset($event['step']) ? (int) $event['step'] : null;
        $wpdb->insert(bbgc_table('events'), [
            'event_uuid' => $uuid,
            'journey_id' => (int) $journey->id,
            'event_type' => $type,
            'step' => $step,
            'insurer_id' => $journey->insurer_id ? (int) $journey->insurer_id : null,
            'source' => $journey->source,
            'campaign_code' => $journey->campaign_code,
            'directory_version' => (int) $journey->directory_version,
            'received_at' => bbgc_now(),
            'client_occurred_at' => null,
            'metadata' => null,
        ]);
        $saved++;
    }

    return bbgc_rest_ok(['saved' => $saved], 200);
}

function bbgc_validate_response($journey, $params, $isUpdate)
{
    global $wpdb;

    $coverage = isset($params['coverage']) ? sanitize_key($params['coverage']) : '';
    if (!in_array($coverage, ['yes', 'no', 'unclear'], true)) {
        return new WP_Error('coverage', 'Tell us whether you found coverage, or choose Couldn\'t confirm.');
    }
    $email = isset($params['email']) ? sanitize_email(wp_unslash((string) $params['email'])) : '';
    if ($email === '' || !is_email($email)) {
        return new WP_Error('email', 'Enter the email address from your BabyBrands registration.');
    }
    $noAmount = !empty($params['noAmount']);
    $amount = isset($params['amount']) ? trim((string) $params['amount']) : '';
    $percent = isset($params['percent']) ? trim((string) $params['percent']) : '';
    $meaning = isset($params['amountMeaning']) ? sanitize_key($params['amountMeaning']) : '';
    $prescription = isset($params['prescription']) ? sanitize_key($params['prescription']) : '';
    $category = isset($params['category']) ? sanitize_text_field($params['category']) : '';
    $otherCategory = isset($params['otherCategory']) ? sanitize_text_field($params['otherCategory']) : '';
    $ease = isset($params['ease']) ? sanitize_key($params['ease']) : '';
    $comment = isset($params['comment']) ? sanitize_textarea_field($params['comment']) : '';
    $otherBrand = isset($params['otherBrand']) ? sanitize_text_field($params['otherBrand']) : '';
    $otherRetailer = isset($params['otherRetailer']) ? sanitize_text_field($params['otherRetailer']) : '';

    if ($coverage !== 'yes') {
        $noAmount = false;
        $amount = '';
        $percent = '';
        $meaning = '';
        $prescription = '';
        $category = '';
        $otherCategory = '';
    }
    if ($noAmount) {
        $amount = '';
    }
    if ($coverage === 'yes' && !$noAmount) {
        $amount = preg_replace('/[^\d]/', '', (string) $amount);
        if ($amount !== '') {
            $amount = substr($amount, 0, 3);
        }
        if (!preg_match('/^\d{1,3}$/', $amount) || (int) $amount > 999) {
            return new WP_Error('amount', 'Enter a valid dollar amount, or select "I couldn\'t find a dollar amount".');
        }
    }
    if ($percent !== '') {
        $percent = preg_replace('/[^\d]/', '', (string) $percent);
        if ($percent !== '') {
            $percent = ltrim($percent, '0');
            if ($percent === '') {
                $percent = '0';
            }
        }
        if (!preg_match('/^\d{1,3}$/', $percent) || (int) $percent < 0) {
            return new WP_Error('percent', 'Enter a whole number from 0 to 100, or leave it blank.');
        }
        if ((int) $percent > 100) {
            return new WP_Error('percent', 'Reimbursement percentage cannot be more than 100.');
        }
    }
    $meanings = ['', 'remaining', 'reimbursement_limit', 'eligible_expense_limit', 'shared_balance', 'unknown'];
    if (!in_array($meaning, $meanings, true)) {
        return new WP_Error('meaning', 'Choose what the dollar figure describes, or leave it blank.');
    }
    if ($prescription !== '' && !in_array($prescription, ['yes', 'no', 'unknown'], true)) {
        return new WP_Error('prescription', 'Choose a prescription answer from the list.');
    }
    if ($ease !== '' && !in_array($ease, ['very_easy', 'somewhat_easy', 'difficult', 'not_found'], true)) {
        return new WP_Error('ease', 'Choose a feedback answer from the list.');
    }
    if (strlen($comment) > 500 || strlen($otherCategory) > 120 || strlen($otherBrand) > 100 || strlen($otherRetailer) > 100) {
        return new WP_Error('length', 'One of the answers is too long.');
    }
    if ($category === 'Other - please specify' && $otherCategory === '') {
        return new WP_Error('category', 'Enter the other section name, or leave the optional section question blank.');
    }

    $categoryId = null;
    $snapshot = '';
    if ($coverage === 'yes' && $category !== '' && $category !== 'Other - please specify') {
        $area = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . bbgc_table('insurer_areas') . ' WHERE insurer_id = %d AND active = 1 AND selectable = 1 AND response_label = %s',
            $journey->insurer_id,
            $category
        ));
        if (!$area) {
            return new WP_Error('category', 'That section does not belong to the selected insurer.');
        }
        $categoryId = (int) $area->id;
        $snapshot = $area->response_label;
    }

    $brandMap = [
        'Medela' => 'medela',
        'Momcozy' => 'momcozy',
        'Spectra' => 'spectra',
        'Philips Avent' => 'philips_avent',
        'Lansinoh' => 'lansinoh',
        'Elvie' => 'elvie',
        'Willow' => 'willow',
        'Other' => 'other',
        'I haven\'t decided yet' => 'undecided',
    ];
    $brands = [];
    $incoming = isset($params['brands']) && is_array($params['brands']) ? $params['brands'] : [];
    foreach ($incoming as $brand) {
        $brand = sanitize_text_field($brand);
        if (!isset($brandMap[$brand])) {
            return new WP_Error('brand', 'Choose brands from the list.');
        }
        $brands[] = $brandMap[$brand];
    }
    $brands = array_values(array_unique($brands));
    if (in_array('undecided', $brands, true)) {
        $brands = ['undecided'];
        $otherBrand = '';
    }
    if (!in_array('other', $brands, true)) {
        $otherBrand = '';
    }

    $retailerMap = [
        'Snuggle Bugz' => 'snuggle_bugz',
        'West Coast Kids' => 'west_coast_kids',
        'Walmart' => 'walmart',
        'Amazon.ca' => 'amazon_ca',
        'Well.ca' => 'well_ca',
        'Shoppers Drug Mart' => 'shoppers_drug_mart',
        'Rexall' => 'rexall',
        'Clément' => 'clement',
        'London Drugs' => 'london_drugs',
        'Best Buy' => 'best_buy',
        'Other' => 'other_retailer',
    ];
    $timingMap = [
        'Within 30 days' => 'within_30_days',
        'Within 1-3 months' => 'one_to_three_months',
        'More than 3 months from now' => 'over_three_months',
        'I already purchased one' => 'already_purchased',
        'I haven\'t decided' => 'undecided',
    ];
    $retailer = isset($params['retailer']) ? sanitize_text_field($params['retailer']) : '';
    $timing = isset($params['timing']) ? sanitize_text_field($params['timing']) : '';
    $retailerCode = '';
    $timingCode = '';
    if ($retailer !== '') {
        if (!isset($retailerMap[$retailer])) {
            return new WP_Error('retailer', 'Choose a retailer from the list.');
        }
        $retailerCode = $retailerMap[$retailer];
    }
    if ($timing !== '') {
        if (!isset($timingMap[$timing])) {
            return new WP_Error('timing', 'Choose a time frame from the list.');
        }
        $timingCode = $timingMap[$timing];
    }
    if ($retailerCode === 'already_purchased') {
        $timingCode = 'already_purchased';
    }
    if ($retailerCode !== 'other_retailer') {
        $otherRetailer = '';
    }

    $notice = isset($params['noticeVersion']) ? sanitize_text_field($params['noticeVersion']) : '';
    if ($notice !== BBGC_NOTICE_VERSION) {
        return new WP_Error('notice', 'Refresh the page so the current privacy notice is shown, then finish again.');
    }

    return [
        'coverage_status' => $coverage,
        'email' => $email,
        'amount_cad' => $amount === '' ? null : $amount,
        'amount_not_found' => $noAmount ? 1 : 0,
        'reimbursement_percent' => $percent === '' ? null : $percent,
        'amount_meaning' => $meaning === '' ? 'unknown' : $meaning,
        'prescription_required' => $prescription === '' ? null : $prescription,
        'category_id' => $categoryId,
        'category_label_snapshot' => $snapshot,
        'category_other' => $category === 'Other - please specify' ? $otherCategory : null,
        'feedback_ease' => $ease === '' ? null : $ease,
        'feedback_comment' => $comment === '' ? null : $comment,
        'retailer_code' => $retailerCode === '' ? null : $retailerCode,
        'retailer_other' => $otherRetailer === '' ? null : $otherRetailer,
        'purchase_timing' => $timingCode === '' ? null : $timingCode,
        'other_brand' => $otherBrand === '' ? null : $otherBrand,
        'is_self_reported' => 1,
        'directory_version' => (int) $journey->directory_version,
        'collection_notice_version' => BBGC_NOTICE_VERSION,
        'brands' => $brands,
        'suggestion' => $category === 'Other - please specify' ? $otherCategory : '',
    ];
}

function bbgc_save_brands($responseId, $brands)
{
    global $wpdb;

    $wpdb->delete(bbgc_table('response_brands'), ['response_id' => $responseId]);
    $now = bbgc_now();
    foreach ($brands as $code) {
        $wpdb->insert(bbgc_table('response_brands'), [
            'response_id' => $responseId,
            'brand_code' => $code,
            'selected_at' => $now,
        ]);
    }
}

function bbgc_save_suggestion($journey, $responseId, $label)
{
    global $wpdb;

    if ($label === '' || !$journey->insurer_id) {
        return;
    }
    $exists = $wpdb->get_var($wpdb->prepare(
        'SELECT id FROM ' . bbgc_table('category_suggestions') . ' WHERE response_id = %d AND proposed_label = %s AND status = %s',
        $responseId,
        $label,
        'pending'
    ));
    if ($exists) {
        return;
    }
    $wpdb->insert(bbgc_table('category_suggestions'), [
        'insurer_id' => (int) $journey->insurer_id,
        'response_id' => $responseId,
        'proposed_label' => $label,
        'status' => 'pending',
        'created_at' => bbgc_now(),
    ]);
}

function bbgc_rest_save_response($request)
{
    global $wpdb;

    $journey = bbgc_require_journey($request);
    if ($journey instanceof WP_REST_Response) {
        return $journey;
    }
    if (!$journey->insurer_id) {
        return bbgc_rest_error('insurer', 'Choose an insurance company before finishing.', 400);
    }
    $params = $request->get_json_params();
    if (!is_array($params)) {
        return bbgc_rest_error('invalid', 'The guide could not read these answers.', 400);
    }
    $key = isset($params['idempotencyKey']) ? sanitize_text_field($params['idempotencyKey']) : '';
    if (!wp_is_uuid($key)) {
        return bbgc_rest_error('idempotency', 'Refresh the page and finish again.', 400);
    }
    $existingKey = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . bbgc_table('responses') . ' WHERE idempotency_key = %s',
        $key
    ));
    if ($existingKey) {
        if ((int) $existingKey->journey_id !== (int) $journey->id) {
            return bbgc_rest_error('idempotency', 'These answers belong to a different visit.', 409);
        }
        $brands = $wpdb->get_col($wpdb->prepare(
            'SELECT brand_code FROM ' . bbgc_table('response_brands') . ' WHERE response_id = %d',
            $existingKey->id
        ));
        $csrf = (string) $request->get_header('x-bbgc-csrf');

        return bbgc_rest_ok([
            'response' => bbgc_response_payload($existingKey, $brands),
            'rowVersion' => (int) $journey->row_version,
            'created' => false,
        ], 200);
    }
    $already = $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . bbgc_table('responses') . ' WHERE journey_id = %d', $journey->id));
    if ($already) {
        return bbgc_rest_error('exists', 'These answers were already saved. Use edit to update them.', 409);
    }
    $clean = bbgc_validate_response($journey, $params, false);
    if (is_wp_error($clean)) {
        bbgc_record_event($journey, 'validation_failed', 3, ['code' => $clean->get_error_code()]);

        return bbgc_rest_error($clean->get_error_code(), $clean->get_error_message(), 400);
    }
    $brands = $clean['brands'];
    $suggestion = $clean['suggestion'];
    unset($clean['brands'], $clean['suggestion']);
    $now = bbgc_now();
    $clean['journey_id'] = (int) $journey->id;
    $clean['insurer_id'] = (int) $journey->insurer_id;
    $clean['idempotency_key'] = $key;
    $clean['consent_at'] = $now;
    $clean['submitted_at'] = $now;
    $clean['updated_at'] = $now;
    $clean['row_version'] = 1;

    $wpdb->query('START TRANSACTION');
    $inserted = $wpdb->insert(bbgc_table('responses'), $clean);
    if (!$inserted) {
        $wpdb->query('ROLLBACK');
        bbgc_record_event($journey, 'save_failed', 3, ['code' => 'insert']);

        return bbgc_rest_error('save', 'We could not save your answers. Please try again.', 500);
    }
    $responseId = (int) $wpdb->insert_id;
    bbgc_save_brands($responseId, $brands);
    bbgc_save_suggestion($journey, $responseId, $suggestion);
    $wpdb->update(bbgc_table('journeys'), [
        'status' => 'submitted',
        'submitted_at' => $now,
        'current_step' => 3,
        'last_seen_at' => $now,
        'row_version' => (int) $journey->row_version + 1,
    ], ['id' => $journey->id]);
    $wpdb->query('COMMIT');

    $saved = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('responses') . ' WHERE id = %d', $responseId));
    $journey = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('journeys') . ' WHERE id = %d', $journey->id));
    bbgc_record_event($journey, 'response_submitted', 3, null);
    $csrf = (string) $request->get_header('x-bbgc-csrf');

    return bbgc_rest_ok([
        'response' => bbgc_response_payload($saved, $brands),
        'rowVersion' => (int) $journey->row_version,
        'created' => true,
    ], 201);
}

function bbgc_rest_update_response($request)
{
    global $wpdb;

    $journey = bbgc_require_journey($request);
    if ($journey instanceof WP_REST_Response) {
        return $journey;
    }
    $current = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('responses') . ' WHERE journey_id = %d', $journey->id));
    if (!$current) {
        return bbgc_rest_error('missing', 'Finish the guide before editing answers.', 404);
    }
    $params = $request->get_json_params();
    if (!is_array($params)) {
        return bbgc_rest_error('invalid', 'The guide could not read these answers.', 400);
    }
    $sent = isset($params['responseVersion']) ? (int) $params['responseVersion'] : 0;
    if ($sent !== (int) $current->row_version) {
        return bbgc_rest_error('conflict', 'These answers changed. Refresh the page and try again.', 409);
    }
    $clean = bbgc_validate_response($journey, $params, true);
    if (is_wp_error($clean)) {
        bbgc_record_event($journey, 'validation_failed', 3, ['code' => $clean->get_error_code()]);

        return bbgc_rest_error($clean->get_error_code(), $clean->get_error_message(), 400);
    }
    $brands = $clean['brands'];
    $suggestion = $clean['suggestion'];
    unset($clean['brands'], $clean['suggestion']);
    $clean['updated_at'] = bbgc_now();
    $clean['row_version'] = (int) $current->row_version + 1;
    $wpdb->query('START TRANSACTION');
    $wpdb->update(bbgc_table('responses'), $clean, ['id' => $current->id]);
    bbgc_save_brands((int) $current->id, $brands);
    bbgc_save_suggestion($journey, (int) $current->id, $suggestion);
    $wpdb->query('COMMIT');
    $saved = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . bbgc_table('responses') . ' WHERE id = %d', $current->id));
    bbgc_record_event($journey, 'response_updated', 3, null);
    $csrf = (string) $request->get_header('x-bbgc-csrf');

    return bbgc_rest_ok([
        'response' => bbgc_response_payload($saved, $brands),
        'rowVersion' => (int) $journey->row_version,
        'created' => false,
    ], 200);
}

function bbgc_register_rest()
{
    register_rest_route('bbgc/v1', '/insurers', [
        'methods' => 'GET',
        'callback' => 'bbgc_rest_insurers',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('bbgc/v1', '/journeys', [
        'methods' => 'POST',
        'callback' => 'bbgc_rest_open_journey',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('bbgc/v1', '/journey', [
        [
            'methods' => 'GET',
            'callback' => 'bbgc_rest_get_journey',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => 'PATCH',
            'callback' => 'bbgc_rest_patch_journey',
            'permission_callback' => '__return_true',
        ],
    ]);
    register_rest_route('bbgc/v1', '/events', [
        'methods' => 'POST',
        'callback' => 'bbgc_rest_events',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('bbgc/v1', '/response', [
        [
            'methods' => 'POST',
            'callback' => 'bbgc_rest_save_response',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => 'PUT',
            'callback' => 'bbgc_rest_update_response',
            'permission_callback' => '__return_true',
        ],
    ]);
}

add_action('rest_api_init', 'bbgc_register_rest');
