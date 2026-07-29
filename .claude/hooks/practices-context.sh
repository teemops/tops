#!/usr/bin/env bash
# Injects the team's practices into agent context. Informational only — this never
# blocks a tool call or a prompt, so it stays out of the way during a hotfix.
#
# Wired up in .claude/settings.json:
#   session-start  — reminds every new session where the practices live
#   user-prompt    — when a prompt looks like feature work, points at the process
#
# Exits 0 silently on any failure. A broken reminder must not break a session.
set -uo pipefail

MODE="${1:-}"

case "$MODE" in
  session-start)
    cat <<'EOF'
Project practices are in docs/practices/ and the six-phase process is in
docs/processes/feature-development.md. New features follow that process — the
`feature-development` skill drives it. Before a PR, run `practices-review`.
EOF
    ;;

  user-prompt)
    command -v jq >/dev/null 2>&1 || exit 0

    prompt=$(jq -r '.prompt // ""' 2>/dev/null) || exit 0
    [ -n "$prompt" ] || exit 0

    # Only fire on prompts that read as new feature work. Deliberately narrow —
    # a reminder on every prompt is noise, and noise gets the hook deleted.
    echo "$prompt" | grep -qiE \
      '\b(add|build|implement|create|new)\b.{0,40}\b(feature|page|screen|endpoint|api|report|dashboard|workflow|integration)\b' \
      || exit 0

    jq -n '{
      hookSpecificOutput: {
        hookEventName: "UserPromptSubmit",
        additionalContext: (
          "This looks like new feature work. Follow docs/processes/feature-development.md: "
          + "a user story with Given-When-Then acceptance criteria comes before implementation, "
          + "and the `feature-development` skill drives the six phases. "
          + "If this is actually a bugfix, refactor or infrastructure change, skip the story "
          + "but still follow docs/practices/ and ship tests with the change."
        )
      }
    }'
    ;;
esac

exit 0
