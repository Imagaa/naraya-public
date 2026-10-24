<?php

use App\Support\AttendanceWatermark;
use Illuminate\Http\UploadedFile;

it('stamps a band on the photo, scales it to max 1280px and returns a JPEG', function () {
    // white canvas, so the dark band below is the watermark's doing (a fake upload is black)
    $canvas = imagecreatetruecolor(2000, 1500);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    ob_start();
    imagejpeg($canvas);
    $original = ob_get_clean();

    $out = AttendanceWatermark::apply($original, ['MASUK · Sen, 5 Okt 2026 · 07:58:12 WIB', 'Tepat waktu · Kantor (32 m)', '-6.175394, 106.827153 · ±12 m · Budi']);

    $info = getimagesizefromstring($out);
    expect($info['mime'])->toBe('image/jpeg')
        ->and($info[0])->toBe(1280)
        ->and($info[1])->toBe(960)
        ->and($out)->not->toBe($original);

    // the bottom band is darkened: the fake image is white-ish/flat, the band pixel is dark
    $img = imagecreatefromstring($out);
    $rgb = imagecolorsforindex($img, imagecolorat($img, 1270, 955));
    expect($rgb['red'] + $rgb['green'] + $rgb['blue'])->toBeLessThan(450);
});

it('rejects bytes that are not an image', function () {
    AttendanceWatermark::apply('not an image', ['x']);
})->throws(InvalidArgumentException::class);
