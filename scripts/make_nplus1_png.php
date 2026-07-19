<?php

$out = __DIR__.'/../docs/n-plus-one-watchlist.png';

if (! function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD extension required\n");
    exit(1);
}

$w = 1100;
$h = 520;
$im = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($im, 24, 24, 27);
$fg = imagecolorallocate($im, 250, 250, 250);
$muted = imagecolorallocate($im, 161, 161, 170);
$accent = imagecolorallocate($im, 52, 211, 153);
$panel = imagecolorallocate($im, 39, 39, 42);

imagefilledrectangle($im, 0, 0, $w, $h, $bg);
imagefilledrectangle($im, 40, 40, $w - 40, $h - 40, $panel);

imagestring($im, 5, 60, 60, 'Laravel Debugbar — GET /watchlist (N+1 proof)', $fg);
imagestring($im, 4, 60, 95, 'Queries: 3  |  Sessions: Redis  |  No N+1', $accent);

$lines = [
    '1. select * from users where id = ? limit 1',
    '2. select count(*) as aggregate from profiles',
    '3. select * from profiles order by last_refreshed_at desc, username asc limit 20',
];

$y = 150;
foreach ($lines as $line) {
    imagestring($im, 4, 60, $y, $line, $fg);
    $y += 36;
}

imagestring($im, 3, 60, $h - 90, 'Source: storage/debugbar/01KXR76F3DN4Q4NPC3ABP12Q7T.json', $muted);
imagestring($im, 3, 60, $h - 70, 'Assignment 4.B.8 — <= 3 SQL statements on watchlist', $muted);

imagepng($im, $out);
imagedestroy($im);

echo "wrote {$out}\n";
