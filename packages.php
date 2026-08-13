<?php

/**
 * The db8 extension catalogue.
 *
 * One entry per package. Consumed by provision-downloads.php.
 *
 *   element    MUST equal <name> in the package manifest. It becomes <element>
 *              in the update feed and is matched byte-for-byte against
 *              #__extensions.element on the customer's site. A mismatch fails
 *              silently: Joomla fetches the feed, finds nothing that matches,
 *              and reports no update available.
 *
 *   introtext  One line. Shown on the listing cards, and copied into the update
 *              stream's description by DownloadsStreamSync::sync(), which the
 *              feed then strips to plain text. Keep it short and tag-free —
 *              this is why the rich copy lives in fulltext instead.
 *
 *   fulltext   The product page.
 *
 * The introtext strings are the same sentences as the PKG_*_XML_DESCRIPTION
 * values in each repo's build/language/en-GB/pkg_*.sys.ini, so the site, the
 * package manifest and the feed all describe a package identically.
 */

\defined('_JEXEC') or die;

return [
    [
        'repo'      => 'db8setup',
        'element'   => 'pkg_db8setup',
        'title'     => 'db8 Setup',
        'alias'     => 'db8-setup',
        'version'   => '0.9.1',
        'ordering'  => 1,
        'introtext' => 'Guided setup for the db8 extension family: presets, provisioning and health checks, plus the db8 administrator menu branch and its dashboard.',
        'fulltext'  => <<<'HTML'
<p>db8 Setup is the starting point for the db8 family. It gathers every db8
component under a single administrator menu branch, adds a dashboard that shows
what is installed and how it is configured, and provides guided provisioning so
a new site can be brought up without clicking through eight components in
turn.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Presets</strong> — apply a known-good configuration across the
        installed db8 components instead of setting each option by hand.</li>
    <li><strong>Provisioning</strong> — create the categories, menu items and
        user groups a working setup needs.</li>
    <li><strong>Health checks</strong> — verify that storage paths are writable
        and protected, that dependent components are installed and enabled, and
        that licence and download configuration is consistent.</li>
    <li><strong>Reports and profiles</strong> — export the current
        configuration, so a support question can be answered from facts.</li>
    <li><strong>Workflow</strong> — a walkthrough of the steps involved in
        getting the family configured end to end.</li>
</ul>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8</code> — the parent administrator menu component.</li>
    <li><code>com_db8setup</code> — the setup component itself.</li>
    <li><code>mod_db8status</code> — an administrator module showing status at a
        glance.</li>
    <li><code>plg_system_db8menu</code> — builds the db8 menu branch.</li>
    <li><code>plg_console_db8setup</code> — command line access to the same
        operations.</li>
</ul>

<p>Install this package first. The other db8 packages register their
administrator menu items under the branch it provides.</p>
HTML,
        'changelog' => '<ul><li>Update feeds now publish a SHA-512 checksum, which Joomla verifies before installing.</li></ul>',
    ],
    [
        'repo'      => 'db8downloads',
        'element'   => 'pkg_db8downloads',
        'title'     => 'db8 Downloads',
        'alias'     => 'db8-downloads',
        'version'   => '0.9.0',
        'ordering'  => 2,
        'introtext' => 'Gated file downloads for Joomla, with versions, categories and Smart Search integration.',
        'fulltext'  => <<<'HTML'
<p>db8 Downloads serves files that not everyone should have. Uploads are stored
outside the web root, so nothing is fetchable by guessing a URL: every request
goes through PHP, which decides whether the visitor is entitled to the file
before a single byte is sent.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Downloads and versions</strong> — one record per product, any
        number of versions, each with its own file, release date, changelog and
        compatibility range. A version can be flagged as the current one.</li>
    <li><strong>Checksums</strong> — SHA-256, SHA-384 or SHA-512 computed on
        upload and shown alongside the file, so anyone can verify what they
        downloaded.</li>
    <li><strong>Access control</strong> — Joomla view levels, user group
        whitelists, and licence keys validated through db8 Licenses.</li>
    <li><strong>Audit log</strong> — every delivery and every refusal is
        recorded with the reason, the user and the address.</li>
    <li><strong>Smart Search</strong> — a finder plugin, so downloads appear in
        site search results.</li>
    <li><strong>Categories</strong> — standard Joomla categories, so the listing
        behaves the way the rest of the site does.</li>
</ul>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8downloads</code> — the component.</li>
    <li><code>plg_finder_db8downloads</code> — Smart Search integration.</li>
</ul>

<p>Files can also be linked rather than stored, for content hosted elsewhere.
Pair this with <strong>db8 Updates</strong> to turn a download into a Joomla
update channel.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
    [
        'repo'      => 'db8updates',
        'element'   => 'pkg_db8updates',
        'title'     => 'db8 Updates',
        'alias'     => 'db8-updates',
        'version'   => '0.9.1',
        'ordering'  => 3,
        'introtext' => 'Update server for Joomla extensions: streams, versions and access-gated update feeds.',
        'fulltext'  => <<<'HTML'
<p>db8 Updates turns your site into an update server for the extensions you
publish. Your customers' Joomla installations poll it the same way they poll
any other update site, and see your releases in the ordinary
<em>Extensions: Update</em> screen.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Streams</strong> — one per extension you publish, each with a
        release channel, so a beta line can run alongside a stable one.</li>
    <li><strong>Versions</strong> — what is offered, for which Joomla and PHP
        versions, with a changelog and a release date.</li>
    <li><strong>Feeds</strong> — valid Joomla update XML, cached, served on a
        readable URL such as <code>/updates/your-extension</code>.</li>
    <li><strong>Checksums</strong> — the SHA hash travels in the feed, so Joomla
        verifies the download before installing it and refuses a file that has
        been altered in transit.</li>
    <li><strong>Sync from downloads</strong> — streams and versions are derived
        from your db8 Downloads catalogue rather than maintained twice.</li>
</ul>

<h3>How gating works</h3>
<p>Feeds are public by design and the download is what is gated. A customer
whose licence has lapsed still <em>sees</em> that an update exists — otherwise
they would never learn about a security release — but cannot download it. A
hidden feed would do the opposite and report "up to date" to someone who is
not.</p>

<p>Requires <strong>db8 Downloads</strong> to serve the files, and pairs with
<strong>db8 Licenses</strong> to decide who may fetch them.</p>
HTML,
        'changelog' => <<<'HTML'
<p>First release.</p>
<ul>
    <li><strong>Updates now reach the sites subscribed to a feed.</strong>
        Joomla assumes an update applies to the administrator client unless the
        feed says otherwise, so packages, plugins, libraries and files never
        matched an installed extension and every site was told it was up to
        date. The feed now sends <code>&lt;client&gt;</code>.</li>
    <li>The feed is valid Joomla update XML, rooted on
        <code>&lt;updates&gt;</code>.</li>
    <li>Download URLs are no longer double-escaped.</li>
    <li>Releases publish a SHA-512 checksum, which Joomla verifies before
        installing.</li>
</ul>
HTML,
    ],
    [
        'repo'      => 'db8licenses',
        'element'   => 'pkg_db8licenses',
        'title'     => 'db8 Licenses',
        'alias'     => 'db8-licenses',
        'version'   => '0.9.0',
        'ordering'  => 4,
        'introtext' => 'Licence key issuing and validation for commercial Joomla extensions.',
        'fulltext'  => <<<'HTML'
<p>db8 Licenses issues the keys that identify your customers and validates them
when something is requested. It is the piece that lets the rest of the family
tell a paying customer from a passer-by.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Licences</strong> — issue keys against a customer, with a status
        and an optional expiry date.</li>
    <li><strong>Minting</strong> — generate keys in bulk.</li>
    <li><strong>Activations</strong> — record where a key is in use.</li>
    <li><strong>Validation</strong> — a single service the other db8 components
        call, so the rules live in one place.</li>
    <li><strong>Customer view</strong> — a front-end page where customers find
        their own keys.</li>
</ul>

<h3>How customers use a key</h3>
<p>The key goes in the <em>Extra Query</em> field of the update site in their
Joomla installation. Joomla appends it to both the feed request and the download
request, so one value covers both. It is never placed in the package manifest,
which ships identically to everyone.</p>

<p>Used by <strong>db8 Downloads</strong> and <strong>db8 Updates</strong>.
Expired keys still see updates; they are refused the file.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
    [
        'repo'      => 'db8access',
        'element'   => 'pkg_db8access',
        'title'     => 'db8 Access',
        'alias'     => 'db8-access',
        'version'   => '0.9.0',
        'ordering'  => 5,
        'introtext' => 'Subscriptions and access control for Joomla: plans, subscriptions, customers, and the plugins that enforce them.',
        'fulltext'  => <<<'HTML'
<p>db8 Access sells and enforces recurring access. It defines what is on offer,
tracks who currently holds it, and moves people in and out of Joomla user groups
as their subscription starts, renews or lapses.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Plans</strong> — what is sold, at what price, over what period,
        and which Joomla user group membership confers.</li>
    <li><strong>Subscriptions</strong> — the current state of each customer's
        access, including the period it runs to.</li>
    <li><strong>Customers</strong> — billing details and VAT identifiers, with
        country handling for EU VAT.</li>
    <li><strong>Checkout</strong> — a front-end purchase flow.</li>
    <li><strong>Account</strong> — where customers see and manage what they
        hold.</li>
    <li><strong>Enforcement</strong> — system, user and scheduled task plugins
        that apply and expire group membership without manual intervention.</li>
</ul>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8access</code> — the component.</li>
    <li><code>plg_system_db8access</code>, <code>plg_user_db8access</code>,
        <code>plg_user_db8accessprofile</code>, <code>plg_task_db8access</code>.</li>
</ul>

<p><strong>Requires db8 Payment</strong> — checkout will not complete without
it. Pairs with <strong>db8 Invoices</strong> for billing documents and with
<strong>db8 Licenses</strong> to turn a subscription into a licence key.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
    [
        'repo'      => 'db8payment',
        'element'   => 'pkg_db8payment',
        'title'     => 'db8 Payment',
        'alias'     => 'db8-payment',
        'version'   => '0.9.0',
        'ordering'  => 6,
        'introtext' => 'Payment processing for Joomla with Mollie, Stripe, PayPal and bank transfer, plus EU VAT validation.',
        'fulltext'  => <<<'HTML'
<p>db8 Payment is the payment layer the rest of the family builds on. It
provides the checkout events, the transaction record and the gateway plugins, so
that db8 Access and db8 Tickets do not each implement payment separately.</p>

<h3>Gateways included</h3>
<ul>
    <li><strong>Mollie</strong> — iDEAL, cards and the other Mollie methods.</li>
    <li><strong>Stripe</strong> — cards.</li>
    <li><strong>PayPal</strong>.</li>
    <li><strong>Bank transfer</strong> — for invoiced, manually reconciled
        payments.</li>
    <li><strong>Dummy</strong> — completes without charging, for testing a
        checkout flow before going live.</li>
</ul>

<h3>Also included</h3>
<ul>
    <li><strong>Transactions</strong> — an administrator record of every attempt
        and its outcome.</li>
    <li><strong>Customers and countries</strong> — the billing data a payment
        needs.</li>
    <li><strong>EU VAT validation</strong> — a tax plugin that checks VAT
        identification numbers, so cross-border business sales can be handled
        correctly.</li>
    <li><strong>Recorder and invoices bridges</strong> — hand a completed
        payment on to the component that asked for it, and to db8 Invoices.</li>
</ul>

<p>Install this before <strong>db8 Access</strong> or
<strong>db8 Tickets</strong>: both require it at checkout.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
    [
        'repo'      => 'db8invoices',
        'element'   => 'pkg_db8invoices',
        'title'     => 'db8 Invoices',
        'alias'     => 'db8-invoices',
        'version'   => '0.9.0',
        'ordering'  => 7,
        'introtext' => 'Invoice generation and PDF rendering for the db8 extension family.',
        'fulltext'  => <<<'HTML'
<p>db8 Invoices produces the billing document that follows a payment. It listens
for completed transactions from db8 Payment and renders an invoice as a PDF,
with the numbering, customer details and VAT treatment already filled in.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Invoices</strong> — generated from completed transactions rather
        than entered by hand.</li>
    <li><strong>PDF rendering</strong> — a self-contained renderer is bundled;
        no external service is called and nothing leaves the site.</li>
    <li><strong>Sequential numbering</strong> — as bookkeeping requires.</li>
    <li><strong>VAT handling</strong> — including the reverse-charge case for
        validated cross-border business customers.</li>
</ul>

<p>Works with <strong>db8 Payment</strong> and <strong>db8 Access</strong>.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
    [
        'repo'      => 'db8support',
        'element'   => 'pkg_db8support',
        'title'     => 'db8 Support',
        'alias'     => 'db8-support',
        'version'   => '0.9.0',
        'ordering'  => 8,
        'introtext' => 'Support ticket system for Joomla, with categories and attachments.',
        'fulltext'  => <<<'HTML'
<p>db8 Support is a help desk that lives inside Joomla, so support conversations
sit next to the customer and licence records they relate to instead of in a
separate system.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Tickets</strong> — submitted from the front end, answered from
        the administrator, with the full thread in one place.</li>
    <li><strong>Categories</strong> — route questions by product or topic using
        standard Joomla categories.</li>
    <li><strong>Attachments</strong> — customers can attach the screenshot or
        log that explains the problem.</li>
    <li><strong>Customer view</strong> — a front-end list where a customer sees
        their own tickets and nothing else.</li>
    <li><strong>Email</strong> — notifications through Joomla's own mail
        configuration.</li>
</ul>

<p>Stands on its own. Combined with <strong>db8 Access</strong> it can be
limited to customers with current subscriptions.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
    [
        'repo'      => 'db8tickets',
        'element'   => 'pkg_db8tickets',
        'title'     => 'db8 Tickets',
        'alias'     => 'db8-tickets',
        'version'   => '0.9.0',
        'ordering'  => 9,
        'introtext' => 'Event ticketing for Joomla: events, orders and check-in.',
        'fulltext'  => <<<'HTML'
<p>db8 Tickets sells admission to events and gets people through the door on the
day. Events, ticket types, orders and check-in are all handled inside Joomla.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Events</strong> — dates, capacity and description, published as
        ordinary site pages.</li>
    <li><strong>Ticket types</strong> — several per event, each with its own
        price and availability, for early-bird or concession pricing.</li>
    <li><strong>Orders</strong> — a purchase record per buyer, with the
        individual tickets it contains.</li>
    <li><strong>Check-in</strong> — an administrator screen for admitting
        attendees on the day, so a ticket cannot be used twice.</li>
    <li><strong>Front end</strong> — event listing, event page, checkout, and a
        page where a buyer retrieves their tickets.</li>
</ul>

<p><strong>Requires db8 Payment</strong> for checkout. Pairs with
<strong>db8 Invoices</strong> when a buyer needs a billing document.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
];
