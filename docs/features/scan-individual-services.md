# Scan Individual Services - User Story

**Roadmap:** F-2 · **Issue:** [#84](https://github.com/teemops/tops/issues/84) · **Agreed:** 2026-08-01

## User Story

As a solo engineer who has just changed one part of my AWS estate, I want to scan only the
services I care about, so that I can check my change in a minute instead of waiting for a
full scan of everything.

## Expected Behavior

The New Scan modal lists each available benchmark with its services underneath. Ticking a
benchmark selects all of its services and behaves exactly as today. Expanding it and ticking
individual services scans only those, evaluated against that benchmark's rules.

Before starting, the modal says what will actually run — how many services, which benchmark,
how many rules — because a scan costs time and API calls and the user should not have to
guess.

Nothing about the existing flow changes for someone who does not expand anything.

## User Acceptance Criteria

- [ ] Given the New Scan modal, when I open it, then each available benchmark is listed and
      can be expanded to show its services
- [ ] Given I tick a benchmark without expanding it, when I start the scan, then the scan runs
      exactly as it does today — same services, same rulesets
- [ ] Given I expand a benchmark and tick two of its services, when I start the scan, then
      only those two services are collected and only that benchmark's rules are evaluated
- [ ] Given I have selected services, when I look at the modal before submitting, then it tells
      me how many services and rules will run
- [ ] Given I select no benchmark and no service, when I try to start, then I see a validation
      error and no scan is created
- [ ] Given a benchmark whose ruleset is empty, such as PCI, when the modal renders, then it
      is not offered at all
- [ ] Given I submit a service that is not in the registry, when the request is validated
      server-side, then it is rejected with a validation error
- [ ] Given I submit a service that exists but has no rules in the chosen benchmark, when the
      request is validated, then it is rejected rather than running a scan that cannot produce
      a finding
- [ ] Given a scan request for another organization's AWS account, when it is submitted, then
      it is rejected — the account must belong to the caller's organization
- [ ] Given a scan of one service, when a later scan runs, then findings for other services
      are untouched (see [durable findings](./durable-findings.md))

## Technical Notes

**Most of this exists.** The API has accepted explicit `scan_types` since January,
`scans.scan_types` stores them, `ScanTypesService::getAllWithLabels()` enumerates services,
and `ScanProfilesService::getAvailableWithLabels()` **already returns each profile's services**
— the modal receives the tree and ignores it. `ServiceRegistry` derives all of it from each
service's `rules/tasks/<service>/tasks.json`.

**The one real change** is in `ScansController::store`. Today the two inputs are mutually
exclusive:

```php
if (!empty($profiles)) { /* profiles → services + rulesets */ }
else { $scanTypes = $validated['scan_types'] ?? []; $rulesets = ['basic']; }
```

Explicit services therefore always evaluate the **basic** ruleset, so "only S3, against CIS"
cannot be expressed. They become combinable: profiles determine the rulesets, and
`scan_types`, when present, **narrow** the services to a subset of those the profiles cover.

**Narrowing must be validated, not silently intersected.** Asking for CIS + DynamoDB is a
request for a scan that cannot produce a finding — DynamoDB has no CIS rules. Rejecting it
is kinder than running it and reporting nothing, which reads as "you are compliant".

**Rule counts for the modal** come from counting a ruleset's rules by service. Cheap, and
the rulesets are already loaded and cached.

**Out of scope:** selecting services across two benchmarks with different service sets in one
scan is allowed by this model and needs no special handling — the union of services is
collected, each ruleset evaluated against what it covers.

## Out of Scope

- Saving a service selection as a reusable custom profile — wait for someone to ask
- Per-region selection — a separate axis, not requested
- Changing what a benchmark means, or adding new benchmarks

## Success Metrics

- A single-service scan completes materially faster than a full scan of the same account
- The default path (tick a benchmark, start) takes the same number of clicks as today
- No increase in scans that complete with zero findings because of an impossible combination

## Related Practices

- [Product Practices](../practices/product.md) — simplicity first; the default path must not
  get longer for people who do not want this
- [Security Practices](../practices/security.md) — server-side validation of the service list;
  AWS account scoped to the caller's organization
- [Testing Practices](../practices/testing.md) — feature tests for the controller, including
  cross-organization rejection
