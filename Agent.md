# AGENT.md

## Project Brief

This repository contains a WordPress plugin located at:

```text
wp-content/plugins/agency-client-plugin
```

The plugin powers:

* A Case Study content section
* A newsletter sign-up form
* A partner content feed

The client reports that the site feels slow and that something looks wrong or suspicious on the newsletter sign-up form.

The goal is to improve the plugin in a practical, production-minded way. This is a timeboxed task, so working solutions and clear reasoning are more important than polish.

---

## Timebox

Target time: **3–4 hours**

Do not over-engineer. Prioritize:

1. Working code
2. Clear documentation
3. WordPress-idiomatic fixes
4. Good commit history
5. Strong reasoning in notes

If time runs out, document what remains and what should be done next.

---

## Local Setup

Use the provided Docker environment unless there is a good reason not to.

### Setup commands

```bash
cp .env.example .env
docker compose up -d
docker compose --profile setup run --rm wp-setup
```

Then open:

```text
http://localhost:8080
```

Admin:

```text
http://localhost:8080/wp-admin
```

Credentials:

```text
Username: admin
Password: admin
```

---

## Required Deliverables

The final submission must include:

```text
docs/SETUP.md
docs/NOTES.md
docs/PROMOTION-PLAN-TASK.md
```

And code changes inside:

```text
wp-content/plugins/agency-client-plugin
```

Submit as:

* A Git repository link, or
* A Git bundle, or
* A ZIP file that includes `.git` history

The commit history matters. Use focused commits.

---

# Work Plan

## 1. Stand It Up and Document It

### Goal

Get the site running locally and confirm the plugin is active.

### Steps

Run:

```bash
cp .env.example .env
docker compose up -d
docker compose --profile setup run --rm wp-setup
```

Confirm:

* Homepage loads at `http://localhost:8080`
* Admin loads at `http://localhost:8080/wp-admin`
* Plugin is active
* Partner feed appears
* Newsletter form appears
* Case Studies are seeded or visible in admin

### Write `docs/SETUP.md`

Include exact setup steps from a clean machine.

Suggested content:

````md
# Setup

## Requirements

- Docker Desktop
- Git

## Steps

1. Clone the repository.
2. Copy the environment file:

```bash
cp .env.example .env
````

3. Start the containers:

```bash
docker compose up -d
```

4. Run the WordPress setup command:

```bash
docker compose --profile setup run --rm wp-setup
```

5. Open the site:

* Site: http://localhost:8080
* Admin: http://localhost:8080/wp-admin

6. Login:

* Username: admin
* Password: admin

## Notes

I used the provided Docker setup. No additional local services were required.

````

### Commit

```bash
git add docs/SETUP.md
git commit -m "Document local setup steps"
````

---

## 2. Finish the Case Study Feature

### Main file

```text
includes/class-acp-cpt.php
```

### Goal

Complete the Case Study custom post type so it behaves like a first-class WordPress content type.

### Requirements

The Case Study CPT should support:

* Admin menu
* Editor support
* Title
* Content editor
* Excerpt
* Featured image
* Revisions
* REST API support
* A place to store a headline metric

### CPT Registration

Use `register_post_type()` with WordPress-friendly arguments.

Recommended settings:

```php
'public'       => true,
'show_ui'      => true,
'show_in_menu' => true,
'show_in_rest' => true,
'has_archive'  => true,
'menu_icon'    => 'dashicons-portfolio',
'supports'     => array(
    'title',
    'editor',
    'excerpt',
    'thumbnail',
    'revisions',
),
```

### Headline Metric Field

Store the metric as post meta.

Recommended meta key:

```text
_acp_headline_metric
```

Add:

* Meta box
* Nonce field
* Save handler
* Autosave protection
* Capability check
* Sanitization

Important functions:

```php
add_meta_box()
wp_nonce_field()
check_admin_referer()
current_user_can()
sanitize_text_field()
update_post_meta()
```

### Shortcode

Build a shortcode to output published case studies.

Recommended shortcode:

```text
[acp_case_studies]
```

The shortcode should display:

* Case Study title
* Headline metric
* Excerpt
* Link to the full case study

Use `WP_Query`:

```php
$query = new WP_Query( array(
    'post_type'      => 'acp_case_study',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
```

Escape all output:

```php
esc_html()
esc_attr()
esc_url()
wp_kses_post()
```

Reset post data:

```php
wp_reset_postdata();
```

### Why Shortcode Instead of Block

Use a shortcode because:

* It is faster to implement within the timebox.
* It works without a build process.
* It can be used in the block editor, classic editor, widgets, or seeded pages.
* It is easy for a teammate or client to test.
* It is enough for the required task.

### Add to `docs/NOTES.md`

```md
## Case Study CPT

I completed the Case Study custom post type so it behaves like a first-class WordPress content type.

Changes:
- Enabled admin UI.
- Enabled editor support.
- Enabled REST support.
- Added support for title, editor, excerpt, thumbnail, and revisions.
- Added a headline metric post meta field.
- Added the `[acp_case_studies]` shortcode.

I chose a shortcode instead of a block because it is faster to implement, does not require a build step, works with the existing seeded landing page, and is easy for non-technical editors to place anywhere.
```

### Commit

```bash
git add includes/class-acp-cpt.php docs/NOTES.md
git commit -m "Complete case study CPT and shortcode"
```

---

## 3. Find and Fix the Performance Problem

### Main file

```text
includes/class-acp-market-widget.php
```

### Goal

Fix the partner content feed so the homepage no longer feels slow.

### Likely Issue

The partner feed is probably making a remote HTTP request during every frontend page load.

Look for:

```php
wp_remote_get()
file_get_contents()
curl_exec()
sleep()
```

The common bad pattern is:

```php
$response = wp_remote_get( $url );
```

inside frontend rendering with no caching.

This makes every page load depend on the external feed endpoint.

### Fix Strategy

Use WordPress transients.

Recommended behavior:

1. Check cached feed data first.
2. If cache exists, render cached data.
3. If cache is missing, fetch remote feed.
4. Use a short timeout.
5. Validate the response.
6. Cache valid results.
7. Fail gracefully if the feed is unavailable.

Example pattern:

```php
$cache_key = 'acp_partner_feed';
$items = get_transient( $cache_key );

if ( false === $items ) {
    $response = wp_remote_get( $url, array(
        'timeout' => 3,
    ) );

    if ( is_wp_error( $response ) ) {
        return array();
    }

    $response_code = wp_remote_retrieve_response_code( $response );

    if ( 200 !== $response_code ) {
        return array();
    }

    $body = wp_remote_retrieve_body( $response );
    $items = json_decode( $body, true );

    if ( ! is_array( $items ) ) {
        return array();
    }

    set_transient( $cache_key, $items, 15 * MINUTE_IN_SECONDS );
}
```

### Also Check

Make sure output is escaped:

```php
esc_html()
esc_url()
esc_attr()
wp_kses_post()
```

### Add to `docs/NOTES.md`

```md
## Performance issue: Partner feed

### What was wrong

The partner content feed was performing a remote request during frontend rendering. This meant every page view depended on the response time of an external service. If the external endpoint was slow or unavailable, the WordPress page also became slow.

### How I confirmed it

I reviewed `includes/class-acp-market-widget.php` and found the feed request was executed during page render without a caching layer. I tested the homepage before and after the change and confirmed that repeated page loads no longer need to perform the expensive remote fetch path.

### Fix

I added WordPress transient caching around the partner feed request.

The plugin now:
- Checks cached partner feed data first.
- Only calls the remote endpoint when the cache is empty or expired.
- Uses a short HTTP timeout.
- Validates the response code.
- Validates decoded JSON before using it.
- Handles failures gracefully instead of blocking the page.

This improves frontend performance because most page loads now use cached data instead of waiting on a remote service.
```

### Commit

```bash
git add includes/class-acp-market-widget.php docs/NOTES.md
git commit -m "Cache partner feed to improve frontend performance"
```

---

## 4. Find and Fix the Newsletter Security Problem

### Main file

```text
includes/class-acp-shortcode.php
```

### Goal

Fix the newsletter sign-up form in a WordPress-idiomatic way.

### Likely Issues

Look for:

* Missing nonce check
* Raw `$_POST` usage
* Missing `wp_unslash()`
* Missing sanitization
* Missing email validation
* Unsafe output
* Direct database insert using raw input
* CSRF risk
* XSS risk

### Required Fixes

The form should include a nonce.

In the form:

```php
wp_nonce_field( 'acp_newsletter_signup', 'acp_newsletter_nonce' );
```

On submit:

```php
if (
    ! isset( $_POST['acp_newsletter_nonce'] ) ||
    ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['acp_newsletter_nonce'] ) ),
        'acp_newsletter_signup'
    )
) {
    return '<p>Security check failed. Please try again.</p>';
}
```

Sanitize and validate email:

```php
$email = isset( $_POST['acp_email'] )
    ? sanitize_email( wp_unslash( $_POST['acp_email'] ) )
    : '';

if ( ! is_email( $email ) ) {
    return '<p>Please enter a valid email address.</p>';
}
```

Escape output:

```php
esc_html()
esc_attr()
esc_url()
```

If saving to database with `$wpdb`, use safe methods:

```php
$wpdb->insert(
    $table_name,
    array(
        'email' => $email,
    ),
    array(
        '%s',
    )
);
```

Do not save raw `$_POST` values.

### Add to `docs/NOTES.md`

```md
## Security issue: Newsletter signup

### What was wrong

The newsletter form accepted frontend POST submissions without a WordPress nonce check. This made the form vulnerable to CSRF because another website could potentially submit the form on behalf of a visitor.

The form also needed strict handling of user input. Email values should not be trusted directly from `$_POST`.

### Why it was dangerous

Without a nonce, the site could not verify that the submission came from the intended newsletter form. Without sanitization and validation, malformed or malicious input could be stored or reflected later.

Depending on how the submitted value is displayed or stored, this could lead to spam, bad data, or XSS risk.

### Fix

I updated the form to use WordPress nonce protection with `wp_nonce_field()` and `wp_verify_nonce()`.

I also:
- Used `wp_unslash()` before reading submitted data.
- Sanitized the email with `sanitize_email()`.
- Validated the email with `is_email()`.
- Escaped output before rendering it back to the browser.

This follows WordPress frontend form handling best practices.
```

### Commit

```bash
git add includes/class-acp-shortcode.php docs/NOTES.md
git commit -m "Secure newsletter signup form handling"
```

---

## 5. Write the Promotion Plan

### File

```text
docs/PROMOTION-PLAN-TASK.md
```

### Goal

Explain how to promote changes from staging to production safely.

This is the most important writing task.

### Suggested Content

```md
# Promotion Plan

## Goal

Promote the Agency Client Plugin changes from staging to production with minimal risk, no accidental production data loss, and a clear rollback path.

## Principles

- Production is the source of truth for live content and user data.
- Code can move from staging to production.
- The staging database should not overwrite the production database unless there is a specific, approved migration plan.
- Secrets must never be committed to Git.
- Every deployment should have a backup and rollback path.

## Pre-deployment Checklist

Before deploying:

- Confirm all code is committed.
- Confirm the branch has been reviewed.
- Confirm staging matches the production PHP version as closely as possible.
- Confirm staging matches the production WordPress version as closely as possible.
- Confirm the plugin is active on staging.
- Confirm the homepage renders correctly.
- Confirm Case Studies display correctly.
- Confirm the Case Study admin screen works.
- Confirm headline metrics save correctly.
- Confirm the newsletter form works.
- Confirm the newsletter form rejects invalid email addresses.
- Confirm the partner feed loads from cache after the first request.
- Confirm no secrets are committed.
- Confirm `.env` is ignored.
- Take a fresh production file backup.
- Take a fresh production database backup.

## Database Handling

Production data must be protected.

For this plugin change, I would not copy the staging database over production. Production may contain real content, users, newsletter signups, settings, and editorial changes that do not exist in staging.

The preferred approach is:

1. Deploy code only.
2. Let the plugin use existing production content.
3. Add any new metadata or options in a backward-compatible way.
4. Use idempotent upgrade routines if schema changes are needed.
5. Avoid destructive database changes.

If Case Study content needs to move from staging to production:

- Export only the required Case Study posts.
- Include related post meta, especially the headline metric field.
- Include featured images if needed.
- Import using WordPress export/import, WP-CLI, or a controlled migration script.
- Do not replace the entire production database.
- Verify slugs, post status, media references, and post meta after import.

If a database migration is required:

- Test it first on a recent production database copy in staging.
- Make the migration repeat-safe where possible.
- Back up production immediately before running it.
- Document exactly what the migration changes.
- Have a rollback plan before running it.

## Secrets Handling

Secrets must not be committed to the repository.

Examples of secrets:

- Database credentials
- API keys
- SMTP credentials
- Partner feed tokens
- Production salts
- Third-party service credentials

Secrets should be stored in:

- Hosting environment variables
- Server-level configuration
- Managed secret storage
- `wp-config.php` outside version control

Before production deployment:

- Confirm `.env` is ignored by Git.
- Confirm `.env.example` contains placeholder values only.
- Confirm staging and production use separate credentials.
- Confirm production credentials are not copied into local development.
- Rotate any secret that may have been exposed.

## Deployment Steps

1. Confirm staging has passed testing.
2. Confirm stakeholders know the deployment window.
3. Take a fresh production database backup.
4. Take a fresh production files backup.
5. Tag the release in Git.
6. Deploy the code to production.
7. Clear PHP opcode cache if applicable.
8. Clear WordPress object cache if applicable.
9. Clear page cache or CDN cache if applicable.
10. Visit the production homepage.
11. Confirm the partner feed renders.
12. Confirm the newsletter form renders.
13. Submit a test newsletter signup.
14. Confirm invalid newsletter emails are rejected.
15. Confirm Case Studies render on the frontend.
16. Confirm Case Studies can be edited in wp-admin.
17. Check PHP error logs.
18. Check browser console errors.
19. Monitor production after release.

## Rollback Plan

If the deployment breaks the site:

1. Revert the code to the previous Git tag or release artifact.
2. Clear all relevant caches.
3. Re-test the homepage.
4. Re-test wp-admin.
5. Check PHP logs.
6. Notify stakeholders.

I would not restore the database unless the deployment changed production data and a database rollback is specifically required.

If a database rollback is needed:

- Confirm what data will be lost.
- Get approval before restoring.
- Restore from the pre-deployment backup.
- Re-test the site immediately after restore.

## Post-deployment Checks

After deployment:

- Confirm no PHP fatal errors.
- Confirm no new PHP warnings.
- Confirm the homepage response time is acceptable.
- Confirm the partner feed uses cached data.
- Confirm newsletter submissions are protected by nonce validation.
- Confirm submitted emails are sanitized and validated.
- Confirm Case Study headline metrics display correctly.
- Confirm no secrets are visible in frontend source, logs, or the repository.

## Communication

Before deployment:

- Share the planned deployment time.
- Share expected user impact.
- Share rollback plan.

After deployment:

- Confirm deployment is complete.
- Summarize what changed.
- Mention any issues found.
- Mention any follow-up work.
```

### Commit

```bash
git add docs/PROMOTION-PLAN-TASK.md
git commit -m "Add production promotion plan"
```

---

## 6. Bonus: Headless Slice

Only do this after required tasks are complete.

### Main file

```text
includes/class-acp-rest.php
```

### Goal

Create a read-only REST endpoint for Case Studies.

Recommended endpoint:

```text
GET /wp-json/acp/v1/case-studies
```

### Response Shape

```json
[
  {
    "id": 123,
    "title": "Case Study Title",
    "link": "http://localhost:8080/case-study/example",
    "metric": "42% increase",
    "excerpt": "Short summary"
  }
]
```

### REST Route

Use:

```php
register_rest_route()
WP_REST_Server::READABLE
```

For public published case studies:

```php
'permission_callback' => '__return_true'
```

Only return published content.

### Optional Widget

Folder:

```text
assets/widget/
```

Build a simple React or Vue widget that:

* Fetches `/wp-json/acp/v1/case-studies`
* Displays title
* Displays metric
* Displays excerpt
* Links to the case study

Keep it simple. Do not let the bonus hurt the required submission.

### Commit

```bash
git add includes/class-acp-rest.php assets/widget docs/NOTES.md
git commit -m "Add case study REST endpoint and widget"
```

---

# Final QA Checklist

Before submission, check:

```bash
git status
```

It should be clean.

Also check:

* Site loads.
* Admin loads.
* Plugin is active.
* Case Study menu appears.
* New Case Study can be created.
* Headline metric saves correctly.
* `[acp_case_studies]` displays published case studies.
* Partner feed does not make every page load slow.
* Partner feed fails gracefully if remote request fails.
* Newsletter form includes a nonce.
* Newsletter form validates email.
* Newsletter form sanitizes submitted data.
* Output is escaped.
* `docs/SETUP.md` is complete.
* `docs/NOTES.md` explains the performance and security fixes.
* `docs/PROMOTION-PLAN-TASK.md` clearly explains production promotion, database handling, secrets, and rollback.
* Git history is clean and understandable.

---

# Suggested Commit History

A good commit history:

```text
Document local setup steps
Complete case study CPT and shortcode
Cache partner feed to improve frontend performance
Secure newsletter signup form handling
Add production promotion plan
```

Optional bonus commit:

```text
Add case study REST endpoint and widget
```

---

# Interview Walkthrough Notes

Use this explanation if shortlisted:

```text
I prioritized the required production risks first: setup reproducibility, completing the CPT, frontend performance, and newsletter form security.

For the Case Study output, I chose a shortcode because it is reliable, fast to implement, works without a build step, and fits the seeded landing page.

For the partner feed, I found that the frontend render path depended on a remote request. I added transient caching, a short timeout, response validation, and graceful failure handling so normal page views do not wait on the third-party service.

For the newsletter form, I fixed the CSRF risk using WordPress nonces and improved input handling with wp_unslash(), sanitize_email(), is_email(), and escaped output.

For the promotion plan, I treated production data and secrets as protected assets. I would deploy code from staging to production, but I would not overwrite the production database with staging. Any database changes should be controlled, backed up, tested, and reversible.
```

---

# Priorities If Time Runs Out

If time is limited, complete in this order:

1. Setup documentation
2. Case Study CPT and shortcode
3. Partner feed caching
4. Newsletter nonce, sanitization, and validation
5. Promotion plan
6. REST endpoint bonus
7. React/Vue widget bonus

A strong required submission is better than an incomplete bonus.
