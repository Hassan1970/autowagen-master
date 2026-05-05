<?php
/**
 * One-shot: embed docs/BUILD_WHOLE_PROJECT_AI_PROMPT.md + CLAUDE.md into a single print HTML.
 * Run from project root: php tools/generate_build_claude_combined_print.php
 * Output: docs/BUILD_WHOLE_PROJECT_AND_CLAUDE_print.html
 *
 * If `php` is not on PATH (common in Windows PowerShell), use instead:
 *   powershell -ExecutionPolicy Bypass -File tools/generate_build_claude_combined_print.ps1
 * Or open Laragon's terminal (php is usually on PATH there).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$outPath = $root . '/docs/BUILD_WHOLE_PROJECT_AND_CLAUDE_print.html';
$buildPath = $root . '/docs/BUILD_WHOLE_PROJECT_AI_PROMPT.md';
$claudePath = $root . '/CLAUDE.md';

foreach ([$buildPath, $claudePath] as $p) {
    if (!is_readable($p)) {
        fwrite(STDERR, "Missing or unreadable: {$p}\n");
        exit(1);
    }
}

$build = file_get_contents($buildPath);
$claude = file_get_contents($claudePath);
$genAt = (new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i T');

$htmlBuild = htmlspecialchars($build, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$htmlClaude = htmlspecialchars($claude, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Build spec + CLAUDE.md — Autowagen Master (Print → PDF)</title>
<style>
  :root { --r:#c8102e; --k:#0a0a0a; }
  @page { size: A4 portrait; margin: 12mm; }
  body { margin:0 auto; max-width: 190mm; padding:8mm 10mm; font-family:system-ui,sans-serif; font-size:9pt; line-height:1.4; color:var(--k); }
  header { background:var(--k); color:#fff; padding:10px 14px; margin:0 0 14px 0; border-bottom:4px solid var(--r); print-color-adjust:exact; }
  header h1 { margin:0; font-size:11pt; letter-spacing:.05em; }
  header span { color:var(--r); font-weight:700; }
  header .sub { margin:5px 0 0; font-size:7.5pt; opacity:.9; }
  .hint { font-size:8pt; background:#fff8e6; border:1px solid #ddc653; padding:8px 10px; margin-bottom:12px; border-radius:4px; }
  @media print { .no-print{display:none!important;} }
  h2.doc-title { font-size:10pt; color:var(--r); margin:20px 0 8px; padding-bottom:4px; border-bottom:2px solid var(--r); page-break-after:avoid; }
  pre.doc { font-family:Consolas,"Courier New",monospace; font-size:7.5pt; line-height:1.35; white-space:pre-wrap; word-wrap:break-word; background:#fafafa; border:1px solid #ddd; padding:10px 12px; margin:0 0 16px; border-radius:4px; }
  .section-break { page-break-before:always; }
  kbd { border:1px solid #999; padding:0 4px; border-radius:3px; font-size:.9em; background:#eee; }
  .foot { font-size:7.5pt; color:#666; margin-top:8px; }
</style>
</head>
<body>

<header>
  <h1><span>AUTOWAGEN</span> MASTER — combined reference (Print → PDF)</h1>
  <div class="sub">Part A: docs/BUILD_WHOLE_PROJECT_AI_PROMPT.md · Part B: CLAUDE.md · Generated {$genAt}</div>
</header>

<div class="hint no-print"><strong>Save as PDF:</strong> Chrome or Edge → click in the page → <kbd>Ctrl</kbd>+<kbd>P</kbd> → <strong>Save as PDF</strong> · <strong>Pages: All</strong> · optional <strong>Background graphics</strong> ON.</div>
<p class="no-print"><strong>Regenerate this file</strong> after editing either source Markdown: open a terminal in the project folder and run <kbd>php tools/generate_build_claude_combined_print.php</kbd></p>

<h2 class="doc-title">Part A — BUILD_WHOLE_PROJECT_AI_PROMPT.md</h2>
<pre class="doc">{$htmlBuild}</pre>

<h2 class="doc-title section-break">Part B — CLAUDE.md</h2>
<pre class="doc">{$htmlClaude}</pre>

<div class="foot">End of combined export. Source files in repo may be newer than this HTML — regenerate if needed.</div>
</body>
</html>
HTML;

if (file_put_contents($outPath, $html) === false) {
    fwrite(STDERR, "Failed to write: {$outPath}\n");
    exit(1);
}

echo "Wrote {$outPath} (" . number_format(strlen($html)) . " bytes)\n";
