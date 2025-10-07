<?php
declare(strict_types=1);

namespace Abivia\Wp\LinkShortener;

class Link
{
    public string $alias = '';
    public string $defaultText = '';
    public Destinations $destinations;
    public int $geoCoded = 0;
    public int $httpCode = 307;
    public int $linkId = 0;
    public int $isRotating = 0;
    public ?string $password = null;

    public static function fromDb(?object $source): ?self
    {
        if ($source === null) {
            return null;
        }
        $link = new self();
        $link->linkId = (int) $source->linkId;
        $link->alias = $source->alias;
        $link->defaultText = $source->defaultText;
        $link->password = $source->password;
        $link->isRotating = (int) $source->isRotating;
        $link->geoCoded = (int) $source->geoCoded;
        $link->httpCode = (int) $source->httpCode;

        return $link;
    }

}
