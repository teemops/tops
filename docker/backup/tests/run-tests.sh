#!/usr/bin/env bash
#
# run-tests.sh — proves that all three recovery scenarios actually recover.
#
# A backup you have never restored is a hypothesis, not a backup. This suite
# stands up a disposable MySQL instance, writes known markers at known times,
# destroys the data, and then checks that each restore mode brings back exactly
# the markers it should — and, just as importantly, that it does NOT bring back
# the ones written after the recovery target.
#
#   Scenario 1  Full recovery          restore full          -> A
#   Scenario 2  Hourly PITR            restore hourly        -> A B
#   Scenario 3  Transactional PITR     restore pitr --to T   -> A B C, not D
#
# Nothing here touches the development database: separate compose project,
# separate volume, separate backups directory under /tmp.
#
# Usage:  ./backup.sh test            (or)  docker/backup/tests/run-tests.sh
#         KEEP=1 ... to leave the stack running for inspection afterwards

set -Eeuo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$HERE/../../.." && pwd)"

export TOPS_COMPOSE_FILE="$HERE/docker-compose.test.yml"
export TOPS_COMPOSE_PROJECT="tops-backup-test"
export TOPS_BACKUP_DIR="${TOPS_BACKUP_TEST_DIR:-/tmp/tops-backup-test}"
export TOPS_BACKUP_TZ="${TOPS_BACKUP_TZ:-$(cat /etc/timezone 2>/dev/null || echo UTC)}"
export TOPS_BACKUP_UID="${TOPS_BACKUP_UID:-$(id -u)}"
export TOPS_BACKUP_GID="${TOPS_BACKUP_GID:-$(id -g)}"

COMPOSE=(docker compose -f "$TOPS_COMPOSE_FILE" -p "$TOPS_COMPOSE_PROJECT")

c_red=$'\033[31m'; c_green=$'\033[32m'; c_bold=$'\033[1m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
[[ -t 1 ]] || { c_red=; c_green=; c_bold=; c_dim=; c_off=; }

PASS=0; FAIL=0

step() { printf '\n%s── %s%s\n' "$c_bold" "$*" "$c_off"; }
info() { printf '%s   %s%s\n' "$c_dim" "$*" "$c_off"; }
pass() { printf '%s   PASS%s %s\n' "$c_green" "$c_off" "$*"; PASS=$(( PASS + 1 )); }
fail() { printf '%s   FAIL%s %s\n' "$c_red"   "$c_off" "$*"; FAIL=$(( FAIL + 1 )); }

# ---------------------------------------------------------------------------

sql() { "${COMPOSE[@]}" exec -T mysql mysql -uroot -ptest_root --batch --raw --skip-column-names teemops_test -e "$1" 2>/dev/null; }

# Timestamps for --stop-datetime are generated inside the backup container, not
# on the host. mysqlbinlog resolves --stop-datetime against the timezone of the
# process running it, so producing the marker anywhere else invites an
# off-by-one-timezone failure that looks like data loss.
container_now() { "${COMPOSE[@]}" exec -T backup date '+%Y-%m-%d %H:%M:%S'; }

backup_now() { "${COMPOSE[@]}" exec -T backup tops-backup "$1" >/dev/null 2>&1 || { fail "tops-backup $1 failed"; "${COMPOSE[@]}" exec -T backup tops-backup "$1"; return 1; }; }

latest_stamp() { "${COMPOSE[@]}" exec -T backup bash -c "ls -1 /backups/$1 2>/dev/null | sort | tail -n1" | tr -d '\r\n'; }

insert_marker() { sql "INSERT INTO markers (label) VALUES ('$1');"; }

# The assertion that matters. Checking only that expected rows came back would
# pass even if the restore silently replayed everything to 'now'; the absent
# set is what proves the recovery stopped where it was told to.
assert_markers() {
    local scenario="$1" expected="$2" absent="$3" actual m
    actual=$(sql "SELECT GROUP_CONCAT(label ORDER BY id) FROM markers;" | tr -d '\r')
    [[ $actual == NULL ]] && actual=""
    info "markers present: [${actual}]  expected: [${expected}]"

    if [[ $actual == "$expected" ]]; then
        pass "$scenario — recovered exactly [$expected]"
    else
        fail "$scenario — expected [$expected] but found [$actual]"
    fi

    for m in $(echo "$absent" | tr ',' ' '); do
        [[ -z $m ]] && continue
        if [[ ",$actual," == *",$m,"* ]]; then
            fail "$scenario — '$m' was written AFTER the recovery target and should not be present"
        fi
    done
}

reset_stack() {
    step "Resetting the test stack"
    "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
    # Backup artefacts are chowned to the invoking user, but XtraBackup's
    # scratch dirs are created as root mid-run. Remove the tree from inside a
    # container so a leftover root-owned file cannot wedge the next run.
    docker run --rm --user root -v "$TOPS_BACKUP_DIR:/x" --entrypoint sh busybox -c 'rm -rf /x/..?* /x/.[!.]* /x/*' >/dev/null 2>&1 || true
    mkdir -p "$TOPS_BACKUP_DIR"

    "${COMPOSE[@]}" up -d --build mysql >/dev/null
    info "waiting for MySQL"
    local waited=0
    until "${COMPOSE[@]}" exec -T mysql mysqladmin ping -uroot -ptest_root --silent >/dev/null 2>&1; do
        (( waited >= 180 )) && { echo "MySQL never came up"; "${COMPOSE[@]}" logs mysql | tail -30; exit 1; }
        sleep 3; waited=$(( waited + 3 ))
    done
    "${COMPOSE[@]}" up -d --build backup >/dev/null
    sleep 3

    sql "CREATE TABLE IF NOT EXISTS markers (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(16) NOT NULL, at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;"
    sql "TRUNCATE TABLE markers;"
    info "test schema ready"
}

# XtraBackup narrates every file it touches on stderr, which buries the
# assertions. Capture it and only surface it when something actually breaks.
restore() {
    local logfile="$TOPS_BACKUP_DIR/../restore-$$.log"
    if "$REPO_ROOT/backup.sh" restore "$@" --yes --no-safety-backup >"$logfile" 2>&1; then
        info "restore completed ($(wc -l <"$logfile") lines of xtrabackup output suppressed)"
        rm -f "$logfile"
        return 0
    fi
    fail "restore command itself failed — last 40 lines:"
    tail -40 "$logfile" | sed 's/^/     /'
    rm -f "$logfile"
    return 1
}

cleanup() {
    if [[ ${KEEP:-0} == 1 ]]; then
        printf '\n%sKEEP=1 — leaving the stack up. Tear down with:%s\n' "$c_dim" "$c_off"
        printf '  docker compose -f %s -p %s down -v\n' "$TOPS_COMPOSE_FILE" "$TOPS_COMPOSE_PROJECT"
        return
    fi
    "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
    docker run --rm --user root -v "$TOPS_BACKUP_DIR:/x" --entrypoint sh busybox -c 'rm -rf /x/..?* /x/.[!.]* /x/*' >/dev/null 2>&1 || true
}
trap cleanup EXIT

# ===========================================================================
# Scenario 1 — Full recovery
#
#   write A -> FULL backup -> write B -> restore full
#   Expect A. B was written after the backup and is unrecoverable by design;
#   that is what distinguishes this tier from the other two.
# ===========================================================================

scenario_full() {
    reset_stack
    step "SCENARIO 1 — Full recovery"

    insert_marker A
    info "wrote marker A"
    backup_now full || return
    info "full backup taken: $(latest_stamp full)"

    insert_marker B
    info "wrote marker B (after the backup — must NOT survive)"

    restore full || return
    assert_markers "Scenario 1 (full recovery)" "A" "B"
}

# ===========================================================================
# Scenario 2 — Hourly point-in-time recovery via a differential
#
#   write A -> FULL -> write B -> DIFF -> write C -> restore full+diff
#   Expect A,B. This is the hourly tier: recovery to the top of an hour without
#   replaying any transaction log.
# ===========================================================================

scenario_hourly() {
    reset_stack
    step "SCENARIO 2 — Hourly point-in-time recovery (full + differential)"

    insert_marker A
    backup_now full || return
    local full_stamp; full_stamp=$(latest_stamp full)
    info "full backup: $full_stamp  (contains A)"

    insert_marker B
    backup_now diff || return
    local diff_stamp; diff_stamp=$(latest_stamp diff)
    info "differential: $diff_stamp  (contains A B)"

    insert_marker C
    info "wrote marker C (after the differential — must NOT survive)"

    restore hourly --full "$full_stamp" --diff "$diff_stamp" || return
    assert_markers "Scenario 2 (hourly PITR)" "A,B" "C"
}

# ===========================================================================
# Scenario 3 — Transactional point-in-time recovery
#
#   write A -> FULL -> write B -> DIFF -> write C -> [T] -> write D
#   -> archive binlogs -> restore full+diff and replay binlogs up to T
#
#   Expect A,B,C and NOT D. This is the tier that makes the 15-minute RPO real:
#   C exists only in the binary log, never in any physical backup.
#
#   The sleeps are load-bearing. Binary log event timestamps have one-second
#   resolution, so C, the cut-off T, and D must land in distinct seconds or the
#   test cannot tell a correct stop from an off-by-one.
# ===========================================================================

scenario_transactional() {
    reset_stack
    step "SCENARIO 3 — Transactional point-in-time recovery (binlog replay)"

    insert_marker A
    backup_now full || return
    local full_stamp; full_stamp=$(latest_stamp full)
    info "full backup: $full_stamp  (contains A)"

    insert_marker B
    backup_now diff || return
    local diff_stamp; diff_stamp=$(latest_stamp diff)
    info "differential: $diff_stamp  (contains A B)"

    sleep 2
    insert_marker C
    info "wrote marker C — exists ONLY in the binary log"

    sleep 2
    local cutoff; cutoff=$(container_now)
    info "recovery target: $cutoff (${TOPS_BACKUP_TZ})"

    sleep 2
    insert_marker D
    info "wrote marker D after the target — must NOT survive"

    backup_now binlog || return
    info "binary logs archived"

    restore pitr --full "$full_stamp" --diff "$diff_stamp" --to "$cutoff" || return
    assert_markers "Scenario 3 (transactional PITR)" "A,B,C" "D"
}

# ===========================================================================

printf '%sTeemops backup restore test suite%s\n' "$c_bold" "$c_off"
printf '  project     : %s\n' "$TOPS_COMPOSE_PROJECT"
printf '  backups dir : %s\n' "$TOPS_BACKUP_DIR"
printf '  timezone    : %s\n' "$TOPS_BACKUP_TZ"

scenario_full
scenario_hourly
scenario_transactional

printf '\n%s─────────────────────────────%s\n' "$c_bold" "$c_off"
if (( FAIL )); then
    printf '%s%d passed, %d FAILED%s\n' "$c_red" "$PASS" "$FAIL" "$c_off"
    exit 1
fi
printf '%s%d passed, 0 failed%s\n' "$c_green" "$PASS" "$c_off"
