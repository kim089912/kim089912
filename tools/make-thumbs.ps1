# 사이트 화면을 찍은 PNG를 갤러리에 넣을 크기의 JPEG로 줄인다.
# 사용:  powershell -ExecutionPolicy Bypass -File tools\make-thumbs.ps1 <PNG가 든 폴더> [루코 그림 경로]
# 결과는 tools\shots\ 에 쓴다. build.php 가 그 JPEG를 그림 안에 담는다.
param([Parameter(Mandatory = $true)][string]$Source, [string]$Rooco = '')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$outDir = Join-Path $PSScriptRoot 'shots'
if (-not (Test-Path -LiteralPath $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
$jpeg = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }

function Save-Resized([string]$path, [string]$name, [int]$width, [int]$quality) {
    $src = [System.Drawing.Image]::FromFile($path)
    try {
        $height = [int][math]::Round($src.Height * $width / $src.Width)
        $bmp = New-Object System.Drawing.Bitmap($width, $height)
        $g = [System.Drawing.Graphics]::FromImage($bmp)
        $g.InterpolationMode = 'HighQualityBicubic'
        $g.PixelOffsetMode = 'HighQuality'
        $g.DrawImage($src, 0, 0, $width, $height)
        $g.Dispose()
        $p = New-Object System.Drawing.Imaging.EncoderParameters(1)
        $p.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]$quality)
        $out = Join-Path $outDir ($name + '.jpg')
        $bmp.Save($out, $jpeg, $p)
        $bmp.Dispose()
        '{0}.jpg  {1}x{2}  {3} KB' -f $name, $width, $height, [math]::Round((Get-Item $out).Length / 1KB)
    } finally { $src.Dispose() }
}

foreach ($f in Get-ChildItem -LiteralPath $Source -Filter *.png | Sort-Object Name) {
    $img = [System.Drawing.Image]::FromFile($f.FullName); $portrait = $img.Height -gt $img.Width; $img.Dispose()
    # 세로로 긴 것은 휴대폰 화면이다. 갤러리에서 작게 들어가므로 폭을 줄인다.
    if ($portrait) { Save-Resized $f.FullName $f.BaseName 420 84 } else { Save-Resized $f.FullName $f.BaseName 1000 80 }
}
if ($Rooco) { Save-Resized $Rooco 'rooco' 900 86 }
