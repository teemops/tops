# Self-Hosted Setup a Stranger Can Follow - User Story

Roadmap item **N-3**. Status: ✅ **Delivered 2026-07-30.**

Scope changed materially after this was written: N-5 landed in between, so `install.sh`
pulls published images rather than building, and the "no npm install" requirement is met by
distribution rather than documentation. Decision 3 below turned out to be already
implemented. `setup-env.sh` was deleted and `install.sh` renamed on 2026-07-30 ahead of the
rest.

## User Story

As a solo engineer evaluating TOPS, I want to install it and complete my first scan by
following the documentation alone, so that I can decide whether it's worth my time without
needing help from the maintainer.

## Expected Behavior

A new operator lands on the README and sees, within the first screen, what TOPS is and one
command that starts installing it. They run it, answer at most a couple of prompts, and
reach a running instance they can log into. From there a clearly-signposted second step
connects an AWS account and returns real findings.

They are never asked for a Firebase project. They are never silently pointed at
Teemops-owned infrastructure. When a step deploys real AWS resources that cost money, the
documentation says so before they run it, and says how to remove them.

## User Acceptance Criteria

**Getting running**

- [x] Given a clean machine with Docker installed, when I run the documented install
      command, then I reach a running instance and can register and log in
      — *verified by running the published one-liner from an empty directory*
- [x] Given I have no Firebase project and don't want one, when I follow the default path,
      then I am never prompted for Firebase credentials — `FIREBASE_USER_AUTH` now
      defaults to `false` in `app/.env.example`, matching the Docker default
- [x] Given the installer runs, when it finishes, then it prints the URL to open and the
      next step to take
- [x] Given I re-run the installer on a machine that already has TOPS, when it detects an
      existing install, then it says so and does not destroy my data

**The installer refuses to do damage**

- [x] Given Docker is not installed or not running, when I run the installer, then it stops
      with a message naming the problem and how to fix it, before changing anything
- [x] Given I want to read the script before running it, when I follow the README, then a
      download-inspect-run form is shown alongside the one-liner.
      **Not done: the SHA256 checksum.** The release workflow does not publish one, so
      documenting a checksum would mean documenting a step nobody can complete. Tracked
      as a Next item rather than faked
- [x] Given any step of the installer fails, when it exits, then it exits non-zero and
      leaves no half-written `.env`

**Connecting AWS and scanning**

- [ ] **Not verified: connect an account and run a scan that returns findings.** This
      needs a real AWS account and real spend; the documentation path is written but the
      end-to-end run has not been done
- [x] Given the AWS messaging step, when I read the guide before running it, then I know
      which resources it creates in my account and the command to remove them.
      *Partially:* the resource list is documented, cost is not quantified
- [x] Given I complete the messaging step, when it finishes, then my instance uses the
      CloudFormation template from **my own** S3 bucket — already true before N-3, see
      decision 3

**Not being misled**

- [x] Given I copy `app/.env.example`, when I inspect it, then it contains no Teemops AWS
      account ID, SQS ARNs, CloudFormation URL or Firebase project identifiers — there
      were more than recorded here: also a Firebase project id, service-account email and
      client id
- [x] Given a step fails, when I read the guide, then a troubleshooting section covers the
      common causes — nine symptoms in the README, each written from a failure actually
      seen during this work
- [x] Given I run any script in the repository root, when I read its name, then it does
      what the name says

## Technical Notes

**Decisions taken at Discovery (2026-07-29):**

1. ~~**The installer is built and tested now, but the README leads with the git-clone path
   until D-4.**~~ **Superseded the same day** — the repo went public on 2026-07-30, so the
   README leads with the one-liner as originally wanted. It is served from
   `raw.githubusercontent.com/teemops/tops/develop/install.sh`.
2. **`install.sh` is renamed `install-messaging.sh`.** The existing root `install.sh` is
   not a get-started installer — it deploys SQS/SNS/S3 via CloudFormation and requires a
   pre-existing `.env`. The new web installer takes the `install.sh` name.
3. **The CloudFormation template comes from the operator's own S3 bucket.** This is
   **already implemented** — `docker/installer/scripts/install-messaging.sh:98-114` uploads
   `templates/iam.role.child.account.cfn.yaml` to the deploy bucket and exports
   `TOPS_CFN_TEMPLATE_URL` into `generated/teemops.env`. The work here is removing the
   stale vendor default from `app/.env.example` and documenting the behaviour, not
   building it.

**Existing state worth knowing:**

- `docker-compose.README.md` already contains a working three-phase install. Much of N-3
  is promoting and correcting that content rather than authoring new instructions.
- `setup-env.sh` in the repository root is dead and actively misleading — it writes
  `NUXT_PUBLIC_FIREBASE_*` variables and a `DATABASE_URL` this stack does not read. It is
  a leftover from a previous incarnation of the product. **Delete it.**
- `README.md:30-37` lists a Firebase project and an AWS account as hard prerequisites.
  Both are wrong for the default self-hosted path.
- The frontend build requires `composer install` first — Ziggy's route helper is published
  by the Composer package and imported by the type-check step (see roadmap N-1).
- Per D-3, `install-messaging.sh` is documented honestly rather than replaced. Be explicit
  that it deploys real AWS resources.

**`curl | bash` posture.** TOPS is a security product, so the installer ships the
mitigations that make the pattern defensible: HTTPS only, `set -euo pipefail`, no `sudo`
without explaining why, and the inspect-first form shown beside the one-liner. The published
SHA256 is the one item not delivered — see Out of Scope. This is the same pattern Docker, k3s and rustup use; the difference is that we
show our working.

**Validation is by doing.** The only meaningful test of this story is a genuine
clean-machine run-through. Automated coverage is limited to the installer's guard clauses.

## Success Metrics

- A design partner reaches first scan without contacting us
- Time from landing on the README to first findings: **under 30 minutes**
- Zero instances of a self-hoster unknowingly pointing at Teemops infrastructure

## Out of Scope

Deliberately excluded from this story:

- **Simplifying AWS onboarding** — promoting the manual role-ARN path or dropping the
  SNS/SQS requirement. That is D-3's revisit trigger and a separate Later item.
- **Contributor documentation** — code of conduct, issue templates, good-first-issues.
  Belongs with the public-release milestone (D-4).
- ~~**Publishing container images.** Also D-4.~~ **Done** — N-5 published them, which is
  what made the rest of this story possible.
- **Windows and non-Docker install paths.** Docker Compose is the supported deployment.
- **An uninstall command.** Documented teardown steps are enough for v1; build the command
  when someone asks.
- ~~**Serving the installer from a real URL.** Blocked on D-4 by decision 1 above.~~
  **Unblocked and done** — the repo is public.
- **A published SHA256 for the installer.** Still not done: the release workflow does not
  emit one. The README shows the download-and-read form instead, which is the more useful
  habit anyway. Promoted to a Next item rather than left as a silent gap.

## Related Practices

- [Product Practices](../practices/product.md) — simplicity first, MVP scope
- [Infrastructure Practices](../practices/infrastructure.md) — deployment and Docker
- [Security Practices](../practices/security.md) — no secrets in the repo
- [Code Quality Practices](../practices/code-quality.md) — the installer is code
