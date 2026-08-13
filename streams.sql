--
-- One update stream per db8 package, for com_db8updates on extensions.db8.nl.
--
-- Before running, replace `#__` with the site's table prefix — with your SQL
-- client's search-and-replace, or: sed 's/`#__/`jos_/' streams.sql
--
-- `element` MUST equal <name> in the package manifest exactly. A mismatch is
-- silent: Joomla fetches the feed, finds no matching extension, and reports no
-- update available.
--
-- WHY access_mode IS 'public' AND SHOULD STAY THAT WAY
--
-- Gating belongs on the download, not on the feed. A site whose licence has
-- expired must still SEE that an update exists — otherwise it never learns
-- about a security release — while being unable to download it.
--
-- A protected stream does the opposite. UpdateFeedService returns 403 with no
-- update for a request that fails the check, and buildForChannel() silently
-- omits the stream from channel-wide feeds. The customer sees "up to date"
-- when they are not.
--
-- Enforcement already happens downstream: the feed's download URL routes
-- through com_db8downloads, whose DownloadAccessService checks published
-- state, view level, user groups and the licence token, and LicenseValidator
-- rejects expired keys. So a lapsed customer sees the update and is refused
-- the file — which is the intent.
--
-- Requirement: version rows must set download_id (linking a com_db8downloads
-- version) rather than a literal download_url. UpdateXmlRenderer prefers a
-- literal URL and a literal URL is NOT access-checked.
--
-- The other modes (license_key, subscription, user_group, token) exist for
-- feeds that genuinely must be invisible — pre-release or customer-specific
-- channels, not the public stable line.
--
-- Re-runnable: existing rows for the same element are left untouched.
--

INSERT INTO `#__db8updates_streams`
  (`title`, `element`, `extension_type`, `folder`, `channel`, `access_mode`, `description`, `published`, `created`)
SELECT * FROM (
  SELECT 'db8 Access'    AS t, 'pkg_db8access'    AS e, 'package' AS x, NULL AS f, 'stable' AS c, 'public' AS a, 'Subscriptions and access control for Joomla: plans, subscriptions, customers, and the plugins that enforce them.' AS d, 1 AS p, UTC_TIMESTAMP() AS cr
  UNION ALL SELECT 'db8 Downloads', 'pkg_db8downloads', 'package', NULL, 'stable', 'public', 'Gated file downloads for Joomla, with versions, categories and Smart Search integration.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Invoices',  'pkg_db8invoices',  'package', NULL, 'stable', 'public', 'Invoice generation and PDF rendering for the db8 extension family.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Licenses',  'pkg_db8licenses',  'package', NULL, 'stable', 'public', 'Licence key issuing and validation for commercial Joomla extensions.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Payment',   'pkg_db8payment',   'package', NULL, 'stable', 'public', 'Payment processing for Joomla with Mollie, Stripe, PayPal and bank transfer, plus EU VAT validation.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Setup',     'pkg_db8setup',     'package', NULL, 'stable', 'public', 'Guided setup for the db8 extension family: presets, provisioning and health checks, plus the db8 administrator menu branch and its dashboard.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Support',   'pkg_db8support',   'package', NULL, 'stable', 'public', 'Support ticket system for Joomla, with categories and attachments.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Tickets',   'pkg_db8tickets',   'package', NULL, 'stable', 'public', 'Event ticketing for Joomla: events, orders and check-in.', 1, UTC_TIMESTAMP()
  UNION ALL SELECT 'db8 Updates',   'pkg_db8updates',   'package', NULL, 'stable', 'public', 'Update server for Joomla extensions: streams, versions and access-gated update feeds.', 1, UTC_TIMESTAMP()
) AS s
WHERE NOT EXISTS (
  SELECT 1 FROM `#__db8updates_streams` existing WHERE existing.`element` = s.e
);

SELECT `id`, `element`, `channel`, `access_mode`, `published` FROM `#__db8updates_streams` ORDER BY `id`;

-- Next: run create-update-menu.php to build the /updates/<package> menu items
-- that the package manifests point at.
