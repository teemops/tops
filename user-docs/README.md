# TOPS user documentation

The source for **docs.teemops.com** — documentation for the people *running* TOPS, as opposed
to `docs/`, which is the contributor and maintainer knowledge base. See
[D-10](../docs/roadmap.md#d-10-in-full-where-user-documentation-lives) for why the two are
kept apart.

**Status: first slice.** One page so far. The structure it will grow into is designed in
[`design/docs-site/information-architecture.md`](../design/docs-site/information-architecture.md)
— 34 pages across 8 sections, with a content map showing what already exists elsewhere in
the repo and needs porting rather than writing.

## What is here

| Page | For |
| --- | --- |
| [How TOPS connects to AWS](start-here/how-tops-connects-to-aws.md) | Security reviewers approving TOPS for an AWS organisation |

## Conventions

**Plain Markdown, no framework.** The renderer is deliberately undecided until there is
enough content to need navigation and search. Nothing here may depend on renderer-specific
syntax — no MDX, no components, no shortcodes. If a page cannot be written in CommonMark plus
tables, that is a signal about the page, not about the tooling.

**Install instructions live in `README.md`, not here.** Linking beats copying; two canonical
copies drift. Pages reference it rather than restating commands.

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

Pick one of the three archetypes in the IA document — **task**, **explainer**, or
**reference** — and follow its shape. Depth increases down the page: the first screen serves
a non-technical reader, the last third serves someone debugging. That is what lets one site
serve both without splitting into audience tracks.
