# Run your first scan

Starts a scan against a connected AWS account and gets you to a list of findings. A full
scan of one account takes a few minutes; you don't have to wait on the page for it.

## Before you start

- At least one AWS account showing **Active** — see
  [Connect your first AWS account](connect-your-first-aws-account.md).

## Steps

1. **Open Scans, and choose New Scan.** If this is the account's first scan, you'll land
   here already — the empty state offers the same button.

2. **Choose the AWS account.** Only accounts showing **Active** appear in the list; if
   none do, the modal tells you to add one first.

3. **Choose what to scan.** Each row is a scan profile — the general "basic" baseline and
   the CIS AWS Foundations Benchmark — shown with its service and rule counts. Ticking a
   profile scans everything it covers. Expand one to tick individual services instead; as
   you narrow it down, a summary line updates to say exactly what will run, for example
   *"Scanning 2 of 11 services against Basic — 12 rules."*

   You don't have to choose one or the other. Both profiles combine, and a service common
   to both is only scanned once.

4. **Choose Start Scan.** The scan begins immediately as **Pending**, then **Running**
   once a worker picks it up — the list updates on its own every few seconds, so you don't
   need to refresh.

5. **Choose View once it's Completed.** That's the scan detail page: what ran, how long it
   took, and a severity breakdown that links straight into
   [Findings](../using-tops/reading-a-finding.md), filtered to what that scan found.

## When it goes wrong

**"No active AWS accounts available."**
The account you want to scan isn't showing Active yet. See
[Connect your first AWS account](connect-your-first-aws-account.md) — its "when it goes
wrong" section covers a stack that's stuck.

**A scan stays "Pending" and never moves to "Running".**
Nothing is picking jobs up. Check the worker is alive:
`docker compose logs -f worker`. If you just started TOPS, give it a few seconds — the
worker and the app start at roughly the same time.

**A scan stays "Running" much longer than you'd expect.**
Check the same worker log. To clear a scan that's genuinely stuck rather than just slow:

```bash
docker compose exec app php artisan scans:mark-stale-region-complete --dry-run
```

Drop `--dry-run` once you're happy with what it lists. A scan cleared this way is marked
**partial**, not clean — it means "we didn't finish looking," not "nothing was found."

**A service you use shows no findings at all.**
Either nothing failed, or TOPS doesn't have rules for that service yet. It currently
covers IAM, S3, RDS, EC2, CloudTrail, KMS, Lambda, SQS, SNS, DynamoDB and ELBv2 — an
uncovered service looks exactly like a clean one, which is worth knowing before you treat
a quiet result as good news.

## Next

- [Reading a finding](../using-tops/reading-a-finding.md)
- [Resolving a finding](../using-tops/resolving-a-finding.md)

*Source of truth for this page: `Pages/Scans/Index.vue` and `Pages/Scans/NewScanModal.vue`
in the TOPS repository.*
