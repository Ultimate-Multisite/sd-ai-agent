# t169 — Investigate `@wordpress/core-abilities` not populating `wp.data` store

## Session origin

Filed 2026-04-08 during the #822 / t166 browser smoke test. The
observation was unexpected and didn't block the fix, but it's a
loose end worth chasing before WP 7.0 final ships.

## What

On WP 7.0-RC2 running locally at `http://wordpress.local:8080`,
the `@wordpress/data` store mirror for abilities appears to be
empty even though the script-module API has everything:

```js
// In the browser on /wp-admin/index.php, after our registration
// Promises have resolved:

await wp.abilities.getAbilities()
// → 62 abilities (PHP server abilities + our 2 client abilities)

wp.data.select('core/abilities').getAbilities()
// → 0 abilities (!!)
```

Per the WP 7.0 Abilities API dev note
(https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/):

> `@wordpress/core-abilities` is the WordPress integration layer.
> When loaded, it automatically fetches all abilities and categories
> registered on the server via the REST API and registers them in
> the `@wordpress/abilities` store with appropriate callbacks.
> WordPress core enqueues `@wordpress/core-abilities` on all admin
> pages, so server abilities are available by default in the admin.

The dev note also says the abilities store integrates with
`@wordpress/data` for reactive React queries:

```js
import { store as abilitiesStore } from '@wordpress/abilities';
const abilities = useSelect(
  ( select ) => select( abilitiesStore ).getAbilities(),
  []
);
```

Both of these should be working on a stock WP 7.0 admin page, but
neither is on our test install. Our t166 fix worked around this by
reading via `wp.abilities.getAbilities()` directly — this task is
the follow-up to figure out WHY the `wp.data` path is empty.

## Why

1. **We're working around something without understanding it.** If
   the root cause is a local misconfiguration, t166's workaround is
   fine but the workaround is inelegant. If the root cause is a core
   RC2 bug, we should file it upstream before 7.0 final.
2. **React integration is blocked.** Any future code that wants to
   reactively query client abilities from a React component (per the
   dev note's documented React pattern) can't, because the Redux
   store mirror is empty.
3. **Other plugins on the site may be impacted the same way**, and
   if so it's a class of breakage worth reporting.

## How

### Hypotheses to test (in order)

1. **`@wordpress/core-abilities` is not actually enqueued on this
   install.** The dev note says core enqueues it on all admin pages,
   but something (another plugin, a `remove_action`, a script module
   registry bug) may be blocking it.
   - Check: in browser devtools on a dashboard load, look for a
     script-module tag with `src*="core-abilities"` in `<head>`. If
     it's missing, that's the bug.
   - Also check: `wp.data.select('core/abilities').getIsResolving('getAbilities')`
     — if it's resolving, the REST fetch is in flight; if it's never
     been called, `@wordpress/core-abilities` never kicked off.

2. **`@wordpress/core-abilities` IS enqueued but its REST fetch is
   failing silently.** Check the Network panel for a request to
   `/wp-abilities/v1/`. If it returns 404 or 401, core-abilities is
   bailing without populating the store.
   - Check the REST namespace is registered: `curl -s
     "http://wordpress.local:8080/wp-json/wp-abilities/v1/"`
     should return a JSON list of abilities.

3. **The `@wordpress/abilities` store is being overwritten by
   another plugin.** Some sites register their own redux store under
   `core/abilities` (unlikely but possible). Check
   `wp.data.select('core/abilities') === wp.abilities.store` — if
   the two references differ, there's store identity confusion.

4. **RC2 regression.** Compare behaviour against:
   - a fresh WP 7.0-RC2 download with no other plugins active
     (isolate our site's plugin soup)
   - WP trunk if there's a newer dev build
   - the WordPress-develop test harness used in CI

5. **Our plugin is registering before the store is ready.** Our code
   registers during `admin_enqueue_scripts` via script modules. If
   `@wordpress/core-abilities` is enqueued AFTER our registration
   code runs and overwrites the store, our abilities could be in the
   `wp.abilities.store` instance but the data layer reads a different
   one. (Unlikely but a known pattern.)

### Data to collect

Run a focused browser probe on `/wp-admin/index.php` and collect:

```js
// Paste into devtools console on the dashboard:
const probe = {
  wpAbilitiesKeys: Object.keys(window.wp.abilities || {}),
  scriptModuleTags: Array.from(document.querySelectorAll('script[type="module"]')).map(s => s.src),
  importMapTags: Array.from(document.querySelectorAll('script[type="importmap"]')).map(s => s.textContent),
  wpAbilitiesStoreRef: window.wp.abilities?.store ? 'exists' : 'missing',
  coreAbilitiesStoreViaData: !!window.wp.data?.select?.('core/abilities'),
  storeRefMatch: window.wp.abilities?.store === window.wp.data?.stores?.['core/abilities'],
  dataGetAbilities: window.wp.data?.select?.('core/abilities')?.getAbilities?.() ?? 'no selector',
  directGetAbilities: await window.wp.abilities?.getAbilities?.(),
  isResolvingDirect: window.wp.data?.select?.('core/abilities')?.getIsResolving?.('getAbilities'),
  restPing: await fetch('/wp-json/wp-abilities/v1/').then(r => ({ status: r.status, body: r.json() })).catch(e => String(e)),
};
console.log(JSON.stringify(probe, null, 2));
```

Paste the output into this task's follow-up comment on the GitHub
issue for t169. That dataset will determine which hypothesis is
correct.

### Expected deliverables

1. A **root-cause statement** posted as a comment on this task's
   issue: which of the hypotheses above matched, with evidence.
2. One of:
   - **Code fix PR** if the issue is in our plugin (e.g. enqueue
     order, store identity confusion).
   - **Enqueue addition PR** if we just need to explicitly enqueue
     `@wordpress/core-abilities` ourselves.
   - **Upstream bug report link** filed at
     https://github.com/WordPress/ai or
     https://core.trac.wordpress.org if it's a core RC2 issue.
   - **"No action needed"** comment if the workaround in t166 is
     confirmed correct for some principled reason we document here.
3. Update `src/abilities/registry.js` comments (currently explain the
   workaround) with the confirmed root cause and a link to the
   upstream fix (if any), so future readers know whether to remove
   the workaround.

## Acceptance criteria

1. Root cause identified with browser-probe evidence.
2. One of the three outcomes above landed (fix / bug report / noop
   with documented rationale).
3. If a fix is landed, a browser smoke test confirms
   `wp.data.select('core/abilities').getAbilities()` now matches
   `wp.abilities.getAbilities()` in length.
4. If no fix is landed and the workaround stays, the `registry.js`
   comment is updated with a link to the upstream tracking issue
   and a deletion-reminder for the workaround.

## Verification

```bash
# Browser probe (paste into devtools console on /wp-admin/index.php):
# — see "Data to collect" section above —

# After any fix:
node /tmp/t165-smoke.mjs  # or the Playwright spec from t168
# Expect coreDataAbilitiesCount === storeApiCount (currently 0 vs 62)
```

## Context

- Observed in: browser smoke test for PR #822 / t166, on a dev
  install at http://wordpress.local:8080 running WP 7.0-RC2
- Workaround in: `src/abilities/registry.js#snapshotDescriptors`
  (reads via `wp.abilities.getAbilities()` directly)
- Related task: t168 (Playwright spec) — would catch a regression
  of this if `@wordpress/core-abilities` starts populating the
  store and we want to assert on it
- Non-blocking: our feature works via the workaround; this is
  engineering hygiene, not a user-visible bug

## Size estimate

~2h for the investigation + root-cause write-up + decision. Fix or
bug report time depends on which hypothesis matches — add another
1-3h if a code fix is needed.
