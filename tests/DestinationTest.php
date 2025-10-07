<?php
declare(strict_types=1);

include '../abivia-shortlinks/vendor/autoload.php';

use Abivia\Wp\LinkShortener\Destination;
use Abivia\Wp\LinkShortener\GeoSelector;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../wp-stub/wp-stub.php';

class DestinationTest extends TestCase
{
    protected Destination $testObj;

    private function extractUrl(array $list): array
    {
        $result = $list;
        array_walk($result, function (&$element) {
            $element = $element->url;
        });
        return $result;
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->testObj = new Destination();
    }

    public function testParseUnique()
    {
        $this->testObj->parse('[CA|ca|Ca]https://canada.ca');
        $this->assertEquals('CA', implode('|',  $this->testObj->countryCode));
    }

    public function testToString()
    {
        $this->testObj->countryCode = [new GeoSelector('XX')];
        $this->testObj->url = 'https://example.com';
        $result = (string) $this->testObj;
        $this->assertEquals('[XX]https://example.com', $result);

        $this->testObj->city = [new GeoSelector('Testerville')];
        $result = (string) $this->testObj;
        $this->assertEquals('[XX,,Testerville]https://example.com', $result);
        $this->testObj->city[] = new GeoSelector('TesterVille');
        $result = (string) $this->testObj;
        $this->assertEquals('[XX,,Testerville]https://example.com', $result);
        $this->testObj->city[] = new GeoSelector('Bugtown');
        $result = (string) $this->testObj;
        $this->assertEquals('[XX,,Testerville|Bugtown]https://example.com', $result);

        $this->testObj->regionCode = [
            new GeoSelector('NY'), new GeoSelector('WA'), new GeoSelector('ny')
        ];
        $result = (string) $this->testObj;
        $this->assertEquals('[XX,NY|WA,Testerville|Bugtown]https://example.com', $result);

    }

}
