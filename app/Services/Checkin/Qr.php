<?php

namespace App\Services\Checkin;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Generator;

/** QR pictures: SVG for pages, PNG (drawn with GD) for e-mails and the app. */
class Qr
{
    public static function svg(string $text, int $size = 260): string
    {
        return (string) (new Generator())->format('svg')->size($size)->margin(1)->errorCorrection('M')->generate($text);
    }

    public static function png(string $text, int $scale = 8, int $margin = 2): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n = $matrix->getWidth();
        $px = ($n + $margin * 2) * $scale;
        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $px, $px, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle($img, ($x + $margin) * $scale, ($y + $margin) * $scale, ($x + $margin + 1) * $scale - 1, ($y + $margin + 1) * $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
