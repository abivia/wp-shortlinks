<?php

namespace Abivia\Wp\LinkShortener;

use wpdb;

class Exporter
{
    protected wpdb $dbc;
    protected mixed $outputFile;

    public function __construct(wpdb $dbc)
    {
        $this->dbc = $dbc;
    }

    private function close()
    {
        fclose($this->outputFile);
    }

    public function csv(): void
    {
        // Check nonce for security
        check_ajax_referer('abisl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        if ($_GET['type'] === 'overview') {
            $this->overview();
        } elseif ($_GET['type'] === 'detail') {
            $this->detail();
        } else {
            wp_die('Invalid report type.');
        }
        wp_die();
    }

    private function detail()
    {
        $this->open();
        // Export detailed click data for a specific link and date
        $linkId = absint($_GET['linkId']);
        $date = sanitize_text_field($_GET['date']);

        fputcsv($this->outputFile, ['Date', 'IP Address', 'User Agent']);


        $results = new Clicks($this->dbc)->selectPage($linkId, $date);

        foreach ($results as $row) {
            fputcsv($this->outputFile, [
                mysql2date('Y-m-d H:i:s', $row->clickedAt),
                $row->ipAddress,
                $row->userAgent
            ]);
        }
        $this->close();
    }

    private function open()
    {
        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="'
            . sanitize_file_name($_GET['filename']) . '"'
        );

        $this->outputFile = fopen('php://output', 'w');
    }

    private function overview()
    {
        $this->open();
        $clicks = new Clicks($this->dbc);
        $links = new Links($this->dbc);
        // Export overview data
        fputcsv($this->outputFile, ['Short URL', 'Total Clicks', 'Last Click', 'Type']);

        $results = $this->dbc->get_results(
            "SELECT l.alias, COUNT(c.id) as totalClicks, MAX(c.clickedAt) as lastClick"
            . ",l.isRotating, l.password"
            . "FROM {$links->tableName()} AS l"
            . " LEFT JOIN {$clicks->tableName()} AS c"
            . " ON l.id = c.linkId"
            . " GROUP BY l.id"
            . " ORDER BY totalClicks DESC"
        );

        foreach ($results as $row) {
            $type = $row->isRotating ? 'Rotating' : 'Standard';
            $type .= $row->password ? ' (Password)' : '';

            fputcsv($this->outputFile, [
                $row->alias,
                $row->totalClicks,
                $row->lastClick ? mysql2date('Y-m-d H:i:s', $row->lastClick) : 'Never',
                $type
            ]);
        }
        $this->close();
    }

}
