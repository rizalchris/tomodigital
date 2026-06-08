# Notes

Use this file as your running log. We read it as closely as the code.

## Status

Complete for the required scope, with the bonus REST/widget slice also implemented.

## Task 2: Case Study feature
Completed the `acp_case_study` custom post type as a first-class WordPress content type.

Changes:
- Public visibility, admin UI, archive support, and REST support.
- Title, editor, excerpt, featured image, and revisions support.
- Clearer labels and a portfolio-style menu icon.
- Headline metric post meta exposed to REST.
- Admin meta box with nonce verification, autosave protection, capability checks, and sanitization.
- `[acp_case_studies]` for published case studies with title, metric, excerpt, and permalink.

I chose a shortcode instead of a block because it was the fastest reliable path in this repo:
- no JS build pipeline
- works in classic and block content
- fits the timebox
- easy to drop into the seeded Home page

## Task 3: Performance
The partner feed did expensive work during frontend rendering on every request.

What was wrong:
- `wp_remote_get()` ran inside the render loop for each partner domain.
- A separate WordPress query ran per partner to count mentions.

How I confirmed it:
- The code in `includes/class-acp-market-widget.php` clearly placed remote I/O inside the render path.
- Runtime verification in Docker was blocked earlier by transient Docker Hub `504 Gateway Timeout` failures.

What changed:
- Added transient caching around authority scores.
- Added a short HTTP timeout and response validation.
- Added cached aggregate mention counts.
- Added cache invalidation when case studies are saved or deleted.

Result:
- Repeated frontend renders should now read mostly from transients instead of remote I/O and repeated search queries.

## Task 4: Security
The newsletter form had three problems: no nonce, unsafe SQL handling, and unescaped reflected output.

What was wrong:
- The form accepted submissions without a nonce, which exposed it to CSRF.
- It used raw `$_POST` values directly.
- It interpolated input into SQL with `$wpdb->query()`.
- It echoed the `ref` query parameter without escaping.

Why it mattered:
- Another site could potentially submit the form on a visitor's behalf.
- Raw SQL interpolation created a direct SQL injection risk.
- Unescaped output created an XSS risk.
- Malformed input could poison the signups table.

What changed:
- Added `wp_nonce_field()` and `wp_verify_nonce()`.
- Used `wp_unslash()` before reading input.
- Sanitized the name and email.
- Validated the email address with `is_email()`.
- Replaced raw SQL with `$wpdb->insert()`.
- Escaped all reflected output.
- Swapped the footer echo for request-scoped notices in the shortcode itself.

## Task 6: Headless widget (bonus, optional)
Implemented a public read endpoint at `/wp-json/acp/v1/case-studies`.

The endpoint returns `items`, `page`, `per_page`, `total`, and `total_pages`, with `id`, `title`, `link`, `metric`, and `excerpt` for each item.

I added a small React widget in `assets/widget/` that mounts on `[acp_case_studies_widget]` and fetches the endpoint client-side.

Trade-off:
- Lightweight on purpose.
- Uses WordPress's bundled `wp.element`.
- Only fetches the first page of results.

## Anything else
- Fixed the obvious CSS typo in the partner feed numeric column (`text-aling` -> `text-align`).
- Updated the seed script so fresh installs render `[acp_case_studies]` on the Home page.
- If I had more time, I would add a minimal test strategy around the newsletter handler and case study meta save flow.
- I would also consider moving the newsletter form to a PRG flow (`POST` -> redirect -> notice) to avoid duplicate submissions on refresh.
