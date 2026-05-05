# Supervisor dashboard freshness incident notes

## 2026-05-05: issue #1254 / dashboard #1115

Issue #1254 reported that the supervisor dashboard issue #1115 for this repo had
not refreshed since `2026-04-30T18:34:09Z`.

Evidence gathered during remediation:

- `gh api repos/Ultimate-Multisite/superdav-ai-agent/issues/1115` initially
  returned `updated_at: 2026-04-30T18:34:21Z` with a `last_refresh:` marker.
- `~/.aidevops/logs/stats.log` showed repeated
  `HEALTH-DASHBOARD-FAIL exit=1` entries beginning on 2026-04-21. The adjacent
  error was `stats-health-dashboard.sh: line 412: File: unbound variable`, so
  the wrapper was exiting under `set -euo pipefail` before completing all health
  issue updates.
- A later manual run of `~/.aidevops/agents/scripts/stats-wrapper.sh` refreshed
  #1115. The dashboard then reported `last_refresh: 2026-05-05T03:56:20Z` and
  `updated_at: 2026-05-05T03:56:46Z`.

Operational follow-up pattern:

1. Check the dashboard issue directly with `gh api repos/<owner>/<repo>/issues/<n>`
   and verify both `updated_at` and the `last_refresh:` marker.
2. Inspect `~/.aidevops/logs/stats.log` for `HEALTH-DASHBOARD-FAIL` before
   assuming a missing scheduler.
3. Run `~/.aidevops/agents/scripts/stats-wrapper.sh` manually to confirm the
   current wrapper can refresh the issue.
