<?php
declare(strict_types=1);

include '../abivia-shortlinks/vendor/autoload.php';

use Abivia\Wp\LinkShortener\Destinations;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../wp-stub/wp-stub.php';

class GeoFilterTest extends TestCase
{
    protected Destinations $testObj;

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
        $this->testObj = new Destinations(new wpdb());
    }

    public function testFilterDestinationsExact1()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            'anywhere',
            '[ca]Canada',
            '[fr]France',
            '[ca,on]Ontario',
            '[ca,bc]BritishColumbia',
            '[ca,on,toronto]Toronto',
            '[,,toronto]AnyToronto',
            '[fr,,toronto]NotToronto',
        ];
        $result = $this->testObj
            ->parse(implode("\n", $links))
            ->geoFilter($location);
        $this->assertEquals(
            ['anywhere', 'Canada', 'Ontario', 'Toronto', 'AnyToronto'],
            $this->extractUrl($result)
        );
    }

    public function testFilterDestinationsExact2()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            '[ca,on,toronto]Toronto',
            '[ca,on,ajax|toronto]TorontoOrAjax',
            '[ca,on,guelph]Guelph',
        ];
        $result = $this->testObj
            ->parse(implode("\n", $links))
            ->geoFilter($location);
        $this->assertEquals(
            ['Toronto', 'TorontoOrAjax'], $this->extractUrl($result)
        );
    }

    public function testFilterDestinationsFuzzy1()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            'anywhere',
            '[ca]Canada',
            '[fr]France',
            '[ca,on]Ontario',
            '[ca,?bc]BritishColumbia',
            '[ca,on,toronto]Toronto',
            '[ca,on,?bolton]Bolton',
            '[?fr,?,toronto]NotToronto',
        ];
        $result = $this->testObj
            ->parse(implode("\n", $links))
            ->geoFilter($location);
        $this->assertEquals(
            ['anywhere', 'Canada', 'Ontario', 'Toronto'], $this->extractUrl($result)
        );
    }

    public function testFilterDestinationsFuzzy2()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            '[ca,on,?ajax]Ajax',
            '[fr]France',
            '[ca,on,?bolton]Bolton',
        ];
        $this->testObj->parse(implode("\n", $links));
        $result = $this->testObj->geoFilter($location);
        $this->assertEquals(
            ['Ajax', 'Bolton'], $this->extractUrl($result)
        );
    }

    public function testFilterDestinationsFuzzy3()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            '[ca,on,?ajax|?bolton]AjaxOrBolton',
            '[fr]France',
            '[ca,on,?bolton]Bolton',
        ];
        $result = $this->testObj
            ->parse(implode("\n", $links))
            ->geoFilter($location);
        $this->assertEquals(
            ['AjaxOrBolton', 'Bolton'], $this->extractUrl($result)
        );
    }

    public function testFilterDestinationsFuzzy4()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            '[ca]Canada',
            '[fr]France',
            '[ca,on]Ontario',
            '[ca,?bc]BritishColumbia',
            '[ca,on,toronto]Toronto',
            '[ca,on,?bolton]Bolton',
            '[?fr,?,toronto]NotToronto',
            '[?x]anywhere',
        ];
        $result = $this->testObj
            ->parse(implode("\n", $links))
            ->geoFilter($location);
        $this->assertEquals(
            ['Canada', 'Ontario', 'Toronto'], $this->extractUrl($result)
        );
    }

    public function testFilterDestinationsFuzzy5()
    {
        $location = [
            'countryCode' => 'FR',
            'regionCode' => 'PA',
            'city' => 'Paris',
        ];
        $links = [
            '[fr,pa,paris]Paris',
            '[?,,paris]Wanker',
        ];
        $dest = $this->testObj
            ->parse(implode("\n", $links));
        $result = $dest->geoFilter($location);
        $this->assertEquals(
            ['Paris'], $this->extractUrl($result), "Test 1"
        );
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Paris',
        ];
        $result = $dest->geoFilter($location);
        $this->assertEquals(
            ['Wanker'], $this->extractUrl($result), "Test 2"
        );
    }

    public function testFilterDestinationsUnique()
    {
        $location = [
            'countryCode' => 'CA',
            'regionCode' => 'ON',
            'city' => 'Toronto',
        ];
        $links = [
            '[ca,on,?ajax|?bolton]Toronto',
            '[fr]France',
            '[ca,on,?bolton]Toronto',
        ];
        $result = $this->testObj
            ->parse(implode("\n", $links))
            ->geoFilter($location);
        $this->assertEquals(
            ['Toronto'], $this->extractUrl($result)
        );
    }

    public function testExample()
    {
        $links = [
            "[CA,ON]https://example.ca/ontario",
            "[CA,AB]https://example.ca/alberta",
            "[CA,?]https://example.ca/",
            "[?]https://example.com",
        ];
        $this->testObj->parse(implode("\n", $links));
        $this->assertEquals(
            ['https://example.ca/ontario'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'ON', 'city' => 'Toronto']
            )),
            "Toronto"
        );
        $this->assertEquals(
            ['https://example.ca/ontario'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'ON', 'city' => 'Ottawa']
            )),
            "Ottawa"
        );
        $this->assertEquals(
            ['https://example.ca/ontario'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'ON', 'city' => 'Waterloo']
            )),
            "Waterloo"
        );
        $this->assertEquals(
            ['https://example.ca/alberta'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'AB', 'city' => 'Edmonton']
            )),
            "Toronto"
        );
        $this->assertEquals(
            ['https://example.ca/'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'SK', 'city' => 'Regina']
            )),
            "Regina"
        );
        $this->assertEquals(
            ['https://example.com'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'US', 'regionCode' => 'TX', 'city' => 'Austin']
            )),
            "Austin"
        );

    }

    public function testExample2()
    {
        $links = [
            "[CA,ON,Toronto]https://example.ca/toronto",
            "[CA,ON,Ottawa]https://example.ca/ottawa",
            "[CA,ON,?]https://example.ca/ontario",
            "[CA,AB]https://example.ca/alberta",
            "[CA,?]https://example.ca/",
            "[?]https://example.com",
        ];
        $this->testObj->parse(implode("\n", $links));
        $this->assertEquals(
            ['https://example.ca/toronto'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'ON', 'city' => 'Toronto']
            )),
            "Toronto"
        );
        $this->assertEquals(
            ['https://example.ca/ottawa'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'ON', 'city' => 'Ottawa']
            )),
            "Ottawa"
        );
        $this->assertEquals(
            ['https://example.ca/ontario'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'ON', 'city' => 'Waterloo']
            )),
            "Waterloo"
        );
        $this->assertEquals(
            ['https://example.ca/alberta'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'AB', 'city' => 'Edmonton']
            )),
            "Toronto"
        );
        $this->assertEquals(
            ['https://example.ca/'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'CA', 'regionCode' => 'SK', 'city' => 'Regina']
            )),
            "Regina"
        );
        $this->assertEquals(
            ['https://example.com'],
            $this->extractUrl($this->testObj->geoFilter(
                ['countryCode' => 'US', 'regionCode' => 'TX', 'city' => 'Austin']
            )),
            "Austin"
        );

    }

}
