# Universal Security Services - Hostinger Deployment & Packaging Synchronizer

$rootProject = 'C:\Users\micro\OneDrive\Desktop\onlineincidentreport'
$destUpload = "$rootProject\UPLOAD_TO_HOSTINGER"
$destDeployment = "$rootProject\hostinger_deployment"
$destPublicHtml = 'C:\Users\micro\OneDrive\Desktop\public_html'

Write-Host ">>> Starting Hostinger All-in-One Deployment Packaging..."

# 1. Sync Attendance module
$attSrc = "$destDeployment\attendance"
$attDest = "$destUpload\attendance"
Copy-Item -Path "$attSrc\*" -Destination $attDest -Recurse -Force
Write-Host "Copied attendance to UPLOAD_TO_HOSTINGER\attendance"

# 2. Sync Attendance Roster
$rosterSrc = "$rootProject\attendance_roster"
$rosterDestUpload = "$destUpload\attendance_roster"
$rosterDestDeploy = "$destDeployment\attendance_roster"

if (-not (Test-Path $rosterDestUpload)) {
    New-Item -ItemType Directory -Path $rosterDestUpload -Force | Out-Null
}
Copy-Item -Path "$rosterSrc\*" -Destination $rosterDestUpload -Recurse -Force
Write-Host "Copied attendance_roster to UPLOAD_TO_HOSTINGER\attendance_roster"

if (-not (Test-Path $rosterDestDeploy)) {
    New-Item -ItemType Directory -Path $rosterDestDeploy -Force | Out-Null
}
Copy-Item -Path "$rosterSrc\*" -Destination $rosterDestDeploy -Recurse -Force
Write-Host "Copied attendance_roster to hostinger_deployment\attendance_roster"

# 3. Sync to public_html if present
if (Test-Path $destPublicHtml) {
    Copy-Item -Path "$destUpload\*" -Destination $destPublicHtml -Recurse -Force
    Write-Host "Copied all root files to public_html"
}

# 4. Build Deployment ZIP packages
$zipSource = $destUpload
$zipPaths = @(
    "$rootProject\HOSTINGER_UPDATE_PATCH_LATEST.zip",
    'C:\Users\micro\OneDrive\Desktop\HOSTINGER_UPDATE_PATCH_LATEST.zip',
    "$rootProject\UPLOAD_TO_HOSTINGER.zip",
    'C:\Users\micro\OneDrive\Desktop\UPLOAD_TO_HOSTINGER.zip'
)

foreach ($zp in $zipPaths) {
    if (Test-Path $zp) {
        Remove-Item $zp -Force
    }
    Compress-Archive -Path "$zipSource\*" -DestinationPath $zp -Force
    Write-Host "Created updated ZIP: $zp"
}

Write-Host "All files assembled in ONE ROOT DIRECTORY FOLDER:"
Write-Host "  $destUpload"
Write-Host "All ZIP files created successfully!"
