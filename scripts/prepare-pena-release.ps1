# Cria dois pacotes de homologacao a partir de um commit limpo, nunca do working tree.
# Nao instala, migra ou altera a hospedagem.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$OutputDirectory
)

$ErrorActionPreference = 'Stop'
$repository = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$status = @(git -C $repository status --porcelain=v1 --untracked-files=normal)
if ($LASTEXITCODE -ne 0) { throw 'Nao foi possivel consultar o estado do Git.' }
if ($status.Count -gt 0) { throw 'O working tree nao esta limpo. Revise e versione somente o que pertence ao release antes de empacotar.' }

$commit = (git -C $repository rev-parse --verify HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $commit -notmatch '^[0-9a-f]{40}$') { throw 'Commit de origem invalido.' }
$output = [IO.Path]::GetFullPath($OutputDirectory)
if (Test-Path -LiteralPath $output) { throw 'O diretorio de saida ja existe. Escolha um novo destino privado fora do repositorio.' }
if (-not (Test-Path -LiteralPath (Split-Path -Path $output -Parent) -PathType Container)) {
    throw 'O diretorio pai da saida precisa existir.'
}
if ($output.StartsWith($repository + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'O pacote nao pode ser gerado dentro do repositorio.'
}

$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$stage = Join-Path $tempRoot ('veneza-release-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage | Out-Null
try {
    $sourceZip = Join-Path $stage 'source.zip'
    & git -C $repository archive --format=zip "--output=$sourceZip" $commit -- site PENA
    if ($LASTEXITCODE -ne 0) { throw 'git archive falhou.' }
    Expand-Archive -LiteralPath $sourceZip -DestinationPath $stage
    $site = Join-Path $stage 'site'
    $pena = Join-Path $stage 'PENA'
    if (-not (Test-Path -LiteralPath (Join-Path $site '.htaccess')) -or
        -not (Test-Path -LiteralPath (Join-Path $pena 'public/.htaccess')) -or
        -not (Test-Path -LiteralPath (Join-Path $pena 'composer.lock'))) {
        throw 'O commit nao contem todos os arquivos necessarios para o site e o PENA.'
    }

    # A copia editorial estatica e os testes nao pertencem ao site em producao.
    foreach ($relative in @('tests', 'assets/data/posts-data.js', 'AGENTS.md', 'README.md', 'PENDENCIAS-PENA.md')) {
        $path = Join-Path $site $relative
        if (-not ([IO.Path]::GetFullPath($path)).StartsWith($site + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
            throw 'Caminho de limpeza do site fora da area temporaria.'
        }
        if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Recurse -Force }
    }
    foreach ($relative in @('tests', 'tools', 'scripts', 'database/seeders', 'Dockerfile', 'compose.yaml', 'phpunit.xml', '.dockerignore', '.env.example', '.gitignore', 'AGENTS.md')) {
        $path = Join-Path $pena $relative
        if (-not ([IO.Path]::GetFullPath($path)).StartsWith($pena + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
            throw 'Caminho de limpeza do PENA fora da area temporaria.'
        }
        if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Recurse -Force }
    }
    Get-ChildItem -LiteralPath $pena -File -Filter '*.md' | Remove-Item -Force

    & docker build -q -t pena-release-php84 (Join-Path $repository 'PENA')
    if ($LASTEXITCODE -ne 0) { throw 'Nao foi possivel preparar a imagem PHP 8.4.' }
    $phpVersion = (& docker run --rm --network none pena-release-php84 php -r 'echo PHP_VERSION;').Trim()
    if ($LASTEXITCODE -ne 0 -or $phpVersion -notmatch '^8\.4\.') { throw 'A imagem de empacotamento nao usa PHP 8.4.' }

    & docker run --rm -v "${pena}:/app" -w /app pena-release-php84 composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative --no-progress
    if ($LASTEXITCODE -ne 0) { throw 'composer install de producao falhou.' }
    & docker run --rm --network none -v "${pena}:/app:ro" -w /app pena-release-php84 composer check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'Dependencias incompatíveis com a plataforma de empacotamento.' }
    if (Test-Path -LiteralPath (Join-Path $pena '.env')) { throw 'Um .env apareceu no pacote; empacotamento interrompido.' }

    $artifacts = Join-Path $stage 'artifacts'
    New-Item -ItemType Directory -Path $artifacts | Out-Null
    $siteZip = Join-Path $artifacts 'site.zip'
    $penaZip = Join-Path $artifacts 'pena.zip'
    & tar -a -c -f $siteZip -C $site .
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao compactar o site.' }
    & tar -a -c -f $penaZip -C $pena .
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao compactar o PENA.' }
    $siteEntries = @(& tar -t -f $siteZip)
    $penaEntries = @(& tar -t -f $penaZip)
    if ($LASTEXITCODE -ne 0 -or -not ($siteEntries -match '(^|/)\.htaccess$') -or
        -not ($penaEntries -match '(^|/)public/\.htaccess$') -or
        ($siteEntries -match 'posts-data\.js$') -or ($penaEntries -match '(^|/)\.env$')) {
        throw 'A verificacao do conteudo dos pacotes falhou.'
    }

    $manifest = [ordered]@{
        commit = $commit
        built_at_utc = (Get-Date).ToUniversalTime().ToString('o')
        php_build = $phpVersion
        composer_lock_sha256 = (Get-FileHash -LiteralPath (Join-Path $pena 'composer.lock') -Algorithm SHA256).Hash.ToLowerInvariant()
        site_zip_sha256 = (Get-FileHash -LiteralPath $siteZip -Algorithm SHA256).Hash.ToLowerInvariant()
        pena_zip_sha256 = (Get-FileHash -LiteralPath $penaZip -Algorithm SHA256).Hash.ToLowerInvariant()
        site_files = @(Get-ChildItem -LiteralPath $site -File -Recurse -Force).Count
        pena_files = @(Get-ChildItem -LiteralPath $pena -File -Recurse -Force).Count
    }
    $manifest | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $artifacts 'manifest.json') -Encoding UTF8
    if (-not ([IO.Path]::GetFullPath($artifacts)).StartsWith($stage + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Caminho dos artefatos fora da area temporaria.'
    }
    Move-Item -LiteralPath $artifacts -Destination $output
    Write-Output "Pacotes gerados em $output a partir de $commit (PHP $phpVersion)."
}
finally {
    $resolvedStage = [IO.Path]::GetFullPath($stage)
    if ($resolvedStage.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and
        [IO.Path]::GetFileName($resolvedStage) -match '^veneza-release-[0-9a-f]{32}$' -and
        (Test-Path -LiteralPath $resolvedStage)) {
        Remove-Item -LiteralPath $resolvedStage -Recurse -Force
    }
}
