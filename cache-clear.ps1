<#
.SYNOPSIS
    Clear Laravel view cache files from storage/framework/views/
.DESCRIPTION
    Deletes all compiled Blade .php files from the view cache directory.
    Run when views don't reflect recent changes (Ctrl+F5 didn't help).
    Safer than php artisan view:clear which sometimes hangs.
.EXAMPLE
    .\cache-clear.ps1
#>

$viewCacheDir = "$PSScriptRoot\storage\framework\views"
$count = 0

if (Test-Path $viewCacheDir) {
    $count = (Get-ChildItem "$viewCacheDir\*.php" -Force).Count
    Remove-Item "$viewCacheDir\*.php" -Force
    Write-Host "✓ View cache cleared: $count file(s) removed" -ForegroundColor Green
} else {
    Write-Host "! View cache directory not found at: $viewCacheDir" -ForegroundColor Yellow
}
