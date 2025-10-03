<?php
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

class GeoSelector
{
    function __construct(public ?string $value = null, public bool $fuzzy = false)
    {}

    public function __toString(): string
    {
        if ($this->value === null) {
            return '';
        }
        return ($this->fuzzy ? '?' : '') . $this->value;
    }
}
