<?php /** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

use Abivia\Penknife\ParseError;
use Abivia\Penknife\Penknife;
use wpdb;

class LinkShortener
{
    const string DB_VERSION = '1.0.0';
    protected Clicks $clicks;
    protected Destinations $destinations;
    protected array $formData;
    protected static ?self $instance = null;
    protected Links $links;
    protected string $message = '';
    protected Penknife $penknife;
    static string $slugPrefix = 'l';

    public function __construct(private readonly string $baseMenuSlug, protected wpdb $dbc)
    {
        $this->links = new Links($this->dbc);
        $this->destinations = new Destinations($this->dbc);
        $this->clicks = new Clicks($this->dbc);
        $this->penknife = new Penknife()->includePath(__DIR__ . '/../penknife');
    }

    public function activate(): void
    {
        $this->createDb();
        $this->addRewriteRule();
        flush_rewrite_rules();
    }

    public function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^' . self::$slugPrefix . '/([^/]+)/?$',
            'index.php?abisl_slug=$matches[1]',
            'top'
        );
    }

    /**
     * @return void
     * @noinspection PhpUnused
     */
    public function adminPage(): void
    {
        try {
            // Process any request data
            $operation = $this->linkFormRequest();

            if ($operation === '') {
                // No operation was performed. Either there was no data or there is an error.
                $this->formData['nonce'] = wp_nonce_field('abisl_create_link', 'abisl_nonce', false);
                $this->formData['submit'] = get_submit_button(
                    'Create Short Link', 'primary', 'submit', false
                );
                $this->queueAdminAssets();
                echo $this->penknife->format(
                    file_get_contents(__DIR__ . '/../penknife/adminPage.html'),
                    function (string $expr) {
                        if (str_starts_with($expr, 'error.')) {
                            $field = substr($expr, 6);
                            return isset($this->formData['error'][$field])
                                ? "<p>{$this->formData['error'][$field]}</p>"
                                : '';
                        }
                        return $this->formData[$expr] ?? null;
                    }
                );
            } else {
                // Operation was a success, return to admin page
                $this->message = $this->formData['messages']['form'];
                $this->analyticsPage();
            }
        } catch (ParseError $exception) {
            echo '<div class="wrap abisl-admin">'
                . $this->errorNotice('Template error.', true, $exception->getMessage())
                . '</div>';
        } catch (Problem $problem) {
            $this->errorNotice($problem->getMessage());
        }
    }

    public function analyticsPage(): void
    {
        $this->queueAdminAssets();

        // Handle deletion from overview
        if (isset($_GET['delete'])) {
            $deleteId = absint($_GET['delete']);
            if ($deleteId && check_admin_referer("abisl_delete_$deleteId")) {
                $this->linkDelete($deleteId);
                $this->message = 'Link deleted successfully.';
                $this->overviewPage();
                return;
            } else {
                echo $this->errorNotice('Invalid deletion request.');
            }
        }

        $editId = absint($_GET['linkId'] ?? 0);
        if ($editId) {
            $this->linkEdit($editId);
            return;
        }

        $linkId = absint($_GET['link'] ?? 0);
        $viewDate = sanitize_text_field($_GET['view_date'] ?? '');

        if ($linkId) {
            $link = $this->links->getOne("linkId=%d", [$linkId]);
            if ($link === null) {
                echo '<div class="wrap abisl-admin">';
                echo $this->errorNotice('Link not found.') . '</div>';
                return;
            }
            // Get clicks grouped by day
            $dailyStats = $this->clicks->selectDaily($linkId);

            // Format click fields for display
            foreach ($dailyStats as $stat) {
                $stat->click_date = esc_html(mysql2date('Y-m-d', $stat->click_date));
                $stat->clicks = intval($stat->clicks);
                $stat->detail = esc_url(add_query_arg('view_date', $stat->click_date));
            }

            // Gather fields for the analytics page
            $map = [
                'alias' => esc_html($link->alias),
                'analyticsUrl' => esc_url($this->myUrl()),
                'dailyStats' => $dailyStats,
            ];

            // Show click details only if a specific date is selected
            if ($viewDate) {
                $map['viewDate'] = esc_html(mysql2date('Y-m-d', $viewDate));
                $map['returnUrl'] = esc_url(remove_query_arg('view_date'));
                // Pagination variables
                $perPage = 50;
                $currentPage = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $map['currentPage'] = $currentPage;
                $offset = ($currentPage - 1) * $perPage;

                // Get total clicks count for pagination
                $totalClicks = $this->clicks->getDailyTotal($linkId, $viewDate);
                $map['totalPages'] = $totalPages = ceil($totalClicks / $perPage);

                // Get detailed clicks for the selected date with pagination
                $rows = $this->clicks->selectPage($linkId, $viewDate, $perPage, $offset);

                foreach ($rows as $row) {
                    $row->clickedAt = esc_html(mysql2date('Y-m-d H:i:s', $row->clickedAt));
                    $row->ipAddress = esc_html($row->ipAddress);
                    $row->destinationUrl = esc_html($row->destinationUrl);
                    $row->userAgent = esc_html(substr($row->userAgent, 0, 70));
                }
                $map['clickRows'] = $rows->getList();

                // Pagination controls
                if ($totalPages > 1) {
                    echo '<div class="abisl-pagination">';
                    if ($currentPage > 1) {
                        $map['firstUrl'] = esc_url(add_query_arg(
                            [
                                'paged' => 1,
                                'view_date' => $viewDate
                            ]
                        ));
                        $map['prevUrl'] = esc_url(add_query_arg(
                            [
                                'paged' => $currentPage - 1,
                                'view_date' => $viewDate
                            ]
                        ));
                    }
                    if ($currentPage < $totalPages) {
                        $map['lastUrl'] = esc_url(add_query_arg(
                            [
                                'paged' => $totalPages,
                                'view_date' => $viewDate
                            ]
                        ));
                        $map['nextUrl'] = esc_url(add_query_arg(
                            [
                                'paged' => $currentPage + 1,
                                'view_date' => $viewDate
                            ]
                        ));
                    }
                }
            }
            try {
                echo $this->penknife->format(
                    file_get_contents(__DIR__ . '/../penknife/analyticsPage.html'),
                    function ($attr) use ($map) {
                        return $map[$attr] ?? null;
                    }
                );
            } catch (ParseError $exception) {
                $this->message = 'Template error:' . $exception->getMessage();
                $this->overviewPage();
            }

            $csvFilename = 'analytics_' . esc_js($link->alias) . '_' . esc_js($viewDate) . '.csv';
            wp_localize_script(
                'abisl-admin-js',
                'abislAdmin',
                [
                    'analyticsUrl' => $this->myUrl(),
                    'csvFilename' => $csvFilename,
                    'nonce' => wp_create_nonce('abisl_admin_nonce')
                ]
            );

        } else {
            $this->overviewPage();
        }
    }

    public function call(string $method): array
    {
        return [self::$instance, $method];
    }

    public function checkDbVersion(): void
    {
        $currentVersion = get_option('abisl_db_version', '1.0.0');

        if (version_compare($currentVersion, self::DB_VERSION, '<')) {
            $this->createDb();
            update_option('abisl_db_version', self::DB_VERSION);
        }
    }

    public function createDb(): void
    {
        $this->links->createTable();
        $this->destinations->createTable();
        $this->clicks->createTable();
    }

    public function csvExport(): void
    {
        new Exporter($this->dbc)->csv();
    }

    private function editPage($link = null): void
    {
        try {
            if ($link === null) {
                $link = new Link();
                if (isset($_GET['linkId'])) {
                    $link = $this->links->getOne('linkId=%d', [$_GET['linkId']]);
                    if ($link === null) {
                        throw new Problem('Specified link does not exist.');
                    }
                }
            }
            $this->loadForm($link);
            // Process any request data
            $operation = $this->linkFormRequest();

            if ($operation === '') {
                // No operation was performed. Either there was no data or there is an error.
                // Merge in the additional fields for the form,
                $slugPrefix = self::$slugPrefix;
                $extra = [
                    'nonce' => wp_nonce_field('abisl_create_link', 'abisl_nonce', false),
                    'aliasLink' => esc_html(home_url("/$slugPrefix/" . $link->alias)),
                    'aliasUrl' => esc_url(home_url("/$slugPrefix/" . $link->alias)),
                    'deleteLink' => esc_url(wp_nonce_url(
                        $this->myUrl("add&linkId=$link->linkId&delete=1"),
                        "abisl_delete_$link->linkId"
                    )),
                    'returnLink' => esc_url($this->myUrl()),
                    'rotate' => $link->isRotating ? 'checked' : '',
                    'select301' => $link->httpCode === 301 ? 'selected' : '',
                    'select302' => $link->httpCode === 302 ? 'selected' : '',
                    'select307' => $link->httpCode === 307 ? 'selected' : '',
                    'submit' => get_submit_button('Save Changes', 'primary', 'submit', false),
                    'text' => $link->defaultText,
                ];
                $this->formData = array_merge($this->formData, $extra);
                $this->queueAdminAssets();
                echo $this->penknife->format(
                    file_get_contents(__DIR__ . '/../penknife/editPage.html'),
                    function (string $expr) {
                        if (str_starts_with($expr, 'error.')) {
                            $field = substr($expr, 6);
                            return isset($this->formData['error'][$field])
                                ? "<p>{$this->formData['error'][$field]}</p>"
                                : '';
                        }
                        return $this->formData[$expr] ?? null;
                    }
                );
            } else {
                // Operation was a success, generate an admin page
                $this->message = $this->formData['messages']['form'];
                unset($_GET['linkId']);
                $this->analyticsPage();
            }
        } catch (ParseError $exception) {
            echo '<div class="wrap abisl-admin">'
                . $this->errorNotice('Template error.', true, $exception->getMessage())
                . '</div>';
        } catch (Problem $problem) {
            $this->errorNotice($problem->getMessage());
        }
    }

    private function errorNotice(string $message, $log = false, string $extra = ''): string
    {
        if ($log) {
            $delim = ($extra === '') ? '' : ' ';
            error_log("ABISL: $message$delim$extra");
        }
        return '<div class="notice notice-error is-dismissible">'
            . "<p>" . esc_html($message) . "</p>"
            . '</div>';
    }

    public function geoCode(string $ipAddress): array
    {
        $nullResult = ['country' => null, 'region' => null, 'city' => null];
        if ($ipAddress === '0.0.0.0') {
            return $nullResult;
        }
        $response = wp_remote_get("https://ipapi.co/$ipAddress/json/");

        if (is_wp_error($response)) {
            return $nullResult;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return [
            'countryCode' => $data['country_code'] ?? null,
            'regionCode' => $data['region_code'] ?? null,
            'city' => $data['city'] ?? null,
        ];
    }

    /**
     * Connect to all the WP things.
     * @return void
     */
    public function hookIn(): void
    {
        add_action('plugins_loaded', function () {
            $this->checkDbVersion();
        });
        add_action('init', function () {
            $this->addRewriteRule();
        });
        add_filter('query_vars', $this->call('registerQueryVars'));
        add_action('template_redirect', function () {
            $this->redirect();
        });
        add_action('admin_menu', function () {
            $this->insertAdminMenu();
        });
        add_action('wp_ajax_abisl_export_csv', function () {
            $this->csvExport();
        });
        add_shortcode('short', $this->call('shortcode'));
        add_shortcode('shortlist', $this->call('shortcodeList'));
    }

    public function insertAdminMenu(): void
    {
        add_menu_page(
            'Abivia Link Shortener',
            'Short Links',
            'manage_options',
            $this->baseMenuSlug,
            function () {
                $this->analyticsPage();
            },
            'dashicons-admin-links',
        );
        add_submenu_page(
            $this->baseMenuSlug,
            'Add Link',
            'Add Link',
            'manage_options',
            'abisl-add',
            function () {
                $this->editPage();
            }
        );
    }

    /**
     * Create a new link.
     *
     * @param array $linkData
     * @return int
     * @throws Problem
     */
    private function linkCreate(array $linkData): int
    {
        // Insert link
        $result = $this->links->insert($linkData);

        if ($result === false) {
            $error = "Failed to create short link with alias \"{$linkData['alias']}\": "
                . $this->dbc->last_error;

            // Additional debug info
            error_log("ABISL: $error");
            error_log('ABISL: Insert data: ' . print_r($linkData, true));
            throw new Problem($error);
        }

        $insertId = $this->dbc->insert_id;
        if (!$insertId) {
            throw new Problem('Failed to retrieve new link ID.');
        }

        $this->destinations->parse($linkData['destinations'])->replace($insertId);

        return $insertId;
    }

    private function linkDelete(int $deleteId): void
    {
        /** @noinspection PhpExpressionResultUnusedInspection */
        $this->dbc->query('START TRANSACTION');
        $this->links->delete(['linkId' => $deleteId]);
        $this->destinations->delete(['linkId' => $deleteId]);
        $this->clicks->delete(['linkId' => $deleteId]);
        /** @noinspection PhpExpressionResultUnusedInspection */
        $this->dbc->query('COMMIT');
    }

    private function linkEdit(int $editId): void
    {
        $link = $this->links->getOne("linkId = %d", [$editId]);
        echo '<div class="wrap abisl-admin">';
        try {
            if ($link) {
                $link->destinations = $this->destinations->get($editId);
                $this->editPage($link);
            } else {
                throw new Problem('Link not found.');
            }
        } catch (Problem $exception) {
            echo $this->errorNotice($exception->getMessage());
        }
        echo '</div>';
    }

    /**
     * Handle data sent from the create/edit link form.
     * @return string
     * @throws Problem
     */
    public function linkFormRequest(): string
    {
        $operation = '';
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && check_admin_referer('abisl_create_link', 'abisl_nonce')
        ) {
            // Sanitize and validate POST data
            $this->validateEditFormData();

            // All checks pass, insert/update the link
            if (count($this->formData['error']) === 0) {
                if ($this->formData['linkId'] === 0) {
                    $linkId = $this->linkCreate($this->formData);
                    $this->formData['linkId'] = $linkId;
                    $operation = 'created';
                } else {
                    $this->linkUpdate();
                    $operation = 'updated';
                }

                $slugPrefix = self::$slugPrefix;
                $shortUrl = home_url("/$slugPrefix/{$this->formData['alias']}");
                $this->formData['messages']['form'] = "Short link $operation successfully!"
                    . ' <a href="' . esc_url($shortUrl) . '" target="_blank">'
                    . esc_html($shortUrl) . '</a>';
            }
            return $operation;
        }
        return $operation;
    }

    /**
     * Update an existing link.
     *
     * @return void
     * @throws Problem Thrown if there's a database error.
     */
    private function linkUpdate(): void
    {
        // Update link
        $result = $this->links->update($this->formData, ['linkId' => $this->formData['linkId']]);
        if ($result === false) {
            error_log(
                "Failed to update short link for alias {$this->formData['alias']}: "
                . $this->dbc->last_error,
            );
            error_log('ABISL: form data: '
                . print_r($this->formData, true));
            throw new Problem("Failed to update short link: {$this->dbc->last_error}");
        }
        $this->destinations->parse($this->formData['destinations'])
            ->replace($this->formData['linkId']);
    }

    /**
     * @throws Problem
     */
    private function loadForm(Link $link): void
    {
        $this->formData = (array) $link;
        $this->formData['destinations'] = (string) $this->destinations->get($link->linkId);
    }

    private function makeHtmlClass(string $classes): string
    {
        return empty($classes) ? '' : ' class="' . esc_attr($classes) . '"';
    }

    private function myUrl(string $page = ''):string
    {
        if ($page !== '') {
            $page = "-$page";
        }
        return admin_url("admin.php?page=$this->baseMenuSlug$page");
    }

    private function overviewPage(): void
    {
        // Get all links with stats
        $allLinks = $this->dbc->get_results(
            "SELECT l.*, COUNT(c.id) AS totalClicks"
            . ", MAX(c.clickedAt) AS lastClick"
            . " FROM {$this->links->tableName()} AS l"
            . " LEFT JOIN {$this->clicks->tableName()} AS c ON l.linkId = c.linkId"
            . " GROUP BY l.alias"
            . " ORDER BY l.alias",
            OBJECT_K
        );
        $destinationCount = $this->dbc->get_results(
            "SELECT l.*, COUNT(d.id) AS totalLinks"
            . " FROM {$this->links->tableName()} AS l"
            . " INNER JOIN {$this->destinations->tableName()} AS d ON l.linkId=d.linkId"
            . " GROUP BY l.alias"
            . " ORDER BY l.alias",
            OBJECT_K
        );
        foreach ($allLinks as $alias => $link) {
            $link->totalLinks = $destinationCount[$alias]->totalLinks;
        }

        $this->formData = [
            'createUrl' => esc_url($this->myUrl('add')),
        ];
        if ($this->message !== '') {
            $this->formData['message'] = $this->message;
            $this->message= '';
        }
        $slugPrefix = self::$slugPrefix;
        foreach ($allLinks as $link) {
            $link->shortUrl = home_url("/$slugPrefix/$link->alias");
            $link->copyUrl = esc_attr($link->shortUrl);
            $link->deleteUrl = esc_url(wp_nonce_url(
                add_query_arg('delete', $link->linkId), 'abisl_delete_' . $link->linkId
            ));
            $link->editUrl = esc_url($this->myUrl("add&linkId=$link->linkId"));
            $link->viewUrl = esc_url(add_query_arg('link', $link->linkId));
            $link->linkType = $link->isRotating ? 'Rotating' : 'Fixed';
            $link->geo = $link->geoCoded  ? 'Y' : 'N';
            $link->protected = $link->password !== null ? 'Y' : 'N';
        }
        $this->formData['allLinks'] = $allLinks;

        // Overview page
        try {
            echo $this->penknife->format(
                file_get_contents(__DIR__ . '/../penknife/overviewPage.html'),
                function ($attr) {
                    return $this->formData[$attr] ?? null;
                }
            );
        } catch (ParseError $exception) {
            $this->formData['Penknife error'] = $exception->getMessage();
            file_put_contents(__DIR__ . '/../form.json', json_encode($this->formData));
        }

        if (!empty($allLinks)) {
            // Add clipboard.js functionality
            wp_enqueue_script('clipboard');
            wp_add_inline_script(
                'clipboard',
                'new ClipboardJS(".abisl-copy").on("success", function(e) {'
                . ' var originalText = e.trigger.textContent;'
                . ' e.trigger.textContent = "Copied!";'
                . ' setTimeout(function() { e.trigger.textContent = originalText; }, 2000);'
                . '});'
            );
        }

    }

    public function queueAdminAssets(): void
    {
        // Enqueue CSS
        wp_enqueue_style(
            'abisl-admin-styles',
            plugins_url('../css/admin-styles.css', __FILE__),
            [],
            '1.4.3'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'abisl-admin-js',
            plugins_url('../js/admin.js', __FILE__),
            ['jquery'],
            '1.4.3',
            true
        );

        // Enqueue clipboard.js for copy functionality
        wp_enqueue_script(
            'clipboard',
            'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js',
            [],
            '2.0.11',
            true
        );

        // Localize script for dynamic data - UPDATED WITH AJAX URL
        wp_localize_script(
            'abisl-admin-js',
            'abislAdmin',
            [
                'analyticsUrl' => $this->myUrl(),
                'ajaxUrl' => admin_url('admin-ajax.php'), // Added AJAX URL
                'csvFilename' => 'analytics_export_' . gmdate('Y-m-d') . '.csv',
                'nonce' => wp_create_nonce('abisl_admin_nonce')
            ]
        );
    }

    public function redirect(): void
    {
        $slug = get_query_var('abisl_slug');
        if (!$slug) {
            return;
        }

        $link = $this->links->getOne('alias=%s', [$slug]);
        if (!$link) {
            wp_die('Invalid short URL.', 'Short URL Error', ['response' => 404]);
        }

        // Password-protected link check
        if ($link->password) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abisl_password'])) {
                $enteredPassword = sanitize_text_field($_POST['abisl_password']);
                if ($enteredPassword !== $link->password) {
                    wp_die('Incorrect password.', 'Access Denied', ['response' => 403]);
                }
            } else {
                echo '<form method="post" style="text-align:center; margin-top:50px;">'
                    . '<h2>This page is password protected</h2>'
                    . '<input type="password" name="abisl_password" placeholder="Enter password" required>'
                    . '<button type="submit">Access</button>'
                    . '</form>';
                exit;
            }
        }
        $destinations = $this->destinations->select($link->linkId);

        // Track click
        $ipAddress = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = '0.0.0.0'; // fallback
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';

        if ($link->geoCoded) {
            $destinations = new Destinations($this->dbc)
                ->geoFilter($this->geoCode($ipAddress), $destinations);
        }
        if (empty($destinations)) {
            wp_die('No destination URL is available.', 'Short URL Error', ['response' => 404]);
        }

        $target = $link->isRotating
            ? $destinations->pickRandom()
            : $destinations[0];

        $this->clicks->insert([
            'linkId' => $link->linkId,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'clickedAt' => current_time('mysql'),
            'destinationUrl' => $target->url,
        ]);

        http_response_code($link->httpCode);
        header("Location: $target->url");
        exit;
    }

    /**
     * Add query variables to Wordpress
     * @param array $vars
     * @return array
     * @noinspection PhpUnused
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'abisl_slug';
        return $vars;
    }

    /**
     * Insert the result of a shortcode call.
     * @param array $attributes
     * @param string|null $content
     * @return string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function shortcode(array $attributes, ?string $content): string
    {
        $slugPrefix = self::$slugPrefix;
        $attributes = shortcode_atts(
            [
                'alias' => '',
                'text' => home_url("/$slugPrefix/{$attributes['alias']}"),
                'class' => ''
            ],
            $attributes
        );

        if (empty($attributes['alias'])) {
            return current_user_can('edit_posts') ? '{short link: missing alias}' : '';
        }
        $link = $this->links->getOne('alias=%s', ['alias' => $attributes['alias']]);
        if ($link === null) {
            return current_user_can('edit_posts')
                ? "{short link {$attributes['alias']} not found.}" : '';
        }

        $class = empty($attributes['class']) ? '' : ' class="' . esc_attr($attributes['class']) . '"';
        $text = esc_html($attributes['text']);
        $url = esc_url(home_url("/$slugPrefix/{$attributes['alias']}"));

        return "<a href=\"$url\"$class>$text</a>";
    }

    /**
     * Insert the result of a shortcode list call.
     * @param array $attributes
     * @param string|null $content
     * @return string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function shortcodeList(array $attributes, ?string $content): string
    {
        $attributes = shortcode_atts(
            [
                'alias' => '',
                'item_class' => '',
                'link_class' => '',
                'list_class' => '',
                'empty' => '',
                'password' => '',
            ],
            $attributes
        );

        if (empty($attributes['alias'])) {
            return current_user_can('edit_posts') ? '{short link: missing alias}'
                : $attributes['empty'];
        }
        $alias = $attributes['alias'];
        $link = $this->links->getOne('alias=%s', [$alias]);
        if (!$link) {
            return "{Short link $alias not found.}";
        }
        if ($link->password && $link->password !== $alias['password']) {
            return current_user_can('edit_posts')
                ? "{Shortcode $alias is password protected.}" : $attributes['empty'];
        }
        $destinations = $this->destinations->select($link->linkId);
        $ipAddress = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = '0.0.0.0'; // fallback
        }
        if ($link->geoCoded) {
            $destinations = new Destinations($this->dbc)
                ->geoFilter($this->geoCode($ipAddress), $destinations);
        }

        if (count($destinations) === 0) {
            return $attributes['empty'];
        }

        $html = "<ul" . $this->makeHtmlClass($attributes['list_class']) . ">\n";

        $itemClass = $this->makeHtmlClass($attributes['item_class']);
        $linkClass = $this->makeHtmlClass($attributes['link_class']);
        foreach ($destinations->getList() as $destination) {
            $url = esc_url($destination->url);
            $html .= "<li$itemClass><a href=\"$url\"$linkClass>"
                . esc_html($destination->text ?? $destination->url)
                . "</a></li>\n";
        }
        $html .= "</ul>\n";
        return $html;
    }

    public static function singleton(): static
    {
        global $wpdb;

        if (self::$instance === null) {
            self::$instance = new static('abisl', $wpdb);
        }
        return self::$instance;
    }

    private function validateEditFormData(): void
    {
        $this->formData['alias'] = strtolower(sanitize_title($_POST['abisl_alias'] ?? ''));
        $this->formData['defaultText'] = strtolower(sanitize_text_field($_POST['abisl_text'] ?? ''));
        $this->formData['password'] = sanitize_text_field($_POST['abisl_password'] ?? '');
        $this->formData['httpCode'] = (int)sanitize_text_field($_POST['abisl_http'] ?? 307);
        $this->formData['isRotating'] = isset($_POST['abisl_rotate']) ? 1 : 0;
        $this->formData['destinations'] = trim(sanitize_textarea_field($_POST['abisl_destinations']));
        $this->formData['error'] = [];

        $args = [$this->formData['alias']];
        if (is_numeric($_POST['abisl_linkId'] ?? 0)) {
            $this->formData['linkId'] = (int)$this->formData['linkId'];
            $idClause = ' AND linkId != %d';
            $args[] = $this->formData['linkId'];
        } else {
            $this->formData['linkId'] = '';
            $idClause = '';
        }
        if ($this->formData['alias'] === '') {
            $this->formData['error']['alias'] = 'Alias is required.';
        } else {
            // Check for duplicate alias
            $existingAlias = $this->links->getOne("alias=%s$idClause", $args);
            if ($existingAlias) {
                $this->formData['error']['alias'] = 'Alias "' . esc_html($this->formData['alias'])
                    . '" already exists. Please choose a different one.';
            }
        }
        // Check for invalid redirect code
        if (!in_array($this->formData['httpCode'], [301, 302, 307])) {
            $this->formData['error']['httpCode'] = "Invalid HTTP code: {$this->formData['httpCode']}";
        }

        if ($this->formData['password'] === '') {
            $this->formData['password'] = null;
        }
        // Validate destinations
        $this->destinations->parse($this->formData['destinations']);
        $this->formData['geoCoded'] = $this->destinations->geoCoded;

        if (count($this->destinations) === 0) {
            $this->formData['error']['destinations'] = 'Please provide at least one valid destination URL.';
        }
    }

}
