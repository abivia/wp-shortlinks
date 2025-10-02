<?php

require_once __DIR__ . '/../abivia-shortlinks/vendor/autoload.php';
use Abivia\Penknife\Penknife;

$map = [
    'alias' => 'link-alias',
    'aliasLink' => 'slug/alias',
    'aliasUrl' => '#alias',
    'deleteLink' => '#delete',
    'destinations' => "link1\nlink2",
    'nonce' => 'your nonce here',
    'returnLink' => '#analytics',
    'rotate' => 'checked',
    'password' => 'password',
    'submit' => 'submit',
];

$pk = new Penknife();
$html = $pk->format(
    file_get_contents(__DIR__ . '/../abivia-shortlinks/Lib/editPage.html'),
    function ($attr) use ($map) {
        return $map[$attr] ?? '';
    }
);
file_put_contents(__DIR__ . '/edit-form-output.html', $html);

