<?php

require_once __DIR__ . '/../abivia-shortlinks/vendor/autoload.php';
use Abivia\Penknife\Penknife;

$map = [
    'alias' => 'link-alias',
    'analyticsUrl' => '#alias',
    'clickRows' => [
        ['clicked_at' => '2020-01-01 23:00:09', 'ipAddress' => '12.34.56.78', 'userAgent' => 'foobot'],
    ],
    'currentPage' => 2375,
    'dailyStats' => [
        ['click_date' => '1969-12-31', 'clicks' => 10, 'detail' => '#detail'],
        ['click_date' => '2001-12-31', 'clicks' => 15, 'detail' => '#detail'],
    ],
    'firstUrl' => '#next',
    'lastUrl' => '#next',
    'nextUrl' => '#next',
    'paginated' => true,
    'prevUrl' => '#prev',
    'totalPages' => 7,
    'viewDate' => '2020-02-02',
];

$pk = new Penknife();
$html = $pk->format(
    file_get_contents(__DIR__ . '/../abivia-shortlinks/Lib/analyticsPage.html'),
    function ($attr) use ($map) {
        return $map[$attr] ?? null;
    }
);
file_put_contents(__DIR__ . '/analytics.html', $html);

