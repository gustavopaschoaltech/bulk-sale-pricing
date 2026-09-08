# Bulk Percentage Pricing — Agent Instructions

## Project purpose and scope

- Build a portfolio-quality WordPress and WooCommerce plugin that applies percentage-based sale prices in bulk to products selected by taxonomy rules.
- Typical rules include product categories and product tags, such as `Toys` at 10%, `Gift Products` at 20%, and `Clearance` at 30%.
- This plugin changes WooCommerce product sale prices. It does not provide dynamic cart or checkout discounts.
- Keep the scope limited to percentage-based bulk sale pricing by taxonomy targeting. Do not add coupons, payment features, subscriptions, analytics, CRM features, external SaaS integrations, or unrelated functionality unless an approved issue explicitly requires them.

## WordPress and WooCommerce engineering

- Follow current WordPress Coding Standards and WooCommerce extension-development best practices.
- Use only public WordPress and WooCommerce APIs. Never use WooCommerce internal or `@internal` APIs.
- Prefer WordPress and WooCommerce hooks and APIs over custom implementations, and avoid custom database tables unless clearly necessary.
- Keep the design simple, readable, maintainable, and testable. Do not introduce unnecessary files, classes, abstractions, or dependencies.
- Prevent direct access to executable PHP files.
- Use WordPress internationalization functions for all user-facing text, with a consistent project text domain once established.
- English is the project language for identifiers, comments, UI strings, documentation, commit messages, issues, and pull requests.

## Security

- Validate and sanitize input as early as possible; escape output at render time.
- Require appropriate capability checks and nonces for every privileged state-changing request.
- Do not trust request data directly: access specific keys, unslash it, validate it, and sanitize according to its expected type.
- Use WordPress and WooCommerce APIs for persistence. If direct SQL is ever justified, use prepared queries.

## Compatibility

- Develop with current supported WordPress, WooCommerce, and PHP versions in mind.
- Consider High-Performance Order Storage (HPOS) and relevant current WooCommerce functionality whenever changes could affect them.
- Do not state a compatibility claim without documented, successful testing for that exact claim.
- Reassess compatibility and update documented test results when changes can affect supported WordPress, WooCommerce, PHP, or HPOS behavior.

## Workflow and change discipline

- Follow: Issue → dedicated branch → focused implementation → testing → commit → pull request. Treat `main` as the stable branch.
- Before implementation, review the relevant issue and these project requirements. Ask for clarification when a material requirement is ambiguous, contradictory, or incomplete.
- Preserve working behavior unless the issue explicitly changes it. Do not rewrite working code unnecessarily.
- Keep commits and pull requests small, focused, and reviewable; never mix unrelated changes.
- Pull requests must state what changed and how it was tested. Explain significant architectural decisions when helpful.

## Testing and documentation

- Test meaningful changes in a functioning WordPress and WooCommerce environment before calling them complete.
- Test plugin activation, deactivation, normal behavior, relevant edge cases, compatibility scenarios, and HPOS where applicable.
- Use `WP_DEBUG` and relevant diagnostics when useful.
- Never claim a feature, version, or compatibility target was tested unless it actually was. Record significant testing and compatibility results in project documentation when appropriate.
- Keep the README, changelog, and other documentation accurate. Document user-facing behavior, requirements, configuration, and only verified compatibility information.

## WordPress.org readiness

- Design for WordPress Plugin Directory compliance from the outset.
- Use a GPL-compatible license and maintain accurate plugin headers, metadata, and documentation.
- Keep the plugin complete and functional; avoid unauthorized tracking, dashboard hijacking, spam, unrelated promotions, trialware, and other prohibited behavior.
- Keep release documentation and changelogs accurate, and perform a directory-guideline review before submission.

## Current repository status

- No plugin implementation exists yet. Do not create plugin source or add functionality until a specific issue or request authorizes it.
