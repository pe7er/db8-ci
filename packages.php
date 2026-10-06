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
        'introtext' => 'db8 Setup installs and configures the other db8 extensions for you.',
        'fulltext'  => <<<'HTML'
<p>db8 Setup installs and configures the other db8 extensions for you. It puts
all db8 components in one administrator menu, checks if your site is configured
correctly, and creates the categories, user group and access level you need.
This saves time
and prevents configuration mistakes. It is for site builders who use one or more
db8 extensions on a Joomla site.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Presets</strong> — apply a known-good configuration across the
        installed db8 components instead of setting each option by hand.</li>
    <li><strong>Provisioning</strong> — create the categories, user group and
        access level a working setup needs.</li>
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
        'introtext' => 'db8 Downloads lets you offer files on your Joomla site and decide who can download them.',
        'fulltext'  => <<<'HTML'
<p>db8 Downloads lets you offer files on your Joomla site and decide who can
download them. Files are stored in a protected folder, so nobody can download
them with a direct link. You give access by access level and user group,
and licence keys through db8 Access. Every download is logged.
It is for developers, publishers and organisations that share files with customers or members.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Downloads and versions</strong> — one record per product, any
        number of versions, each with its own file, release date, changelog and
        compatibility range. A version can be flagged as the current one.</li>
    <li><strong>Checksums</strong> — SHA-256, SHA-384 or SHA-512 computed on
        upload and shown alongside the file, so anyone can verify what they
        downloaded.</li>
    <li><strong>Access control</strong> — a Joomla access level and a list of
        allowed user groups. A licence key gives access when its owner is in
        one of those groups through db8 Access.</li>
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
        'introtext' => 'db8 Updates turns your Joomla site into an update server for your own extensions.',
        'fulltext'  => <<<'HTML'
<p>db8 Updates turns your Joomla site into an update server for your own
extensions. Your customers see new versions in the normal Joomla update screen
and install them with one click. With db8 Licenses, only customers with a valid
licence can download the update. It is for developers who sell or distribute
Joomla extensions.</p>

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

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8updates</code> — the component.</li>
</ul>

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
        'introtext' => 'db8 Licenses creates licence keys for your customers and checks them when a customer downloads a file or an update.',
        'fulltext'  => <<<'HTML'
<p>db8 Licenses creates licence keys for your customers and checks them when a
customer downloads a file or an update. You see which keys are active and when they
expire. This way, only paying customers get your files.
It is for developers and companies that sell software or other digital products
with Joomla.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Licences</strong> — issue keys against a customer, with a status
        and an optional expiry date.</li>
    <li><strong>Minting</strong> — generate keys in bulk.</li>
    <li><strong>Validation</strong> — a single service the other db8 components
        call, so the rules live in one place.</li>
    <li><strong>Download codes</strong> — customers create their own download
        codes and give each one a label, for example the website that uses it.
        You set how many codes a customer may create.</li>
</ul>

<h3>How customers use a key</h3>
<p>The key goes in the <em>Extra Query</em> field of the update site in their
Joomla installation. Joomla appends it to both the feed request and the download
request, so one value covers both. It is never placed in the package manifest,
which ships identically to everyone.</p>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8licenses</code> — the component.</li>
</ul>

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
        'introtext' => 'db8 Access lets you sell subscriptions on your Joomla site.',
        'fulltext'  => <<<'HTML'
<p>db8 Access lets you sell subscriptions on your Joomla site. When a customer
pays, they automatically get access to the content of their plan. When the
subscription ends, the access ends too. You never add or remove users by hand.
It is for membership sites, online courses, publishers and software
developers.</p>

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
        'introtext' => 'db8 Payment adds online payments to the db8 extensions.',
        'fulltext'  => <<<'HTML'
<p>db8 Payment adds online payments to the db8 extensions. Customers pay with
iDEAL, credit card, PayPal or bank transfer, and EU VAT numbers are checked
when a customer buys a subscription or a ticket for an online event. All payments are listed in one overview. db8 Access and db8
Tickets need it. It is for site owners who want to sell on their Joomla site
without a separate webshop.</p>

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
    <li><strong>Recorder</strong> — routes a gateway's payment confirmation to
        the component that started the checkout, so payments confirmed later
        by webhook are recorded too.</li>
    <li><strong>Invoices bridge</strong> — hands completed payments to
        db8 Invoices.</li>
</ul>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8payment</code> — the component: transactions, customers
        and countries.</li>
    <li><code>plg_db8payment_mollie</code>, <code>plg_db8payment_stripe</code>,
        <code>plg_db8payment_paypal</code>,
        <code>plg_db8payment_banktransfer</code> — the payment gateways.</li>
    <li><code>plg_db8payment_payment_dummy</code> — the test gateway.</li>
    <li><code>plg_db8payment_recorder</code> — routes a gateway's payment
        confirmation to the component that started the checkout.</li>
    <li><code>plg_db8payment_invoices</code> — hands completed payments to
        db8 Invoices.</li>
    <li><code>plg_db8tax_vatcheck</code> — EU VAT number validation through
        VIES.</li>
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
        'introtext' => 'db8 Invoices creates a PDF invoice automatically after each payment.',
        'fulltext'  => <<<'HTML'
<p>db8 Invoices creates a PDF invoice automatically after each payment. Every
invoice gets the next invoice number, the customer details and the correct VAT,
including reverse charge for EU business customers who buy a subscription. You do not have to make
invoices by hand. It is for companies and organisations in the EU that sell with
db8 Access or db8 Tickets.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Invoices</strong> — generated from completed transactions rather
        than entered by hand.</li>
    <li><strong>PDF rendering</strong> — a self-contained renderer is bundled;
        no external service is called and nothing leaves the site.</li>
    <li><strong>Sequential numbering</strong> — as bookkeeping requires.</li>
    <li><strong>VAT handling</strong> — including the reverse-charge case for
        validated cross-border business customers who buy a
        subscription.</li>
</ul>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8invoices</code> — the component, including the bundled
        PDF renderer.</li>
</ul>

<p>Invoices are created by the <code>plg_db8payment_invoices</code> plugin,
which ships with <strong>db8 Payment</strong>.</p>

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
        'introtext' => 'db8 Support adds a help desk to your Joomla site.',
        'fulltext'  => <<<'HTML'
<p>db8 Support adds a help desk to your Joomla site. Customers send their
questions as tickets, with screenshots or PDF files, and you answer them in the
Joomla administrator, so you do not need a separate support system. It is for companies that
support customers or members.</p>

<h3>What it does</h3>
<ul>
    <li><strong>Tickets</strong> — submitted from the front end, answered from
        the administrator, with the full thread in one place.</li>
    <li><strong>Categories</strong> — route questions by product or topic using
        standard Joomla categories.</li>
    <li><strong>Attachments</strong> — customers can attach a screenshot, PDF
        or text file that explains the problem.</li>
    <li><strong>Customer view</strong> — a front-end list where a customer sees
        their own tickets and nothing else.</li>
    <li><strong>Email</strong> — notifications through Joomla's own mail
        configuration.</li>
</ul>

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8support</code> — the component.</li>
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
        'introtext' => 'db8 Tickets lets you sell tickets for events on your Joomla site.',
        'fulltext'  => <<<'HTML'
<p>db8 Tickets lets you sell tickets for events on your Joomla site. You create
events with different ticket types and prices. Customers buy their tickets and show the QR
code on their phone at the event, where you check them in. Each ticket works only once. It is
for organisers of workshops, conferences, courses and other events.</p>

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

<h3>What is in the package</h3>
<ul>
    <li><code>com_db8tickets</code> — the component.</li>
</ul>

<p><strong>Requires db8 Payment</strong> for checkout. Pairs with
<strong>db8 Invoices</strong> when a buyer needs a billing document.</p>
HTML,
        'changelog' => '<ul><li>First packaged release.</li></ul>',
    ],
];
