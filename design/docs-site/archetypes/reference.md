<!--
ARCHETYPE 3 — REFERENCE. 10 of the 34 pages in the map.

Use when the page is arrived at from search or a link and never read top to bottom.
Glossary, command reference, configuration, what TOPS scans, release notes.

The reader already knows what they are looking for. Your only job is to let them
find it and leave. Narrative is actively harmful here — it is padding between the
reader and the row they came for.

THE RULE: sort by how it is looked up, not by how it is built. Configuration sorts
by variable name because that is what someone has in front of them in a .env file,
not by subsystem.
-->

# Configuration reference

Every environment variable TOPS reads, what it does, and what happens if you leave it unset.
Set these in `.env` in your install directory, then restart with `docker compose up -d`.

<!-- One line of scope, one line of how to use it. Then stop. No history, no rationale,
     no "in this guide we will". -->

## Jump to

[Application](#application) · [Database](#database) · [AWS](#aws) · [Workers](#workers) · [Backups](#backups)

<!-- Jump links once the page is longer than a screen or two. Cheap, and it is the
     difference between a reference page and a wall.

     If the page is long enough to need filtering rather than jumping, it is probably
     two pages. -->

## Application

| Variable | Default | What it does |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8080` | The URL TOPS builds links with. Set it to the address people actually browse to, or emailed links break |
| `APP_KEY` | generated at install | Laravel's encryption key. Rotate with `php artisan key:generate`; everyone is logged out |
| `APP_DEBUG` | `true` | Leave `false` anywhere reachable by someone you do not trust — debug pages expose configuration |

## AWS

| Variable | Default | What it does |
| --- | --- | --- |
| `TOPS_DEPLOYMENT_REGION` | *unset* | The region the parent stacks were deployed into. Set by the AWS install step |
| `TOPS_SQS_ARN` | *unset* | The account-linking queue. **While unset, account linking is switched off** and the worker pool does not start |
| `TOPS_WORKER_PROCESSES` | `5` | Region workers in parallel. Each holds ~60–120 MB and one MySQL connection |

<!-- Three columns is usually right: the thing, its default, what it does. A fourth
     column is a signal that some rows want prose instead.

     Write the "what it does" from the consequence side. "The account-linking queue" is
     half an answer; "while unset, account linking is switched off" is the whole one.

     Mark unset defaults as *unset*, not blank — a blank cell reads as an omission.

     Bold the consequence that surprises people. One or two per table, not every row. -->

## Related

- [Upgrading and rolling back](upgrading-and-rolling-back.md)
- [Workers, queues and scan speed](workers-queues-and-scan-speed.md) — before raising `TOPS_WORKER_PROCESSES`

<!-- MAINTENANCE NOTE, and the reason reference pages rot faster than any other kind:

     A reference page is a second copy of something the code already states. It goes
     stale silently, and a stale reference is worse than no reference because it is
     trusted.

     So: generate it if you can, and if you cannot, name the file it mirrors so the next
     person knows what to diff against. Never restate a default here without checking it
     against the source in the same sitting. -->
