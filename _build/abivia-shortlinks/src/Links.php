<?php

namespace Abivia\Wp\LinkShortener;

use wpdb;

class Links extends DbTable
{
    protected static array $fields = [
        'linkId' => '%d',
        'alias' => '%s',
        'defaultText' => '%s',
        'password' => '%s',
        'isRotating' => '%d',
        'httpCode' => '%d',
        'geoCoded' => '%d',
    ];

    public function __construct(wpdb $dbc)
    {
        parent::__construct($dbc);
        $this->dbTable = "{$this->dbc->prefix}abisl_links";
    }

    public function createTable(): void
    {
        $charsetCollate = $this->dbc->get_charset_collate();
        $sqlLinks = "CREATE TABLE {$this->tableName()} ("
            . "linkId bigint(20) NOT NULL AUTO_INCREMENT"
            . ",alias varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL"
            . ",defaultText varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''"
            . ",password varchar(255)  DEFAULT NULL"
            . ",isRotating tinyint(1) DEFAULT 0"
            . ",geoCoded tinyint(1) DEFAULT 0"
            . ",httpCode int(4) DEFAULT 307"
            . ",PRIMARY KEY (linkId)"
            . ",UNIQUE KEY alias (alias)"
            . ") $charsetCollate;";
        dbDelta($sqlLinks);
    }

    public function delete(array $where): bool|int
    {
        return $this->dbc->delete($this->dbTable, $where, $this->whereFormats($where));
    }

    public function getOne(string $where, array $args): ?Link
    {
        return Link::fromDb($this->dbc->get_row(
            $this->dbc->prepare("SELECT * FROM $this->dbTable WHERE $where", $args)
        ));
    }

    public function insert(array $data): bool|int
    {
        // Insert link
        $format = [];
        $fields = array_filter(
            $data,
            function ($key) use (&$format) {
                if (isset(self::$fields[$key])) {
                    $format[] = self::$fields[$key];
                    return true;
                }
                return false;
            },
            ARRAY_FILTER_USE_KEY
        );

        return $this->dbc->insert($this->dbTable, $fields, $format);
    }

    public function update(array $data, array $where): bool|int
    {
        // Extract only fields that go into the database
        $format = [];
        $dbFields = array_filter(
            $data,
            function ($key) use (&$format) {
                if ($key !== $this->primaryKey && isset(self::$fields[$key])) {
                    $format[] = self::$fields[$key];
                    return true;
                }
                return false;
            },
            ARRAY_FILTER_USE_KEY
        );
        return $this->dbc->update(
            $this->dbTable, $dbFields, $where, $format, $this->whereFormats($where)
        );
    }

    private function whereFormats(array $where)
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
