# Breast Pump Insurance Guide

WordPress shortcode that renders the BabyBrands breast pump insurance guide. Parents pick their insurer, open that insurer's own website, then come back and record what they found. The guide does not sign anyone in, read a member portal, or submit a claim.

**Text domain:** `breast-pump-insurance-plugin`
**Version:** `1.0.0` (`BBGC_VERSION` in the main plugin file).

## Requirements

- WordPress 5.x or newer.
- PHP 7.4+.
- A modern browser. Progress is kept in `sessionStorage` for the current tab.

## Installation

1. Copy the `breast-pump-insurance-plugin` folder into `wp-content/plugins/`.
2. In **Plugins**, activate **Breast Pump Insurance Guide**.
3. Add the shortcode `[bbgc_insurance_guide]` to any page or post.

There is no settings screen and no database table. Styles and scripts load only on pages that render the shortcode.

## Shortcode

```text
[bbgc_insurance_guide]
```

### Attributes (all optional)

| Attribute | Default | Purpose |
|-----------|---------|---------|
| `return_url` | Site home | Where **No thanks, continue** and **Return to BabyBrands** send the visitor. |
| `start` | See below | First screen: `invitation` or `page1`. |

When `start` is omitted, the guide opens on the invitation only if the URL includes `source=bbgc_registration`. Every other visit starts on the insurer step (page 1).

`start` accepts only `invitation` and `page1`. Anything else is ignored and the rule above is used.

### Examples

Insurer step first (QR codes, direct links, in-page embeds):

```text
[bbgc_insurance_guide]
```

Invitation after registration, then back to a specific page:

```text
[bbgc_insurance_guide start="invitation" return_url="/thank-you/"]
```

## URL parameters

These are read from the page URL. They are not shortcode attributes.

| Parameter | Accepted values | Purpose |
|-----------|-----------------|---------|
| `source` | `bbgc_registration`, `qr`, `direct` | How the visitor arrived. Anything else is stored as `direct`. Also chooses the default first screen when `start` is omitted. |
| `campaign` | 1–40 characters: letters, numbers, `_`, `-` | Optional campaign code kept with the in-browser journey. Invalid values are dropped. It is never shown as the visitor's identity. |
| `bbgc_view` | `invitation`, `page1`, `page2`, `results`, `thanks` | Opens that screen. Page 2, results, and the summary fall back to page 1 until an insurer has been chosen. |

Examples:

```text
/breast-pump-insurance-guide/?source=qr&campaign=spring2026
/breast-pump-insurance-guide/?source=bbgc_registration
/breast-pump-insurance-guide/?bbgc_view=page1
```

## What the visitor sees

1. **Invitation** (optional). Registration-complete message, **Check My Coverage**, and **No thanks, continue**.
2. **Your insurer.** Native select. **Check My Breast Pump Coverage** stays disabled until a company is chosen. "Another insurance company" asks for a name. Changing insurer clears coverage answers and keeps shopping answers.
3. **Find your coverage.** Known insurers get their member-login link (new tab), sign-in help, and the places to check in that plan. Sun Life also shows the route Health → your plan → Coverage search. "Another insurance company" and "I'm not sure" explain that only the official site on the card or plan documents should be used.
4. **Your results.** Coverage yes / no / couldn't confirm. Yes asks for the dollar amount (or "I couldn't find a dollar amount"), optional percent and meaning, prescription, and where the information was found. Optional feedback and shopping plans sit in collapsed sections.
5. **Summary.** Reported insurer, coverage, and amount, labelled as self-reported and not a payment guarantee. **Edit my answers** returns to the results step. **Return to BabyBrands** uses `return_url`.

Footer actions **Privacy** and **About this guide** open a dialog. They do not leave the page.

## Answers

Answers stay in the browser under the `sessionStorage` key `bbgc_guide_v1` until the tab's session ends. Refreshing the same tab restores the journey. A new tab starts over.

This release does not write responses to WordPress, send them to an API, or build an admin report. The summary is only what the visitor just entered. Do not describe the guide as storing or verifying benefits on the server.

Validation on **Finish & See My Summary**:

- Coverage is required.
- A "Yes" answer needs a CAD amount from 0 to 1,000,000 with up to two decimals, or the "couldn't find a dollar amount" choice. Zero is allowed.
- Percent, if filled in, must be from 0 to 100.
- "Other" section text is required only when that section option is selected.
- "I haven't decided yet" is exclusive among brand choices.
- If the retailer is "I already purchased a breast pump", timing is set to "I already purchased one".

## Insurers

The directory is `includes/directory.php` (`bbgc_insurer_directory()`). Each entry has the display name, member login, registration help, coverage-help URL, sign-in copy, and the areas to check.

| Key | Name |
|-----|------|
| `sunlife` | Sun Life |
| `canadalife` | Canada Life |
| `manulife` | Manulife |
| `greenshield` | GreenShield |
| `desjardins` | Desjardins Insurance |
| `beneva` | Beneva |
| `ia` | iA Financial Group |
| `pacific` | Pacific Blue Cross |
| `alberta` | Alberta Blue Cross |
| `other` | Another insurance company |
| `unsure` | I'm not sure |

`other` and `unsure` have no outbound insurer links. Screen wording follows the BabyBrands mobile preview. Login, registration, and help URLs are the Canadian destinations from the Phase 1 directory (reviewed 19 October 2026).

External insurer links use `target="_blank"` and `rel="noopener noreferrer"`.

## Architecture

| Piece | Path |
|-------|------|
| Plugin entry and shortcode | `breast-pump-insurance-plugin.php` — `[bbgc_insurance_guide]`, `bbgc_render_shortcode()` |
| Insurer directory | `includes/directory.php` |
| Front-end behaviour | `assets/js/guide.js` |
| Styles | `assets/css/guide.css` (every rule scoped under `.bbgc-shell`) |
| Logo | `assets/images/babybrands-logo.svg` |

`BBGC_GUIDE` is passed to the script with `wp_localize_script`. It includes the logo URL, return URL, source, campaign, start screen, preview view, insurer directory, and the privacy, about, and decline copy.

## Theme notes

The Kidsa theme runs nice-select on every `select`. The guide keeps a native dropdown: selects stay `display: block`, and `.nice-select`, `.select2`, and `.chosen-container` inside `.bbgc-shell` are hidden. `guide.js` also removes those widgets after render.

Theme button backgrounds are overridden with `.bbgc-shell button.bbgc-btn-primary` (coral) and `.bbgc-shell button.bbgc-btn-dark` (dark teal). Paragraph and heading margins inside the shell are reset, so spacing utilities are written as `.bbgc-shell .bbgc-…` rather than a single class.

Colours live on `.bbgc-shell` and can be overridden from the theme:

```css
.bbgc-shell {
    --bbgc-coral: #ff7276;
    --bbgc-aqua: #66cccc;
    --bbgc-dark: #3f5c5a;
    --bbgc-teal: #018f91;
    --bbgc-yellow: #fcedc0;
    --bbgc-lilac: #94a2de;
}
```

The guide column is 440px wide and mobile-first.

## Reference files

`BabyBrands_Mobile_Insurance_Preview.html` and `BabyBrands_WordPress_Developer_Specification.pdf` sit in the plugin folder for reference. WordPress does not load them. The root `babybrands-logo.png` is unused; the header uses the SVG.
