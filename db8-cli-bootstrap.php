<?php

/**
 * Shared bootstrap for the db8 provisioning CLI scripts.
 *
 * Include this from a script sitting in the Joomla root's cli/ directory:
 *
 *     require_once __DIR__ . '/db8-cli-bootstrap.php';
 *
 * It returns nothing; it defines $app and $db in the including scope and leaves
 * Factory wired up.
 *
 * The scripts have to live one level under the Joomla root because JPATH_BASE
 * is derived with dirname(__DIR__) — the same constraint create-update-menu.php
 * already lives with.
 */

\defined('_JEXEC') or \define('_JEXEC', 1);

if (!\defined('JPATH_BASE')) {
    \define('JPATH_BASE', \dirname(__DIR__));
}

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();

// The console application resolves a session; without these aliases the
// container has no 'session' binding. Same set as cli/joomla.php.
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

/** @var ConsoleApplication $app */
$app = $container->get(ConsoleApplication::class);
Factory::$application = $app;

// Register the extension namespaces (administrator/cache/autoload_psr4.php).
//
// ConsoleApplication does this in doExecute(), which these scripts never reach:
// they take the application out of the container and drive it directly rather
// than running a console command. Without this call every component class —
// Table, Service, Extension — fails to autoload, and the first symptom is a
// ClassNotFoundError for the component's own Extension class inside
// bootComponent(). create-update-menu.php gets away without it only because it
// touches nothing outside core.
$app->createExtensionNamespaceMap();

/** @var \Joomla\Database\DatabaseDriver $db */
$db = $container->get(DatabaseInterface::class);

// ConsoleApplication uses the IdentityAware trait, whose getIdentity() returns
// the raw property — null until loadIdentity() is called. Table\Category does
// asset handling and Table::store() stamps created_by, so both need a real
// user. create-update-menu.php gets away without this only because Table\Menu
// tracks no assets.
$db8SuperUserId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('u.id'))
        ->from($db->quoteName('#__users', 'u'))
        ->join(
            'INNER',
            $db->quoteName('#__user_usergroup_map', 'm')
            . ' ON ' . $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id')
        )
        ->where($db->quoteName('m.group_id') . ' = 8')
        ->order($db->quoteName('u.id') . ' ASC'),
    0,
    1
)->loadResult();

if (!$db8SuperUserId) {
    exit("No Super User found — is this a finished Joomla install?\n");
}

$app->loadIdentity($container->get(UserFactoryInterface::class)->loadUserById($db8SuperUserId));

/**
 * Prints a one-line status with a consistent prefix.
 *
 * @param   string  $message  The message.
 *
 * @return  void
 */
function db8_say(string $message): void
{
    echo $message . "\n";
}

/**
 * Stores a Table row, turning its exception-based check() into a fatal error.
 *
 * Both DownloadTable::check() and VersionTable::check() throw
 * \UnexpectedValueException rather than returning false, so a plain
 * `if (!$table->check())` would let a validation failure through unnoticed.
 *
 * @param   \Joomla\CMS\Table\Table  $table  The table to check and store.
 * @param   string                   $what   Label used in the error message.
 *
 * @return  void
 */
function db8_store(\Joomla\CMS\Table\Table $table, string $what): void
{
    try {
        if (!$table->check()) {
            exit("  FAILED check on {$what}: " . $table->getError() . "\n");
        }

        if (!$table->store()) {
            exit("  FAILED store on {$what}: " . $table->getError() . "\n");
        }
    } catch (\Throwable $e) {
        exit("  FAILED {$what}: " . $e->getMessage() . "\n");
    }
}
