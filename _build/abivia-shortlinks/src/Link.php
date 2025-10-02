<?php

namespace Abivia\Wp\LinkShortener;

class Link
{
    public string $alias = '';
    public string $defaultText;
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
        $link->linkId = $source->linkId;
        $link->alias = $source->alias;
        $link->defaultText = $source->defaultText;
        $link->password = $source->password;
        $link->isRotating = $source->isRotating;
        $link->geoCoded = $source->geoCoded;
        $link->httpCode = $source->httpCode;

        return $link;
    }

}
