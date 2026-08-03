# The security model

> **Written for security reviewers.** If you have been asked to approve TOPS, this page is
> the whole answer for the product itself. For the AWS-specific boundary — what crosses
> the wire into an account you scan — see
> [How TOPS connects to AWS](../start-here/how-tops-connects-to-aws.md) instead; this page
> doesn't repeat it.

TOPS is a single Laravel application and one MySQL database, self-hosted on infrastructure
you control. There is no hosted control plane, no telemetry endpoint, and no path for your
data to leave your install other than the ones you configure yourself (email, and the AWS
calls the other page covers).

## At a glance

| Layer | What protects it | Enforced by |
| --- | --- | --- |
| Login | Password auth by default; OAuth available if you opt into Firebase | `Auth/` controllers, session middleware |
| Cross-organisation access | Every query touching accounts, scans or findings is scoped to the signed-in user's organisation | `SetOrganizationContext` middleware, applied to every web and API route |
| Team roles | Four roles — owner, administrator, auditor, viewer — gate who can invite, remove, or change settings | `OrganizationPermission` service |
| Data at rest | Delegated to your host's disk encryption and database permissions, not the application | See *What we do not claim* below |

## Authentication

The default, and the only path that runs without any external dependency, is ordinary
email and password — Laravel's own session-based auth. A second path exists for operators
who want Google, GitHub or Microsoft sign-in: it's built, tested, and off by default
(`FIREBASE_USER_AUTH=false` in the Docker configuration), because the default install must
never require a Firebase project. Turning it on is a configuration change, not a code
change.

## Authorization and data isolation

Every user belongs to one or more organisations, and every row that matters — AWS
accounts, scans, findings — is scoped to one. That scoping is applied once, in middleware,
to every web and API route, rather than repeated per-controller: the practice this
codebase holds itself to is that every endpoint touching tenant data has a test proving a
different organisation cannot read it.

Within an organisation, four roles — owner, administrator, auditor, viewer — decide who
can invite members, change roles, or remove someone. **This part has a known rough edge**:
some code comments describe a narrower rule ("only the owner can manage members") than the
code actually enforces, and the internal plan document agrees with the comments, not the
code. It's tracked and open, not silently wrong — see roadmap item X-5 in
[`docs/roadmap.md`](https://github.com/teemops/tops/blob/develop/docs/roadmap.md) if you
want the specifics before relying on the exact boundary.

## Data at rest

TOPS stores the IAM role ARN for each connected account, and nothing more sensitive than
that — no AWS access keys are ever requested or stored. That ARN is kept in plaintext, by
a deliberate decision recorded as **D-9** in the roadmap: an ARN alone grants nothing
without the trust policy and `ExternalId` that actually gate `AssumeRole`, and on a
single-host self-hosted install, application-level encryption would put the key on the
same disk as the data it protects — real encryption at rest, on this deployment model, is
your disk encryption and your database permissions, not a layer TOPS adds on top.

## What we do not claim

- **The child-account IAM role is not read-only.** It carries write permissions for
  operational features that don't exist yet in the product. See
  [What the IAM role can do](../aws-accounts/what-the-iam-role-can-do.md) for the
  policy-by-policy detail, and how to strip the parts you don't want before you hand out
  the link.
- **There is no application-level encryption at rest.** See *Data at rest* above — this
  was a deliberate removal, not an oversight, and D-9 explains why.
- **Anyone with shell access to your host can read the database.** This is a self-hosted
  application; an operator with a shell is trusted by design. There is no protection
  against your own operators, and none is planned.
- **The member-permissions boundary has a known inconsistency**, described above, until
  roadmap item X-5 closes it.

## Currently known issues

Publicly tracked, with what's already been done about each: see the
[Already known](https://github.com/teemops/tops/security/policy) section of
`SECURITY.md` in the repository, rather than this page keeping its own copy that can drift
out of date.

## Related

- [How TOPS connects to AWS](../start-here/how-tops-connects-to-aws.md)
- [Reporting a vulnerability](reporting-a-vulnerability.md)

*Source of truth for this page: `docs/roadmap.md` decisions D-1, D-2 and D-9,
`docs/PROGRESS.md`, and `SECURITY.md` in the TOPS repository.*
