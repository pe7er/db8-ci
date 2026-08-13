# db8 CI

Shared release automation and site provisioning for the db8 Joomla extension
packages.

- `.github/workflows/release.yml` — reusable workflow every package repo calls
- `release-workflow.md` — how releasing works end to end

One workflow serves all nine package repos, so a change to the release process
is made once here rather than nine times.

## Provisioning scripts

These build and maintain extensions.db8.nl — the site that serves the downloads
and the update feeds. Copy them into the Joomla root's `cli/` directory and run
them from there:

```bash
cp *.php /path/to/joomla/cli/
cd /path/to/joomla
php cli/provision-downloads.php    # catalogue, files, checksums, update streams
php cli/provision-licensing.php    # customer group, whitelist, plan, licence key
php cli/create-update-menu.php     # /updates and /updates/<package>
php cli/provision-site.php         # articles, menus, modules
```

Run them in that order the first time: `create-update-menu.php` reads the
streams the first script creates, and `provision-site.php` reads the downloads.

| Script | What it does |
|---|---|
| `db8-cli-bootstrap.php` | Shared bootstrap. Not run directly. |
| `packages.php` | The catalogue: one entry per package, with the copy for its page. |
| `provision-downloads.php` | Category, nine downloads, nine versions, places each zip in storage, hashes it, then derives the com_db8updates streams. |
| `provision-licensing.php` | The gating chain: customer user group, per-download whitelist, plan, test customer with an active subscription and a licence key. |
| `provision-site.php` | Home and documentation articles, main and customer menus, module positions. |
| `create-update-menu.php` | The hidden `updates` menu that gives each feed a readable URL. |

All of them are idempotent — every create is an upsert on a natural key, so
re-running applies only what changed. That is the intended way to publish a new
release: bump the version in `packages.php` and re-run `provision-downloads.php`.

They must live one level under the Joomla root: the bootstrap derives
`JPATH_BASE` from `dirname(__DIR__)`.

### Adding a package

Add an entry to `packages.php` — `element` must equal the `<name>` in the
package manifest exactly — and re-run `provision-downloads.php` followed by
`create-update-menu.php`.
