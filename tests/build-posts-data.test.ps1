# Exercita a conversao com dados sinteticos; nao le nem modifica o banco.
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$source = New-TemporaryFile
$destination = 'tests/.tmp-public-posts-' + [guid]::NewGuid().ToString('N') + '.js'
$outputPath = Join-Path $root $destination

try {
    # Unicode por codigo evita que o proprio teste dependa do encoding do .ps1 no Windows PowerShell 5.1.
    $accentedTitle = [string][char]0x00C1 + 'gua saud' + [char]0x00E1 + 'vel'
    $rows = @(
        ('{"id":1,"title":"' + $accentedTitle + '","status":"PP"}')
        '{"id":2,"title":"Rascunho privado","status":"PO"}'
        '{"id":3,"title":"Excluido privado","status":"PE"}'
    )
    [IO.File]::WriteAllLines($source.FullName, $rows, (New-Object Text.UTF8Encoding($false)))
    Push-Location $root
    try {
        & (Join-Path $root 'scripts/build-posts-data.ps1') -Source $source.FullName -Destination $destination | Out-Null
    }
    finally { Pop-Location }

    $result = [IO.File]::ReadAllText($outputPath, [Text.Encoding]::UTF8)
    if (-not $result.Contains($accentedTitle)) { throw 'UTF-8 was not preserved.' }
    if ($result.Contains('Rascunho') -or $result.Contains('Excluido')) { throw 'A non-public article entered the snapshot.' }
    if ($result -notmatch '"status":"PP"') { throw 'The published article was lost.' }
    Write-Output 'PASS: PP-only JSONL conversion preserves UTF-8 on this PowerShell version.'
}
finally {
    Remove-Item -LiteralPath $source.FullName -Force
    if (Test-Path -LiteralPath $outputPath) { Remove-Item -LiteralPath $outputPath -Force }
}
