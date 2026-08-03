# Resolving a finding

How a finding leaves your open list, what the next scan does to that decision, and when TOPS
will overrule you.

The short version: **fix it in AWS and let a scan confirm.** Marking things resolved by hand
works, but it is a claim rather than evidence, and evidence wins.

## Before you start

- A finding you have looked at — see [Reading a finding](reading-a-finding.md)
- Permission to change the resource in AWS, or someone who has it

## The three statuses

| Status | Means | Set by |
| --- | --- | --- |
| **Open** | Failing as of the last scan that looked | TOPS, when the rule first fails |
| **Resolved** | No longer failing, or you say it is dealt with | TOPS after a scan, or you |
| **Ignored** | You have decided this one does not apply | You |

Resolved findings are hidden from the list by default. They are not deleted — filter by
status to see them.

## Fixing it properly

1. **Read the remediation on the finding.** Every rule has one, and for the critical and high
   rules it is specific enough to act on rather than a restatement of the problem.
2. **Make the change in AWS.** TOPS does not change anything in your accounts — it has no
   code that writes to them at all.
3. **Run a new scan** for that account.
4. **The finding resolves itself.** When a scan examines that resource and the rule now
   passes, TOPS marks it resolved, records the reason as *fixed*, and timestamps it. You do
   not have to tell it.

This is the path worth taking. The finding's history then says the rule passed on a given
date, which is a different and better claim from someone having ticked a box.

## Marking it resolved yourself

Use this when the fix is real but TOPS cannot see it — a compensating control it does not
know about, or a resource being decommissioned this week.

The finding is recorded as resolved with the reason *manual*, which stays distinguishable
from *fixed* forever. Nobody reading it later has to guess which kind of resolved it was.

**A later scan can overrule you.** If a scan finds the rule still failing on that resource,
the finding is **reopened** — status back to `open`, reason cleared. This is deliberate:
evidence that something is still failing is more useful than a record of someone's earlier
opinion, and a silent stale "resolved" is exactly how a real problem gets lost.

## Ignoring it

Use this when the rule genuinely does not apply — a bucket that is public because it serves a
public website, an account that will never have MFA because it has no console users.

**Ignored is respected.** A later scan finding it *still failing* leaves it ignored. That is
the difference between ignored and resolved: ignored is a standing judgement about the rule,
resolved is a claim about the state of the world, and only the second one can be contradicted
by evidence.

If a scan later finds the resource **compliant**, the finding is resolved and leaves your
open list, which is usually what you want.

## What a scan will and will not touch

| Situation | What happens |
| --- | --- |
| Open, still failing | Stays open |
| Open, now passing | **Resolved**, reason *fixed* |
| Resolved by you, still failing | **Reopened** |
| Resolved by you, now passing | Stays resolved |
| Ignored, still failing | **Stays ignored** |
| Ignored, now passing | Resolved |
| The scan did not examine that service or region at all | **Untouched.** Not resolved, not reopened |

That last row matters more than it looks. A scan that skipped a service tells you nothing
about it, so TOPS does not treat silence as good news. A finding only changes when a scan
actually looked.

## When it does not behave

**I fixed it, scanned, and it is still open.**
Check the scan covered the right service and region — a scan of a subset does not touch
findings elsewhere. Check the resource id on the finding matches the resource you changed.

**A finding I resolved came back.**
It was reopened because a scan found the rule still failing on that resource. Look at the
resource rather than the finding: something has been reverted, or the fix did not apply to
the resource TOPS is checking.

**I want it gone permanently.**
There is no delete. Ignore is the durable version of that decision, and it survives scans.

## Next

- [Reading a finding](reading-a-finding.md) — severities, filters, and the anatomy
- [How TOPS connects to AWS](../start-here/how-tops-connects-to-aws.md) — why TOPS cannot fix things for you
