#!/usr/bin/env bash
#
# backup.sh — host-side driver for the Teemops database backup scheduler.
#
# The scheduler itself lives in the `backup` container and needs no help. This
# script exists for the operations that span container lifecycles — chiefly
# restore, which has to stop MySQL, rewrite its datadir, and start it again.
#
# Run ./backup.sh with no arguments for usage.

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

# Which compose project to act on. Defaults to this repo's stack; the restore
# test suite points these at its own throwaway project so that it exercises
# this exact orchestration rather than a reimplementation of it.
COMPOSE=(docker compose)
[[ -n ${TOPS_COMPOSE_FILE:-}    ]] && COMPOSE+=(-f "$TOPS_COMPOSE_FILE")
[[ -n ${TOPS_COMPOSE_PROJECT:-} ]] && COMPOSE+=(-p "$TOPS_COMPOSE_PROJECT")

BACKUP_DIR="${TOPS_BACKUP_DIR:-$HOME/.tops/backups}"

c_red=$'\033[31m'; c_yellow=$'\033[33m'; c_green=$'\033[32m'; c_bold=$'\033[1m'; c_off=$'\033[0m'
[[ -t 1 ]] || { c_red=; c_yellow=; c_green=; c_bold=; c_off=; }

say()  { printf '%s==>%s %s\n' "$c_bold" "$c_off" "$*"; }
warn() { printf '%s==> %s%s\n' "$c_yellow" "$*" "$c_off" >&2; }
die()  { printf '%s==> %s%s\n' "$c_red" "$*" "$c_off" >&2; exit 1; }
ok()   { printf '%s==> %s%s\n' "$c_green" "$*" "$c_off"; }

# ---------------------------------------------------------------------------

ensure_backup_dir() {
    # Created on the host, not by Docker: a bind-mount target that does not
    # exist yet is created by the daemon as root, which would leave the whole
    # tree unwritable from the host shell.
    mkdir -p "$BACKUP_DIR"
}

mysql_container() { "${COMPOSE[@]}" ps -q mysql; }

# The test project has no app/worker services. Everything that touches them is
# guarded by this so the same restore path works against both stacks.
has_service() { "${COMPOSE[@]}" config --services 2>/dev/null | grep -qx "$1"; }

wait_for_mysql_healthy() {
    local cid timeout=180 waited=0 state
    say "waiting for MySQL to become healthy"
    while :; do
        cid=$(mysql_container)
        if [[ -n $cid ]]; then
            state=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || echo starting)
            [[ $state == healthy || $state == running ]] && { ok "MySQL is up"; return 0; }
        fi
        (( waited >= timeout )) && die "MySQL did not become healthy within ${timeout}s"
        sleep 3; waited=$(( waited + 3 ))
    done
}

confirm() {
    local prompt="$1"
    [[ ${ASSUME_YES:-0} == 1 ]] && return 0
    printf '%s%s%s [type "yes" to continue]: ' "$c_yellow" "$prompt" "$c_off"
    local reply; read -r reply
    [[ $reply == yes ]] || die "aborted"
}

# ---------------------------------------------------------------------------
# Read-only commands — run inside the already-running scheduler container.
# ---------------------------------------------------------------------------

cmd_status()  { "${COMPOSE[@]}" exec -T backup tops-backup status; }
cmd_list()    { "${COMPOSE[@]}" exec -T backup tops-backup list; }
cmd_window()  { "${COMPOSE[@]}" exec -T backup tops-restore window; }
cmd_logs()    { "${COMPOSE[@]}" logs -f backup; }

cmd_now() {
    local kind="${1:-}"
    case "$kind" in
        full|diff|binlog|prune) ;;
        *) die "usage: ./backup.sh now {full|diff|binlog|prune}" ;;
    esac
    "${COMPOSE[@]}" exec -T backup tops-backup "$kind"
}

# ---------------------------------------------------------------------------
# Restore
#
# All three recovery scenarios funnel through here. The difference between them
# is only which pieces get applied:
#
#   full     : latest (or named) full backup                    -> that instant
#   hourly   : full + the one differential for the target hour  -> that hour
#   pitr     : full [+ differential] + binlog replay to --to    -> any second
# ---------------------------------------------------------------------------

cmd_restore() {
    local mode="${1:-}"; shift || true
    local full="" diff="" to="" stop_position="" safety=1

    while (( $# )); do
        case "$1" in
            --full)          full="$2"; shift 2 ;;
            --diff)          diff="$2"; shift 2 ;;
            --to)            to="$2"; shift 2 ;;
            --stop-position) stop_position="$2"; shift 2 ;;
            --yes|-y)        ASSUME_YES=1; shift ;;
            --no-safety-backup) safety=0; shift ;;
            *) die "unknown option: $1" ;;
        esac
    done

    case "$mode" in
        full)
            [[ -z $diff && -z $to ]] || die "'restore full' takes only --full; use 'hourly' or 'pitr' for the others"
            ;;
        hourly)
            [[ -n $diff ]] || die "'restore hourly' needs --diff STAMP (see ./backup.sh list)"
            ;;
        pitr)
            [[ -n $to || -n $stop_position ]] || die "'restore pitr' needs --to 'YYYY-MM-DD HH:MM:SS'"
            ;;
        *)
            die "usage: ./backup.sh restore {full|hourly|pitr} [options] — see ./backup.sh help"
            ;;
    esac

    echo
    warn "This REPLACES the contents of the '${MYSQL_DATABASE:-teemops}' database and every"
    warn "other schema on this MySQL instance with the contents of a backup."
    echo "  mode            : $mode"
    echo "  full backup     : ${full:-<latest>}"
    [[ -n $diff ]] && echo "  differential    : $diff"
    [[ -n $to ]]   && echo "  replay until    : $to (timezone: ${TOPS_BACKUP_TZ:-as configured in the scheduler})"
    [[ -n $stop_position ]] && echo "  stop at position: $stop_position"
    echo
    confirm "Proceed with the restore?"

    # A safety full backup of the CURRENT state, taken before anything is
    # destroyed. Restoring the wrong backup is a normal human error, and
    # without this there is no way back to where you started.
    if (( safety )); then
        say "taking a safety backup of the current database first"
        if ! "${COMPOSE[@]}" exec -T backup tops-backup full; then
            warn "safety backup FAILED — there will be no way back to the current state."
            confirm "Continue anyway?"
        fi
    fi

    say "stopping writers, then mysql"
    for svc in app worker backup; do
        has_service "$svc" && "${COMPOSE[@]}" stop "$svc" >/dev/null 2>&1 || true
    done
    "${COMPOSE[@]}" stop mysql

    local -a datadir_args=(datadir)
    [[ -n $full ]] && datadir_args+=(--full "$full")
    [[ -n $diff ]] && datadir_args+=(--diff "$diff")

    say "rebuilding the datadir from the backup"
    "${COMPOSE[@]}" run --rm db-restore "${datadir_args[@]}" \
        || die "datadir restore failed — MySQL is stopped and its datadir may be empty. Re-run this command."

    say "starting mysql"
    "${COMPOSE[@]}" start mysql
    wait_for_mysql_healthy

    if [[ $mode == pitr ]]; then
        local -a replay_args=(replay)
        [[ -n $to ]]            && replay_args+=(--stop-datetime "$to")
        [[ -n $stop_position ]] && replay_args+=(--stop-position "$stop_position")
        say "replaying archived binary logs"
        "${COMPOSE[@]}" run --rm db-restore "${replay_args[@]}" \
            || die "binary log replay failed. The database is restored to the backup instant; fix the cause and re-run 'tops-restore replay'."
    fi

    say "restarting the scheduler and application"
    has_service backup && "${COMPOSE[@]}" start backup >/dev/null 2>&1 || true
    for svc in app worker; do
        has_service "$svc" && "${COMPOSE[@]}" up -d "$svc"
    done

    echo
    ok "Restore complete."
    case "$mode" in
        full)   ok "The database is at the state captured by the full backup." ;;
        hourly) ok "The database is at the state captured by differential $diff." ;;
        pitr)   ok "The database is at ${to:-position $stop_position}." ;;
    esac
    warn "The binary log sequence has restarted on a new timeline. The next"
    warn "15-minute archive run will retire the old archive to binlog.superseded.*"
    warn "rather than overwrite it. Take a fresh full backup now:  ./backup.sh now full"
}

# ---------------------------------------------------------------------------

cmd_test() { exec docker/backup/tests/run-tests.sh "$@"; }

usage() {
    cat <<'USAGE'
backup.sh — Teemops database backup and recovery

SCHEDULE (runs automatically in the `backup` container)
  daily 17:00   full backup           complete physical copy of the datadir
  hourly :10    differential backup   pages changed since the latest full
  every 15 min  binary log archive    the transaction stream, for PITR
  daily 03:30   prune                 retention

INSPECT
  ./backup.sh status                  health summary: latest backups, sizes, retention
  ./backup.sh list                    inventory of fulls, differentials, binlogs
  ./backup.sh window                  the time range point-in-time recovery can target
  ./backup.sh logs                    follow the scheduler log

RUN A BACKUP NOW
  ./backup.sh now full | diff | binlog | prune

RESTORE  (all three stop MySQL, rewrite the datadir, and bring it back up)

  1. Full recovery — back to the most recent (or a named) full backup:
       ./backup.sh restore full
       ./backup.sh restore full --full 20260728T170000

  2. Hourly point-in-time — back to a specific hourly differential:
       ./backup.sh list                 # find the differential and its base
       ./backup.sh restore hourly --full 20260728T170000 --diff 20260728T210000

  3. Transactional point-in-time — back to any second, by replaying binlogs:
       ./backup.sh restore pitr --to '2026-07-28 21:43:07'
       ./backup.sh restore pitr --full 20260728T170000 --diff 20260728T210000 \
                                --to '2026-07-28 21:43:07'

  Options: --yes (skip the confirmation prompt), --no-safety-backup
  Times passed to --to are read in the scheduler's timezone (TOPS_BACKUP_TZ).

VERIFY
  ./backup.sh test                    run all three restore scenarios against a
                                      throwaway MySQL instance; never touches
                                      your development database
USAGE
}

case "${1:-help}" in
    status)  ensure_backup_dir; cmd_status ;;
    list)    ensure_backup_dir; cmd_list ;;
    window)  cmd_window ;;
    logs)    cmd_logs ;;
    now)     shift; ensure_backup_dir; cmd_now "$@" ;;
    restore) shift; ensure_backup_dir; cmd_restore "$@" ;;
    test)    shift; cmd_test "$@" ;;
    help|-h|--help) usage ;;
    *) usage; exit 64 ;;
esac
