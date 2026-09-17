Add-Type -AssemblyName PresentationCore
Add-Type -AssemblyName WindowsBase

$projectRoot = Split-Path -Parent $PSScriptRoot
$sourceDir = Join-Path $projectRoot 'assets\sources\hero'
$outputDir = Join-Path $projectRoot 'assets\images\hero'
New-Item -ItemType Directory -Path $outputDir -Force | Out-Null

$slides = @(
    @{ Source = 'piscina-hotel-vista-mar-pingo-convite-ia.png'; Output = 'piscina-hotel-vista-mar-pingo.jpg' }
    @{ Source = 'piscina-vista-ilha-pingo-convite-ia.png'; Output = 'piscina-vista-ilha-pingo.jpg' }
    @{ Source = 'piscina-santorini-sol-de-verao-pingo-convite-ia.png'; Output = 'piscina-santorini-sol-de-verao-pingo.jpg' }
    @{ Source = 'piscina-casa-vista-montanhas-sol-de-verao-pingo-convite-ia.png'; Output = 'piscina-casa-vista-montanhas-sol-de-verao-pingo.jpg' }
)

foreach ($slide in $slides) {
    $sourcePath = Join-Path $sourceDir $slide.Source
    $outputPath = Join-Path $outputDir $slide.Output
    $stream = [System.IO.File]::OpenRead($sourcePath)
    try {
        $decoder = [System.Windows.Media.Imaging.BitmapDecoder]::Create(
            $stream,
            [System.Windows.Media.Imaging.BitmapCreateOptions]::None,
            [System.Windows.Media.Imaging.BitmapCacheOption]::OnLoad
        )
        $frame = $decoder.Frames[0]
    }
    finally {
        $stream.Dispose()
    }

    $cropWidth = $frame.PixelWidth
    $cropHeight = [int][Math]::Round($cropWidth / 1.5)
    if ($cropHeight -gt $frame.PixelHeight) {
        $cropHeight = $frame.PixelHeight
        $cropWidth = [int][Math]::Round($cropHeight * 1.5)
    }
    $cropX = [int][Math]::Floor(($frame.PixelWidth - $cropWidth) / 2)
    $cropY = [int][Math]::Floor(($frame.PixelHeight - $cropHeight) / 2)
    $crop = [System.Windows.Media.Imaging.CroppedBitmap]::new(
        $frame,
        [System.Windows.Int32Rect]::new($cropX, $cropY, $cropWidth, $cropHeight)
    )

    $visual = [System.Windows.Media.DrawingVisual]::new()
    [System.Windows.Media.RenderOptions]::SetBitmapScalingMode(
        $visual,
        [System.Windows.Media.BitmapScalingMode]::HighQuality
    )
    $context = $visual.RenderOpen()
    $context.DrawImage($crop, [System.Windows.Rect]::new(0, 0, 1200, 800))
    $context.Close()

    $bitmap = [System.Windows.Media.Imaging.RenderTargetBitmap]::new(
        1200, 800, 96, 96, [System.Windows.Media.PixelFormats]::Pbgra32
    )
    $bitmap.Render($visual)
    $encoder = [System.Windows.Media.Imaging.JpegBitmapEncoder]::new()
    $encoder.QualityLevel = 88
    $encoder.Frames.Add([System.Windows.Media.Imaging.BitmapFrame]::Create($bitmap))
    $output = [System.IO.File]::Create($outputPath)
    try {
        $encoder.Save($output)
    }
    finally {
        $output.Dispose()
    }
    Write-Output $outputPath
}
