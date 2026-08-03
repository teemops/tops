# Architecture explainer — design exploration

`aws-integration.html` is the standalone page that was built first, to work out how to
explain the AWS integration to a security reviewer. It did its job twice over: it settled the
structure of the explainer archetype, and writing it is what surfaced the open SNS topic now
tracked as [N-11](../../docs/roadmap.md#n-11--lock-down-the-account-linking-sns-topic).

**It is not the canonical version.** That is
[`user-docs/start-here/how-tops-connects-to-aws.md`](../../user-docs/start-here/how-tops-connects-to-aws.md),
which is what publishes to docs.teemops.com. If the two disagree, the docs page wins.

The difference that matters: this page embeds its diagrams inline at a 1160px viewBox, sized
for a full-bleed layout. The docs page uses standalone 900px SVGs in
`user-docs/assets/diagrams/`, re-authored so the text survives being scaled into a docs
column. See the sample-test findings in
[`design/docs-site/information-architecture.md`](../docs-site/information-architecture.md).

Kept because the exploration is worth having on record, not because it needs maintaining.
**Do not edit it to track product changes** — edit the docs page. This one can be deleted
whenever it stops being interesting.
