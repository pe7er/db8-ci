<?php

/**
 * Build the public face of extensions.db8.nl: articles, menus and modules.
 *
 * Extension descriptions are NOT created here. Each package's page is its
 * com_db8downloads record — introtext and fulltext — so the catalogue, the
 * listing cards and the update feed's <description> all come from one source.
 * Mirroring that copy into an article would guarantee it drifts. Only narrative
 * pages (home, documentation) are com_content.
 *
 * Idempotent. Run after provision-downloads.php and create-update-menu.php:
 *
 *     php cli/provision-site.php
 */

require_once __DIR__ . '/db8-cli-bootstrap.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\MenuType;

$now = Factory::getDate()->toSql();

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------

/**
 * Finds or creates a content category.
 *
 * @param   \Joomla\Database\DatabaseInterface  $db     Database.
 * @param   string                              $title  Category title.
 * @param   string                              $alias  Category alias.
 * @param   string                              $desc   Description HTML.
 *
 * @return  int  The category id.
 */
function db8_content_category($db, string $title, string $alias, string $desc): int
{
    $id = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
    )->loadResult();

    if ($id) {
        db8_say("  category /{$alias}: exists (id {$id})");
        return $id;
    }

    $cat = new Category($db);
    $cat->bind([
        'extension'   => 'com_content',
        'title'       => $title,
        'alias'       => $alias,
        'description' => $desc,
        'published'   => 1,
        'access'      => 1,
        'language'    => '*',
        'params'      => '{}',
        'metadata'    => '{}',
    ]);
    $cat->setLocation(1, 'last-child');

    db8_store($cat, "category {$alias}");

    (new Category($db))->rebuild();

    db8_say("  category /{$alias}: created (id {$cat->id})");

    return (int) $cat->id;
}

/**
 * Finds or creates an article.
 *
 * @param   \Joomla\Database\DatabaseInterface  $db      Database.
 * @param   array                               $data    Article fields.
 * @param   int                                 $catId   Category id.
 * @param   int                                 $author  Author user id.
 *
 * @return  int  The article id.
 */
function db8_article($db, array $data, int $catId, int $author): int
{
    $id = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($data['alias']))
            ->where($db->quoteName('catid') . ' = ' . $catId)
    )->loadResult();

    $article = new Content($db);

    if ($id) {
        $article->load($id);
    }

    $article->bind($data + [
        'catid'      => $catId,
        'state'      => 1,
        'access'     => 1,
        'language'   => '*',
        'created_by' => $author,
        'images'     => '{}',
        'urls'       => '{}',
        'attribs'    => '{}',
        'metadata'   => '{}',
    ]);

    db8_store($article, 'article ' . $data['alias']);

    db8_say('  article /' . $data['alias'] . ': ' . ($id ? 'updated' : 'created') . ' (id ' . $article->id . ')');

    return (int) $article->id;
}

/**
 * Finds or creates a menu type.
 *
 * @param   \Joomla\Database\DatabaseInterface  $db     Database.
 * @param   string                              $type   Menutype.
 * @param   string                              $title  Title.
 * @param   string                              $desc   Description.
 *
 * @return  void
 */
function db8_menutype($db, string $type, string $title, string $desc): void
{
    $exists = (int) $db->setQuery(
        $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($type))
    )->loadResult();

    if ($exists) {
        db8_say("  menu type '{$type}': exists");
        return;
    }

    $mt = new MenuType($db);
    $mt->bind(['menutype' => $type, 'title' => $title, 'description' => $desc, 'client_id' => 0]);
    db8_store($mt, "menu type {$type}");
    db8_say("  menu type '{$type}': created");
}

/**
 * Finds or creates a menu item, matched on its path.
 *
 * @param   \Joomla\Database\DatabaseInterface  $db        Database.
 * @param   array                               $data      Menu fields.
 * @param   int                                 $parentId  Parent menu id.
 *
 * @return  int  The menu item id.
 */
function db8_menuitem($db, array $data, int $parentId): int
{
    $existing = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('path') . ' = ' . $db->quote($data['path']))
            ->where($db->quoteName('client_id') . ' = 0')
    )->loadResult();

    $item = new Menu($db);

    if ($existing) {
        // Update in place. setLocation() is deliberately NOT called: the row
        // already sits in the nested set, and re-placing it would move it.
        $item->load($existing);
        $item->bind($data);

        db8_store($item, 'menu item ' . $data['path']);

        db8_say('  /' . $data['path'] . ': updated (id ' . $existing . ')');

        return $existing;
    }

    $item->bind($data);
    $item->setLocation($parentId, 'last-child');

    db8_store($item, 'menu item ' . $data['path']);

    db8_say('  /' . $data['path'] . ': created (id ' . $item->id . ')');

    return (int) $item->id;
}

/**
 * Returns the extension_id of a component.
 *
 * @param   \Joomla\Database\DatabaseInterface  $db       Database.
 * @param   string                              $element  Component element.
 *
 * @return  int
 */
function db8_component_id($db, string $element): int
{
    return (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
    )->loadResult();
}

// ---------------------------------------------------------------------------
// 1. content
// ---------------------------------------------------------------------------

db8_say('content:');

$docsCat = db8_content_category(
    $db,
    'Documentation',
    'documentation',
    '<p>Installing, licensing and updating db8 extensions.</p>'
);

$uncat = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote('uncategorised'))
)->loadResult() ?: 2;

$homeArticleId = db8_article($db, [
    'title'     => 'Joomla extensions by db8',
    'alias'     => 'home',
    'introtext' => <<<'HTML'
<p>db8 builds commercial extensions for Joomla: a support desk, a licensing and
update server, subscriptions and payments, event ticketing. They are written to
work together, and each one is useful on its own.</p>
HTML,
    'fulltext'  => <<<'HTML'
<h2>What is here</h2>

<h3>Sell and license software</h3>
<p><strong>db8 Downloads</strong> serves files that are not public, storing them
outside the web root so nothing can be fetched by guessing a URL.
<strong>db8 Updates</strong> turns those files into a Joomla update channel, so
your customers see your releases in their own administrator.
<strong>db8 Licenses</strong> issues the keys that decide who may download.</p>

<h3>Take money</h3>
<p><strong>db8 Payment</strong> provides the gateways — Mollie, Stripe, PayPal,
bank transfer — plus EU VAT validation. <strong>db8 Access</strong> sells
recurring subscriptions and moves customers in and out of Joomla user groups as
those subscriptions start and lapse. <strong>db8 Invoices</strong> renders the
billing document that follows.</p>

<h3>Support and events</h3>
<p><strong>db8 Support</strong> is a help desk inside Joomla, so tickets sit
beside the customer records they relate to. <strong>db8 Tickets</strong> sells
admission to events and handles check-in on the day.</p>

<h3>Get started</h3>
<p><strong>db8 Setup</strong> ties the family together: one administrator menu
branch, a dashboard, guided provisioning and health checks. Install it first.</p>

<p>All nine are built for Joomla 6 and PHP 8.1 or later, and are released under
the GNU General Public License.</p>
HTML,
], $uncat, $db8SuperUserId);

db8_article($db, [
    'title'     => 'Using the db8 update server',
    'alias'     => 'update-server',
    'introtext' => '<p>How your Joomla site finds and installs db8 updates, and where the licence key goes.</p>',
    'fulltext'  => <<<'HTML'
<h3>It is already configured</h3>
<p>Every db8 package registers its own update site when you install it. There is
nothing to set up: open <em>System → Extensions: Update</em>, choose
<em>Find Updates</em>, and any new release appears.</p>

<h3>Where the licence key goes</h3>
<p>Your key belongs in the <strong>Extra Query</strong> field of the update site,
not in any file. Go to <em>System → Update Sites</em>, open the db8 entry, and
set Extra Query to:</p>

<pre>license_key=YOUR-KEY-HERE</pre>

<p>Joomla appends that to both the update check and the download request, so one
value covers both. <code>token=</code> is accepted as an alias if you prefer it.
You can find your key on the <em>My licence keys</em> page of your account.</p>

<h3>Why you can see an update you cannot install</h3>
<p>Update feeds are public on purpose. If your licence has lapsed you will still
be told that a new version exists — otherwise you would never learn about a
security release — but the download itself is refused. Renew, and the same
update installs with no further configuration.</p>

<h3>Verifying what you downloaded</h3>
<p>Each release publishes a SHA-512 checksum, and it travels inside the update
feed. Joomla checks it before installing and refuses a file that does not match,
so a package altered in transit cannot be installed. The same checksum is shown
on each extension's page if you want to verify a manual download:</p>

<pre>sha512sum pkg_db8setup-0.9.1.zip</pre>

<h3>Installing manually</h3>
<p>Download the package from its page here and install it through
<em>System → Install → Upload Package File</em>. Updates will still be offered
automatically afterwards.</p>
HTML,
], $docsCat, $db8SuperUserId);

db8_article($db, [
    'title'     => 'Requirements and installation',
    'alias'     => 'requirements',
    'introtext' => '<p>What db8 extensions need, and the order to install them in.</p>',
    'fulltext'  => <<<'HTML'
<h3>Requirements</h3>
<ul>
    <li>Joomla 6.0 or later</li>
    <li>PHP 8.1 or later</li>
    <li>MySQL 8 or MariaDB 10.4 or later</li>
</ul>

<h3>Install order</h3>
<p>Each package installs on its own, but a couple of them build on others, so
this order avoids surprises:</p>
<ol>
    <li><strong>db8 Setup</strong> — provides the shared administrator menu
        branch the rest register under.</li>
    <li><strong>db8 Licenses</strong>, then <strong>db8 Payment</strong> — the
        services others call.</li>
    <li><strong>db8 Invoices</strong> and <strong>db8 Access</strong> — both
        build on db8 Payment.</li>
    <li><strong>db8 Downloads</strong>, then <strong>db8 Updates</strong> —
        Updates derives its streams from the Downloads catalogue.</li>
    <li><strong>db8 Support</strong> and <strong>db8 Tickets</strong> — Tickets
        needs db8 Payment for checkout.</li>
</ol>

<h3>After installing</h3>
<p>Joomla installs plugins disabled. Open <em>System → Plugins</em>, filter on
<code>db8</code>, and enable them — in particular <strong>System - db8 Menu</strong>,
without which the db8 administrator menu branch does not appear.</p>

<p>Then open <em>Components → db8 → Setup</em> and run the health checks.</p>
HTML,
], $docsCat, $db8SuperUserId);

// ---------------------------------------------------------------------------
// 2. menus
// ---------------------------------------------------------------------------

db8_say('');
db8_say('menus:');

db8_menutype($db, 'customer', 'Customer pages', 'Account, licence keys and tickets. Linked to from the account area.');

$catId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_db8downloads'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote('extensions'))
)->loadResult();

if (!$catId) {
    exit("The com_db8downloads 'extensions' category is missing — run provision-downloads.php first\n");
}

$base = [
    'menutype'          => 'mainmenu',
    'type'              => 'component',
    'published'         => 1,
    'access'            => 1,
    'language'          => '*',
    'client_id'         => 0,
    'browserNav'        => 0,
    'template_style_id' => 0,
    'home'              => 0,
    'params'            => '{}',
];

// -- retarget the default Home item rather than replacing it. Joomla requires
//    exactly one home=1 item per language, and deleting it means nested-set
//    surgery for no gain.
$homeId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('home') . ' = 1')
        ->where($db->quoteName('client_id') . ' = 0')
)->loadResult();

if ($homeId) {
    $home = new Menu($db);
    $home->load($homeId);
    $home->title        = 'Home';
    $home->link         = 'index.php?option=com_content&view=article&id=' . $homeArticleId;
    $home->component_id = db8_component_id($db, 'com_content');
    $home->params       = json_encode([
        'show_page_heading'     => 0,
        'menu-meta_description' => 'Commercial Joomla extensions by db8: downloads, licensing and updates, '
            . 'subscriptions and payments, support and event ticketing.',
    ]);

    db8_store($home, 'home menu item');

    db8_say('  home: retargeted to article ' . $homeArticleId . ' (menu id ' . $homeId . ')');
} else {
    db8_say('  home: NO default item found — set one by hand');
}

$items = [
    [
        'title'        => 'Extensions',
        'alias'        => 'extensions',
        'params'       => json_encode(['menu-meta_description' => 'Every db8 Joomla extension, with its current version, checksum and changelog.']),
        'path'         => 'extensions',
        'link'         => 'index.php?option=com_db8downloads&view=category&id=' . $catId,
        'component_id' => db8_component_id($db, 'com_db8downloads'),
        'menutype'     => 'mainmenu',
    ],
    [
        'title'        => 'Pricing',
        'alias'        => 'pricing',
        'params'       => json_encode(['menu-meta_description' => 'Subscription plans for the db8 Joomla extensions, including updates and support.']),
        'path'         => 'pricing',
        'link'         => 'index.php?option=com_db8access&view=plans',
        'component_id' => db8_component_id($db, 'com_db8access'),
        'menutype'     => 'mainmenu',
    ],
    [
        'title'        => 'Documentation',
        'alias'        => 'documentation',
        'params'       => json_encode(['menu-meta_description' => 'Requirements, installation order, and how the db8 update server and licence keys work.']),
        'path'         => 'documentation',
        'link'         => 'index.php?option=com_content&view=category&layout=blog&id=' . $docsCat,
        'component_id' => db8_component_id($db, 'com_content'),
        'menutype'     => 'mainmenu',
    ],
    [
        'title'        => 'Support',
        'alias'        => 'support',
        'params'       => json_encode(['menu-meta_description' => 'Ask a question or report a problem with a db8 Joomla extension.']),
        'path'         => 'support',
        'link'         => 'index.php?option=com_db8support&view=form',
        'component_id' => db8_component_id($db, 'com_db8support'),
        'menutype'     => 'mainmenu',
    ],
    [
        'title'        => 'My Account',
        'alias'        => 'account',
        'path'         => 'account',
        'link'         => 'index.php?option=com_db8access&view=account',
        'component_id' => db8_component_id($db, 'com_db8access'),
        'menutype'     => 'mainmenu',
    ],
    // Hidden: reached from the account area, but they need menu items so the
    // router produces readable URLs and each page gets its own title.
    [
        'title'        => 'My licence keys',
        'alias'        => 'licence-keys',
        'path'         => 'licence-keys',
        'link'         => 'index.php?option=com_db8licenses&view=tokens',
        'component_id' => db8_component_id($db, 'com_db8licenses'),
        'menutype'     => 'customer',
    ],
    [
        'title'        => 'My tickets',
        'alias'        => 'my-tickets',
        'path'         => 'my-tickets',
        'link'         => 'index.php?option=com_db8support&view=tickets',
        'component_id' => db8_component_id($db, 'com_db8support'),
        'menutype'     => 'customer',
    ],
    [
        'title'        => 'Ticket',
        'alias'        => 'ticket',
        'path'         => 'ticket',
        'link'         => 'index.php?option=com_db8support&view=ticket',
        'component_id' => db8_component_id($db, 'com_db8support'),
        'menutype'     => 'customer',
    ],
    [
        'title'        => 'Billing details',
        'alias'        => 'billing',
        'path'         => 'billing',
        'link'         => 'index.php?option=com_db8access&view=customer',
        'component_id' => db8_component_id($db, 'com_db8access'),
        'menutype'     => 'customer',
    ],
];

$menuIds = [];

foreach ($items as $item) {
    if (!$item['component_id']) {
        db8_say('  /' . $item['path'] . ': SKIPPED — component not installed');
        continue;
    }

    // array_merge, not +: with the union operator the LEFT operand wins on a
    // duplicate key, so $base's empty 'params' would silently discard the
    // item's own.
    $menuIds[$item['alias']] = db8_menuitem($db, array_merge($base, $item), 1);
}

// -- one item per package, as children of Extensions.
//
// The router already yields /extensions/<alias> without these, so the URLs do
// not change. What they add is a real <title> and meta description per page —
// without them every package page inherits "Extensions" from the category item
// — and a dropdown listing the nine extensions in the main menu.
if (!empty($menuIds['extensions'])) {
    $downloadsComponentId = db8_component_id($db, 'com_db8downloads');

    $packages = $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'alias', 'introtext']))
            ->from($db->quoteName('#__db8downloads_downloads'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering'))
    )->loadObjectList();

    foreach ($packages as $pkg) {
        db8_menuitem($db, array_merge($base, [
            'menutype'     => 'mainmenu',
            'title'        => $pkg->title,
            'alias'        => $pkg->alias,
            'path'         => 'extensions/' . $pkg->alias,
            'link'         => 'index.php?option=com_db8downloads&view=download&id=' . (int) $pkg->id,
            'component_id' => $downloadsComponentId,
            'params'       => json_encode([
                'menu-meta_description' => strip_tags((string) $pkg->introtext),
                'show_page_heading'     => 0,
            ]),
        ]), $menuIds['extensions']);
    }
}

// The nested set gained nodes across two menutypes; rebuild once at the end.
(new Menu($db))->rebuild();
db8_say('  nested set rebuilt');

// ---------------------------------------------------------------------------
// 3. modules
// ---------------------------------------------------------------------------

db8_say('');
db8_say('modules:');

// A fresh Joomla puts the main menu in sidebar-right, which in Cassiopeia is
// not a navigation position — the site reads as though it has no menu at all.
$menuModuleId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_menu'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->order($db->quoteName('id') . ' ASC'),
    0,
    1
)->loadResult();

if ($menuModuleId) {
    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('position') . ' = ' . $db->quote('menu'))
            ->set($db->quoteName('showtitle') . ' = 0')
            ->set($db->quoteName('published') . ' = 1')
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode(['menutype' => 'mainmenu'])))
            ->where($db->quoteName('id') . ' = ' . $menuModuleId)
    )->execute();

    // Make sure it shows on every page.
    $assigned = (int) $db->setQuery(
        $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules_menu'))
            ->where($db->quoteName('moduleid') . ' = ' . $menuModuleId)
    )->loadResult();

    if (!$assigned) {
        $row           = new \stdClass();
        $row->moduleid = $menuModuleId;
        $row->menuid   = 0;
        $db->insertObject('#__modules_menu', $row);
    }

    db8_say('  mod_menu (id ' . $menuModuleId . '): position=menu, mainmenu, all pages');
} else {
    db8_say('  mod_menu: not found');
}

// Login module in the sidebar, so customers can reach their account.
$loginModuleId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_login'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->order($db->quoteName('id') . ' ASC'),
    0,
    1
)->loadResult();

if ($loginModuleId) {
    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('position') . ' = ' . $db->quote('sidebar-right'))
            ->set($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('id') . ' = ' . $loginModuleId)
    )->execute();

    db8_say('  mod_login (id ' . $loginModuleId . '): position=sidebar-right');
}

db8_say('');
db8_say('Done.');
