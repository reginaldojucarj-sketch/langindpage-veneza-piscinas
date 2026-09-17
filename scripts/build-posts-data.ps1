param(
  [Parameter(Mandatory = $true)][string]$Source,
  [Parameter(Mandatory = $true)][string]$Destination
)

$ErrorActionPreference = 'Stop'

$fields = @(
  'id', 'title', 'html', 'description', 'snippet', 'status', 'highlight',
  'published_at', 'created_at', 'updated_at', 'slug', 'keywords', 'author',
  'category', 'categories', 'image', 'image_name'
)

$posts = @(
  Get-Content -LiteralPath $Source | Where-Object { $_.Trim() } | ForEach-Object {
    $row = $_ | ConvertFrom-Json
    $post = [ordered]@{}
    foreach ($field in $fields) { $post[$field] = $row.$field }
    $post
  }
)

if ($posts.Count -eq 0) { throw 'No posts found in source export.' }
$ids = @($posts | ForEach-Object { $_.id })
if (@($ids | Sort-Object -Unique).Count -ne $posts.Count) { throw 'Duplicate post IDs in source export.' }

$json = ConvertTo-Json -InputObject $posts -Depth 5 -Compress
$json = $json.Replace([string][char]0x2028, '\u2028').Replace([string][char]0x2029, '\u2029')
$output = "// Static snapshot of POST_pena and its article-related tables.`nwindow.VENEZA_POSTS = $json;`n"
$encoding = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Join-Path (Get-Location) $Destination), $output, $encoding)
Write-Output "Generated $($posts.Count) posts at $Destination"
