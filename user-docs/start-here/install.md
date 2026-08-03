# Install TOPS

Gets TOPS running on your own machine. Five to ten minutes, most of it waiting for Docker
to pull images.

## Before you start

- **Docker, with Compose v2.** That's the whole list — no PHP, no Composer, no Node, no
  npm, no database to set up separately.
- You do **not** need an AWS account or a Firebase project to try TOPS. The default login
  is ordinary email and password, and connecting a real AWS account is a separate,
  explicit step you can skip and come back to.

## Steps

1. **Run the installer.** The one-line command is in the
   [README's Install section](https://github.com/teemops/tops#install) — copy it from
   there rather than from here, so there's exactly one canonical copy. It pulls the
   published images from Docker Hub, generates a `.env` with its own application key, and
   starts the stack.

2. **Answer the AWS question.** Partway through, the installer asks whether you want to
   connect an AWS account now. It's safe to say no — nothing about running TOPS or trying
   it out depends on that answer, and you can run it later. See
   [Connect your first AWS account](connect-your-first-aws-account.md).

3. **Open the app and register.** Once the installer says it's up, go to
   **http://localhost:8080** and create an account — the first one you create is yours.
   Sign-up and password-reset emails are captured locally at **http://localhost:8090**
   instead of being sent anywhere, so nothing needs an SMTP server to get started.

## When it goes wrong

**"the Docker daemon is not reachable."**
Docker is installed but your user can't talk to it yet. See the README's
[troubleshooting section](https://github.com/teemops/tops#if-something-goes-wrong) — the
fix is a group membership change and a re-login.

**Port 8080 is already in use, or a container with that name already exists.**
Both are covered, with the exact fix, in the same
[troubleshooting section](https://github.com/teemops/tops#if-something-goes-wrong).

**Something else.** [`DEBUG.md`](https://github.com/teemops/tops/blob/develop/DEBUG.md) in
the repository has more symptoms and causes than fit on this page.

## Next

- [Connect your first AWS account](connect-your-first-aws-account.md)
- [What TOPS is — and what it isn't](what-tops-is.md) — if you haven't read it yet

*Source of truth for this page: the README's Install section, kept there deliberately so
installation has exactly one canonical copy.*
