<!-- reviewed-by-orchestrator: TUMBLEWEED -->
# Staging to Production Promotion Plan

## 1. Code promotion

I would treat code promotion as an artifact promotion problem, not a "whatever is on the branch right now" problem.

Recommended flow:

1. Work happens on short-lived feature branches.
2. A pull request is merged into the staging branch or main integration branch.
3. Staging deploys from that exact merge commit.
4. QA and stakeholder review happen on staging against that exact commit.
5. Production deploy uses the same tested commit, ideally via a Git tag or release artifact.

That matters because it guarantees the code that reaches production is the same code that was tested on staging. I would not rebuild or manually edit files between staging and production.

For this plugin specifically:

- version the release in Git with an annotated tag
- deploy the same tagged commit to production
- keep the plugin directory under source control
- keep uploads and environment-specific config out of the release artifact

## 2. Database handling

This is the most important part. I would not overwrite the production database with staging.

Production is the source of truth for:

- live content edits
- newsletter signups
- users
- options and configuration that may differ by environment
- any content created since the last staging refresh

The safe default is:

1. Promote code only.
2. Apply narrowly scoped database changes only when necessary.
3. Move content selectively, never by replacing the whole production database.

### How I would handle this plugin's database needs

This plugin adds:

- a custom post type for Case Studies
- post meta for the headline metric
- a custom table for newsletter signups

That means promotion should separate these concerns:

- Code: deploy from the tested Git commit.
- Schema: use versioned, idempotent upgrade logic in the plugin activation/update path if schema changes are needed.
- Content: migrate only the specific Case Studies or settings that must move.
- Live data: never overwrite `acp_signups` in production from staging.

### URL and domain differences

WordPress stores URLs in many places, including serialized option/meta data. Because of that:

- do not use raw SQL search/replace on WordPress databases
- use WP-CLI `search-replace` or another WordPress-aware migration tool that understands serialized data

If content needs to move from staging to production:

1. Export only the relevant content.
2. Import it into production.
3. Run a serialized-data-safe domain replacement if needed.
4. Re-verify links, media, and canonical URLs.

### Serialized data

WordPress serializes values in places like:

- `wp_options`
- post meta
- term meta
- widget and block-related settings

A plain text dump-and-replace can corrupt serialized lengths and break the site. That is why any search/replace must use a tool that rewrites serialized payloads correctly.

### Protecting live data

I would explicitly protect:

- the `acp_signups` table
- production-only users
- new posts/pages created after the last staging refresh
- option values that are production-specific

For this plugin, I would not copy newsletter submissions from staging to production and I would not restore a staging copy over live signups.

### Practical migration approach

If there are new Case Studies to promote:

1. Export only the relevant Case Study posts from staging.
2. Include the associated headline metric post meta.
3. Include any referenced media if needed.
4. Import into production with WordPress import tooling, WP-CLI, or a one-off migration script.
5. Verify:
   - post status
   - slugs/permalinks
   - featured images
   - headline metric meta
   - frontend rendering

If a database schema change is required:

1. Test it on a recent copy of production in staging first.
2. Make the migration idempotent.
3. Back up production immediately before deployment.
4. Run the migration once.
5. Verify application behavior and logs before declaring the deploy complete.

## 3. Secrets and per-environment config

Secrets must not live in the repo.

That includes:

- database credentials
- API keys
- SMTP credentials
- WordPress salts
- any external service token

I would manage them through environment-specific configuration:

- environment variables
- hosting platform secret storage
- server-managed `wp-config.php` values outside version control

Repository rules:

- commit `.env.example` with placeholders only
- ignore real `.env` files
- never commit production `wp-config.php`
- keep staging and production credentials separate

For WordPress specifically, I want the application code to read config from environment variables or server-level configuration so:

- local uses local credentials
- staging uses staging credentials
- production uses production credentials

No deploy should require hand-editing plugin code to switch environments.

## 4. Safety net

### Before production deploy

I would require this checklist:

1. Confirm the exact Git tag/commit being released.
2. Confirm staging sign-off against that exact build.
3. Confirm a fresh production database backup exists.
4. Confirm a fresh production file backup exists.
5. Confirm rollback instructions are written and owned.
6. Confirm secrets/config for production are already in place.
7. Confirm a low-risk deploy window and stakeholder notification.
8. Confirm logs and monitoring access are available during the deploy.

### Deploy steps

1. Put the approved release artifact or tagged commit into production.
2. Run any required database migration or plugin upgrade routine.
3. Clear opcode/object/page/CDN caches as needed.
4. Smoke test:
   - homepage loads
   - partner feed renders
   - newsletter form renders and accepts a valid submission
   - invalid newsletter emails are rejected
   - case studies render on the frontend
   - case studies are editable in wp-admin
5. Check PHP logs and web server logs for new errors.

### Rollback plan

If production fails after deploy:

1. Roll code back to the previous known-good tag/release.
2. Clear caches again.
3. Re-run the smoke tests.
4. Review logs to confirm the site is stable.
5. Notify stakeholders.

I would only restore the database if the deployment included a destructive or broken database change and code rollback alone cannot recover service.

If a database rollback is required:

1. Confirm what data would be lost.
2. Get explicit approval.
3. Restore from the pre-deploy backup.
4. Re-test immediately.

Database rollback is last resort because it can discard real production activity such as new signups or content changes.
