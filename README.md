# db8 CI

Shared release automation for the db8 Joomla extension packages.

- `.github/workflows/release.yml` — reusable workflow every package repo calls
- `release-workflow.md` — how releasing works end to end

One workflow serves all nine package repos, so a change to the release process
is made once here rather than nine times.
