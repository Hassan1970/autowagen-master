<?php
/**
 * One-off helper: extract just the AUTOWAGEN logo + slogan area from
 * the current cropped banner, dropping the cell pill and address.
 *
 * Reads:   assets/invoice-letterhead.png         (the cropped banner)
 * Writes:  assets/invoice-logo.png               (logo + slogan only)
 *
 * Run:
 *   C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tools/extract_logo.php
 *
 * How it finds the cut line:
 *   The banner has a horizontal "white pill" bar containing
 *   "Cell / WhatsApp : 079 018 8097". That row is by far the
 *   brightest in the image, because most of the pixels across
 *   the row are white-ish (200+).  We scan top -> bottom and
 *   stop at the first row where the bright-pixel count is large.
 *   Then we cut a few px above that line.
 */

const SRC = __DIR__ . '/../assets/invoice-letterhead.png';
const DST = __DIR__ . '/../assets/invoice-logo.png';

// "Bright" pixel definition: ALL three channels above this (true white-ish).
const BRIGHT = 200;

// A row is considered "white-row" if at least this fraction of its
// columns are bright. The slogan's thin divider lines DO cross this
// threshold for one or two pixel rows, but they're isolated rows; the
// white "Cell / WhatsApp" pill is a CONTIGUOUS block of many such rows.
const ROW_BRIGHT_FRACTION = 0.20;

// Pixels of clearance to keep ABOVE the pill bar (so the slogan and
// its surrounding horizontal divider lines don't get clipped).
const PAD_ABOVE_PILL = 5;

if (!is_file(SRC)) {
    fwrite(STDERR, "Source not found: " . SRC . "\n");
    exit(1);
}
if (!extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension not enabled.\n");
    exit(1);
}

$img = imagecreatefrompng(SRC);
if (!$img) {
    fwrite(STDERR, "Could not load PNG.\n");
    exit(1);
}
$w = imagesx($img);
$h = imagesy($img);
echo "Source: {$w} x {$h}px\n";

// 1. Mark each row as "bright" or not.
$threshold = (int) floor($w * ROW_BRIGHT_FRACTION);
$isBright  = [];
for ($y = 0; $y < $h; $y++) {
    $count = 0;
    for ($x = 0; $x < $w; $x += 2) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8)  & 0xFF;
        $b =  $rgb        & 0xFF;
        if ($r > BRIGHT && $g > BRIGHT && $b > BRIGHT) {
            $count += 2;
        }
    }
    $isBright[$y] = ($count >= $threshold);
}

// 2. Find the LARGEST contiguous block of consecutive bright rows.
//    The white pill is the only block that's many rows tall, so it wins
//    over the slogan's 1-2px divider lines.
$bestStart = -1; $bestLen = 0;
$curStart  = -1; $curLen  = 0;
for ($y = 0; $y < $h; $y++) {
    if ($isBright[$y]) {
        if ($curStart < 0) $curStart = $y;
        $curLen++;
        if ($curLen > $bestLen) {
            $bestLen   = $curLen;
            $bestStart = $curStart;
        }
    } else {
        $curStart = -1; $curLen = 0;
    }
}
if ($bestStart < 0 || $bestLen < 5) {
    fwrite(STDERR, "Could not find the white pill bar. Aborting.\n");
    exit(1);
}
echo "Pill bar: rows {$bestStart} .. " . ($bestStart + $bestLen - 1)
     . "  ({$bestLen} rows tall)\n";

$cutY = max(0, $bestStart - PAD_ABOVE_PILL);
$newH = $cutY;

if ($newH < 60) {
    fwrite(STDERR, "Detected logo height too small ({$newH}px). Aborting.\n");
    exit(1);
}

echo "Cropping rows 0 .. {$cutY}  (logo height = {$newH}px)\n";

$out = imagecreatetruecolor($w, $newH);
imagecopy($out, $img, 0, 0, 0, 0, $w, $newH);

imagepng($out, DST, 6);
imagedestroy($out);
imagedestroy($img);

echo "Wrote logo: " . DST . " (" . filesize(DST) . " bytes)\n";
