<?php

/**
 * Provision the db8 download catalogue and derive the update streams from it.
 *
 * Creates the category, one download record per package, one version per
 * download with its file placed in storage and hashed, then syncs it all into
 * com_db8updates.
 *
 * Idempotent: every create is an upsert on a natural key, so running it twice
 * changes nothing. Re-run it after building new packages.
 *
 * Run from the Joomla root:
 *
 *     php cli/provision-downloads.php
 *     php cli/provision-downloads.php --incoming=/path/to/zips
 *
 * The zips are read from --incoming (default
 * /usr/local/apache2/db8downloads_files/_incoming), which is deliberately NOT
 * under the web root: staged commercial packages must not be fetchable while
 * provisioning runs.
 */

require_once __DIR__ . '/db8-cli-bootstrap.php';

use Db8\Component\Db8downloads\Administrator\Service\ChecksumService;
use Db8\Component\Db8downloads\Administrator\Service\FileStorageService;
use Db8\Component\Db8updates\Administrator\Service\DownloadsStreamSync;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Category;

// --- options ---------------------------------------------------------------

$incoming = '/usr/local/apache2/db8downloads_files/_incoming';

foreach (\array_slice($argv, 1) as $arg) {
    if (\str_starts_with($arg, '--incoming=')) {
        $incoming = \rtrim(\substr($arg, 11), '/');
    }
}

if (!\is_dir($incoming)) {
    exit("Incoming directory not found: {$incoming}\n");
}

if (!ComponentHelper::isEnabled('com_db8downloads')) {
    exit("com_db8downloads is not installed or not enabled\n");
}

$packages = require __DIR__ . '/packages.php';

$now      = Factory::getDate()->toSql();
$factory  = $app->bootComponent('com_db8downloads')->getMVCFactory();
$storage  = new FileStorageService();
$checksum = new ChecksumService(ChecksumService::ALGO_SHA512);

$storage->ensureBaseExists();

db8_say('storage base: ' . $storage->getBasePath());

// Check every zip up front. Failing halfway leaves the catalogue half-updated,
// which is worse than not starting: the earlier packages are already published
// at their new versions while the later ones are not.
$missing = [];

foreach ($packages as $pkg) {
    $zip = $incoming . '/pkg_' . $pkg['repo'] . '-' . $pkg['version'] . '.zip';

    if (!\is_file($zip)) {
        $missing[] = \basename($zip);
    }
}

if ($missing) {
    echo "Missing " . \count($missing) . " package(s) in {$incoming}:\n";

    foreach ($missing as $m) {
        echo "  {$m}\n";
    }

    exit("Build them, or pass --incoming= pointing at where they are.\n");
}

// --- category --------------------------------------------------------------

$catId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_db8downloads'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote('extensions'))
)->loadResult();

if ($catId) {
    db8_say("category: exists (id {$catId})");
} else {
    $category = new Category($db);
    $category->bind([
        'extension'   => 'com_db8downloads',
        'title'       => 'Extensions',
        'alias'       => 'extensions',
        'description' => '<p>Commercial Joomla extensions by db8.</p>',
        'published'   => 1,
        'access'      => 1,
        'language'    => '*',
        'params'      => '{}',
        'metadata'    => '{}',
    ]);
    $category->setLocation(1, 'last-child');

    db8_store($category, 'category');

    $catId = (int) $category->id;

    // The nested set has a new node; keep lft/rgt consistent.
    (new Category($db))->rebuild();

    db8_say("category: created (id {$catId})");
}

// --- downloads and versions ------------------------------------------------

db8_say('');
db8_say('packages:');

foreach ($packages as $pkg) {
    $zip = $incoming . '/pkg_' . $pkg['repo'] . '-' . $pkg['version'] . '.zip';

    if (!\is_file($zip)) {
        exit("  MISSING ZIP for {$pkg['element']}: {$zip}\n");
    }

    // -- download record, upserted on element (not alias: DownloadTable::check()
    //    silently appends -1 to a colliding alias, so matching on alias would
    //    create db8-access-1 on a partial re-run).
    $downloadId = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__db8downloads_downloads'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($pkg['element']))
    )->loadResult();

    $download = $factory->createTable('Download', 'Administrator');

    if ($downloadId) {
        $download->load($downloadId);
    }

    $download->bind([
        'catid'        => $catId,
        'title'        => $pkg['title'],
        'alias'        => $pkg['alias'],
        'element'      => $pkg['element'],
        'package_type' => 'package',
        'introtext'    => $pkg['introtext'],
        'fulltext'     => $pkg['fulltext'],
        'access'       => 1,
        'published'    => 1,
        'ordering'     => $pkg['ordering'],
        'created_by'   => $db8SuperUserId,
    ]);

    db8_store($download, $pkg['element'] . ' download');

    $downloadId = (int) $download->id;

    // -- version row. Stored first because the storage path needs its id.
    $versionId = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__db8downloads_versions'))
            ->where($db->quoteName('download_id') . ' = ' . $downloadId)
            ->where($db->quoteName('version_string') . ' = ' . $db->quote($pkg['version']))
    )->loadResult();

    $version = $factory->createTable('Version', 'Administrator');

    if ($versionId) {
        $version->load($versionId);
    }

    $oldPath = (string) ($version->local_path ?? '');

    $version->bind([
        'download_id'        => $downloadId,
        'version_string'     => $pkg['version'],
        'source_type'        => 'local',
        'external_url'       => null,
        'featured'           => 1,
        'published'          => 1,
        'release_date'       => $now,
        // '6', not '6.0'. UpdateXmlRenderer turns a bare minimum into "$min.*"
        // and Joomla matches it with preg_match('/^' . $version . '/', JVERSION).
        // '6.0' yields /^6.0.*/, which does NOT match 6.1.2 — the '0' fails
        // against the '1' — so every customer would silently see "up to date".
        // '6' yields /^6.*/, which matches every Joomla 6.
        'joomla_min_version' => '6',
        'joomla_max_version' => null,
        'php_min_version'    => '8.1',
        'php_max_version'    => null,
        'changelog'          => $pkg['changelog'],
        'created_by'         => $db8SuperUserId,
    ]);

    db8_store($version, $pkg['element'] . ' version');

    $versionId = (int) $version->id;

    // -- place the file. FileStorageService::storeUploaded() cannot be used
    //    here: it requires is_uploaded_file(), which is false for anything not
    //    delivered by an HTTP POST. Use its path helpers and copy manually so
    //    the layout and permissions stay identical to an admin upload.
    $relative = $storage->getRelativePath($downloadId, $versionId, \basename($zip));
    $absolute = $storage->resolveAbsolute($relative);
    $dir      = \dirname($absolute);

    if (!\is_dir($dir) && !@\mkdir($dir, 0750, true) && !\is_dir($dir)) {
        exit("  could not create {$dir}\n");
    }

    if (!@\copy($zip, $absolute)) {
        exit("  copy failed: {$zip} -> {$absolute}\n");
    }

    @\chmod($absolute, 0640);

    if ($oldPath !== '' && $oldPath !== $relative) {
        $storage->delete($oldPath);
    }

    $digest = $checksum->hashFile($absolute);

    // Cross-check against the sidecar the build wrote, so a truncated copy or a
    // mismatched zip is caught here rather than by a customer's installer.
    $sidecar = $zip . '.sha512';

    if (\is_file($sidecar)) {
        $expected = \strtolower(\trim((string) \strtok(\trim((string) \file_get_contents($sidecar)), " \t\n")));

        if ($expected !== '' && !\hash_equals($expected, $digest)) {
            exit("  CHECKSUM MISMATCH for {$pkg['element']}\n    build: {$expected}\n    file:  {$digest}\n");
        }
    }

    $version->local_path         = $relative;
    $version->file_size          = (int) \filesize($absolute);
    $version->checksum_algorithm = ChecksumService::ALGO_SHA512;
    $version->checksum_value     = $digest;

    db8_store($version, $pkg['element'] . ' version file');

    \printf(
        "  %-18s d%-3d v%-3d %-9s %9s bytes  sha512 %s…\n",
        $pkg['element'],
        $downloadId,
        $versionId,
        $pkg['version'],
        \number_format((int) $version->file_size),
        \substr($digest, 0, 12)
    );
}

// --- derive the update streams ---------------------------------------------

db8_say('');

if (!ComponentHelper::isEnabled('com_db8updates')) {
    db8_say('com_db8updates is not enabled — skipping stream sync');
} else {
    $app->bootComponent('com_db8updates');

    // Call the service directly. The controller action wrapping it starts with
    // checkToken(), which needs a real session and form token.
    $result = (new DownloadsStreamSync($db))->sync();

    \printf(
        "streams: %d created, %d already existed; versions: %d created, %d already existed\n",
        $result['streams_created'],
        $result['streams_skipped'],
        $result['versions_created'],
        $result['versions_skipped']
    );
}

db8_say('');
db8_say('Next: php cli/create-update-menu.php');
