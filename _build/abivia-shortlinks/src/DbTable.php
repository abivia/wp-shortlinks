<?php
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

use ArrayIterator;
use wpdb;

class DbTable extends ArrayIterator
{

    protected string $dbTable;
    protected wpdb $dbc;
    protected array $list;
    protected string $primaryKey = 'id';

    public function __construct(wpdb $dbc)
    {
        parent::__construct();
        $this->dbc = $dbc;
    }

    public function count(): int
    {
        return count($this->list);
    }

    public function offsetExists(mixed $key): bool
    {
        return isset($this->list[$key]);
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->list[$key];
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->list[$key] = $value;
    }

    public function offsetUnset(mixed $key): void
    {
        unset($this->list[$key]);
    }

    public function tableName(): string
    {
        return $this->dbTable; //substr($this->dbTable, 1, -1);
    }

}
