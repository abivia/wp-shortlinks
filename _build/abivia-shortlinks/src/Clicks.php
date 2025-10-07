<?php
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

use wpdb;

class Clicks extends DbTable
{
    protected wpdb $dbc;
    protected string $dbTable;
    protected static array $fields = [
        'id' => '%d',
        'linkId' => '%s',
        'ipAddress' => '%s',
        'userAgent' => '%s',
        'clickedAt' => '%s',
        'destinationUrl' => '%s',
    ];
    protected array $list;

    public function __construct(wpdb $dbc)
    {
        parent::__construct($dbc);
        $this->dbTable = "{$this->dbc->prefix}abisl_clicks";
    }

    public function createTable(): void
    {
        $charsetCollate = $this->dbc->get_charset_collate();
        $sqlClicks = "CREATE TABLE $this->dbTable ("
            . 'id bigint(20) NOT NULL AUTO_INCREMENT'
            . ',linkId bigint(20) NOT NULL'
            . ',ipAddress varchar(45)'
            . ',userAgent text'
            . ',clickedAt datetime NOT NULL'
            . ',destinationUrl text NOT NULL'
            . ',PRIMARY KEY (id)'
            . ',KEY linkId (linkId)'
            . ") $charsetCollate;";
        dbDelta($sqlClicks);
    }

    public function delete(array $where): bool|int
    {
        return $this->dbc->delete($this->dbTable, $where, $this->whereFormats($where));
    }

    public function getDailyTotal(int $linkId, string $forDate)
    {
        return $this->dbc->get_var($this->dbc->prepare(
            "SELECT COUNT(*) FROM $this->dbTable"
            . " WHERE linkId=%d AND DATE(clickedAt)=%s",
            $linkId,
            $forDate
        ));
    }

    public function getList(): array
    {
        return $this->list;
    }

    public function insert(array $fields): self
    {
        $this->dbc->insert($this->dbTable, $fields);
        return $this;
    }

    public function selectPage(
        int $linkId,
        string $forDate,
        ?int $limit = null,
        ?int $start = null
    ): self
    {
        $fragment = '';
        $params = [$linkId, $forDate];
        if ($limit !== null) {
            $fragment .= ' LIMIT %d';
            $params[] = $limit;
        }
        if ($start !== null) {
            $fragment .= ' OFFSET %d';
            $params[] = $start;
        }
        $sql = "SELECT * FROM $this->dbTable"
            . ' WHERE linkId=%d AND DATE(clickedAt)=%s'
            . ' ORDER BY clickedAt DESC'
            . $fragment;
        $this->list = $this->dbc->get_results($this->dbc->prepare($sql, $params));
        return $this;
    }

    public function selectDaily(int $linkId, $fromDate = null, $toDate = null): array
    {
        return $this->dbc->get_results($this->dbc->prepare(
            "SELECT DATE(clickedAt) as click_date, COUNT(*) as clicks FROM $this->dbTable"
            . " WHERE linkId=%d GROUP BY click_date ORDER BY click_date DESC",
            $linkId
        ));
    }

    private function whereFormats(array $where): array
    {
        $formats = [];
        foreach (array_keys($where) as $key) {
            if (isset(self::$fields[$key])) {
                $formats[] = self::$fields[$key];
            }
        }
        return $formats;
    }

}
