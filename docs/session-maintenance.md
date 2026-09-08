# Backup-safe session maintenance

SD AI Agent keeps each conversation in one session row. Normal session writes are
non-destructive, but the plugin reports a maintenance threshold after a row reaches
either 8 MiB of combined `messages`, `tool_calls`, and `paused_state` data or
10,000 messages. Sites can override those thresholds with the
`sd_ai_agent_session_storage_maintenance_bytes` and
`sd_ai_agent_session_storage_maintenance_messages` filters.

## Inspect candidates

Administrators can call the `GET /sessions/oversized` route in the
`sd-ai-agent/v1` REST namespace. It returns only these safe fields for each
candidate:

- session ID and status;
- message count;
- byte counts for messages, tool calls, paused state, and the combined payload.

The inspection response does not include conversation titles, messages, tool
calls, paused state, or user IDs. Optional `min_bytes`, `min_messages`, and
`limit` parameters narrow the inspection without reading conversation content.
Listing is administrative discovery only: export, compact, archive, and delete
operations retain their normal ownership and shared-session permission checks.

## Safe maintenance sequence

1. Record the candidate ID and byte count from `GET /sessions/oversized`.
2. Export the source with the existing `GET /sessions/{id}/export` route and
   store the result outside the WordPress database. Validate the export by
   importing it into a disposable session or restoring it on a non-production
   site before changing the source row.
3. Create a bounded continuation with `POST /sessions/{id}/compact`. The source
   transcript is never overwritten: the route creates a separate session with a
   deterministic compact context. A failed request is safe to retry because the
   source row remains unchanged.
4. Archive the source when it should remain visible in the session UI but is no
   longer used for new work. Archiving and trashing retain the row and therefore
   do **not** reduce database-export size.
5. Only after the independent export and compact continuation are verified, use
   the explicit `DELETE /sessions/{id}` operation to permanently remove the
   source row. This is the only session action that removes its payload from a
   database export.

Compaction rejects active jobs and sessions with paused continuations with HTTP
409. Finish or resume those operations before making a continuation; maintenance
never mutates an active or recoverable session in place.

## Large-row behavior

Maintenance compaction reads the saved `messages` JSON in 64 KiB database slices
and builds a bounded context as it goes. It does not decode or serialize multiple
full copies of the original row. Tool call/result pairs are retained together or
omitted together, preserving their ordering in the continuation context. A single
pathological message larger than 1 MiB is represented by a bounded omission marker
instead of being materialized in memory.

## Backup and export recovery check

After explicitly removing a verified source row, make a fresh database backup and
validate it before plugin maintenance:

```bash
mariadb-dump --single-transaction --quick --routines --events --databases <database> > wordpress-pre-maintenance.sql
mariadb <restored-database> < wordpress-pre-maintenance.sql
mariadb <restored-database> -e "SELECT COUNT(*) FROM <table-prefix>sd_ai_agent_sessions;"
```

The dump command must exit successfully, the restore command must exit
successfully, and the restored session count must match the expected post-removal
count. Keep the independently exported source and the validated SQL backup until
the compact continuation has been reviewed. Do not rely on larger MariaDB packet
or timeout settings as the repair path; those settings do not make an oversized
historical row operationally bounded.
