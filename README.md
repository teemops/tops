# TOPS — open-source AWS security scanning

[![Tests](https://github.com/teemops/tops/actions/workflows/tests.yml/badge.svg?branch=develop)](https://github.com/teemops/tops/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-Apache_2.0-blue)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org/)

Scan your AWS accounts for security misconfigurations, and get told how to fix them.
Self-hosted, Apache-2.0, **no limits and no paid tier**.

- **74 checks** across 11 AWS services — S3, IAM, EC2, RDS, CloudTrail, KMS, Lambda,
  DynamoDB, ELBv2, SNS, SQS
- **CIS AWS Foundations Benchmark** profile alongside a general "basic" profile
- **Every finding carries a remediation.** Critical and high findings also carry
  step-by-step guidance with links to AWS documentation
- **Multi-tenant**: organisations, team roles, per-organisation data isolation

## Install

You need **Docker** with Compose v2. That is the whole list — no PHP, no Composer, no
Node, no npm, no database to set up.

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/teemops/tops/develop/install.sh)
```

Prefer to read a script before running it? Good instinct:

```bash
curl -fsSL https://raw.githubusercontent.com/teemops/tops/develop/install.sh -o install.sh
```

```bash
less install.sh && bash install.sh
```

It pulls the published images from Docker Hub, generates a `.env` with its own application
key, starts the stack, waits until it answers, then asks whether you want to connect an AWS
account. Re-running it is safe — an existing `.env` and your data are left alone.

Then open **http://localhost:8080** and register. The first account you create is yours.
Sign-up and password-reset emails are captured locally at **http://localhost:8090** instead
of being sent, so nothing needs an SMTP server to get started.

**You do not need an AWS account to try TOPS**, and you never need a Firebase project — the
default login is ordinary email and password.

## Scanning a real AWS account

The installer offers this as a second, explicit step, because it deploys real resources
into your account. Say yes when asked, or come back to it later from the directory the
installer created — it prints the path when it finishes, and it is `tops/` unless you set
`TOPS_DIR`:

```bash
./install.sh --aws-only
```

Either way it tells you which AWS account it is about to deploy into — it reads that from
`aws sts get-caller-identity` — and lists what it creates before doing anything: two
CloudFormation stacks, SQS queues, an SNS topic and an S3 bucket, in a region you choose.
[docker-compose.README.md](docker-compose.README.md) has the same list plus how to remove
it. You will need the AWS CLI configured with permission to create those resources; if it
is not, the installer says so and skips the step rather than failing halfway.

Once it finishes, **AWS Accounts → Add AWS Account** in the UI walks you through a
CloudFormation stack that grants TOPS a read-only audit role in the account you want
scanned. Then start a scan; findings appear as they are produced.

Other flags: `--aws` sets it up without asking (for scripted installs) and `--no-aws` skips
the question entirely. `./install.sh --help` lists them.

## Upgrading and rolling back

```bash
docker compose pull && docker compose up -d
```

`install.sh` pins `TOPS_IMAGE_TAG` in your `.env` to the version it installed, so you only
move when you choose to. To roll back, set that variable to an earlier tag and run
`docker compose up -d` again. Database migrations apply automatically on start.

## If something goes wrong

**"the Docker daemon is not reachable".** Docker is installed but your user cannot talk to
it. On Linux: `sudo usermod -aG docker $USER`, then log out and back in, or run
`newgrp docker`.

**"a container named 'teemops-app' already exists".** Another TOPS install — or a second
checkout — owns those names. Stop it with `docker compose down` in its directory, or run the
installer from that directory instead.

**Port 8080 is already in use.** Set `APP_PORT=9090` in `.env`, then `docker compose up -d`.

**"Could not determine the latest release".** Usually a network or GitHub outage. Pin a
version instead: `TOPS_VERSION=0.1.0 ./install.sh`.

**It started but the page does not load.** Follow the application log with
`docker compose logs -f app`. MySQL takes a few seconds longer than the app on a first run.

**"Add AWS Account" says messaging is not configured.** The installer's AWS step has not run
yet, or it failed partway — run `./install.sh --aws-only`. Its log is `generated/install.log`.

**A scan stays "Running".** Region scans are processed by the worker — check it is alive
with `docker compose logs -f worker`. To clear scans already stuck:

```bash
docker compose exec app php artisan scans:mark-stale-region-complete --dry-run
```

Drop `--dry-run` once you are happy with what it lists.

**Emails never arrive.** They are not meant to leave the machine by default — they are
captured at http://localhost:8090. Set the `MAIL_*` variables in `.env` to send for real.

More symptoms and causes: [DEBUG.md](DEBUG.md).

## Contributing, and running from source

Building from source needs Docker **plus** PHP 8.3, Composer, Node ≥22.12 and npm:

```bash
./install-build.sh
```

That builds the images from your working tree instead of pulling them.
[docker-compose.README.md](docker-compose.README.md) has the equivalent commands by hand,
and [CONTRIBUTING.md](CONTRIBUTING.md) covers the DCO sign-off every commit needs.

Run the tests from `app/`:

```bash
php artisan test
```

If your PHP lacks `pdo_sqlite` — which the in-memory test database needs — run the suite
through Docker instead with `app/scripts/run-tests-docker.sh`.

Adding a scan service or a rule is a JSON change with no PHP. Validate it, always:

```bash
php artisan scan:validate-rules
```

## Documentation

| | |
| --- | --- |
| [Docker Compose guide](docker-compose.README.md) | Install, AWS messaging, backups, services |
| [Roadmap](docs/roadmap.md) | The plan of record — Now / Next / Later, and the decisions log |
| [Progress](docs/PROGRESS.md) | What is actually built, verified against the code |
| [Architecture](docs/architecture.md) | System design and boundaries |
| [Scanner coverage](docs/planning.md) | Services and misconfigurations, and the plan for more |
| [Backups](docs/backups.md) | Backup tiers and the three restore procedures |
| [Debugging](DEBUG.md) | Symptoms, causes and fixes |
| [Local development](docs/local-development.md) | Running from source without Docker, and the process-per-terminal layout |
| [Practices](docs/practices/) · [Processes](docs/processes/) | How we build and review |
| [Full index](docs/README.md) | Everything else |

## Technology

Laravel 12 (PHP 8.3) · Vue 3 + TypeScript + Inertia · Tailwind CSS · MySQL · Docker Compose.
Scanning is JSON-driven: adding a service means writing a `tasks.json`, not a PHP class.

## Licence

Apache-2.0. See [LICENSE](LICENSE).

**No limits, no tiers, no usage caps.** Run it at any scale, modify it, fork it, deploy it
commercially, offer it to your clients. There is no edition you eventually outgrow and no
threshold that turns into a bill. The software you self-host is the same software we would
run in any managed offering — there is no "enterprise build".

Two things sit alongside the licence:

- **[TRADEMARK.md](TRADEMARK.md)** — the code is free; the *name* "TOPS" isn't. Fork
  freely, just call your fork something else.
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — we use a DCO rather than a CLA, so contributors
  keep their copyright. That means TOPS **cannot** be relicensed to a proprietary or
  source-available licence later without every contributor agreeing. The guarantee is
  structural, not a promise.

## Support

Open an issue. For commercial support, get in touch.

**Found a security vulnerability?** Do not open an issue —
[report it privately](https://github.com/teemops/tops/security/advisories/new) or email
security@teemops.com. See [SECURITY.md](SECURITY.md) for scope, what to expect, and the
issues already known.
