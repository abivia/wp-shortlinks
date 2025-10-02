<?php
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

use LogicException;
use wpdb;

class Destinations extends DbTable
{
    protected static array $fields = [
        'id' => '%d',
        'linkId' => '%d',
        'destinationUrl' => '%s',
    ];
    public bool $geoCoded;

    public function __construct(wpdb $dbc)
    {
        parent::__construct($dbc);
        $this->dbTable = "{$this->dbc->prefix}abisl_destinations";
    }

    public function __toString(): string
    {
        $lines = [];
        foreach ($this->list as $item) {
            $lines[] = (string)$item;
        }
        return implode("\n", $lines);
    }

    public function createTable()
    {
        $charsetCollate = $this->dbc->get_charset_collate();
        $sqlDestinations = "CREATE TABLE $this->dbTable ("
            . 'id bigint(20) NOT NULL AUTO_INCREMENT'
            . ',linkId bigint(20) NOT NULL'
            . ',destinationUrl text NOT NULL'
            . ',PRIMARY KEY (id)'
            . ',KEY linkId (linkId)'
            . ") $charsetCollate;";

        dbDelta($sqlDestinations);
    }

    public function delete(array $where)
    {
        $this->dbc->delete($this->dbTable, $where, $this->whereFormats($where));
    }

    public function geoFilter(array $location, ?self $destinations = null): array
    {
        $location['countryCode'] = strtoupper($location['countryCode']);
        $location['regionCode'] = strtoupper($location['regionCode']);
        $location['city'] = strtolower($location['city']);
        $scopes = ['countryCode', 'regionCode', 'city'];
        $exactResult = [];
        $fuzzyResult = [];
        // Look for an exact match first
        if ($destinations === null) {
            $destinations = $this;
        }
        $hardMatch = ['countryCode' => false, 'regionCode' => false, 'city' => false];
        foreach ($destinations->list as $destination) {
            if (is_string($destination)) {
                $target = new Destination()->parse($destination);
            } else {
                $target = $destination;
            }
            if ($target->geoCoded) {
                $exactHits = 0;
                $fuzzyHits = 0;
                foreach ($scopes as $scope) {
                    foreach ($target->{$scope} as $selector) {
                        if (
                            $selector->value === null
                            || $selector->value === $location[$scope]
                        ) {
                            ++$exactHits;
                            $hardMatch[$scope] = $hardMatch[$scope] || $selector->value !== null;
                            break;
                        } elseif ($selector->fuzzy && !$hardMatch[$scope]) {
                            ++$fuzzyHits;
                            break;
                        }
                    }
                }
                if ($exactHits === 3) {
                    $exactResult[($target->text ?? '') . '|' . $target->url] = $target;
                }
                if ($exactHits != 0 && $exactHits + $fuzzyHits === 3) {
                    $fuzzyResult[($target->text ?? '') . '|' . $target->url] = $target;
                }
            } else {
                $exactResult[($target->text ?? '') . '|' . $target->url] = $target;
            }
        }
        if (count($exactResult)) {
            return array_values($exactResult);
        }
        return array_values($fuzzyResult);
    }

    /**
     * Load and parse destinations for a short link.
     * @param int $linkId
     * @return $this
     * @throws Problem
     */
    public function get(int $linkId): self
    {
        $this->list = [];
        if ($linkId === 0) {
            return $this;
        }
        $rows = $this->dbc->get_results(
            $this->dbc->prepare("SELECT * FROM $this->dbTable WHERE linkId=%d", $linkId)
        );
        if (count($rows) === 0) {
            throw new Problem("No destinations for $linkId");
        }
        foreach ($rows as $row) {
            $this->list[] = new Destination()->parse($row->destinationUrl);
        }

        return $this;
    }

    public function getList(): array
    {
        return $this->list;
    }

    public function offsetGet(mixed $offset): Destination
    {
        return $this->list[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof Destination) {
            throw new LogicException('Can only store Destination objects here.');
        }
        $this->list[$offset] = $value;
    }

    public function parse(string $text): self
    {
        $lines = explode("\n", trim($text));
        $this->list = [];
        $this->geoCoded = false;
        foreach ($lines as $item) {
            if (trim($item) === '') {
                continue;
            }
            $destination = new Destination()->parse($item);
            if ($destination->url !== '') {
                if ($destination->geoCoded) {
                    $this->geoCoded = true;
                }
                $this->list[] = $destination;
            }
        }
        return $this;
    }

    public function pickRandom()
    {
        return $this->list[array_rand($this->list)];
    }

    /**
     * @param int $linkId
     * @return $this
     * @throws Problem
     */
    public function replace(int $linkId): self
    {
        $remove = [];
        $destinationMap = [];
        if ($linkId !== 0) {
            // Get any existing destinations
            $currentDestinations = $this->dbc->get_results(
                $this->dbc->prepare("SELECT * FROM $this->dbTable WHERE linkId = %d", $linkId)
            );
            foreach ($currentDestinations as $destination) {
                $key = $destination->destinationUrl;
                if (isset($destinationMap[$key])) {
                    // Clean up a pre-existing duplicate.
                    $remove[] = $destination->id;
                } else {
                    $destinationMap[$key] = $destination;
                }
            }
        }
        // Synchronize destinations, remove duplicates
        $exists = [];
        foreach ($this->list as $destination) {
            // Do nothing if the same destination already exists
            $newUrl = (string)$destination;
            if (isset($destinationMap[$newUrl])) {
                unset($destinationMap[$newUrl]);
                $exists[$newUrl] = true;
            } elseif (!isset($exists[$newUrl])) {
                if (
                    $this->dbc->insert(
                        $this->dbTable,
                        ['linkId' => $linkId, 'destinationUrl' => $newUrl],
                        ['%d', '%s']
                    ) === false
                ) {
                    throw new Problem(
                        'Failed to save destination URL: ' . $this->dbc->last_error
                    );
                }
                $exists[$newUrl] = true;
            }
        }
        // Remove any old destination not in the new list
        if (count($destinationMap)) {
            foreach ($destinationMap as $item) {
                $remove[] = $item->id;

            }
        }
        if (count($remove)) {
            /** @noinspection PhpExpressionResultUnusedInspection */
            $this->dbc->query(
                "DELETE FROM $this->dbTable WHERE `id` IN (" . implode(',', $remove) . ')'
            );
        }
        return $this;
    }

    public function select(int $linkId): self
    {
        $destinations = $this->dbc->get_col(
            $this->dbc->prepare(
                "SELECT destinationUrl FROM $this->dbTable WHERE linkId=%d",
                $linkId
            )
        );
        $this->list = [];
        foreach ($destinations as $destination) {
            $this->list[] = new Destination()->parse($destination);
        }
        return $this;
    }

    /**
     * Parse and validate a text block of destinations.
     * @param string $text
     * @return array [geoCoded (true if there's a geo based link), list of valid destinations]
     */
    public static function validate(string $text): array
    {
        $list = explode("\n", trim($text));
        $destinations = [];
        $geoCoded = false;
        foreach ($list as $item) {
            if (trim($item) === '') {
                continue;
            }
            $destination = new Destination()->parse($item);
            if ($destination->url !== '') {
                $geoCoded |= $destination->geoCoded;
                $destinations[] = $destination;
            }
        }
        return [$geoCoded, $destinations];
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
