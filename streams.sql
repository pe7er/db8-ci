--
-- One update stream per db8 package, for com_db8updates on extensions.db8.nl.
--
-- Before running:
--   1. Replace `#__` with the site's table prefix (usually with your SQL client's
--      search-and-replace, or run through: sed 's/`#__/`jos_/' streams.sql).
--   2. Decide `access_mode` — see the note below. This file ships 'public'.
--
-- `element` MUST equal <name> in the package manifest exactly. A mismatch is
-- silent: Joomla fetches the feed, finds no matching extension, and reports no
-- update available.
--
-- access_mode options, per UpdateAccessService:
--   public        anyone with the URL gets the feed and the download
--   license_key   requires &license_key=… (customers set it in Joomla's
--                 Update Site "Extra Query" field)
--   subscription  requires an active com_db8access subscription for the
--                 logged-in user
--   user_group    requires membership of a group allowed on the linked download
--
-- For paid extensions use 'license_key' or 'subscription'. 'public' is
-- appropriate only while nothing paid is published yet, or for free packages.
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

-- Switch every stream to licence-gated once real packages are published:
-- UPDATE `#__db8updates_streams` SET `access_mode` = 'license_key' WHERE `element` LIKE 'pkg_db8%';

SELECT `id`, `element`, `channel`, `access_mode`, `published` FROM `#__db8updates_streams` ORDER BY `id`;
