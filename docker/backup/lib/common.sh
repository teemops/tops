#!/usr/bin/env bash
# Shared helpers for the Teemops backup scheduler. Sourced by bin/tops-backup
# and bin/tops-restore; not executable on its own.

set -Eeuo pipefail

# ---------------------------------------------------------------------------
# Configuration (all overridable from the environment / docker-compose.yml)
# ---------------------------------------------------------------------------

BACKUP_ROOT="${TOPS_BACKUP_ROOT:-/backups}"
DATADIR="${TOPS_MYSQL_DATADIR:-/var/lib/mysql}"

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"

FULL_RETENTION_DAYS="${TOPS_BACKUP_FULL_RETENTION_DAYS:-7}"
BINLOG_RETENTION_DAYS="${TOPS_BACKUP_BINLOG_RETENTION_DAYS:-14}"

# Finished backups are chowned to this uid:gid so ~/.tops/backups is usable
# from the host shell. Defaults match the first non-system user on Linux.
BACKUP_UID="${TOPS_BACKUP_UID:-1000}"
BACKUP_GID="${TOPS_BACKUP_GID:-1000}"

FULL_DIR="$BACKUP_ROOT/full"
DIFF_DIR="$BACKUP_ROOT/diff"
BINLOG_DIR="$BACKUP_ROOT/binlog"
LOG_DIR="$BACKUP_ROOT/logs"
LOCK_DIR="$BACKUP_ROOT/.locks"
WORK_DIR="$BACKUP_ROOT/.work"

# Written by the entrypoint with mode 0600. Credentials go in a defaults-file
# rather than on the argv of every mysql/xtrabackup call, which would expose
# them to anything that can read /proc (`ps auxww` inside the container).
DEFAULTS_FILE="${TOPS_BACKUP_DEFAULTS_FILE:-/run/tops-backup/my.cnf}"

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

log()  { printf '%s [%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "${LOG_TAG:-backup}" "$*"; }
warn() { log "WARNING: $*" >&2; }
die()  { log "ERROR: $*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# MySQL access
# ---------------------------------------------------------------------------

mysql_q() {
    # Batch/raw/no-headers: output is parsed by callers, so keep it tab
    # separated with no column decoration.
    mysql --defaults-file="$DEFAULTS_FILE" --batch --raw --skip-column-names "$@"
}

wait_for_mysql() {
    local timeout="${1:-60}" waited=0
    until mysqladmin --defaults-file="$DEFAULTS_FILE" ping --silent >/dev/null 2>&1; do
        (( waited >= timeout )) && die "MySQL at $DB_HOST:$DB_PORT not reachable after ${timeout}s"
        sleep 2
        waited=$(( waited + 2 ))
    done
}

# ---------------------------------------------------------------------------
# Locking
#
# Two locks, not one. Physical backups (full/diff/prune) mutate the same
# base-and-delta chain and must serialise against each other. Binlog archiving
# only ever copies files MySQL has already closed, so it is safe to run
# concurrently with a long full backup — and it must, or a full backup that
# overruns an hour would open a hole in the 15-minute transaction stream.
# ---------------------------------------------------------------------------

with_lock() {
    local name="$1"; shift
    mkdir -p "$LOCK_DIR"
    exec {lock_fd}>"$LOCK_DIR/$name.lock"
    if ! flock -n "$lock_fd"; then
        log "another '$name' run is still in progress; skipping this tick"
        return 0
    fi
    "$@"
}

# ---------------------------------------------------------------------------
# Backup metadata
#
# Each backup directory carries a META file of key=value lines. XtraBackup's
# own xtrabackup_checkpoints/xtrabackup_binlog_info are the source of truth for
# LSNs and binlog coordinates; META adds what XtraBackup does not record: which
# full a differential is based on, and the wall-clock timestamp with an
# explicit UTC offset (directory names are local time, which is ambiguous for
# one hour every DST fall-back).
# ---------------------------------------------------------------------------

new_stamp() { date '+%Y%m%dT%H%M%S'; }

write_meta() {
    local dir="$1" kind="$2" base="${3:-}"
    {
        echo "kind=$kind"
        echo "stamp=$(basename "$dir")"
        echo "created_at=$(date '+%Y-%m-%dT%H:%M:%S%z')"
        echo "tz=${TZ:-UTC}"
        [[ -n $base ]] && echo "base=$base"
        if [[ -f "$dir/xtrabackup_binlog_info" ]]; then
            echo "binlog_file=$(awk '{print $1}' "$dir/xtrabackup_binlog_info")"
            echo "binlog_pos=$(awk '{print $2}' "$dir/xtrabackup_binlog_info")"
        fi
        if [[ -f "$dir/xtrabackup_checkpoints" ]]; then
            grep -E '^(from_lsn|to_lsn|last_lsn)' "$dir/xtrabackup_checkpoints" || true
        fi
    } >"$dir/META"
}

meta_get() {
    local dir="$1" key="$2"
    [[ -f "$dir/META" ]] || return 1
    awk -F= -v k="$key" '$1==k {sub(/^[^=]*=/,""); print; exit}' "$dir/META"
}

# A backup directory is only considered usable once XtraBackup has written
# xtrabackup_checkpoints AND we have written META. A run killed midway leaves
# the directory without META, so it is never picked as a restore source or as
# a differential base.
backup_is_complete() {
    local dir="$1"
    [[ -f "$dir/META" && -f "$dir/xtrabackup_checkpoints" ]]
}

list_backups() {
    # $1 = full|diff. Emits complete backup directories, oldest first.
    local dir; dir=$([[ $1 == full ]] && echo "$FULL_DIR" || echo "$DIFF_DIR")
    [[ -d $dir ]] || return 0
    local d
    for d in $(ls -1 "$dir" 2>/dev/null | sort); do
        backup_is_complete "$dir/$d" && echo "$dir/$d"
    done
}

latest_backup() { list_backups "$1" | tail -n1; }

# ---------------------------------------------------------------------------
# Ownership
#
# The container runs as root because MySQL's datadir is 0640 mysql:mysql and
# unreadable otherwise. That would leave every file under the host's
# ~/.tops/backups owned by root and undeletable without sudo, so hand each
# finished tree back to the invoking host user.
# ---------------------------------------------------------------------------

reown() {
    local target="$1"
    [[ -e $target ]] || return 0
    chown -R "$BACKUP_UID:$BACKUP_GID" "$target" 2>/dev/null || \
        warn "could not chown $target to $BACKUP_UID:$BACKUP_GID"
}

ensure_layout() {
    mkdir -p "$FULL_DIR" "$DIFF_DIR" "$BINLOG_DIR" "$LOG_DIR" "$LOCK_DIR" "$WORK_DIR"
    # The skeleton itself, not just its contents. XtraBackup creates
    # directories 0700 root, so without this the host user cannot so much as
    # `ls ~/.tops/backups/full` even though every backup inside it is theirs.
    # Non-recursive: individual backups are reowned as they complete.
    # $LOCK_DIR is included so `rm -rf ~/.tops/backups` works from the host:
    # unlinking a file needs write+execute on its directory, so a root-owned
    # 0700 subdirectory would make the whole tree undeletable without sudo.
    chown "$BACKUP_UID:$BACKUP_GID" \
        "$BACKUP_ROOT" "$FULL_DIR" "$DIFF_DIR" "$BINLOG_DIR" "$LOG_DIR" "$LOCK_DIR" "$WORK_DIR" 2>/dev/null || true
}

# ---------------------------------------------------------------------------
# Binlog helpers
# ---------------------------------------------------------------------------

# Binlog files are named <prefix>.NNNNNN. Comparing the numeric suffix orders
# them correctly; comparing the strings does not once the counter passes
# 999999 -> 1000000.
binlog_seq() { local f="${1##*.}"; echo $((10#$f)); }

# Emits archived binlog files, ordered by sequence number, whose sequence is
# >= the one given. This is the set PITR has to replay from a backup whose
# xtrabackup_binlog_info names that starting file.
archived_binlogs_from() {
    local from_file="$1" from_seq
    from_seq=$(binlog_seq "$from_file")
    [[ -d $BINLOG_DIR ]] || return 0
    local f
    for f in $(ls -1 "$BINLOG_DIR" 2>/dev/null | grep -E '\.[0-9]{6,}$' | sort -t. -k2,2n); do
        (( $(binlog_seq "$f") >= from_seq )) && echo "$BINLOG_DIR/$f"
    done
    return 0
}
