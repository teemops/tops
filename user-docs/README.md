# TOPS user documentation

The source for **docs.teemops.com** — documentation for the people *running* TOPS, as opposed
to `docs/`, which is the contributor and maintainer knowledge base. See
[D-10](../docs/roadmap.md#d-10-in-full-where-user-documentation-lives) for why the two are
kept apart.

**Status: first slice.** Two pages so far. The structure it will grow into is designed in
[`design/docs-site/information-architecture.md`](../design/docs-site/information-architecture.md)
— 34 pages across 8 sections, with a content map showing what already exists elsewhere in
the repo and needs porting rather than writing.

## What is here

| Page | For |
| --- | --- |
| [How TOPS connects to AWS](start-here/how-tops-connects-to-aws.md) | Security reviewers approving TOPS for an AWS organisation |
| [What the IAM role can do](aws-accounts/what-the-iam-role-can-do.md) | The same reviewers, once they ask what the permissions actually grant |

## Conventions

**Plain Markdown, no framework.** The renderer is deliberately undecided until there is
enough content to need navigation and search. Nothing here may depend on renderer-specific
syntax — no MDX, no components, no shortcodes. If a page cannot be written in CommonMark plus
tables, that is a signal about the page, not about the tooling.

**Install instructions live in `README.md`, not here.** Linking beats copying; two canonical
copies drift. Pages reference it rather than restating commands.

**No links to pages that do not exist yet.** The IA maps 34 pages; only some are written. A
page may link forward only once the target is real — otherwise the site ships dead links,
which cost more trust than a missing cross-reference. Until a target exists, link the
repository file it will be based on, or leave the link out. Check before committing:

```bash
python3 - <<'EOF'
import pathlib, re
bad = []
for md in pathlib.Path("user-docs").rglob("*.md"):
    text = re.sub(r"```.*?```", "", md.read_text(), flags=re.S)
    for m in re.finditer(r"\]\((?!https?:|#)([^)#]+)(#[^)]*)?\)", text):
        if not (md.parent / m.group(1)).resolve().exists():
            bad.append(f"{md}: -> {m.group(1)}")
print("\n".join(bad) or "all relative links resolve")
EOF
```

**Do not document what does not exist.** Verified against `docs/PROGRESS.md`. Firebase
sign-in is built but off by default, MFA is gated, and report export, scheduled scans and
multi-cloud are not started — none get a page.

### Diagrams

Standalone SVG files in `assets/diagrams/`, referenced as linked images so one click opens
them full size:

```markdown
[![Description of what the diagram shows](../assets/diagrams/name.svg)](../assets/diagrams/name.svg)
```

**Author at a 900px viewBox**, with body text at 12.5px, mono at 13px and titles at 15.5px.
That is what keeps a diagram readable when it is scaled into a docs column — at 900px it
renders near 1:1 on a laptop and stays above 10px down to an 820px viewport. Wider diagrams
do not survive: the same content at a 1160px viewBox scaled to 7.5px body text and was
unreadable. Stack zones vertically rather than side by side; that is what buys the room for
larger type.

Four rules, learned the hard way when the first three diagrams were extracted from inline
HTML:

1. **Numeric character references only.** `&#8212;`, not `&mdash;`. A standalone `.svg` is
   parsed as XML, where HTML named entities are undefined and the file fails to render
   entirely.
2. **No angle brackets anywhere in the SVG's CSS**, including inside comments — an XML parser
   reads them as tags.
3. **Each SVG carries its own theme.** An image-embedded SVG cannot inherit page tokens, so
   every diagram needs its own `@media (prefers-color-scheme: dark)` block.
4. **Check the geometry, do not eyeball it.** Both defects in the first set were a card
   overlapping another card and a text run escaping its card — invisible in review, obvious
   to a script that compares every `getBBox()` against its containing rectangle.

Validate before committing — a malformed SVG shows as a broken image, which looks like a
missing file rather than a syntax error:

```bash
python3 -c "import xml.etree.ElementTree as ET,sys; [ET.parse(f) for f in sys.argv[1:]]; print('ok')" user-docs/assets/diagrams/*.svg
```

### Writing a new page

Copy the matching archetype and replace it. The guidance is inline, as comments that do not
render:

| Archetype | Use for | Template |
| --- | --- | --- |
| **Task** | Someone doing a thing right now | [`task.md`](../design/docs-site/archetypes/task.md) |
| **Explainer** | Someone deciding whether to trust something | [`explainer.md`](../design/docs-site/archetypes/explainer.md) |
| **Reference** | Someone who arrived from search and wants to leave | [`reference.md`](../design/docs-site/archetypes/reference.md) |

Seven rules, in full in the
[IA document](../design/docs-site/information-architecture.md#house-voice):

1. **Depth increases down the page** — first screen for a non-technical reader, last third
   for someone debugging. This is what lets one site serve three audiences.
2. **Write from the reader's side of the screen** — they manage accounts and findings, not
   rows and rule evaluations.
3. **Symptom before cause** — troubleshooting is reached by searching an error message.
4. **Show real output**, not paraphrased output.
5. **Never claim more than the code does.** The child IAM role is not read-only, so no page
   says it is. Every explainer carries a *What we do not claim* section.
6. **Link, do not restate.** Two canonical copies drift, and the stale one gets followed.
7. **No page for a feature that does not exist** — check `docs/PROGRESS.md`, not the roadmap.
