# syntax=docker/dockerfile:1

# Teemops database backup scheduler.
#
# Built on Percona's XtraBackup image rather than a bare distro because it
# already ships every binary this container needs, at versions that are known
# to work together: xtrabackup/xbstream (physical full + differential backups),
# mysql/mysqladmin (bootstrap + health probing), mysqlbinlog (binlog archiving
# and PITR replay), plus qpress/zstd/gzip/rsync/flock.
#
# Version pinning matters. XtraBackup must be from the same MySQL series as the
# server it backs up — 8.0 here, matching docker-compose.yml's mysql:8.0.
# Do NOT bump this to 8.4 without also moving the server: XtraBackup 8.4
# refuses to back up an 8.0 datadir.
FROM percona/percona-xtrabackup:8.0

USER root

# The upstream image is UBI9-minimal with tzdata installed but its zoneinfo
# files stripped, so every TZ resolves to UTC. Reinstalling restores them,
# which is what makes TOPS_BACKUP_TZ (and therefore "5pm local time") mean
# anything. `ps` is used by the entrypoint's readiness probing.
RUN microdnf reinstall -y tzdata \
    && microdnf clean all \
    && rm -rf /var/cache/yum

# supercronic instead of cron/cronie: it runs as PID-1-friendly foreground
# process, logs every job's stdout/stderr to the container log where
# `docker compose logs backup` can see it, does not need root, and does not
# silently drop the container's environment the way crond does.
ARG SUPERCRONIC_VERSION=v0.2.48
ARG SUPERCRONIC_SHA1SUM=016b7c9aebfc8d9fd9526e8ba33b191fc524485f
ADD --chmod=755 \
    https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-amd64 \
    /usr/local/bin/supercronic
RUN echo "${SUPERCRONIC_SHA1SUM}  /usr/local/bin/supercronic" | sha1sum -c -

COPY docker/backup/lib/ /opt/tops-backup/lib/
COPY docker/backup/bin/ /opt/tops-backup/bin/
COPY docker/backup/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /opt/tops-backup/bin/* \
    && ln -sf /opt/tops-backup/bin/tops-backup /usr/local/bin/tops-backup \
    && ln -sf /opt/tops-backup/bin/tops-restore /usr/local/bin/tops-restore

# Runs as root: reading MySQL's datadir requires it (the files are 0640
# mysql:mysql), and so does chowning finished backups back to the host user so
# ~/.tops/backups stays readable outside Docker. Backups are chowned to
# TOPS_BACKUP_UID/GID after every run — see lib/common.sh:reown_backup_tree.
WORKDIR /backups

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["schedule"]
