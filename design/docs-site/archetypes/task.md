<!--
ARCHETYPE 1 — TASK PAGE. The default; 18 of the 34 pages in the map.

Use when the reader is trying to do a thing right now. Get them to done, then help
them when it breaks.

Copy this file, delete the comments, replace everything. Keep the section order —
it is the order a reader needs things in, not a house style.

THE ONE RULE THAT MATTERS: depth increases down the page. Someone non-technical
gets what they need from the first screen. Someone debugging keeps scrolling. That
is what lets one site serve both without splitting into audience tracks.
-->

# Connect an AWS account

<!-- H1 is the task as a verb, from the reader's side of the screen. "Connect an AWS
     account", not "AWS account onboarding". Not the feature name — what they came to do. -->

Links one AWS account to TOPS so it can be scanned. Takes about five minutes, and most of
that is waiting for CloudFormation.

<!-- One or two sentences: what you will have at the end, and roughly how long.
     Time estimates are worth more than they cost — they set expectations and they are
     the first thing people look for. Be honest; round up. -->

## Before you start

- TOPS is installed and you can sign in
- You ran the AWS step during install — if you skipped it, run `./install.sh --aws-only` first
- You can create a CloudFormation stack in the account you want to scan, or you know who can

<!-- Short list, and only genuine blockers. If a prerequisite has its own page, link it
     rather than explaining it here. Resist listing things that are almost always true. -->

## Steps

1. **Open AWS accounts and choose Add account.** TOPS creates a pending record and
   generates a link that is specific to this account.

2. **Send the link to whoever administers the AWS account**, or open it yourself if that
   is you. It opens the CloudFormation quick-create page, pre-filled.

   The link carries an `ExternalId` generated for this account alone. Treat it like a
   password — anyone with it can complete this link.

3. **Create the stack.** The defaults are correct. Acknowledge the IAM capability
   checkbox — the stack creates a role, so AWS requires it.

   ```
   Stack name: tops-vendor-audit
   Status:     CREATE_IN_PROGRESS  →  CREATE_COMPLETE
   ```

4. **Wait for TOPS to pick it up.** Usually a few seconds after the stack completes. The
   account moves from *Pending* to *Active* on its own — you do not need to refresh or
   paste anything back.

<!-- Numbered steps, each starting with a bold imperative. Show real output, not
     paraphrased output — a reader compares what is on their screen to what is on the
     page, character by character.

     Do not document the UI's every affordance. Document the decisions the reader has to
     make, and the things that are not obvious from looking at the screen. -->

## When it goes wrong

**The stack sits at CREATE_IN_PROGRESS for more than a few minutes.**
TOPS never received the notification, so CloudFormation is waiting for a reply that is not
coming. Check that the AWS step ran during install (`TOPS_SQS_ARN` is set) and that the
account-linking worker is running: `docker compose logs worker | grep account-queue`.

**The stack fails with "Account not found".**
The link was generated for a different TOPS organisation, or the pending record was
deleted. Generate a fresh link and create the stack again.

**The account shows Active but scans return nothing.**
The role exists but cannot be assumed. Confirm the account is not in a region your
credentials cannot reach, and see [Troubleshooting](../running-the-service/troubleshooting.md).

<!-- Symptom in bold, then cause, then fix. Symptom first, because that is what the
     reader has — they do not know the cause yet, that is why they are here.

     Write the symptom in the words the reader would use, including the literal error
     text where there is one. This section is reached by searching for an error message. -->

## Next

- [Run your first scan](run-your-first-scan.md)
- [What the IAM role can do](../aws-accounts/what-the-iam-role-can-do.md) — if you were asked to justify the permissions

<!-- Two or three links, the ones a reader actually needs next. Not a sitemap. -->
