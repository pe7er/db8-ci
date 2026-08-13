# Releasing db8 extension packages

How a change in `src/` becomes an update offered inside a customer's Joomla
back-end.

## The moving parts

| Where | What it holds |
|---|---|
| `pe7er/db8<name>` (×9, private) | extension sources, package manifest, build script |
| `pe7er/db8-ci` | the release workflow all nine repos call |
| GitHub release | build artefact: the package zip and its SHA512 |
| extensions.db8.nl → com_db8downloads | the file customers actually download |
| extensions.db8.nl → com_db8updates | the update feed Joomla polls |

GitHub stores source and builds packages. It does **not** serve customers: the
repos are private, so `raw.githubusercontent.com` and release assets both return
404 to an unauthenticated Joomla site. Everything customer-facing is served by
extensions.db8.nl.

## The nine packages

`db8access` · `db8downloads` · `db8invoices` · `db8licenses` · `db8payment` ·
`db8setup` · `db8support` · `db8tickets` · `db8updates`

Each is one repo, one package, one update stream. A package may contain several
extensions — `db8payment` ships a component and eight plugins.

---

## One-time setup

### 1. Create the repositories

Nine package repos plus `db8-ci`, all private except as noted:

```bash
cd combined-to-commit-to-git-repos/db8access
git init && git add -A && git commit -m "Import from live"
gh repo create pe7er/db8access --private --source=. --push
```

The nine package repos are private. `db8-ci` is **public**, which is what lets
them call its reusable workflow with no extra configuration — a private
reusable workflow would need *Settings → Actions → General → Access* opened up
in `db8-ci` first. It holds no extension source, only build steps and setup
notes.

### 2. Provision the catalogue and derive the streams

The site is built by two scripts in this repo, copied into the Joomla root's
`cli/` directory and run from there:

```bash
cp db8-ci/*.php /path/to/joomla/cli/
php cli/provision-downloads.php    # category, downloads, versions, files, checksums, streams
php cli/provision-licensing.php    # customer group, whitelist, plan, test licence key
php cli/create-update-menu.php     # /updates and /updates/<package>
php cli/provision-site.php         # articles, menus, modules
```

All four are idempotent — re-run them after building new packages and only the
changes are applied.

`provision-downloads.php` populates **com_db8downloads** and then calls
`DownloadsStreamSync::sync()`, which derives one com_db8updates stream per
download and one version per download-version. Streams are never written by
hand: com_db8updates ships no admin edit form for streams or versions
(`StreamModel::getForm()` and `VersionModel::getForm()` both return `null`),
because the downloads catalogue is meant to be the single source.

| Field | Value | Comes from |
|---|---|---|
| Element | `pkg_db8access` (etc.) | the download's `element` — must equal `<name>` in the manifest |
| Extension type | `package` | the download's `package_type` |
| Channel | `stable` | hardcoded by `sync()` |
| Access mode | `public` | hardcoded by `sync()` |

The element match is exact and failure is silent: Joomla fetches the feed,
finds no matching extension, and reports no update available.

> `streams.sql` used to live here and has been **removed**. It wrote streams
> with a NULL `download_id`, which is exactly `sync()`'s idempotency key, so
> running both produced duplicate streams — and it created no version rows,
> which cannot then be added through the UI.

Two settings on the version rows are load-bearing and easy to get wrong:

- **`joomla_min_version` must be `6`, not `6.0`.** The renderer turns a bare
  minimum into `"$min.*"` and Joomla matches it with
  `preg_match('/^' . $version . '/', JVERSION)`. `6.0` becomes `/^6.0.*/`, which
  does not match `6.1.2` — the `0` fails against the `1` — so every customer is
  silently told they are up to date. `6` becomes `/^6.*/` and matches.
- **Do not set `joomla_max_version`.** A min and a max produce `6,7` → `/^6,7/`,
  which matches nothing at all.

**Access mode stays `public`, deliberately.** Gating belongs on the download,
not the feed. A site whose licence has expired must still see that an update
exists — otherwise it never learns about a security release — while being
refused the file. A protected stream does the opposite: `UpdateFeedService`
returns 403 with no update, and channel-wide feeds omit the stream entirely, so
the customer reads "up to date" when they are not.

The refusal happens downstream. `DownloadAccessService` in com_db8downloads
checks published state, view level, user groups and the licence token, and
`LicenseValidator` rejects expired keys.

> For that to work, version rows must set `download_id` linking a
> com_db8downloads version. `UpdateXmlRenderer::resolveDownloadUrl()` prefers a
> literal `download_url`, and a literal URL is **not** access-checked — the feed
> is public, so publishing one would hand the package to anyone.
>
> `VersionTable::check()` enforces this: a version with a literal
> `download_url` and no `download_id` cannot be published while com_db8downloads
> is installed. Turn off **Require gated downloads** in the component options
> only for packages that really are free.

### 3. Create the /updates menu items

The feed is served through menu items, which is what makes the URLs readable.
Run `create-update-menu.php` from the Joomla root:

```bash
php cli/create-update-menu.php
```

It creates a hidden `updates` menu type, a parent item serving the whole stable
channel at `/updates`, and one child per stream at `/updates/<package>`. It is
idempotent and rebuilds the nested set afterwards.

The front-end view reads `stream_id` from **menu parameters only** — a query
string on a shared menu item is ignored — so every package needs its own item.
Stream IDs differ per site, which is why this is a script rather than SQL: it
looks each one up by element.

Requires SEF URLs with rewriting on. Verify:

```bash
curl -s https://extensions.db8.nl/updates/db8setup | head -3
```

### 4. Point customer sites at the feed

The manifest carries the update server, so a fresh install wires itself up:

```xml
<server type="extension" name="pkg_db8access">https://extensions.db8.nl/updates/db8access</server>
```

That URL answers 200 directly with no redirect, which matters: the updater
treats any non-200 as failure.

The customer's licence key goes in Joomla's **Extra Query** field
(*System → Update Sites → edit the site*). Joomla appends it to both the feed
request and the download URL. Never put a key in the manifest — it ships to
everyone.

Either name works, at both ends: the feed reads `license_key` and falls back to
`token`, com_db8downloads reads `token` and falls back to `license_key`. So
`token=XYZ` and `license_key=XYZ` are equivalent in Extra Query, and a gated
channel behaves the same as a gated download.

---

## Day-to-day development

Work in the repo, not in the Joomla install. Install once so Joomla registers
the extensions and runs their SQL:

```bash
./scripts/install.sh /path/to/joomla
```

After that, push code changes to the site with:

```bash
./scripts/sync.sh /path/to/joomla              # one way, repo -> site
./scripts/sync.sh /path/to/joomla --dry-run    # preview
./scripts/sync.sh /path/to/joomla --delete     # also remove files dropped from src/
```

Re-run `install.sh` only when a manifest changes, an extension is added, or a
schema update needs to run. `sync.sh` never touches the database and never
copies anything back from the site.

**Do not symlink extensions into a Joomla site.** `Folder::folders()` in
`joomla/filesystem` skips symlinked directories, so a symlinked extension is
never enumerated when `administrator/cache/autoload_psr4.php` is rebuilt. Its
namespace disappears from the map and every class in it stops autoloading —
which surfaces as unrelated "class not found" errors elsewhere, because the
map is shared. Real directories are the only layout Joomla enumerates.

Directory names encode where each extension belongs:

| Repo directory | Copies to |
|---|---|
| `src/com_x/admin` | `administrator/components/com_x` |
| `src/com_x/site` | `components/com_x` |
| `src/com_x/media` | `media/com_x` |
| `src/plg_<group>_<name>` | `plugins/<group>/<name>` |
| `src/mod_x` | `administrator/modules/mod_x`, or `modules/` if the manifest says `client="site"` |

Renaming a plugin's group means renaming its directory — `plg_db8payment_mollie`
installs into `plugins/db8payment/mollie`.

Component manifests and `script.php` live inside `admin/`, matching the
installed layout. The build copies them to the package root, which is where the
installer looks. Keeping the repo identical to a working install is what lets
`sync.sh` be a plain copy.

> Uploads land on the site, not in the repo: `sync.sh --delete` never touches
> `attachments/`, and ticket attachments are gitignored.

---

## Cutting a release

### 1. Tag

```bash
git tag 0.9.1
git push origin 0.9.1
```

That is the whole trigger. **The tag is the version** — the single source of
truth. Nothing else needs bumping, and CI commits nothing back, so your clone
never goes stale after a release.

Tags must be three-part (`1.2.3`); the workflow rejects anything else. `VERSION`
in `.env` is only the default for local builds and CI ignores it.

### 2. What CI does

`.github/workflows/release.yml` in the package repo calls the shared workflow in
`db8-ci`, which:

1. checks out the tag,
2. stamps `<version>` and `<creationDate>` into a **staging copy** of every
   manifest,
3. resolves composer dependencies into that staging copy,
4. zips each `src/*` extension, bundles them into `pkg_<name>-<version>.zip`,
5. computes the SHA512,
6. creates a GitHub release with the zip, the checksum, and instructions.

Stamping never touches `src/`. Manifests in git keep their comments and
formatting, and rebuilding any tag reproduces the same package.

### 3. Publish on extensions.db8.nl

Two ways, depending on whether you are adding a version or rebuilding one.

**By script (preferred).** Bump the version in `db8-ci/packages.php`, put the
new zip where the script can see it, and re-run:

```bash
php cli/provision-downloads.php --incoming=/path/to/zips
```

It creates the download version, places the file, computes the SHA-512,
cross-checks it against the `.sha512` the build wrote, and syncs the stream
version. Existing rows are left alone, so it is safe to re-run.

**By hand.** From the GitHub release:

1. Download `pkg_<name>-<version>.zip`.
2. **com_db8downloads** — upload it as a new version of the package's download.
   Set `joomla_min_version` to `6` (see the warning above), flag it
   **featured**, and publish it.
3. **com_db8updates → Streams → Sync from downloads** — this creates the
   matching stream version, linked by `download_id`, with no literal URL.

Note the admin version form cannot set `release_date`, `joomla_min_version`,
`php_min_version` or `changelog` — those fields are not in `version.xml`. Only
the script (or SQL) can populate them, which is the main reason to prefer it.

The feed picks it up immediately, subject to the `feed_cache_minutes` parameter
in com_db8updates.

`UpdateXmlRenderer::resolveDownloadUrl()` prefers a literal `download_url` and
otherwise routes through com_db8downloads, which is what enforces licence and
subscription gating. Prefer linking a download over pasting a URL: a literal URL
is not access-checked.

### 4. Verify

On a test site: **System → Extensions: Update → Find Updates**. The new version
should appear and install. Check with a gated stream and no licence key too —
the feed must return nothing rather than the download URL.

---

## Version rules

One version per package, shared by every extension inside it — the build stamps
them all from the tag. `plg_console_db8setup` therefore carries the same number
as `com_db8setup`.

This is deliberate: customers install a package, so the package number is what
they see and quote in support. Independent per-extension versions would mean
nine numbers to reason about per release.

Eight packages sit at **0.9.0**; `db8setup` is at **0.9.1**. None are released
publicly yet.

---

## Troubleshooting

**Workflow doesn't run.** It triggers on tags only. `git push` alone does
nothing; you need `git push origin <tag>`. Check the tag is three-part.

**"expected dist/pkg_… but the build did not produce it".** `PACKAGE_NAME` in
`.env` disagrees with the manifest filename. They must match: `PACKAGE_NAME=db8access`
→ `pkg_db8access.xml` → `pkg_db8access-<version>.zip`.

**Customer sees no update.** In order: is the version row published; does the
stream element exactly equal the package `<name>`; is `joomla_min_version` a
bare major (`6`, not `6.0`) with no maximum; does the customer's update site URL
match the manifest; for gated streams, is `license_key=…` set in Extra Query;
has `feed_cache_minutes` elapsed.

A useful check is whether the update reached `#__updates` at all. If the row is
there but `extension_id` is `0`, the feed was fetched and parsed fine and Joomla
simply did not match it to anything installed — it filed it as a *new* extension
rather than an update. That is a mismatch in one of element, type, folder or
**client_id**.

`client_id` is the subtle one. Joomla's `ExtensionAdapter` assumes
`client_id = 1` (administrator) for every `<update>` unless the feed sends
`<client>`, but packages, plugins, libraries and files all install with
`client_id = 0`. `UpdateXmlRenderer::resolveClient()` emits the element for
those types; if you add a stream type it does not cover, check this first. The
failure is completely silent — the customer just sees "up to date".

**Update found but download fails.** The download URL resolved to something the
customer can't fetch — usually a GitHub asset on a private repo. It must resolve
to extensions.db8.nl.

**A plugin installs into the wrong group.** The directory name is the source of
truth: `src/plg_<group>_<name>`. Rename the directory, and update the `group`
attribute in the package manifest.

---

## Possible next step

Step 3 is the only manual part. com_db8updates and com_db8downloads both already
hold the data a release needs, so a small authenticated endpoint accepting the
zip, version and SHA512 would let the workflow publish directly and reduce a
release to pushing a tag. Worth doing once the release cadence justifies it.
