# Membuat Invoice.pdf dari invoice.html memakai Microsoft Edge / Google Chrome (mode headless).
# Jalankan dari folder ini:  .\make-pdf.ps1

$html = Join-Path $PSScriptRoot 'invoice.html'
$pdf = Join-Path $PSScriptRoot 'Invoice.pdf'

$browser = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $browser) {
    Write-Error 'Microsoft Edge atau Google Chrome tidak ditemukan.'
    exit 1
}

$profileDir = Join-Path $env:TEMP 'invoice-pdf-profile'

& $browser --headless=new --disable-gpu --no-pdf-header-footer --user-data-dir="$profileDir" `
    --virtual-time-budget=3000 --print-to-pdf="$pdf" ("file:///" + ($html -replace '\\', '/')) 2>$null | Out-Null

if (Test-Path $pdf) {
    Write-Output "PDF dibuat: $pdf"
} else {
    Write-Error 'Gagal membuat PDF.'
    exit 1
}
