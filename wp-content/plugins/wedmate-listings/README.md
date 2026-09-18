# Wedmate Listings

Local implementation on wedmate.test, September 18, 2026.

## Content model

- One public `listings` post type replaces `vendors` and `venues`.
- `vendor_category` classifies services. Wedding Venues is the category that selects the venue profile layout.
- `location` remains shared with Stories. A business may have multiple locations and service categories.
- Venue types are currently hidden in the editor and removed from directory filters, as requested. Existing taxonomy data is retained for possible future use.
- Existing post IDs, content, gallery metadata and featured images are retained.
- Stories remain separate. `selected_vendors` stores the ordered listing IDs, including venue IDs. `wml_team_roles` holds per-wedding credit labels.

## Routes

| Route | View |
| --- | --- |
| `/vendors/` | All listings |
| `/delhi/` | All listings in Delhi |
| `/delhi/wedding-venues/` | Delhi venues |
| `/delhi/photography/` | Delhi photographers |
| `/vendors/all/wedding-venues/` | Nationwide venues |
| `/wedding-venues/{slug}/` | Venue profile |
| `/vendor/{slug}/` | Other business profile |

Directory pagination uses `/page/2/`. Search uses `?q=...`; search variants are noindex with a clean-directory canonical. Invalid categories and out-of-range pages return 404. Only existing, top-level city slugs get root routes. Existing page slugs and reserved paths take precedence. Rewrite rules refresh when cities or pages change.

## Owner submission and approval

Share `/submit-listing/` once the site is deployed to a publicly reachable host. The local `wedmate.test` address works only on the local machine. The page uses the plugin's submission template; ensure a published WordPress page with the slug `submit-listing` exists when deploying.

Owners can submit without an account. The form collects business/service/city details, pricing, public contact details, private owner contact details, optional venue details, a portfolio link and up to three JPG/PNG/WebP photos (maximum 2 MB each, limited further by the host's upload limit). The first photo becomes the featured image.

New records are always Pending Review. Go to **Listings → Owner submissions**, open a submission, review/edit the details and photos, then **Publish** to approve. **Move to Trash** rejects the submission. Publishing automatically includes the record in its matching city/category directories. Publishing does not automatically mark the business verified or add ratings.

Owner name/email/phone and portfolio-review links use protected metadata and are not rendered on the public profile. Only the separately labelled public business contact fields appear after approval. Standard WordPress uploaded media files can have accessible file URLs before a listing is published; the listing itself is not publicly accessible while pending. Do not request confidential documents through the photo upload.

Validation includes signed expiring form tokens, WordPress nonces, a honeypot, IP/email submission limits, duplicate-request protection, a fixed field allowlist and validated image types/sizes. There is no automatic email notification: reviewers use the WordPress queue. Private contact details are available for manual follow-up.

Legacy `/venue/{slug}/`, `/venue/`, `/vendor/`, `/vendors/{category}/{city}/`, `/vendors/{category}/`, `/venues/{city}/` and `/location/{city}/` resolve or redirect to the appropriate new view. Historical `/vendors/{business-slug}/` URLs redirect when the slug identifies a published listing rather than a category.

## Editing

Use **Listings** in WordPress admin. Choose service categories and cities, then complete Listing details. Venue, Photography and Makeup Artist panels provide service-specific fields. Set the featured image as usual; galleries use the WordPress media picker.

For Stories, choose the Wedding Team in Story Details, including venues. The Wedding team roles and order box sets the label and sequence. The original free-text venue field is retained for narrative details. Connected published stories appear on listing profiles.

The directory is rendered by this plugin. Profiles reuse the child theme's vendor shortcodes and venue template parts. Global Divi header/footer are retained. A compatibility query adapter keeps existing homepage and related-item shortcodes working against the new post type.

## Migration and rollback

`migrate.php` is CLI-only and version-guarded. On a fresh copy, take a DB and code backup before running `php wp-content/plugins/wedmate-listings/migrate.php`. The plugin is activated by this command. It records the old registration options and IDs in `wml_migration_snapshot`, retains the original type/URL on each listing, migrates menu references, recounts terms and refreshes Yoast indexes.

Do not deploy by merely copying the plugin: production requires the matching child-theme changes, reviewed migration, URL decisions and fresh backup. URLs remain provisional.

The local backup is outside the web root:

`C:/Users/!ADMIN!/.codex/visualizations/2026/09/16/01a0aa19-55cc-7242-a1ad-80e79f02f260/wedmate-before-restructure-20260918/`

It includes the full database, pre-change child-theme ZIP, Divi layout export and 45 rendered pages. The September 17 backup additionally includes all wp-content files.

Rollback requires restoring the saved database and child theme together, then disabling/removing this plugin and refreshing permalinks. Restoring the DB also restores plugin activation and CPT UI registration options. Do not restore over subsequent editorial changes without reconciling them first.

## Validation

The implementation was checked using all 12 profile URLs, all four Stories, the homepage, city/category directories, legacy redirects and Yoast sitemaps. Temporary test records exercised editor nonce checks, gallery sanitization, venue/vendor story credits, credit ordering, reverse relationships and actual HTTP page-two pagination; test records were removed afterwards.

Evidence is saved outside the web root in `listings-check.json` and `listing-editor-tests.json` alongside the backup.

Local data corrections: Namrata Soni Makeup moved from Photography to Makeup Artists; the Florals and Décor category name and Wedding Filmer's currency symbol were repaired. Other existing editorial text, sample contact details and placeholder enquiry/save controls were preserved for the later design/content pass.
