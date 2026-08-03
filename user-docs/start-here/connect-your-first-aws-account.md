# Connect your first AWS account

Links one AWS account to TOPS so it can be scanned. Two stages: setting up messaging in a
parent account (once, ever), then linking the account you actually want scanned (once per
account). Together, about ten minutes plus however long you take to find someone who can
create a CloudFormation stack.

## Before you start

- TOPS is installed and you can sign in — see [Install](install.md).
- The AWS CLI is configured on the machine you installed TOPS on, with permission to
  create CloudFormation stacks, SNS topics, SQS queues and an S3 bucket. This is only
  needed once, for the parent-account step below — it is not needed for every account you
  connect afterward.
- You can create a CloudFormation stack in the account you want to scan, or you know who
  can. It does not need to be the same account or the same AWS credentials as the step
  above.

## Steps

### 1. Set up messaging, if you haven't already

If you said yes to the AWS question during install, this is done — skip to step 2.

Otherwise, from the directory the installer created (`tops/`, unless you set `TOPS_DIR`):

```bash
./install.sh --aws-only
```

It tells you which AWS account it's about to deploy into — read from
`aws sts get-caller-identity` — and lists what it creates before doing anything: two
CloudFormation stacks, an SNS topic, four SQS queues, and an S3 bucket, all in an account
and region you choose. If your AWS CLI isn't configured, it says so and skips the step
rather than failing halfway; you can run it again once it is.

This is the *only* step that needs your own AWS credentials on the machine running TOPS.
Nothing in step 2 does.

### 2. Add the account you want scanned

1. **In TOPS, open AWS accounts and choose Add account.** TOPS creates a pending record
   and generates a CloudFormation quick-create link that's specific to this account.

2. **Send the link to whoever administers the AWS account**, or open it yourself if that's
   you. It opens the CloudFormation quick-create page in the AWS console, pre-filled.

   The link carries an `ExternalId` generated for this account alone. Treat it like a
   password — anyone with it can complete this link.

3. **Create the stack.** The defaults are correct. Acknowledge the IAM capability
   checkbox — the stack creates a role, so AWS requires it.

   ```
   Stack name: tops-vendor-audit
   Status:     CREATE_IN_PROGRESS  →  CREATE_COMPLETE
   ```

4. **Wait for TOPS to pick it up.** Usually a few seconds after the stack completes. The
   account moves from *Pending* to *Active* on its own — nothing to refresh or paste back.

## When it goes wrong

**The stack sits at CREATE_IN_PROGRESS for more than a few minutes.**
TOPS never received the notification, so CloudFormation is waiting for a reply that isn't
coming. Confirm step 1 actually completed — check for `TOPS_SQS_ARN` in your `.env` — and
that the worker is running: `docker compose logs worker | grep account`.

**"Add AWS Account" says messaging is not configured.**
Step 1 hasn't run yet, or it failed partway. Run `./install.sh --aws-only` again; its log
is `generated/install.log`.

**The stack fails with "Account not found".**
The link was generated for a different TOPS organisation, or the pending record was
deleted. Generate a fresh link from AWS accounts and create the stack again.

**The account shows Active but a scan returns nothing.**
The role exists but can't be assumed — usually a region your credentials can't reach.
`docker compose logs -f worker` shows what the scan actually tried.

## Next

- [Run your first scan](run-your-first-scan.md)
- [What the IAM role can do](../aws-accounts/what-the-iam-role-can-do.md) — if you were
  asked to justify the permissions before creating the stack

*Source of truth for this page: [How TOPS connects to AWS](how-tops-connects-to-aws.md),
which shows the same two stages as diagrams, and the README's
[Scanning a real AWS account](https://github.com/teemops/tops#scanning-a-real-aws-account)
section.*
