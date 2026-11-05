(function () {
    'use strict';

    var cfg = window.BBGC_GUIDE || {};
    var root = document.getElementById('bbgc-view');
    if (!root || !cfg.insurers) {
        return;
    }

    var INSURERS = cfg.insurers;
    var KEY = 'bbgc_guide_v1';
    var PAGES = ['invitation', 'page1', 'page2', 'results', 'thanks'];
    var DEFAULT = {
        view: cfg.start === 'invitation' ? 'invitation' : 'page1',
        insurer: '',
        otherInsurer: '',
        account: '',
        coverage: '',
        email: '',
        amount: '',
        noAmount: false,
        percent: '',
        amountMeaning: '',
        prescription: '',
        category: '',
        otherCategory: '',
        ease: '',
        comment: '',
        brands: [],
        otherBrand: '',
        retailer: '',
        otherRetailer: '',
        timing: '',
        expanded: [],
        source: cfg.source || 'direct',
        campaign: cfg.campaign || '',
        publicId: '',
        journeyVersion: 0,
        responseId: 0,
        responseVersion: 0,
        submitted: false
    };

    var state = clone(DEFAULT);
    try {
        var stored = JSON.parse(sessionStorage.getItem(KEY));
        if (stored && typeof stored === 'object') {
            state = Object.assign({}, state, stored);
            if (!Array.isArray(state.brands)) {
                state.brands = [];
            }
            if (!Array.isArray(state.expanded)) {
                state.expanded = [];
            }
        }
    } catch (err) {
        state = clone(DEFAULT);
    }

    if (cfg.view && PAGES.indexOf(cfg.view) !== -1) {
        state.view = cfg.view;
    }
    if (cfg.source) {
        state.source = cfg.source;
    }
    if (!state.insurer && (state.view === 'page2' || state.view === 'results' || state.view === 'thanks')) {
        state.view = 'page1';
    }

    function clone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function icon(name) {
        var paths = {
            arrow: '<path d="M5 12h14m-5-5 5 5-5 5"/>',
            external: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M20 14v6H4V4h6"/>',
            check: '<path d="m5 12 4 4 10-10"/>',
            back: '<path d="m14 6-6 6 6 6"/>'
        };
        return '<svg class="bbgc-icon" viewBox="0 0 24 24" aria-hidden="true">' + (paths[name] || paths.check) + '</svg>';
    }

    function save() {
        try {
            sessionStorage.setItem(KEY, JSON.stringify(state));
        } catch (err) {
            /* Private mode can block storage; the current view still works. */
        }
    }

    function header() {
        return '<header class="bbgc-header"><img class="bbgc-logo" src="' + esc(cfg.logoUrl) + '" alt="BabyBrands Gift Club" width="204" height="75"><div class="bbgc-caption">Breast Pump Insurance Guide</div><span class="bbgc-accent"></span></header>';
    }

    function footer() {
        return '<footer class="bbgc-footer"><button type="button" data-modal="privacy">Privacy</button><span class="bbgc-micro">·</span><button type="button" data-modal="about">About this guide</button><p>BabyBrands Gift Club</p></footer>';
    }

    function step(n, label) {
        var tracks = [1, 2, 3].map(function (i) {
            return '<i class="' + (i <= n ? 'is-on' : '') + '"></i>';
        }).join('');
        return '<div class="bbgc-step"><span>STEP ' + n + ' OF 3 &nbsp; / &nbsp; ' + label + '</span><span class="bbgc-tracks" aria-hidden="true">' + tracks + '</span></div>';
    }

    function btn(label, action, cls, extra) {
        return '<button type="button" class="bbgc-btn ' + (cls || 'bbgc-btn-primary') + '" data-action="' + action + '" ' + (extra || '') + '>' + label + icon('arrow') + '</button>';
    }

    function ext(label, url, style, evt) {
        return '<a class="' + style + '" href="' + esc(url) + '" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" data-event="' + (evt || 'help_clicked') + '">' + label + icon('external') + '</a>';
    }

    function option(value, label, selected) {
        return '<option value="' + esc(value) + '"' + (value === selected ? ' selected' : '') + '>' + esc(label) + '</option>';
    }

    function select(name, options, placeholder) {
        var html = '<select id="' + name + '" name="' + name + '">' + option('', placeholder || 'Select an option', state[name]);
        options.forEach(function (item) {
            var value = Array.isArray(item) ? item[0] : item;
            var label = Array.isArray(item) ? item[1] : item;
            html += option(value, label, state[name]);
        });
        return html + '</select>';
    }

    function choices(name, options, stack) {
        var html = '<div class="bbgc-choices' + (stack ? ' bbgc-stack' : '') + '">';
        options.forEach(function (pair) {
            html += '<label class="bbgc-choice"><input type="radio" name="' + name + '" value="' + esc(pair[0]) + '"' + (state[name] === pair[0] ? ' checked' : '') + '>' + icon('check') + '<span>' + esc(pair[1]) + '</span></label>';
        });
        return html + '</div>';
    }

    function details(id, label, content) {
        var open = state.expanded.indexOf(id) !== -1 ? ' open' : '';
        return '<details data-detail="' + id + '"' + open + '><summary>' + label + '</summary><div class="bbgc-details-inner">' + content + '</div></details>';
    }

    function insurer() {
        return INSURERS[state.insurer] || INSURERS.other;
    }

    function insurerName() {
        if (state.insurer === 'other') {
            return state.otherInsurer || 'Your insurer';
        }
        return insurer().name;
    }

    function selectedRow() {
        return '<div class="bbgc-insurer-row"><span class="bbgc-insurer-name">' + esc(insurerName()) + '</span><button type="button" class="bbgc-change" data-action="change">Change insurer</button></div>';
    }

    function invitation() {
        return '<main class="bbgc-page bbgc-invitation bbgc-view"><div class="bbgc-badge">' + icon('check') + 'Registration complete</div><div class="bbgc-hero"><div class="bbgc-kicker"><span class="bbgc-dot"></span>A LITTLE EXTRA HELP FOR YOU</div><h1>Check if my insurance covers my breast pump — and for how much.</h1><p class="bbgc-intro">Your health plan may help with the cost. Our free guide shows you where to look.</p></div>' + btn('Check My Coverage', 'start') + '<div class="bbgc-minor-center"><button type="button" class="bbgc-minor" data-action="decline">No thanks, continue</button></div><p class="bbgc-entry-footer">Free to use · No obligation to purchase</p></main>';
    }

    function page1() {
        var options = Object.keys(INSURERS).map(function (key) {
            return [key, INSURERS[key].name];
        });
        var other = state.insurer === 'other'
            ? '<div class="bbgc-field"><label class="bbgc-label" for="otherInsurer">Insurance company name <span class="bbgc-optional">(optional)</span></label><input type="text" id="otherInsurer" name="otherInsurer" maxlength="100" placeholder="Type the name" value="' + esc(state.otherInsurer) + '"></div>'
            : '';
        return '<main class="bbgc-page bbgc-view">' + step(1, 'YOUR INSURER') + '<div class="bbgc-hero"><h1>Your Insurance Could Help Pay for Your Breast Pump!</h1><p class="bbgc-intro">Find out if your plan covers a breast pump and how much you may be eligible to receive.</p></div><div class="bbgc-field"><label class="bbgc-label" for="insurer">Which insurance company do you have?</label>' + select('insurer', options, 'Select your insurance company') + '</div>' + other + btn('Check My Breast Pump Coverage', 'guide', 'bbgc-btn-primary', state.insurer ? '' : 'disabled') + '<p class="bbgc-micro is-center bbgc-sp18">Free to use · No obligation to purchase</p></main>';
    }

    function accountHelp() {
        var current = insurer();
        var html = '<p class="bbgc-label">Have you already created an online account with your insurance company?</p>' + choices('account', [
            ['yes', 'Yes, I have an account and have logged in before.'],
            ['no', 'No, I need to create an account.'],
            ['unsure', 'I\'m not sure / I need help accessing my account.']
        ], true);
        if (state.account === 'yes') {
            html += '<p class="bbgc-sp12">' + esc(current.existing_help || current.login) + '</p>';
        }
        if (state.account === 'no') {
            html += '<p class="bbgc-sp12">' + esc(current.new_help || current.login) + '</p>';
            if (current.register) {
                html += '<div class="bbgc-help-links">' + ext('Registration help', current.register, 'bbgc-minor', 'registration_help_clicked') + '</div>';
            }
        }
        if (state.account === 'unsure') {
            html += '<p class="bbgc-sp12">' + esc(current.recovery_help || 'On your insurer\'s sign-in page, use the account-recovery options. Do not create a second account if you already have one.') + '</p>';
        }
        return html;
    }

    function page2() {
        var current = insurer();
        var known = !!current.url;
        var name = insurerName();
        var html = '<main class="bbgc-page bbgc-view">' + step(2, 'FIND YOUR COVERAGE') + selectedRow();
        html += '<div class="bbgc-guide-heading"><h1>' + (known ? 'Let\'s find your ' + esc(name) + ' coverage.' : 'Find the right insurance website.') + '</h1>';
        html += '<p class="bbgc-intro">' + (known
            ? 'Sign in on your insurer\'s website, then come back here to share what you find.'
            : 'Look at your benefit card or ask your plan administrator for the official website. You can still continue if you cannot find it.') + '</p>';
        if (current.audience) {
            html += '<p class="bbgc-micro">' + esc(current.audience) + '</p>';
        }
        html += '</div>';

        if (known) {
            html += ext('Go to ' + esc(name) + ' Website', current.url, 'bbgc-btn bbgc-btn-dark bbgc-website', 'insurer_website_clicked');
            html += '<p class="bbgc-newtab">Opens in a new tab and continues to your results here so you can report what you find.</p>';
            html += details('account', 'Need help signing in?', accountHelp());
            html += '<h2 class="bbgc-guide-title">' + esc(current.areas_heading || 'Places to check in your plan') + '</h2>';
            html += '<p class="bbgc-micro">' + esc(current.areas_note || '') + '</p>';
            if (current.show_route) {
                html += '<div class="bbgc-route"><strong>On the website:</strong> Health → your plan → Coverage search</div>';
            }
            html += '<div class="bbgc-area-list">' + (current.areas || []).map(function (area, index) {
                return '<section class="bbgc-area"><span class="bbgc-num">' + (index + 1) + '</span><div><h3>' + esc(area[0]) + '</h3><p>' + esc(area[1]) + '</p></div></section>';
            }).join('') + '</div>';
            html += '<p class="bbgc-note">A general equipment or spending-account balance is not a breast pump allowance. Confirm that breast pumps qualify under your plan.</p>';
            if (current.fallback && current.fallback !== current.url) {
                html += '<div class="bbgc-minor-center">' + ext('Official member page', current.fallback, 'bbgc-minor', 'help_link_clicked') + '</div>';
            }
            if (current.help) {
                html += '<div class="bbgc-minor-center">' + ext('View ' + esc(name) + '\'s official guide', current.help, 'bbgc-minor', 'help_clicked') + '</div>';
            }
        } else {
            html += '<div class="bbgc-status">Use only the official website shown on your insurance card or plan documents.</div>';
        }

        html += '<div class="bbgc-next">' + btn('I\'ve Checked My Benefits', 'results') + '<div class="bbgc-minor-center"><button type="button" class="bbgc-minor" data-action="notfound">I couldn\'t find my coverage</button></div></div>';
        html += '<button type="button" class="bbgc-back" data-action="change">' + icon('back') + 'Back</button></main>';
        return html;
    }

    function emailField() {
        return '<div class="bbgc-field"><label class="bbgc-label" for="email">Email</label><input type="email" name="email" id="email" value="' + esc(state.email) + '" placeholder="name@example.com" maxlength="190" autocomplete="email" required><small class="bbgc-help">Use the email from your BabyBrands registration.</small></div>';
    }

    function isValidEmail(value) {
        var email = String(value || '').trim();
        return email !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && email.length <= 190;
    }

    function amountPanel() {
        var percentDisplay = state.percent === '' ? '' : esc(state.percent) + '%';
        return '<div class="bbgc-money"><label class="bbgc-label" for="amount">How much is available toward your breast pump?</label><p class="bbgc-micro">Enter the dollar amount your insurer showed specifically for a breast pump.</p><div class="bbgc-money-input"><span class="bbgc-currency">$</span><input type="text" inputmode="decimal" id="amount" name="amount" value="' + esc(state.amount) + '" placeholder="e.g. 300" autocomplete="off" ' + (state.noAmount ? 'disabled' : '') + '><span class="bbgc-cad">CAD</span></div><label class="bbgc-check"><input type="checkbox" name="noAmount" ' + (state.noAmount ? 'checked' : '') + '>I couldn\'t find a dollar amount</label>' + details('percent', 'Percentage or other benefit details <span class="bbgc-optional">(optional)</span>', '<div class="bbgc-field"><label class="bbgc-label" for="percent">Reimbursement percentage</label><div class="bbgc-percent-input"><input type="text" inputmode="decimal" name="percent" id="percent" value="' + percentDisplay + '" placeholder="e.g. 80%" maxlength="6" autocomplete="off" aria-describedby="bbgc-percent-help"></div><small class="bbgc-help" id="bbgc-percent-help">Enter a value from 0 to 100. Leave blank if your plan does not show one.</small></div><div class="bbgc-field"><label class="bbgc-label" for="amountMeaning">What does the dollar figure describe?</label>' + select('amountMeaning', [['remaining', 'Remaining breast-pump allowance'], ['reimbursement_limit', 'Maximum insurer reimbursement'], ['eligible_expense_limit', 'Maximum eligible purchase expense'], ['shared_balance', 'General equipment / spending-account balance'], ['unknown', 'I\'m not sure']], 'Select if you know') + '</div>') + '</div>';
    }

    /**
     * Keep digits/decimals only: max 3 digits before the decimal, 2 after.
     */
    function normalizePercent(raw) {
        var cleaned = String(raw || '').replace(/[^\d.]/g, '');
        var parts = cleaned.split('.');
        var whole = parts[0].slice(0, 3);
        if (parts.length > 1) {
            cleaned = whole + '.' + parts.slice(1).join('').slice(0, 2);
        } else {
            cleaned = whole;
        }
        if (cleaned === '' || cleaned === '.') {
            return '';
        }
        return cleaned;
    }

    function isValidPercent(value) {
        return value === '' || (/^\d{1,3}(\.\d{1,2})?$/.test(value) && Number(value) >= 0 && Number(value) <= 100);
    }

    /**
     * Live typing helper: strip non-numeric chars, max 3 integer digits.
     */
    function sanitizePercentTyping(raw) {
        var typing = String(raw || '').replace(/[^\d.]/g, '');
        var parts = typing.split('.');
        var whole = parts[0].slice(0, 3);
        if (parts.length > 1) {
            return whole + '.' + parts.slice(1).join('').slice(0, 2);
        }
        return whole;
    }

    function prescriptionField() {
        return '<fieldset class="bbgc-field"><legend class="bbgc-label">Does your insurer require a prescription for reimbursement?</legend>' + choices('prescription', [['yes', 'Yes'], ['no', 'No'], ['unknown', 'Not sure']]) + '<small class="bbgc-help">Check whether a doctor or midwife prescription is accepted by your plan.</small></fieldset>';
    }

    function categoryField() {
        var categories = (insurer().categories || []).concat(['Other - please specify']);
        var other = state.category === 'Other - please specify'
            ? '<div class="bbgc-field"><label class="bbgc-label" for="otherCategory">What was the section called?</label><input type="text" id="otherCategory" name="otherCategory" value="' + esc(state.otherCategory) + '" placeholder="Enter the section name" maxlength="120"><small class="bbgc-help">Only the heading, please. No member numbers or personal medical details.</small></div>'
            : '';
        return '<div class="bbgc-field"><label class="bbgc-label" for="category">Where did you find the information? <span class="bbgc-optional">(optional)</span></label>' + select('category', categories, 'Select the section') + '<small class="bbgc-help">This helps us improve the guide for other parents.</small></div>' + other;
    }

    function coverageFields() {
        return amountPanel();
    }

    function feedback() {
        return '<fieldset class="bbgc-field"><legend class="bbgc-label">How easy was it to find your coverage?</legend>' + choices('ease', [['very_easy', 'Very easy'], ['somewhat_easy', 'Somewhat easy'], ['difficult', 'Difficult'], ['not_found', 'I couldn\'t find it']], true) + '</fieldset><div class="bbgc-field"><label class="bbgc-label" for="comment">Any suggestions? <span class="bbgc-optional">(optional)</span></label><textarea id="comment" name="comment" maxlength="500" placeholder="Tell us what would make this easier...">' + esc(state.comment) + '</textarea><small class="bbgc-help">Please don\'t include policy numbers or medical details.</small></div>';
    }

    function shopping() {
        var brands = ['Medela', 'Momcozy', 'Spectra', 'Philips Avent', 'Lansinoh', 'Elvie', 'Willow', 'Other', 'I haven\'t decided yet'];
        var brandHtml = brands.map(function (brand) {
            var checked = state.brands.indexOf(brand) !== -1 ? 'checked' : '';
            return '<label class="bbgc-check"><input type="checkbox" name="brands" value="' + esc(brand) + '" ' + checked + '>' + esc(brand) + '</label>';
        }).join('');
        var otherBrand = state.brands.indexOf('Other') !== -1
            ? '<input type="text" name="otherBrand" maxlength="100" value="' + esc(state.otherBrand) + '" placeholder="Other brand name" aria-label="Other brand name">'
            : '';
        var otherRetailer = state.retailer === 'Other'
            ? '<div class="bbgc-field"><input type="text" name="otherRetailer" maxlength="100" value="' + esc(state.otherRetailer) + '" placeholder="Other retailer name" aria-label="Other retailer name"></div>'
            : '';
        return '<fieldset class="bbgc-field"><legend class="bbgc-label">Which breast pump brands are you considering?</legend><p class="bbgc-micro">Select all that apply. No model selection needed.</p><div class="bbgc-rows">' + brandHtml + '</div>' + otherBrand + '</fieldset><div class="bbgc-field"><label class="bbgc-label" for="retailer">Where are you planning to purchase?</label>' + select('retailer', ['Snuggle Bugz', 'West Coast Kids', 'Walmart', 'Amazon.ca', 'Well.ca', 'Shoppers Drug Mart', 'Rexall', 'Clément', 'London Drugs', 'Best Buy', 'Other'], 'Select a retailer') + '</div>' + otherRetailer + '<div class="bbgc-field"><label class="bbgc-label" for="timing">When are you planning to buy?</label>' + select('timing', ['Within 30 days', 'Within 1-3 months', 'More than 3 months from now', 'I already purchased one', 'I haven\'t decided'], 'Select a time frame') + '</div>';
    }

    function results() {
        var html = '<main class="bbgc-page bbgc-view">' + step(3, 'YOUR RESULTS') + selectedRow();
        html += '<h1 class="bbgc-form-title">What did you find?</h1><p class="bbgc-form-intro">Tell us what your insurer showed. It\'s okay if you couldn\'t find an answer.</p>';
        html += '<div class="bbgc-error" id="bbgc-form-error" role="alert"></div>';
        html += '<fieldset class="bbgc-field bbgc-coverage"><legend class="bbgc-label">Does your plan cover breast pumps?</legend>' + choices('coverage', [['yes', 'Yes'], ['no', 'No'], ['unclear', 'Couldn\'t confirm']]) + '</fieldset>';
        if (state.coverage === 'yes') {
            html += prescriptionField();
            html += categoryField();
        }
        html += emailField();
        if (state.coverage === 'yes') {
            html += coverageFields();
        } else if (state.coverage === 'no') {
            html += '<div class="bbgc-status">Thanks for checking. You can still help us improve the guide with the optional feedback below.</div>';
        } else if (state.coverage === 'unclear') {
            html += '<div class="bbgc-status">Please don\'t enter a guess. Your insurer or plan administrator can confirm eligibility and any amount.</div>';
        }
        html += '<p class="bbgc-optional-heading">A little extra help · optional</p>';
        html += details('feedback', 'Help us improve this guide', feedback());
        html += details('shopping', 'Your breast pump shopping plans', shopping());
        html += '<div class="bbgc-submit">' + btn('Finish & See My Summary', 'finish');
        html += '<p class="bbgc-micro is-center bbgc-sp12">' + esc(cfg.notice || 'When you finish, BabyBrands will store your answers to improve this guide. Your answers are self-reported, not verified by BabyBrands.') + '</p></div>';
        html += '<button type="button" class="bbgc-back" data-action="backguide">' + icon('back') + 'Back to the guide</button></main>';
        return html;
    }

    function thanks() {
        var entered = state.coverage === 'yes' && !state.noAmount && state.amount !== '';
        var money = 'Not confirmed';
        if (entered) {
            var number = Number(String(state.amount).replace(/,/g, ''));
            money = number.toLocaleString('en-CA', { style: 'currency', currency: 'CAD', maximumFractionDigits: 2 });
        }
        var title = state.amountMeaning === 'shared_balance' ? 'Reported shared-category balance' : 'Dollar amount you reported';
        var coverageLabel = {
            yes: 'Yes - reported by you',
            no: 'No - reported by you',
            unclear: 'Not confirmed'
        }[state.coverage] || 'Not provided';
        var html = '<main class="bbgc-page bbgc-success bbgc-view"><div class="bbgc-success-icon">' + icon('check') + '</div><div class="bbgc-kicker" style="justify-content:center">GUIDE COMPLETE</div><h1>Thank you</h1><p class="bbgc-intro">Thank you for helping other parents. Here\'s a summary of what you entered.</p>';
        html += '<div class="bbgc-summary"><div class="bbgc-summary-title">Your reported results</div>';
        html += '<div class="bbgc-summary-row"><small>Insurance company</small><strong>' + esc(insurerName()) + '</strong></div>';
        html += '<div class="bbgc-summary-row"><small>Email</small><strong>' + esc(state.email) + '</strong></div>';
        html += '<div class="bbgc-summary-row"><small>Breast pump coverage</small><strong>' + esc(coverageLabel) + '</strong></div>';
        html += '<div class="bbgc-summary-row is-amount"><small>' + esc(title) + '</small><strong>' + esc(money) + '</strong><small>CAD · Not a payment guarantee</small></div>';
        if (state.percent && state.coverage === 'yes') {
            html += '<div class="bbgc-summary-row"><small>Reported reimbursement percentage</small><strong>' + esc(state.percent) + '%</strong></div>';
        }
        if (state.category && state.coverage === 'yes') {
            var found = state.category === 'Other - please specify' ? state.otherCategory : state.category;
            html += '<div class="bbgc-summary-row"><small>Where you found it</small><strong>' + esc(found) + '</strong></div>';
        }
        if (state.prescription && state.coverage === 'yes') {
            var prescription = { yes: 'Yes', no: 'No', unknown: 'Not sure' }[state.prescription] || '';
            html += '<div class="bbgc-summary-row"><small>Prescription required</small><strong>' + esc(prescription) + '</strong></div>';
        }
        html += '</div><p class="bbgc-note" style="text-align:left">Your insurer decides eligibility and reimbursement. A dollar limit may be subject to a percentage, remaining benefits and other conditions.</p>';
        html += btn('Return to BabyBrands', 'return', 'bbgc-btn-dark');
        html += '<div class="bbgc-minor-center"><button type="button" class="bbgc-minor" data-action="edit">Edit my answers</button></div></main>';
        return html;
    }

    function removeThemeSelect() {
        var shell = document.getElementById('bbgc-guide');
        if (!shell) {
            return;
        }
        shell.querySelectorAll('.nice-select, .select2, .chosen-container').forEach(function (node) {
            node.remove();
        });
        shell.querySelectorAll('select').forEach(function (select) {
            select.style.display = '';
        });
    }

    var csrf = '';
    var syncQueue = Promise.resolve();

    function uuid() {
        if (window.crypto && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
            var rand = Math.random() * 16 | 0;
            return (char === 'x' ? rand : (rand & 0x3 | 0x8)).toString(16);
        });
    }

    function idempotencyKey() {
        var key = sessionStorage.getItem('bbgc_idem');
        if (!key) {
            key = uuid();
            sessionStorage.setItem('bbgc_idem', key);
        }
        return key;
    }

    function api(method, path, body, keepalive) {
        return fetch(cfg.restUrl + path, {
            method: method,
            credentials: 'same-origin',
            keepalive: !!keepalive,
            headers: {
                'Content-Type': 'application/json',
                'X-BBGC-CSRF': csrf || ''
            },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    var error = new Error((data && data.message) || 'We could not save your answers. Please try again.');
                    error.status = response.status;
                    throw error;
                }
                return data;
            });
        });
    }

    function rememberJourney(data) {
        if (!data) {
            return;
        }
        if (data.csrf) {
            csrf = data.csrf;
        }
        if (data.publicId && state.publicId && data.publicId !== state.publicId) {
            state.responseId = 0;
            state.responseVersion = 0;
            state.submitted = false;
            sessionStorage.removeItem('bbgc_idem');
        }
        if (data.publicId) {
            state.publicId = data.publicId;
        }
        if (data.rowVersion) {
            state.journeyVersion = data.rowVersion;
        }
        save();
    }

    function applyServer(data) {
        rememberJourney(data);
        if (!data) {
            return;
        }
        if (data.response) {
            var response = data.response;
            state.submitted = true;
            state.responseId = response.id;
            state.responseVersion = response.rowVersion;
            state.coverage = response.coverage || '';
            state.email = response.email || '';
            state.amount = response.amount || '';
            state.noAmount = !!response.noAmount;
            state.percent = response.percent || '';
            state.amountMeaning = response.amountMeaning || '';
            state.prescription = response.prescription || '';
            state.category = response.category || '';
            state.otherCategory = response.otherCategory || '';
            state.ease = response.ease || '';
            state.comment = response.comment || '';
            state.brands = response.brands || [];
            state.otherBrand = response.otherBrand || '';
            state.retailer = response.retailer || '';
            state.otherRetailer = response.otherRetailer || '';
            state.timing = response.timing || '';
            if (data.insurer) {
                state.insurer = data.insurer;
            }
            state.otherInsurer = data.insurerOther || state.otherInsurer;
            state.account = data.account || state.account;
            if (!cfg.view) {
                state.view = 'thanks';
            }
        } else if (data.insurer && !state.insurer) {
            state.insurer = data.insurer;
            state.otherInsurer = data.insurerOther || '';
            state.account = data.account || '';
        }
        save();
    }

    var opening = null;

    function ensureSession() {
        if (csrf) {
            return Promise.resolve();
        }
        if (opening) {
            return opening;
        }
        var localInsurer = state.insurer;
        var localAccount = state.account;
        var localOther = state.otherInsurer;
        opening = api('POST', '/journeys', {
            source: state.source || cfg.source || 'direct',
            campaign: state.campaign || cfg.campaign || ''
        }).then(function (data) {
            applyServer(data);
            if (data && data.response) {
                return;
            }
            if (localInsurer && (!data || data.insurer !== localInsurer || (localAccount && data.account !== localAccount))) {
                state.insurer = localInsurer;
                state.otherInsurer = localOther;
                state.account = localAccount;
                save();
                return syncJourney();
            }
        }).finally(function () {
            opening = null;
        });
        return opening;
    }

    function syncJourney() {
        syncQueue = syncQueue.then(function () {
            if (!csrf || !cfg.restUrl) {
                return;
            }
            var step = state.view === 'page2' ? 2 : ((state.view === 'results' || state.view === 'thanks') ? 3 : 1);
            return api('PATCH', '/journey', {
                insurer: state.insurer,
                insurerOther: state.otherInsurer,
                account: state.account,
                step: step,
                rowVersion: state.journeyVersion
            }).then(function (data) {
                rememberJourney(data);
            }).catch(function (error) {
                if (error.status !== 409) {
                    return;
                }
                return api('GET', '/journey').then(function (data) {
                    rememberJourney(data);
                    return api('PATCH', '/journey', {
                        insurer: state.insurer,
                        insurerOther: state.otherInsurer,
                        account: state.account,
                        step: step,
                        rowVersion: state.journeyVersion
                    });
                }).then(function (data) {
                    rememberJourney(data);
                });
            });
        }).catch(function () {});
        return syncQueue;
    }

    function track(type, step) {
        var event = { type: type, step: step, uuid: uuid() };
        ensureSession().then(function () {
            if (!csrf) {
                return;
            }
            api('POST', '/events', { events: [event] }, true).catch(function () {});
        }).catch(function () {});
    }

    function responseBody() {
        return {
            coverage: state.coverage,
            email: String(state.email || '').trim(),
            amount: state.noAmount ? '' : state.amount,
            noAmount: !!state.noAmount,
            percent: state.percent,
            amountMeaning: state.amountMeaning,
            prescription: state.prescription,
            category: state.category,
            otherCategory: state.otherCategory,
            ease: state.ease,
            comment: state.comment,
            brands: state.brands,
            otherBrand: state.otherBrand,
            retailer: state.retailer,
            otherRetailer: state.otherRetailer,
            timing: state.timing,
            noticeVersion: cfg.noticeVersion || ''
        };
    }

    function submitResponse() {
        return ensureSession().then(function () {
            return syncJourney();
        }).then(function () {
            var body = responseBody();
            if (state.responseId) {
                body.responseVersion = state.responseVersion;
                return api('PUT', '/response', body);
            }
            body.idempotencyKey = idempotencyKey();
            return api('POST', '/response', body);
        }).then(function (data) {
            if (data && data.response) {
                state.responseId = data.response.id;
                state.responseVersion = data.response.rowVersion;
                state.submitted = true;
            }
            if (data && data.rowVersion) {
                state.journeyVersion = data.rowVersion;
            }
            save();
        });
    }

    function render() {
        var views = { invitation: invitation, page1: page1, page2: page2, results: results, thanks: thanks };
        var draw = views[state.view] || page1;
        root.innerHTML = header() + draw() + footer();
        save();
        removeThemeSelect();
    }

    function go(view) {
        state.view = view;
        var step = view === 'page2' ? 2 : ((view === 'results' || view === 'thanks') ? 3 : 1);
        if (view === 'results') {
            track('results_started', 3);
        } else {
            track('step_viewed', step);
        }
        syncJourney();
        render();
        window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
        var shell = document.getElementById('bbgc-guide');
        if (shell && shell.scrollIntoView) {
            shell.scrollIntoView({ block: 'start' });
        }
    }

    function tell(title, body) {
        var dialog = document.getElementById('bbgc-notice');
        document.getElementById('bbgc-notice-title').textContent = title;
        document.getElementById('bbgc-notice-body').textContent = body;
        if (dialog && dialog.showModal) {
            dialog.showModal();
        }
    }

    function resetCoverage() {
        state.account = '';
        state.coverage = '';
        state.amount = '';
        state.noAmount = false;
        state.percent = '';
        state.amountMeaning = '';
        state.prescription = '';
        state.category = '';
        state.otherCategory = '';
        state.ease = '';
        state.comment = '';
        state.expanded = [];
    }

    function showError(message) {
        var el = document.getElementById('bbgc-form-error');
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.add('is-shown');
        el.setAttribute('tabindex', '-1');
        el.focus();
        el.scrollIntoView({ block: 'center' });
    }

    function clearError() {
        var el = document.getElementById('bbgc-form-error');
        if (!el) {
            return;
        }
        el.textContent = '';
        el.classList.remove('is-shown');
    }

    document.addEventListener('click', function (event) {
        var modal = event.target.closest('[data-modal]');
        if (modal && root.contains(modal)) {
            if (modal.getAttribute('data-modal') === 'privacy') {
                tell('Privacy', cfg.privacy || '');
            } else {
                tell('About the guide', cfg.about || '');
            }
        }

        var actionEl = event.target.closest('[data-action]');
        if (!actionEl || !root.contains(actionEl)) {
            return;
        }

        switch (actionEl.getAttribute('data-action')) {
            case 'start':
                go('page1');
                break;
            case 'decline':
                if (cfg.returnUrl) {
                    window.location.assign(cfg.returnUrl);
                } else {
                    tell('Continue without the guide', cfg.decline || '');
                }
                break;
            case 'guide':
                if (state.insurer) {
                    go('page2');
                }
                break;
            case 'change':
                go('page1');
                break;
            case 'results':
                go('results');
                break;
            case 'notfound':
                state.coverage = 'unclear';
                state.amount = '';
                state.percent = '';
                state.category = '';
                state.prescription = '';
                state.noAmount = false;
                state.amountMeaning = '';
                state.otherCategory = '';
                track('coverage_not_found_selected', 2);
                go('results');
                break;
            case 'backguide':
                go('page2');
                break;
            case 'finish':
                if (!state.coverage) {
                    showError('Please tell us whether you found coverage, or choose "Couldn\'t confirm".');
                    return;
                }
                state.email = String(state.email || '').trim();
                if (!isValidEmail(state.email)) {
                    showError('Enter the email address from your BabyBrands registration.');
                    return;
                }
                if (state.coverage === 'yes' && !state.noAmount) {
                    var value = String(state.amount).replace(/,/g, '').trim();
                    if (!/^\d+(\.\d{1,2})?$/.test(value) || !isFinite(Number(value)) || Number(value) < 0 || Number(value) > 1000000) {
                        showError('Enter a valid dollar amount, or select "I couldn\'t find a dollar amount".');
                        return;
                    }
                    state.amount = value;
                }
                if (state.coverage === 'yes' && state.percent) {
                    state.percent = normalizePercent(state.percent);
                    if (!isValidPercent(state.percent)) {
                        showError(Number(state.percent) > 100
                            ? 'Reimbursement percentage cannot be more than 100.'
                            : 'Enter a percentage from 0 to 100, or leave it blank.');
                        return;
                    }
                }
                if (state.coverage === 'yes' && state.category === 'Other - please specify' && !String(state.otherCategory).trim()) {
                    showError('Enter the other section name, or leave the optional section question blank.');
                    return;
                }
                if (actionEl) {
                    actionEl.disabled = true;
                }
                submitResponse().then(function () {
                    go('thanks');
                }).catch(function (error) {
                    if (actionEl) {
                        actionEl.disabled = false;
                    }
                    showError(error.message || 'We could not save your answers. Please try again.');
                });
                break;
            case 'return':
                track('return_to_babybrands_clicked', 3);
                if (cfg.returnUrl) {
                    window.location.assign(cfg.returnUrl);
                }
                break;
            case 'edit':
                go('results');
                break;
            default:
                break;
        }
    });

    document.addEventListener('input', function (event) {
        var name = event.target.name;
        if (!name || !(name in state) || !root.contains(event.target)) {
            return;
        }
        if (event.target.type === 'radio' || event.target.type === 'checkbox' || event.target.tagName === 'SELECT') {
            return;
        }
        if (name === 'email') {
            state.email = event.target.value;
            if (isValidEmail(state.email)) {
                clearError();
            }
            save();
            return;
        }
        if (name === 'amount') {
            var amountCleaned = String(event.target.value).replace(/[^\d.]/g, '');
            var amountParts = amountCleaned.split('.');
            if (amountParts.length > 1) {
                amountCleaned = amountParts[0] + '.' + amountParts.slice(1).join('').slice(0, 2);
            }
            if (event.target.value !== amountCleaned) {
                event.target.value = amountCleaned;
            }
            state.amount = amountCleaned;
            save();
            return;
        }
        if (name === 'percent') {
            var typing = sanitizePercentTyping(event.target.value);
            if (event.target.value !== typing) {
                event.target.value = typing;
            }
            state.percent = typing === '' || typing === '.' ? '' : normalizePercent(typing.replace(/\.$/, ''));
            if (isValidPercent(state.percent)) {
                clearError();
            }
            save();
            return;
        }
        state[name] = event.target.value;
        save();
    });

    document.addEventListener('focusin', function (event) {
        if (!root.contains(event.target) || event.target.name !== 'percent') {
            return;
        }
        // Edit the bare number; % is added again when the field is complete.
        event.target.value = state.percent || '';
    });

    document.addEventListener('focusout', function (event) {
        if (!root.contains(event.target) || event.target.name !== 'percent') {
            return;
        }
        state.percent = normalizePercent(event.target.value);
        event.target.value = state.percent === '' ? '' : state.percent + '%';
        save();
        if (state.percent !== '' && Number(state.percent) > 100) {
            showError('Reimbursement percentage cannot be more than 100.');
        } else {
            clearError();
        }
    });

    document.addEventListener('change', function (event) {
        var name = event.target.name;
        if (!name || !(name in state) || !root.contains(event.target)) {
            return;
        }
        var scrollY = window.scrollY;
        if (name === 'brands') {
            var brand = event.target.value;
            if (event.target.checked) {
                if (brand === 'I haven\'t decided yet') {
                    state.brands = [brand];
                } else {
                    state.brands = state.brands.filter(function (item) {
                        return item !== 'I haven\'t decided yet';
                    });
                    if (state.brands.indexOf(brand) === -1) {
                        state.brands.push(brand);
                    }
                }
            } else {
                state.brands = state.brands.filter(function (item) {
                    return item !== brand;
                });
            }
            if (state.brands.indexOf('Other') === -1) {
                state.otherBrand = '';
            }
        } else if (name === 'noAmount') {
            state.noAmount = event.target.checked;
            if (state.noAmount) {
                state.amount = '';
            }
        } else if (name === 'insurer') {
            if (state.insurer !== event.target.value) {
                resetCoverage();
                state.insurer = event.target.value;
                state.otherInsurer = '';
            }
        } else {
            state[name] = event.target.value;
            if (name === 'coverage' && state.coverage !== 'yes') {
                state.amount = '';
                state.noAmount = false;
                state.percent = '';
                state.amountMeaning = '';
                state.prescription = '';
                state.category = '';
                state.otherCategory = '';
            }
            if (name === 'category' && state.category !== 'Other - please specify') {
                state.otherCategory = '';
            }
            if (name === 'retailer' && state.retailer !== 'Other') {
                state.otherRetailer = '';
            }
        }
        save();
        if (name === 'insurer' || name === 'account') {
            syncJourney();
        }
        var refresh = ['insurer', 'coverage', 'noAmount', 'category', 'account', 'brands', 'retailer'];
        if (refresh.indexOf(name) !== -1) {
            render();
            window.scrollTo(0, scrollY);
        }
    });

    document.addEventListener('toggle', function (event) {
        if (!event.target.matches || !event.target.matches('details[data-detail]') || !root.contains(event.target)) {
            return;
        }
        var id = event.target.getAttribute('data-detail');
        state.expanded = state.expanded.filter(function (item) {
            return item !== id;
        });
        if (event.target.open) {
            state.expanded.push(id);
            if (id === 'account') {
                track('help_opened', 2);
            }
        }
        save();
    }, true);

    var closeNotice = document.getElementById('bbgc-close-notice');
    if (closeNotice) {
        closeNotice.addEventListener('click', function () {
            var dialog = document.getElementById('bbgc-notice');
            if (dialog) {
                dialog.close();
            }
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[data-event]') : null;
        if (!link || !root.contains(link)) {
            return;
        }
        var eventName = link.getAttribute('data-event') || 'help_clicked';
        track(eventName, state.view === 'page2' ? 2 : 3);
        if (eventName === 'insurer_website_clicked' && state.view === 'page2') {
            go('results');
        }
    });

    render();
    ensureSession().then(function () {
        render();
    }).catch(function () {});
    if (window.jQuery) {
        window.jQuery(removeThemeSelect);
    }
    window.setTimeout(removeThemeSelect, 0);
}());
