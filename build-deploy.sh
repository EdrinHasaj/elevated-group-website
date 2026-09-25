#!/usr/bin/env bash
# Builds dist/ containing only the files the live site needs, then zips it
# for upload to Bluehost. Run from the project root:  bash build-deploy.sh
set -euo pipefail

cd "$(dirname "$0")"
rm -rf dist elevated-site.zip
mkdir -p dist/img dist/branding dist/clientcarousel dist/fonts

cp index.html contact.php dist/

# hero.webp only — the 2MB hero.png it replaced stays out of the deploy.
cp img/hero.webp img/joana.jpg img/services.webp img/services-wide.webp dist/img/
cp fonts/*.ttf dist/fonts/
cp branding/branding3wide.png branding/elevatedlogo.png branding/favicon.png dist/branding/
cp clientcarousel/* dist/clientcarousel/

# Zip the CONTENTS of dist, so extracting in public_html doesn't nest a folder.
if command -v zip >/dev/null 2>&1; then
    ( cd dist && zip -qr ../elevated-site.zip . )
else
    # Windows Git Bash has no zip. PowerShell's Compress-Archive writes
    # backslash separators, which cPanel's Linux extractor turns into flat
    # files named "img\hero.png", so build the entries by hand instead.
    powershell -NoProfile -Command "
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        \$root = (Resolve-Path 'dist').Path.TrimEnd('\')
        \$zip = [System.IO.Compression.ZipFile]::Open(
            (Join-Path (Get-Location) 'elevated-site.zip'), 'Create')
        Get-ChildItem \$root -Recurse -File | ForEach-Object {
            \$name = \$_.FullName.Substring(\$root.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                \$zip, \$_.FullName, \$name) | Out-Null
        }
        \$zip.Dispose()"
fi

echo "dist/ built — $(du -sh dist | cut -f1)"
echo "elevated-site.zip ready — $(du -h elevated-site.zip | cut -f1)"
