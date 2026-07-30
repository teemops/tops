# APP_KEY Rotation - User Story

> ## ❌ NOT BUILT — rejected 2026-07-30, superseded by decision D-9
>
> This was specified in response to the 2026-07-30 `APP_KEY` exposure, then rejected in
> favour of **removing the encryption that made rotation hard**. See
> [D-9 in the roadmap](../roadmap.md#d-9-in-full-encryption-at-rest-and-why-we-removed-some).
>
> `aws_accounts.iam_role_arn` was the only `Crypt`-encrypted field in the application. An
> ARN is an identifier rather than a credential, the ExternalId that actually gates
> `sts:AssumeRole` was already plaintext and indexed beside it, and in a single-host
> deployment `APP_KEY` lives in `.env` next to the database. So the encryption was
> inconsistent and bought little, while forcing a bespoke rotation subsystem.
>
> **Rotating `APP_KEY` is now `php artisan key:generate`** — native Laravel, no data
> migration. Users are logged out and outstanding email-verification links break; nothing
> else is affected.
>
> **Kept, not deleted.** If TOPS ever stores a genuine credential — an access key, a token,
> a password — this is the starting design, and the `APP_PREVIOUS_KEYS` sequencing below is
> the part worth not rediscovering: it is what removes the window in which data is
> unreadable. The rest of this document is preserved as written.

---

Status: ~~awaiting agreement~~ **rejected.** Prompted by the 2026-07-30 credential exposure,
where `APP_KEY` leaked in `infra/scripts/test/app.env` and no safe way to rotate it existed.

## User Story

As an operator who has leaked or suspects the loss of my `APP_KEY`, I want to rotate it with
a single command that re-encrypts my data and proves it worked, so that I can respond to an
exposure in minutes without risking the data the key protects.

## Expected Behavior

The operator runs one command. It generates a new key, records the old one as a *previous*
key so nothing is ever unreadable, re-encrypts every registered field, verifies each row
decrypts under the new key alone, and then retires the old key. Output names every step,
every table touched and every row count, so the operator can see what happened rather than
trusting it.

If any step fails, the command stops and the application keeps working, because the old key
stays valid until verification passes.

## User Acceptance Criteria

**The happy path**

- [ ] Given a populated database, when I run the rotation command, then a new `APP_KEY` is written to `.env` and every registered encrypted field is re-encrypted under it
- [ ] Given the rotation completes, when it verifies, then it reports the row count checked per field and confirms each decrypts with the new key alone
- [ ] Given the rotation completes, when I read the output, then I can see the old key was backed up, where to, and that it was retired at the end
- [ ] Given I run the command again immediately, when it completes, then it is a no-op on already-rotated rows rather than double-encrypting

**Failing safely — the part that matters**

- [ ] Given the command is interrupted at any point, when the application next reads an encrypted field, then decryption still succeeds — because the old key remains in `APP_PREVIOUS_KEYS` until verification passes
- [ ] Given any row fails to decrypt during re-encryption, when the command notices, then it aborts before retiring the old key, reports which rows failed, and leaves `.env` able to read everything
- [ ] Given a row cannot be decrypted with *any* known key, when the command runs, then it is reported as pre-existing corruption and skipped, not silently overwritten
- [ ] Given no plaintext is ever required on disk, when I inspect the filesystem after a run, then no decrypted value was written anywhere

**Not going stale**

- [ ] Given a developer adds a newly encrypted field without registering it, when the test suite runs, then a test fails naming the unregistered field
- [ ] Given the registry lists a field, when the rotation runs, then that field is covered without any code change to the command

**Refusing to run when it shouldn't**

- [ ] Given `.env` is absent or has no `APP_KEY`, when I run the command, then it stops with an explanation and changes nothing
- [ ] Given I have not confirmed, when I run the command in production without `--force`, then it prompts before modifying anything
- [ ] Given the database is unreachable, when I run the command, then it fails before touching `.env`

## Technical Notes

### Tooling: `php artisan`, not a bash script

The work requires `Crypt` and Eloquent to decrypt and re-encrypt, so it has to run inside the
framework. A `rotate_keys.sh` wrapper would only shell into artisan, adding a layer that can
drift without doing anything. One command, following the existing `namespace:verb`
convention (`scan:validate-rules`, `aws:process-sqs`):

```bash
php artisan security:rotate-app-key
```

### The sequence is deliberately not the obvious one

The intuitive order — decrypt everything, swap the key, re-encrypt — has a window in which
the data cannot be read: after the key changes and before re-encryption finishes. A crash,
an OOM kill or a Ctrl-C in that window leaves ciphertext no key can open. It also implies
holding plaintext somewhere, which is the thing we are trying to protect.

Laravel already solves this, and `config/app.php:102` is already wired for it:

| # | Step | Why |
| --- | --- | --- |
| 1 | Preflight — `.env` writable, `APP_KEY` present, DB reachable, registry non-empty | Fail before changing anything |
| 2 | Back up `.env` to `storage/app/key-rotation/.env.backup-<timestamp>` (0600) | The old key is recoverable if everything else goes wrong |
| 3 | Generate the new key; write `APP_KEY=<new>` **and** `APP_PREVIOUS_KEYS=<old>` | From here, `Crypt` reads ciphertext under *either* key. There is no unreadable window |
| 4 | Re-encrypt row by row: read (decrypts under old or new), write (encrypts under new) | Idempotent and resumable — a re-run is harmless |
| 5 | Verify: decrypt every row with the new key **only** | Proves the rotation before committing to it |
| 6 | Remove `APP_PREVIOUS_KEYS` | The old key stops being accepted only once step 5 passed |

Step 3 before step 4 is the whole trick: the old key is still honoured, so an interruption
degrades to "some rows re-encrypted, all rows readable" instead of data loss.

### The registry

A single file, `config/encryption.php`:

```php
return [
    'encrypted_attributes' => [
        \App\Models\AwsAccount::class => ['iam_role_arn'],
    ],
];
```

Today that is the only encrypted field in the application. The registry exists so rotation
does not need editing when the second one arrives — and so that forgetting to register a
field is a *test failure* rather than a field that silently misses rotation and becomes
unreadable at the next key change. That guard test is the reason the registry earns its
keep; without it, this is a list that goes stale and lies.

### A defect to fix in the same change

`AwsAccount::getIamRoleArnAttribute()` catches decryption failures and returns the raw
ciphertext (`AwsAccount.php:68-71`). That makes a failed rotation invisible: scans would run
against ARN-shaped ciphertext and fail confusingly, far from the cause. The rotation command
must therefore use `Crypt::decryptString()` directly rather than trusting the accessor, and
the accessor should log a warning instead of silently returning junk.

### Scope

Rotation covers **application-level encryption only** — the `Crypt` facade and `encrypted`
casts. Out of scope, and named so nobody assumes otherwise:

- Session and cookie invalidation. Rotating `APP_KEY` logs everyone out; that is expected and
  needs stating in the output, not preventing.
- Password hashes. Bcrypt does not use `APP_KEY` and is unaffected.
- Anything encrypted outside the application — S3 SSE, RDS at-rest, secrets in AWS.
- Rotating the leaked credentials themselves. Different problem, done by hand.

## Success Metrics

- An operator who has leaked `APP_KEY` can rotate it in under five minutes
- Zero rows unreadable after a rotation, including an interrupted one
- A new encrypted field cannot ship without being registered — enforced by a failing test

## Related Practices

- [Security Practices](../practices/security.md) — encryption at rest, no secrets in the repo
- [Testing Practices](../practices/testing.md) — the guard test and the round-trip test
- [Code Quality Practices](../practices/code-quality.md) — the command is code, not a script
- [Database Practices](../practices/database.md) — chunked writes, no full-table load
