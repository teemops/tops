<!--
ARCHETYPE 2 — EXPLAINER. 6 of the 34 pages in the map.

Use when the reader is deciding whether to trust something, not trying to do
something. Architecture, security model, permissions, data handling.

This is the highest-stakes archetype and the smallest audience. A reviewer is a
gate: one unanswered question on their list and the other two audiences never
arrive. Written badly it does not merely fail to convince — it actively costs
trust, because a reader who catches one overstatement stops believing the rest.

THE RULE: diagram first, prose second, specifics last. And never claim more than
the code does. See "What we do not claim" below — that section is the whole
archetype in miniature.

Worked example of this archetype:
user-docs/start-here/how-tops-connects-to-aws.md
-->

# Where your data lives

> **Written for security reviewers.** If you have been asked to approve TOPS, this page is
> the whole answer. No prior knowledge of TOPS is assumed.

<!-- The audience callout. One line. It is the only concession the site makes to
     audience-based navigation, and it exists because a reviewer arriving from a vendor
     review needs to know in one glance that this page was written for them.

     "No prior knowledge assumed" is a promise. Keep it — no unexplained product nouns. -->

Every finding TOPS produces is written to a MySQL database on a server you run. Nothing is
sent anywhere else. This page shows where each category of data is stored, who can read it,
and what happens to it when you remove an account or uninstall.

<!-- The thesis, in two or three sentences. State the claim plainly and up front. A
     reviewer should be able to stop reading here and have the answer; everything below
     is them checking your work. Never open with background or history. -->

![Description of what the diagram shows, written as a full sentence for screen readers and for anyone the image fails to load for.](../assets/diagrams/name.svg)

<!-- Lead with whatever answers "what is the shape of this" fastest, because that is the
     question under all the other questions. Usually a diagram. Sometimes not: on a
     permissions page an at-a-glance table beats any diagram, because the shape *is*
     counts and categories. What matters is that the reader can stop after the first
     screen and have the answer. See "At a glance" in
     user-docs/aws-accounts/what-the-iam-role-can-do.md for the table form.

     Authoring rules are in user-docs/README.md and they are not optional: 900px viewBox,
     numeric entities only, own dark-mode block, geometry checked. Wrap it in a link to
     itself so it can be opened full size:
     [![alt](../assets/diagrams/name.svg)](../assets/diagrams/name.svg) -->

## What the diagram does not say

Prose here — the qualifications, the edge cases, the "except when". The diagram carries the
shape; this carries the caveats that would clutter it.

<!-- One or two sections of prose, not five. If you need five, the page is really two
     pages. -->

## The specifics

| Data | Stored in | Readable by | Removed when |
| --- | --- | --- | --- |
| Findings | Your MySQL, `findings` table | Members of the owning organisation | The account is unlinked |
| IAM role ARNs | Your MySQL, `aws_accounts` | Members of the owning organisation | The account is unlinked |
| Scan logs | Container stdout, your host | Anyone with shell on the host | Your log rotation says so |

<!-- The honest table. This is what a reviewer screenshots and pastes into their own
     report, so it has to survive being read without the surrounding prose.

     Include the row that is awkward. A table with no awkward row reads as marketing and
     gets trusted less than one that admits something. -->

## What we do not claim

- TOPS does not encrypt findings at the application layer. Encryption at rest is delegated
  to the host — see [D-9](https://github.com/teemops/tops/blob/develop/docs/roadmap.md).
- Anyone with shell access to your host can read the database. There is no protection
  against your own operators.

<!-- THE MOST IMPORTANT SECTION ON THE PAGE. Every explainer gets one.

     List the things a reader might reasonably assume that are not true. A reviewer's job
     is to find the gap between what you claim and what you do; handing them the gap
     yourself is what buys credibility for everything else on the page.

     Rules:
       - Never write "read-only" unless every permission is read. The TOPS child role is
         not read-only, and the architecture page says so in bold.
       - Never claim a control that is not implemented yet. If it is on the roadmap, say
         it is on the roadmap, and link the issue.
       - If a claim needs a qualifier, put the qualifier in the same sentence. A caveat
         three paragraphs later reads as a retraction. -->

## Related

- [How TOPS connects to AWS](../start-here/how-tops-connects-to-aws.md)
- [Backups and restore](../running-the-service/backups-and-restore.md)

*Source of truth for this page: `path/to/file` in the TOPS repository.*

<!-- Name the files this page describes. It tells a sceptical reader where to check, and
     it tells the next maintainer what to re-read when the code changes. -->
