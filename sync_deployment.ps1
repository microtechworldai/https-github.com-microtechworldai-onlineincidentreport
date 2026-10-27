$srcDir = 'C:\Users\micro\OneDrive\Desktop\onlineincidentreport\hostinger_deployment\attendance'
$destUpload = 'C:\Users\micro\OneDrive\Desktop\onlineincidentreport\UPLOAD_TO_HOSTINGER\attendance'
$destPublicHtml = 'C:\Users\micro\OneDrive\Desktop\public_html\attendance'

Copy-Item -Path "$srcDir\*" -Destination $destUpload -Recurse -Force
Write-Host "Copied to UPLOAD_TO_HOSTINGER\attendance"

if (Test-Path 'C:\Users\micro\OneDrive\Desktop\public_html') {
    if (-not (Test-Path $destPublicHtml)) { New-Item -ItemType Directory -Path $destPublicHtml -Force | Out-Null }
    Copy-Item -Path "$srcDir\*" -Destination $destPublicHtml -Recurse -Force
    Write-Host "Copied to public_html\attendance"
}

$zipSource = 'C:\Users\micro\OneDrive\Desktop\onlineincidentreport\UPLOAD_TO_HOSTINGER'
$zipPaths = @(
    'C:\Users\micro\OneDrive\Desktop\onlineincidentreport\HOSTINGER_UPDATE_PATCH_LATEST.zip',
    'C:\Users\micro\OneDrive\Desktop\HOSTINGER_UPDATE_PATCH_LATEST.zip',
    'C:\Users\micro\OneDrive\Desktop\onlineincidentreport\UPLOAD_TO_HOSTINGER.zip',
    'C:\Users\micro\OneDrive\Desktop\UPLOAD_TO_HOSTINGER.zip'
)

foreach ($zp in $zipPaths) {
    if (Test-Path $zp) { Remove-Item $zp -Force }
    Compress-Archive -Path "$zipSource\*" -DestinationPath $zp -Force
    Write-Host "Created updated ZIP: $zp"
}
Write-Host "All synchronization and packaging completed successfully!"

