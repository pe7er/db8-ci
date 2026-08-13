<?php

/**
 * Provision the licence gating for the db8 download catalogue.
 *
 * Downloads are gated on user group membership. The chain that lets a licence
 * key stand in for a logged-in session is longer than it looks, and every link
 * is required:
 *
 *   DownloadAccessService::evaluate()
 *     -> the download must have a NON-EMPTY usergroup whitelist. With an empty
 *        whitelist the method short-circuits: logged-in users are allowed and
 *        guests refused, and the token branch is never reached at all — so a
 *        licence key could never grant access.
 *     -> MembershipTokenAdapter::validate($key)
 *          -> LicenseValidator: the key must exist, be 'active' and unexpired
 *          -> resolveUsergroupForUser(): joins #__db8access_subscriptions to
 *             #__db8access_plans and needs status='active' AND
 *             current_period_end > NOW(), returning plans.joomla_usergroup_id
 *     -> that group id must appear in the download's whitelist
 *
 * Without the subscription the key validates but resolves to a null group, and
 * null fails the whitelist check. Licences alone are not enough.
 *
 * Idempotent. Run after provision-downloads.php:
 *
 *     php cli/provision-licensing.php
 */

require_once __DIR__ . '/db8-cli-bootstrap.php';

use Db8\Component\Db8licenses\Administrator\Service\LicenseGenerator;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Usergroup;
use Joomla\CMS\User\UserHelper;

const DB8_GROUP_TITLE   = 'db8 Customers';
const DB8_PLAN_ALIAS    = 'db8-all-access';
const DB8_TEST_USERNAME = 'db8customer';
const DB8_TEST_EMAIL    = 'customer@db8.nl';
const DB8_TEST_PASSWORD = 'customercustomer';

$now = Factory::getDate();

// --- 1. the customer user group --------------------------------------------

$groupId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote(DB8_GROUP_TITLE))
)->loadResult();

if ($groupId) {
    db8_say('group: exists (id ' . $groupId . ')');
} else {
    // Child of Registered (2), so members keep ordinary logged-in permissions.
    $group = new Usergroup($db);
    $group->bind(['title' => DB8_GROUP_TITLE, 'parent_id' => 2]);
    db8_store($group, 'usergroup');
    $groupId = (int) $group->id;
    db8_say('group: created (id ' . $groupId . ')');
}

// --- 2. whitelist that group on every download -----------------------------

$downloads = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'element']))
        ->from($db->quoteName('#__db8downloads_downloads'))
        ->order($db->quoteName('ordering'))
)->loadObjectList();

if (!$downloads) {
    exit("No downloads found — run provision-downloads.php first\n");
}

$added = 0;

foreach ($downloads as $dl) {
    $exists = (int) $db->setQuery(
        $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__db8downloads_download_usergroups'))
            ->where($db->quoteName('download_id') . ' = ' . (int) $dl->id)
            ->where($db->quoteName('usergroup_id') . ' = ' . $groupId)
    )->loadResult();

    if ($exists) {
        continue;
    }

    $row               = new \stdClass();
    $row->download_id  = (int) $dl->id;
    $row->usergroup_id = $groupId;
    $db->insertObject('#__db8downloads_download_usergroups', $row);
    $added++;
}

db8_say('whitelist: ' . $added . ' added, ' . (\count($downloads) - $added) . ' already present');

// --- 3. the plan that confers the group ------------------------------------

if (!ComponentHelper::isEnabled('com_db8access')) {
    exit("com_db8access is not enabled — cannot create the plan\n");
}

$planId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__db8access_plans'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote(DB8_PLAN_ALIAS))
)->loadResult();

if ($planId) {
    // Make sure it still points at the right group even if it was edited.
    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__db8access_plans'))
            ->set($db->quoteName('joomla_usergroup_id') . ' = ' . $groupId)
            ->where($db->quoteName('id') . ' = ' . $planId)
    )->execute();

    db8_say('plan: exists (id ' . $planId . ')');
} else {
    $plan                       = new \stdClass();
    $plan->title                = 'db8 All Access';
    $plan->alias                = DB8_PLAN_ALIAS;
    $plan->description          = 'Access to every db8 extension, including updates and support, for one year.';
    $plan->price                = 199.00;
    $plan->currency             = 'EUR';
    $plan->billing_period_unit  = 'year';
    $plan->billing_period_count = 1;
    $plan->trial_days           = 0;
    $plan->catid               = 0;
    $plan->joomla_usergroup_id  = $groupId;
    $plan->published            = 1;
    $plan->ordering             = 1;
    $plan->created              = $now->toSql();
    $plan->created_by           = $db8SuperUserId;

    $db->insertObject('#__db8access_plans', $plan, 'id');
    $planId = (int) $plan->id;
    db8_say('plan: created (id ' . $planId . ')');
}

// --- 4. a test customer ----------------------------------------------------

$userId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__users'))
        ->where($db->quoteName('username') . ' = ' . $db->quote(DB8_TEST_USERNAME))
)->loadResult();

if ($userId) {
    db8_say('user: exists (id ' . $userId . ')');
} else {
    // #__users has a number of NOT NULL columns with no default (params,
    // activation, otpKey, otep, resetCount, requireReset, authProvider), and
    // Table\User does not fill them in. Set them explicitly or the INSERT is
    // rejected with "Field 'params' doesn't have a default value".
    $user               = new \Joomla\CMS\Table\User($db);
    $user->name         = 'db8 Test Customer';
    $user->username     = DB8_TEST_USERNAME;
    $user->email        = DB8_TEST_EMAIL;
    $user->password     = UserHelper::hashPassword(DB8_TEST_PASSWORD);
    $user->block        = 0;
    $user->sendEmail    = 0;
    $user->registerDate = $now->toSql();
    $user->lastvisitDate = null;
    $user->activation   = '';
    $user->params       = '{}';
    $user->resetCount   = 0;
    $user->requireReset = 0;
    $user->otpKey       = '';
    $user->otep         = '';
    $user->authProvider = '';
    $user->groups       = [2];

    db8_store($user, 'user');
    $userId = (int) $user->id;
    db8_say('user: created (id ' . $userId . ') — ' . DB8_TEST_USERNAME . ' / ' . DB8_TEST_PASSWORD);
}

// Group membership: Registered plus db8 Customers.
foreach ([2, $groupId] as $gid) {
    $inGroup = (int) $db->setQuery(
        $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__user_usergroup_map'))
            ->where($db->quoteName('user_id') . ' = ' . $userId)
            ->where($db->quoteName('group_id') . ' = ' . (int) $gid)
    )->loadResult();

    if (!$inGroup) {
        $map           = new \stdClass();
        $map->user_id  = $userId;
        $map->group_id = (int) $gid;
        $db->insertObject('#__user_usergroup_map', $map);
    }
}

db8_say('user groups: Registered + ' . DB8_GROUP_TITLE);

// Billing record (unique on user_id).
$hasCustomer = (int) $db->setQuery(
    $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__db8access_customers'))
        ->where($db->quoteName('user_id') . ' = ' . $userId)
)->loadResult();

if (!$hasCustomer) {
    $customer               = new \stdClass();
    $customer->user_id      = $userId;
    $customer->company_name = 'db8 Test Customer';
    $customer->first_name   = 'Test';
    $customer->last_name    = 'Customer';
    $customer->address1     = 'Teststraat 1';
    $customer->postcode     = '1000 AA';
    $customer->city         = 'Amsterdam';
    $customer->country_code = 'NL';
    $customer->vat_valid    = 0;
    $customer->created      = $now->toSql();

    $db->insertObject('#__db8access_customers', $customer);
    db8_say('customer record: created');
} else {
    db8_say('customer record: exists');
}

// --- 5. an ACTIVE subscription ---------------------------------------------
//
// This is the link that resolveUsergroupForUser() needs. Without a row that is
// both status='active' and current_period_end in the future, the licence key
// validates but resolves to a null group and the download is refused.

$subId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__db8access_subscriptions'))
        ->where($db->quoteName('user_id') . ' = ' . $userId)
        ->where($db->quoteName('plan_id') . ' = ' . $planId)
)->loadResult();

$periodEnd = (clone $now)->modify('+1 year')->toSql();

if ($subId) {
    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__db8access_subscriptions'))
            ->set($db->quoteName('status') . ' = ' . $db->quote('active'))
            ->set($db->quoteName('current_period_end') . ' = ' . $db->quote($periodEnd))
            ->set($db->quoteName('modified') . ' = ' . $db->quote($now->toSql()))
            ->where($db->quoteName('id') . ' = ' . $subId)
    )->execute();

    db8_say('subscription: refreshed (id ' . $subId . ', active until ' . $periodEnd . ')');
} else {
    $sub                       = new \stdClass();
    $sub->user_id              = $userId;
    $sub->plan_id              = $planId;
    $sub->status               = 'active';
    $sub->started_at           = $now->toSql();
    $sub->current_period_start = $now->toSql();
    $sub->current_period_end   = $periodEnd;
    $sub->payment_provider     = 'manual';
    $sub->notes                = 'Created by provision-licensing.php for testing the gated update channel.';
    $sub->created              = $now->toSql();

    $db->insertObject('#__db8access_subscriptions', $sub, 'id');
    db8_say('subscription: created (id ' . (int) $sub->id . ', active until ' . $periodEnd . ')');
}

// --- 6. a licence key ------------------------------------------------------

if (!ComponentHelper::isEnabled('com_db8licenses')) {
    exit("com_db8licenses is not enabled — cannot issue a key\n");
}

$existingKey = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('license_key'))
        ->from($db->quoteName('#__db8licenses_licenses'))
        ->where($db->quoteName('user_id') . ' = ' . $userId)
        ->where($db->quoteName('status') . ' = ' . $db->quote('active'))
        ->order($db->quoteName('id') . ' DESC'),
    0,
    1
)->loadResult();

if ($existingKey) {
    db8_say('licence: exists');
} else {
    $app->bootComponent('com_db8licenses');

    $licenseId = (new LicenseGenerator())->create([
        'user_id'          => $userId,
        'source_component' => 'com_db8access',
        'source_id'        => $planId,
        'label'            => 'db8 All Access (test)',
        'activation_limit' => 5,
        'expires'          => $periodEnd,
    ]);

    if (!$licenseId) {
        exit("licence: FAILED to create\n");
    }

    $existingKey = $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('license_key'))
            ->from($db->quoteName('#__db8licenses_licenses'))
            ->where($db->quoteName('id') . ' = ' . (int) $licenseId)
    )->loadResult();

    db8_say('licence: created (id ' . $licenseId . ')');
}

db8_say('');
db8_say('Licence key: ' . $existingKey);
db8_say('');
db8_say('On a customer site, put this in the update site Extra Query field as');
db8_say('  license_key=' . $existingKey);
db8_say('Joomla appends it to both the feed request and the download request.');
