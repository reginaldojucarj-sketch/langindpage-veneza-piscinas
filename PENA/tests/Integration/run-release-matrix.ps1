# Executa apenas fixtures sinteticas em MariaDB descartavel, sem rede ou portas.
# Nao aceita host, banco ou credenciais de producao como parametros.
[CmdletBinding()]
param([string]$PhpImage = 'pena-php84-homolog')

$ErrorActionPreference = 'Stop'
$application = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
function Test-DockerCommand([string[]]$Arguments) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & docker @Arguments *> $null
        return $LASTEXITCODE -eq 0
    }
    finally { $ErrorActionPreference = $previous }
}
$cases = @(
    @{ Name = 'access'; Database = 'pena_access_test'; Marker = 'PENA_SYNTHETIC_ACCESS_TEST=1'; Script = 'admin-access-mariadb.php' },
    @{ Name = 'editorial'; Database = 'pena_editorial_test'; Marker = 'PENA_SYNTHETIC_EDITORIAL_TEST=1'; Script = 'editorial-order-mariadb.php' },
    @{ Name = 'content'; Database = 'pena_content_test'; Marker = 'PENA_SYNTHETIC_CONTENT_TEST=1'; Script = 'author-media-mariadb.php' },
    @{ Name = 'posts'; Database = 'pena_posts_test'; Marker = 'PENA_SYNTHETIC_POSTS_TEST=1'; Script = 'post-editor-mariadb.php' }
)

if (-not (Test-DockerCommand @('image', 'inspect', $PhpImage, '--format', '{{.Id}}'))) {
    throw "Imagem PHP ausente: $PhpImage"
}

foreach ($case in $cases) {
    $suffix = [Guid]::NewGuid().ToString('N').Substring(0, 12)
    $container = "pena-b07-$($case.Name)-$suffix"
    $socket = "pena-b07-socket-$suffix"
    $created = $false
    try {
        & docker run -d --name $container --network none --tmpfs /var/lib/mysql:rw,size=256m `
            -v "${socket}:/run/mysqld" -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 `
            -e "MARIADB_DATABASE=$($case.Database)" mariadb:10.11.19 --skip-networking | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Falha ao iniciar MariaDB para $($case.Name)." }
        $created = $true
        $ready = $false
        for ($attempt = 0; $attempt -lt 60; $attempt++) {
            if (Test-DockerCommand @('exec', $container, 'mariadb', '--protocol=socket', '-uroot', $case.Database, '-e', 'SELECT 1')) {
                $ready = $true; break
            }
            Start-Sleep -Milliseconds 500
        }
        if (-not $ready) { throw "MariaDB sintetico nao iniciou para $($case.Name)." }

        Write-Output "Executando matriz: $($case.Name) em $($case.Database)"
        & docker run --rm --network none -v "${application}:/app:ro" -v "${socket}:/run/mysqld" -w /app `
            -e APP_ENV=testing -e DB_CONNECTION=mysql -e DB_HOST=localhost `
            -e "DB_DATABASE=$($case.Database)" -e DB_USERNAME=root -e DB_PASSWORD= -e DB_URL= `
            -e DB_SOCKET=/run/mysqld/mysqld.sock -e CACHE_STORE=array -e SESSION_DRIVER=array `
            -e LOG_CHANNEL=stderr `
            -e $case.Marker $PhpImage php "tests/Integration/$($case.Script)"
        if ($LASTEXITCODE -ne 0) { throw "Falhou a matriz sintetica: $($case.Name)." }
    }
    finally {
        if ($created) { [void](Test-DockerCommand @('rm', '-f', $container)) }
        if (Test-DockerCommand @('volume', 'inspect', $socket)) {
            [void](Test-DockerCommand @('volume', 'rm', $socket))
        }
    }
}
Write-Output 'PASS: quatro suites MariaDB sinteticas em PHP 8.4, sem rede e sem persistencia.'
