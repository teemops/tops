# Database backups and recovery

The `backup` container runs three backup tiers on a schedule and the repo ships
tooling to restore from any of them. Everything is driven from `./backup.sh` at
the repo root.

```bash
./backup.sh              # usage
./backup.sh status       # is it working?
./backup.sh test         # prove recovery works, against a throwaway database
```

---

## The three tiers

| Tier | Schedule | Mechanism | Recovers to |
|------|----------|-----------|-------------|
| **Full** | daily 17:00 local | `xtrabackup --backup` | the instant the backup started |
| **Differential** | hourly at :10 | `xtrabackup --backup --incremental-basedir=<latest full>` | the instant that hour's backup started |
| **Transactional** | every 15 min | `FLUSH BINARY LOGS` + archive the closed logs | **any second** covered by the archive |

Each tier is a fallback for the one above it. The full backup alone gives you a
24-hour worst-case data loss window; the differentials cut that to an hour; the
binary log archive cuts it to 15 minutes, and lets you land on an exact second
rather than on a backup boundary.

### Differential, not incremental

Every hourly backup is based on **the most recent full**, never on the previous
hourly. That is a deliberate trade:

- An incremental chain (`full → h1 → h2 → h3 …`) produces the smallest possible
  hourly backups, but recovery to hour 3 requires hours 1, 2 and 3 to all be
  intact. One corrupt or missing hourly destroys every hour after it.
- A differential (`full → h1`, `full → h2`, `full → h3`) grows through the day,
  but recovery applies exactly **one** delta on top of the full. Any single
  hourly can be lost without affecting the others, and restores are a fixed two
  steps regardless of how far into the day you are.

For a database this size, the storage difference is not worth the fragility.

### Why XtraBackup rather than `mysqldump`

`mysqldump` cannot express the middle tier at all — there is no such thing as a
differential logical dump, so an hourly tier built on it would just be a second
copy of the binary log archive, collapsing three tiers into two.
[Percona XtraBackup](https://docs.percona.com/percona-xtrabackup/8.0/) does
physical, non-blocking, page-level deltas, and records the exact binary log
coordinate each backup corresponds to (`xtrabackup_binlog_info`) — which is what
makes it possible to hand off cleanly from a physical restore to a binary log
replay in scenario 3 below.

---

## What was evaluated

The brief asked for existing containerised options before building anything.

| Option | Outcome |
|--------|---------|
| [`databacker/mysql-backup`](https://github.com/databacker/mysql-backup), [`tiredofit/db-backup`](https://github.com/tiredofit/docker-db-backup), [`PinCarBR/mysql-autobackup`](https://github.com/PinCarBR/mysql-autobackup) | Scheduled `mysqldump` with rotation and remote targets. Mature and easy, but full logical dumps only — no differential tier, and no binary log archiving. Two of the three requirements unmet. |
| [`Aiven-Open/myhoard`](https://github.com/Aiven-Open/myhoard) | The closest match by capability: XtraBackup full + incremental, continuous binlog streaming, real PITR. Rejected on fit — it is built for MySQL 5.7/8.0 fleets with object storage and a service API, expects to manage the server as a replica, and its operational surface is larger than the rest of this stack combined. |
| [`mikemix/percona-xtrabackup-cron`](https://github.com/mikemix/percona-xtrabackup-cron) | XtraBackup + cron in a container, which is the right shape. No binlog archiving, no restore tooling, and unmaintained. |
| **`percona/percona-xtrabackup:8.0` + supercronic** (chosen) | The official Percona image already ships `xtrabackup`, `mysql`, `mysqlbinlog`, `qpress`, `zstd` and `rsync` at mutually-compatible versions. Adding a scheduler and ~400 lines of shell is less code than adapting any of the above, and the restore path stays legible. |

So: open-source packages plus cron, but with a supported base image doing the
heavy lifting rather than assembling XtraBackup from a distro repo.

`supercronic` is used instead of `cron`/`cronie` because it runs in the
foreground, logs each job's output to the container log where
`docker compose logs backup` can see it, and does not strip the container's
environment the way `crond` does.

---

## MySQL engine settings

Point-in-time recovery is only as good as the binary log, so the scheduler
depends on server-side configuration. It lives in
[`docker/mysql/conf.d/10-teemops-backup.cnf`](../docker/mysql/conf.d/10-teemops-backup.cnf),
mounted read-only into the `mysql` service.

| Setting | Value | Why |
|---------|-------|-----|
| `log_bin` | `binlog` | The transaction stream PITR replays. On by default in 8.0; pinned explicitly so a future default change can't silently break archiving. |
| `binlog_format` | `ROW` | The only format that replays deterministically. `STATEMENT`/`MIXED` can produce different results on replay for `NOW()`, `UUID()`, or unsafe UDFs — silent corruption of a restore. |
| `binlog_row_image` | `FULL` | Logs complete before/after images, so replay doesn't depend on target rows already matching. |
| `binlog_expire_logs_seconds` | `1209600` (14d) | Must exceed full-backup retention. A full backup with no binlogs covering the time since it was taken can only be restored to its own instant. |
| `max_binlog_size` | `64M` | Caps a single busy interval; archiving forces a rotation every 15 min anyway. |
| `sync_binlog` | `1` | fsync the binlog on commit — an acknowledged transaction survives a host crash and reaches the archive. |
| `innodb_flush_log_at_trx_commit` | `1` | Same guarantee for the redo log. |
| `innodb_file_per_table` | `ON` | Required for per-table delta tracking in differentials. |
| `server_id` | `1` | Required whenever binary logging is enabled. |

The last three are already MySQL 8.0 defaults; they are stated explicitly
because the backup guarantees depend on them. **Relaxing `sync_binlog` or
`innodb_flush_log_at_trx_commit` trades real recoverability for write
throughput** — committed transactions can be lost on a host crash before they
ever reach a binary log.

GTIDs are deliberately left off. They would make replication topologies easier
but add a failure mode here: replaying binary logs that contain anonymous
transactions into a GTID-enabled server fails outright, which is a trap during
recovery. XtraBackup records file-and-position coordinates, which is all the
restore path needs.

Applying the config requires recreating the container (it is a mount, not a
runtime variable):

```bash
docker compose up -d mysql
```

---

## Storage layout

Backups go to `~/.tops/backups` on the host by default — set `TOPS_BACKUP_DIR`
in `.env` to move them. This is deliberately **outside the repository**: a
physical MySQL backup is a byte-for-byte copy of the database, and one careless
`git add -A` would commit it. `backups/` and `.tops/` are also in `.gitignore`
so pointing `TOPS_BACKUP_DIR` at a relative path stays safe.

```
~/.tops/backups/
├── full/
│   └── 20260728T170000/        xtrabackup full + META
├── diff/
│   └── 20260728T211000/        differential + META (records its base full)
├── binlog/
│   ├── binlog.000041 …         archived closed binary logs
│   └── binlog.index
└── .work/                      restore staging (transient)
```

Backups are uncompressed. XtraBackup can compress with `--compress=zstd`, but
every restore then needs a decompress pass, and for a database of this size the
extra step costs more in recovery complexity than it saves in disk.

The container runs as root because MySQL's datadir is `0640 mysql:mysql` and
unreadable otherwise; finished backups are chowned to `TOPS_BACKUP_UID:GID` so
the tree stays usable from the host shell without `sudo`.
`docker/scripts/prepare-build.sh` fills those in from `id -u` / `id -g`.

### Retention

`TOPS_BACKUP_FULL_RETENTION_DAYS` (7) and `TOPS_BACKUP_BINLOG_RETENTION_DAYS`
(14) drive the nightly prune, with three safety rules:

1. The newest full is never deleted, however old it is.
2. A differential is deleted when its base full is.
3. **Binary logs are never pruned past the oldest surviving full**, regardless
   of the time-based limit. Deleting them would leave that full restorable only
   to its own instant.

---

## Recovery

All three procedures stop the app, worker and MySQL, rewrite the datadir, and
bring everything back up. Each one takes a safety full backup of the current
state first (skip with `--no-safety-backup`), because restoring the wrong
backup is an ordinary human error and there needs to be a way back.

Find what's available first:

```bash
./backup.sh list
./backup.sh window     # the time range PITR can actually target
```

### Scenario 1 — Full recovery

Back to the most recent full backup, or a named one.

```bash
./backup.sh restore full
./backup.sh restore full --full 20260728T170000
```

Data written after that backup is gone. Worst case is a little under 24 hours —
this tier is for "the database is unusable and I want a known-good state
quickly", not for precision.

### Scenario 2 — Hourly point-in-time recovery

Back to a specific hourly differential. No transaction log replay, so it is
about as fast as scenario 1.

```bash
./backup.sh list      # note a differential and the `base` full it belongs to
./backup.sh restore hourly --full 20260728T170000 --diff 20260728T210000
```

A differential can only be applied to its own base full; `restore hourly`
refuses the combination otherwise rather than producing a corrupt datadir.

### Scenario 3 — Transactional point-in-time recovery

Back to any second inside the binary log archive. This restores a physical base
and then replays transactions on top of it up to the moment you name.

```bash
# to a second, using the latest full as the base
./backup.sh restore pitr --to '2026-07-28 21:43:07'

# starting from a specific full + differential (less to replay, so faster)
./backup.sh restore pitr --full 20260728T170000 --diff 20260728T210000 \
                         --to '2026-07-28 21:43:07'
```

The classic use is undoing a bad write: pick a `--to` one second before the
damaging statement.

**Timestamps are read in `TOPS_BACKUP_TZ`**, the scheduler's timezone — not
UTC, and not necessarily your shell's. `./backup.sh status` prints the timezone
in effect.

For surgical cases where a second isn't precise enough — several statements
inside the same second, and you need to stop between them — find the exact
binary log position and use it instead:

```bash
docker compose exec backup mysqlbinlog --defaults-file=/run/tops-backup/root.cnf \
    --start-datetime='2026-07-28 21:43:00' --stop-datetime='2026-07-28 21:43:10' \
    /backups/binlog/binlog.000041 | less

./backup.sh restore pitr --stop-position 194823
```

### After any restore

The server begins a new binary log timeline. The next 15-minute archive run
detects this and moves the old archive aside to `binlog.superseded.<stamp>/`
rather than overwriting it — recovery to a point *before* the restore still
needs those files. Take a fresh full backup to start a clean chain:

```bash
./backup.sh now full
```

---

## Verifying recovery

An untested backup is a hypothesis. `./backup.sh test` stands up a disposable
MySQL instance in its own compose project, with its own volume and its own
backups directory under `/tmp`, then runs all three scenarios end to end:

```bash
./backup.sh test
```

Each scenario writes labelled markers at known times, takes the relevant
backups, then restores and asserts on the **exact** set of markers that comes
back:

| Scenario | Sequence | Expected after restore |
|----------|----------|------------------------|
| 1. Full | write A → full → write B | `A` only |
| 2. Hourly | write A → full → write B → diff → write C | `A B` |
| 3. Transactional | write A → full → write B → diff → write C → *target* → write D | `A B C` |

The absent markers are the point. Asserting only that expected rows came back
would pass even if the restore had silently replayed everything to "now";
checking that `B`/`C`/`D` are *missing* is what proves each recovery stopped
where it was told to.

Your development database is never touched. `KEEP=1 ./backup.sh test` leaves
the test stack running for inspection.

---

## Operations

```bash
./backup.sh status              # latest backups, sizes, retention, timezone
./backup.sh list                # full inventory
./backup.sh window              # recoverable point-in-time range
./backup.sh logs                # follow the scheduler
./backup.sh now full|diff|binlog|prune
```

### Changing the schedule

Cron expressions come from `.env` and are read in `TOPS_BACKUP_TZ`:

```ini
TOPS_BACKUP_FULL_CRON=0 17 * * *
TOPS_BACKUP_DIFF_CRON=10 * * * *
TOPS_BACKUP_BINLOG_CRON=*/15 * * * *
TOPS_BACKUP_PRUNE_CRON=30 3 * * *
```

`docker compose up -d backup` to apply. The differential sits at `:10` rather
than `:00` so it never contends with the 17:00 full — a collision is safe (the
loser skips on a lock) but silently skipping the 17:00 differential would be a
surprise, and a differential taken ten minutes after its own base is nearly
free.

### Credentials

Two identities, on purpose:

- **`tops_backup`** — created automatically on container start with exactly the
  grants Percona documents for XtraBackup, plus `RELOAD` (for `FLUSH BINARY
  LOGS`) and `REPLICATION CLIENT` (for `SHOW BINARY LOGS`). It can copy the
  datadir and rotate logs; it cannot read table data. Every scheduled run uses
  it — thousands of unattended executions.
- **`root`** — used only by restore. Replaying a binary log applies whatever the
  application did, including DDL and `GRANT`s, so least privilege is
  structurally impossible there.

Neither password is ever passed on a command line; both are written to `0600`
defaults-files under `/run/tops-backup/`, because argv is visible to anything
that can read `/proc` inside the container.

---

## Troubleshooting

**`./backup.sh status` says "latest full: none"**
The scheduler takes a seed full backup on first start. Check
`./backup.sh logs`; the most likely cause is that MySQL wasn't reachable, in
which case `./backup.sh now full` retries.

**A restore left MySQL stopped or with an empty datadir**
The datadir is only cleared after the backup has been successfully prepared, so
this means the copy step itself failed. Re-run the same
`./backup.sh restore …` command — it is idempotent from this state.

**PITR stopped earlier than expected**
`--stop-datetime` excludes events at exactly that timestamp, and binary log
event timestamps have one-second resolution. If several statements share a
second, use `--stop-position` (see scenario 3).

**"binary log sequence restarted on the server"**
Expected after a restore or a `docker compose down -v`. The old archive is
preserved as `binlog.superseded.<stamp>/`; nothing was lost. Take a fresh full
backup.

**XtraBackup version**
The `8.0` tag is pinned to match `mysql:8.0`. XtraBackup must come from the
same MySQL series as the server — 8.4 refuses to back up an 8.0 datadir. If the
server is ever upgraded, move both together and drop the
`--no-server-version-check` flag in
[`docker/backup/bin/tops-backup`](../docker/backup/bin/tops-backup) at the same
time. Note that Percona XtraBackup 8.0 reached end-of-life in June 2026, so
MySQL 8.0 (itself EOL since April 2026) and this backup stack should be
upgraded together when the app is ready to move.

---

## Files

| Path | Purpose |
|------|---------|
| [`backup.sh`](../backup.sh) | Host CLI — the only entry point you need |
| [`docker/backup/Dockerfile`](../docker/backup/Dockerfile) | Scheduler image |
| [`docker/backup/entrypoint.sh`](../docker/backup/entrypoint.sh) | Credential + user bootstrap, crontab generation |
| [`docker/backup/bin/tops-backup`](../docker/backup/bin/tops-backup) | The three backup tiers, prune, inventory |
| [`docker/backup/bin/tops-restore`](../docker/backup/bin/tops-restore) | Datadir rebuild and binary log replay |
| [`docker/backup/lib/common.sh`](../docker/backup/lib/common.sh) | Shared helpers, locking, metadata |
| [`docker/backup/tests/run-tests.sh`](../docker/backup/tests/run-tests.sh) | Three-scenario restore test suite |
| [`docker/mysql/conf.d/10-teemops-backup.cnf`](../docker/mysql/conf.d/10-teemops-backup.cnf) | MySQL engine settings |
