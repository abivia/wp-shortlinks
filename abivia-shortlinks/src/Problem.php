<?php
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

use Exception;

class Problem extends Exception
{
    private ?string $extra;

    public function __construct(string $message, ?string $extra = null)
    {
        parent::__construct($message);
        $this->extra = $extra;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }
}
