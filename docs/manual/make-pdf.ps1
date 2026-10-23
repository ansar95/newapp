# Membuat Buku-Panduan-PM-Cost-Control.pdf dari buku-panduan.html.
# Jalankan dari folder ini:  powershell -ExecutionPolicy Bypass -File .\make-pdf.ps1

$html = Join-Path $PSScriptRoot 'buku-panduan.html'
$pdf = Join-Path $PSScriptRoot 'Buku-Panduan-PM-Cost-Control.pdf'

$browser = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $browser) { Write-Error 'Microsoft Edge atau Google Chrome tidak ditemukan.'; exit 1 }

$profile = Join-Path $env:TEMP 'manual-pdf-profile'
& $browser --headless=new --disable-gpu --no-pdf-header-footer --user-data-dir="$profile" `
    --virtual-time-budget=8000 --print-to-pdf="$pdf" ("file:///" + ($html -replace '\\', '/')) 2>$null | Out-Null

if (Test-Path $pdf) { Write-Output "PDF dibuat: $pdf" } else { Write-Error 'Gagal membuat PDF.'; exit 1 }
