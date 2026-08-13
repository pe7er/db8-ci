<?php

/**
 * One-off: build the /updates menu for com_db8updates.
 *
 * Creates a dedicated "updates" menu type, a parent item serving the whole
 * stable channel at /updates, and one child per package at /updates/<name>.
 *
 * The front-end view reads stream_id from MENU PARAMS only, so each package
 * needs its own item — a query string on a shared item would be ignored.
 *
 * Run:  php cli/create-update-menu.php
 */

define('_JEXEC', 1);
define('JPATH_BASE', \dirname(__DIR__));

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\MenuType;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();

// The console application resolves a session; without these aliases the
// container has no 'session' binding. Same set as cli/joomla.php.
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(ConsoleApplication::class);
Factory::$application = $app;

$db = $container->get(DatabaseInterface::class);

$componentId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('extension_id'))
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_db8updates'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadResult();

if (!$componentId) {
    exit("com_db8updates is not installed\n");
}

// --- menu type -------------------------------------------------------------

$exists = $db->setQuery(
    $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' = ' . $db->quote('updates'))
)->loadResult();

if (!$exists) {
    $type = new MenuType($db);
    $type->bind([
        'menutype'    => 'updates',
        'title'       => 'Update feeds',
        'description' => 'Joomla update-server feeds served by com_db8updates. Not displayed on the site.',
        'client_id'   => 0,
    ]);

    if (!$type->check() || !$type->store()) {
        exit('menu type: ' . $type->getError() . "\n");
    }
    echo "created menu type 'updates'\n";
} else {
    echo "menu type 'updates' already present\n";
}

// --- helper ----------------------------------------------------------------

/**
 * Create one menu item, or return the id of the existing one with that path.
 */
function upsert(DatabaseInterface $db, array $data, int $parentId): int
{
    $existing = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('path') . ' = ' . $db->quote($data['path']))
            ->where($db->quoteName('client_id') . ' = 0')
    )->loadResult();

    if ($existing) {
        echo "  exists: /{$data['path']} (id {$existing})\n";
        return $existing;
    }

    $table = new Menu($db);
    $table->bind($data);
    $table->setLocation($parentId, 'last-child');

    if (!$table->check() || !$table->store()) {
        exit("  FAILED /{$data['path']}: " . $table->getError() . "\n");
    }

    echo "  created: /{$data['path']} (id {$table->id})\n";

    return (int) $table->id;
}

$base = [
    'menutype'          => 'updates',
    'type'              => 'component',
    'component_id'      => $componentId,
    'link'              => 'index.php?option=com_db8updates&view=update',
    'published'         => 1,
    'access'            => 1,
    'language'          => '*',
    'client_id'         => 0,
    'browserNav'        => 0,
    'template_style_id' => 0,
    'home'              => 0,
];

// --- parent: whole channel at /updates -------------------------------------

echo "menu items:\n";

$parentId = upsert($db, $base + [
    'title'  => 'Updates',
    'alias'  => 'updates',
    'path'   => 'updates',
    'params' => json_encode(['stream_id' => '', 'channel' => 'stable']),
], 1);

// --- children: one per stream ----------------------------------------------

$streams = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'element', 'title']))
        ->from($db->quoteName('#__db8updates_streams'))
        ->order($db->quoteName('id'))
)->loadObjectList();

foreach ($streams as $stream) {
    // pkg_db8setup -> db8setup
    $alias = preg_replace('/^pkg_/', '', $stream->element);

    upsert($db, $base + [
        'title'  => $stream->title,
        'alias'  => $alias,
        'path'   => 'updates/' . $alias,
        'params' => json_encode(['stream_id' => (string) $stream->id, 'channel' => 'stable']),
    ], $parentId);
}

// --- integrity -------------------------------------------------------------

$menu = new Menu($db);

if (!$menu->rebuild()) {
    exit('rebuild failed: ' . $menu->getError() . "\n");
}

echo "nested set rebuilt\n";
