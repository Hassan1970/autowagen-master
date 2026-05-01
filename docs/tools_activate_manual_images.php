<?php
/**
 * One-off: activate <img> in complete_system_manual.html and add onerror placeholders.
 * Run: php docs/tools_activate_manual_images.php
 */
$path = __DIR__ . '/complete_system_manual.html';
$html = file_get_contents($path);
if ($html === false) {
    fwrite(STDERR, "Cannot read manual\n");
    exit(1);
}
$pat = '/    <!-- <img src="(manual_screenshots\/[^"]+)" alt="([^"]*)"> -->\r?\n/';
$rep = <<<'HTML'
    <div class="shot-frame">
      <img src="$1" alt="$2" class="shot-img" onerror="this.classList.add('shot-img-off'); this.nextElementSibling.classList.add('shot-missing--show');">
      <div class="shot-missing"><strong>Screenshot not added yet.</strong><br>Save your capture as the PNG name in the grey line above, in folder <code>docs/manual_screenshots/</code>. Open this manual with <strong>http://localhost/autowagen-master/docs/complete_system_manual.html</strong>, reload, then <kbd>Ctrl</kbd>+<kbd>P</kbd> → <strong>Save as PDF</strong> again — the photo will appear here.</div>
    </div>

HTML;
$html = preg_replace($pat, $rep, $html);
if ($html === null) {
    fwrite(STDERR, "preg_replace failed\n");
    exit(1);
}
file_put_contents($path, $html);
echo "Updated complete_system_manual.html\n";
