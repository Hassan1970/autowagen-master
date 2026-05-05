# Regenerates docs/BUILD_WHOLE_PROJECT_AND_CLAUDE_print.html from:
#   docs/BUILD_WHOLE_PROJECT_AI_PROMPT.md + CLAUDE.md
# Run from project root:  powershell -ExecutionPolicy Bypass -File tools\generate_build_claude_combined_print.ps1
# Or: right-click Run with PowerShell (set location to repo root first).

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$outPath = Join-Path $root 'docs\BUILD_WHOLE_PROJECT_AND_CLAUDE_print.html'
$buildPath = Join-Path $root 'docs\BUILD_WHOLE_PROJECT_AI_PROMPT.md'
$claudePath = Join-Path $root 'CLAUDE.md'

$build = Get-Content -LiteralPath $buildPath -Raw -Encoding UTF8
$claude = Get-Content -LiteralPath $claudePath -Raw -Encoding UTF8
Add-Type -AssemblyName System.Web
$hb = [System.Web.HttpUtility]::HtmlEncode($build)
$hc = [System.Web.HttpUtility]::HtmlEncode($claude)
$genAt = Get-Date -Format 'yyyy-MM-dd HH:mm'

$html = @"
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
  <div class="sub">Part A: docs/BUILD_WHOLE_PROJECT_AI_PROMPT.md · Part B: CLAUDE.md · Generated $genAt · Regenerate: tools\generate_build_claude_combined_print.ps1 or php tools\generate_build_claude_combined_print.php</div>
</header>

<div class="hint no-print"><strong>Save as PDF:</strong> Chrome or Edge → <kbd>Ctrl</kbd>+<kbd>P</kbd> → <strong>Save as PDF</strong> · <strong>Pages: All</strong></div>
<p class="no-print"><strong>Refresh this file</strong> after editing either source Markdown (run the PowerShell or PHP script from the project folder).</p>

<h2 class="doc-title">Part A — BUILD_WHOLE_PROJECT_AI_PROMPT.md</h2>
<pre class="doc">$hb</pre>

<h2 class="doc-title section-break">Part B — CLAUDE.md</h2>
<pre class="doc">$hc</pre>

<div class="foot">End of combined export.</div>
</body>
</html>
"@

[System.IO.File]::WriteAllText($outPath, $html, [System.Text.UTF8Encoding]::new($false))
Write-Host "Wrote $outPath ($($html.Length) bytes)"
