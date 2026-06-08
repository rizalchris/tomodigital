# Notes

Use this file as your running log. We read it as closely as the code.

## Task 2: Case Study feature
- I completed the `acp_case_study` custom post type so it behaves like a normal WordPress content type:
- Enabled public visibility, admin UI, archive support, and REST support.
- Added support for title, editor, excerpt, featured image, and revisions.
- Added clearer labels and a portfolio-style menu icon.
- Registered the headline metric as post meta and exposed it to REST.
- Added an admin meta box for the headline metric with nonce verification, autosave protection, capability checks, and sanitization.
- Implemented `[acp_case_studies]` to render recent published case studies with title, metric, excerpt, and permalink.

I chose a shortcode instead of a block because it is the fastest reliable path in this repo:
- no JS build pipeline is required
- it works in both classic and block content
- it fits the timebox better than introducing editor tooling
- it can be dropped directly into the seeded landing page or any future page content

## Task 3: Performance
- What was wrong:
- The partner feed did expensive work during frontend rendering on every request.
- It called the remote authority API once per partner domain during page render.
- It also ran a separate WordPress query per partner to count case study mentions.

- How I confirmed it:
- The issue is clear from `includes/class-acp-market-widget.php`: the remote `wp_remote_get()` call lived inside the render loop, so every page view depended on multiple network round-trips.
- The same loop also triggered repeated content lookups for mention counts.
- I attempted to verify the homepage behavior in the provided Docker stack, but runtime verification was blocked in this session by repeated Docker Hub `504 Gateway Timeout` image pull failures while starting the containers.

- What my fix changes:
- Added transient caching around authority scores so repeated page loads do not re-fetch the remote API for every partner.
- Added a short HTTP timeout and response validation.
- Added cached aggregate mention counts so the render path no longer performs a separate query per domain.
- Added cache invalidation when case studies are saved or deleted so the mention counts stay fresh.
- The result is that repeated frontend renders should now read mostly from transients instead of remote I/O and repeated search queries.

## Task 4: Security
- What I found:
- The newsletter form accepted submissions without a nonce check, which exposed it to CSRF.
- It used raw `$_POST` values directly.
- It interpolated user input into SQL with `$wpdb->query()` string construction.
- It reflected user-controlled values back into the page without output escaping.
- It also echoed the `ref` query parameter directly in the rendered form.

- Why it was dangerous:
- Missing nonce verification meant another site could potentially submit the form on a visitor's behalf.
- Raw SQL interpolation created a direct SQL injection risk.
- Unescaped reflected output created an XSS risk.
- Even without a successful exploit, the form would accept malformed data and poison the signups table.

- How I fixed it:
- Added `wp_nonce_field()` to the form and `wp_verify_nonce()` in the submission handler.
- Used `wp_unslash()` before reading input.
- Sanitized the name with `sanitize_text_field()` and the email with `sanitize_email()`.
- Validated email with `is_email()`.
- Replaced raw SQL with `$wpdb->insert()` and explicit format strings.
- Escaped all reflected output in the rendered form and in submission notices.
- Replaced the footer echo pattern with request-scoped success/error notices rendered by the shortcode itself.

## Task 6: Headless widget (bonus, optional)
- I implemented a public read endpoint at `/wp-json/acp/v1/case-studies`.
- The endpoint returns `items`, `page`, `per_page`, `total`, and `total_pages`, and each item includes `id`, `title`, `link`, `metric`, and `excerpt`.
- I added a small React widget in `assets/widget/` that mounts on `[acp_case_studies_widget]` and fetches the endpoint client-side.
- I kept the widget lightweight by using WordPress's bundled `wp.element` package rather than adding a build pipeline.
- Trade-off: the widget is intentionally simple and only fetches the first page of results for the bonus slice. That keeps the implementation small and avoids introducing extra build tooling.

## Anything else
- I fixed the obvious CSS typo in the partner feed numeric column (`text-aling` -> `text-align`) while touching the frontend assets.
- If I had more time, I would add at least a minimal test strategy around the newsletter handler and case study meta save flow, probably with WordPress integration tests or WP-CLI driven smoke checks.
- I would also consider moving the newsletter form to a PRG flow (`POST` -> redirect -> notice) to avoid duplicate submissions on refresh.
- Runtime verification remains the main missing piece because the local Docker stack could not complete image pulls in this session.
