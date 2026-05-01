<?php
/**
 * One-off helper: auto-crop the empty black space above/below the
 * Autowagen letterhead image.
 *
 * Reads:   assets/invoice-letterhead.png
 * Writes:  assets/invoice-letterhead-preview.png   (preview, original kept)
 *
 * Run from the Laragon command line:
 *   C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tools/trim_letterhead.php
 *
 * Logic:
 *   1. Scan top-down → find first row containing any "non-black" pixel.
 *   2. Scan bottom-up → find last row containing any "non-black" pixel.
 *   3. Add a small visual padding (top/bottom) so the content has breathing room.
 *   4. Crop the image vertically (keep full width).
 *
 * "Black" means any RGB channel is below the BLACK_THRESHOLD constant. The
 * letterhead has a near-pure-black background, so even a low threshold
 * reliably finds the first text/logo pixel.
 */

const SRC          = __DIR__ . '/../assets/invoice-letterhead.png';
const DST          = __DIR__ . '/../assets/invoice-letterhead-preview.png';

// A pixel is "content" if any RGB channel is brighter than this (0-255).
// 30 leaves a tiny tolerance for JPEG-style noise but ignores pure black.
const BLACK_THRESH = 30;

// Pixels of solid-black padding to leave above the logo / below the address.
const PAD_TOP    = 20;
const PAD_BOTTOM = 30;

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

/**
 * Returns true if the given row contains at least one non-black pixel.
 * Samples every 4th column for speed (banner widths are 1000+ px).
 */
$rowHasContent = static function (\GdImage $img, int $w, int $y): bool {
    for ($x = 0; $x < $w; $x += 4) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8)  & 0xFF;
        $b =  $rgb        & 0xFF;
        if ($r > BLACK_THRESH || $g > BLACK_THRESH || $b > BLACK_THRESH) {
            return true;
        }
    }
    return false;
};

$top = 0;
for ($y = 0; $y < $h; $y++) {
    if ($rowHasContent($img, $w, $y)) { $top = $y; break; }
}
$bot = $h - 1;
for ($y = $h - 1; $y >= 0; $y--) {
    if ($rowHasContent($img, $w, $y)) { $bot = $y; break; }
}

echo "Content rows: {$top} … {$bot}\n";

$cropY1 = max(0, $top - PAD_TOP);
$cropY2 = min($h - 1, $bot + PAD_BOTTOM);
$newH   = $cropY2 - $cropY1 + 1;

echo "Cropping rows {$cropY1} … {$cropY2}  (new height = {$newH}px)\n";

$out = imagecreatetruecolor($w, $newH);
imagecopy($out, $img, 0, 0, 0, $cropY1, $w, $newH);

imagepng($out, DST, 6);
imagedestroy($out);
imagedestroy($img);

echo "Wrote preview: " . DST . " (" . filesize(DST) . " bytes)\n";

$orig = filesize(SRC);
$diff = $h - $newH;
$pct  = round(($diff / $h) * 100, 1);
echo "Removed {$diff}px of empty black ({$pct}% shorter).\n";
