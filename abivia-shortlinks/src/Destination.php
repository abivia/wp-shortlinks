<?php

namespace Abivia\Wp\LinkShortener;

/**
 *
 * [countryCode,regionCode,city]URL where country code and city are optional.
 *  Examples: https://example.com [CA]https://example.ca [CA,ON]https://example.ca/ontario
 *  [CA,,Ottawa]https://example.ca/ott
 */
class Destination
{
    const string FUZZY_FLAG = '?';
    public array $city;
    public array $countryCode;
    public bool $geoCoded;
    public array $regionCode;
    public ?string $text = null;
    public string $url;

    public function __toString(): string
    {
        $result = '['
            . implode('|', $this->countryCode)
            . ',' . implode('|', $this->regionCode)
            . ',' . implode('|', $this->city)
            . ']';
        if ($result === '[,,]') {
            $result = '';
        }
        if ($this->text !== null && $this->text !== '') {
            $result .= "$this->text|";
        }
        return "$result$this->url";
    }

    public function parse(string $destination): self
    {
        // Extract any geo-targeting
        if (str_starts_with($destination, '[')) {
            [$geoFilter, $this->url] = explode(']', $destination, 2);
            $geoParts = explode(',', substr($geoFilter, 1));
            $this->countryCode = $this->parseGeo(strtoupper($geoParts[0] ?? ''));
            $this->regionCode = $this->parseGeo(strtoupper($geoParts[1] ?? ''));
            $this->city = $this->parseGeo(strtolower($geoParts[2] ?? ''));
            $this->geoCoded = true;
        } else {
            $this->url = $destination;
            $this->countryCode = [];
            $this->regionCode = [];
            $this->city = [];
            $this->geoCoded = false;
        }
        // See if there's a title
        if (str_contains($this->url, '|')) {
            [$this->text, $this->url] = explode('|', $this->url, 2);
        }
        $this->url = esc_url_raw($this->url);
        return $this;
    }

    /**
     * @param string $arg
     * @return array
     */
    protected function parseGeo(string $arg): array
    {
        $result = [];
        $options = explode('|', $arg);
        foreach ($options as $option) {
            $element = new GeoSelector($option !== '' ? $option : null);
            if (str_starts_with($option, self::FUZZY_FLAG)) {
                $option = trim(substr($option, 1));
                $element->value = $option;// !== '' ? $option : null;
                $element->fuzzy = true;
            }
            $result[] = $element;
        }
        return $result;
    }

}
