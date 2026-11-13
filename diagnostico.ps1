<#
    diagnostico.ps1
    Comprueba si tu hosting (InfinityFree, Render, etc.) puede servir la ROM
    al emulador y te dice exactamente que corregir.

    USO:
      powershell -ExecutionPolicy Bypass -File .\diagnostico.ps1 -Url "https://tu-sitio.great-site.net"
      powershell -ExecutionPolicy Bypass -File .\diagnostico.ps1 -Url "https://tu-sitio.great-site.net" -Descargar

    -Url       : direccion base del sitio (sin / final)
    -Archivo   : nombre de la ROM (por defecto tomodachi.nds)
    -Descargar : ademas descarga la ROM y compara el SHA256 con el archivo local
#>

param(
    [Parameter(Mandatory = $true)][string]$Url,
    [string]$Archivo = 'tomodachi.nds',
    [switch]$Descargar
)

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Url = $Url.TrimEnd('/')
$ok  = $true

function Titulo([string]$t) {
    Write-Host ''
    Write-Host "== $t ==" -ForegroundColor Cyan
}

function Resultado([string]$etiqueta, [bool]$bien, [string]$detalle) {
    $marca = if ($bien) { 'OK  ' } else { 'FALLA' }
    $color = if ($bien) { 'Green' } else { 'Red' }
    Write-Host ("  [{0}] {1,-28} {2}" -f $marca, $etiqueta, $detalle) -ForegroundColor $color
}

function Probar([string]$url, [string]$metodo = 'HEAD') {
    try {
        return Invoke-WebRequest -Uri $url -Method $metodo -UseBasicParsing -TimeoutSec 45
    } catch {
        $codigo = $null
        if ($_.Exception.Response) { $codigo = [int]$_.Exception.Response.StatusCode }
        return [pscustomobject]@{
            StatusCode = $codigo
            Headers    = @{}
            Error      = $_.Exception.Message
        }
    }
}

Write-Host ''
Write-Host 'DIAGNOSTICO DEL EMULADOR TOMODACHI' -ForegroundColor White
Write-Host "Sitio: $Url" -ForegroundColor White

# ----------------------------------------------------------------------
Titulo '1. Pagina principal (index.html)'
$r = Probar "$Url/" 'GET'
if ($r.StatusCode -eq 200) {
    $tieneGameUrl = $r.Content -match "EJS_gameUrl\s*=\s*'([^']+)'"
    $urlRom = if ($tieneGameUrl) { $Matches[1] } else { '(no encontrada)' }
    Resultado 'index.html' $true "HTTP 200 - $($r.Content.Length) bytes"
    Resultado 'EJS_gameUrl' $tieneGameUrl "apunta a: $urlRom"
    if (-not $tieneGameUrl) { $ok = $false }
} else {
    Resultado 'index.html' $false "HTTP $($r.StatusCode) - $($r.Error)"
    $ok = $false
}

# ----------------------------------------------------------------------
Titulo "2. ROM estatica ($Archivo)"
$r = Probar "$Url/$Archivo"
if ($r.StatusCode -eq 200) {
    $len = $r.Headers['Content-Length']
    $tipo = $r.Headers['Content-Type']
    $cors = $r.Headers['Access-Control-Allow-Origin']
    Resultado 'HEAD' $true "HTTP 200 - $len bytes - $tipo"
    Resultado 'CORS' ($cors -eq '*') "Access-Control-Allow-Origin: $cors"
    if ($len -and [int64]$len -lt 1000000) {
        Write-Host '  AVISO: el archivo pesa menos de 1 MB; probablemente sea una pagina de error, no la ROM.' -ForegroundColor Yellow
    }
} else {
    Resultado 'HEAD' $false "HTTP $($r.StatusCode) - $($r.Error)"
    if ($r.StatusCode -eq 404) {
        Write-Host '  CAUSA: la ROM no esta en el servidor (limite de subida por archivo en el plan gratuito).' -ForegroundColor Yellow
    } elseif ($r.StatusCode -eq 403) {
        Write-Host '  CAUSA: el servidor bloquea la descarga directa del binario.' -ForegroundColor Yellow
    }
    $ok = $false
}

# ----------------------------------------------------------------------
Titulo '3. Proxy PHP (rom.php)'
$r = Probar "$Url/rom.php"
if ($r.StatusCode -eq 200) {
    $len = $r.Headers['Content-Length']
    Resultado 'rom.php' $true "HTTP 200 - $len bytes"
} else {
    Resultado 'rom.php' $false "HTTP $($r.StatusCode) - $($r.Error)"
    Write-Host '  CAUSA: subilo por FTP o por el panel (debe estar en la MISMA carpeta que index.html).' -ForegroundColor Yellow
}

# ----------------------------------------------------------------------
if ($Descargar) {
    Titulo '4. Descarga completa y comparacion de hash (opcional)'
    $tmp = Join-Path ([IO.Path]::GetTempPath()) 'rom_descargada.nds'
    try {
        Invoke-WebRequest -Uri "$Url/$Archivo" -OutFile $tmp -UseBasicParsing -TimeoutSec 900
        $descargado = (Get-Item $tmp).Length
        Resultado 'GET' $true "$descargado bytes"
        $local = Join-Path $PSScriptRoot $Archivo
        if (Test-Path $local) {
            $a = (Get-FileHash $local  -Algorithm SHA256).Hash
            $b = (Get-FileHash $tmp   -Algorithm SHA256).Hash
            Resultado 'SHA256' ($a -eq $b) ("local=$a descargado=$b")
            if ($a -ne $b) { $ok = $false }
        }
        Remove-Item $tmp -ErrorAction SilentlyContinue
    } catch {
        Resultado 'GET' $false $_.Exception.Message
        $ok = $false
    }
}

# ----------------------------------------------------------------------
Titulo '5. Diagnostico final'
if ($ok) {
    Write-Host '  El hosting PUEDE servir la ROM. Si el emulador sigue mostrando' -ForegroundColor Green
    Write-Host '  "Error de Red", recarga con Ctrl+F5 (cacha del navegador) y' -ForegroundColor Green
    Write-Host '  revisa la consola: debe aparecer [ROM] OK: <url>.' -ForegroundColor Green
} else {
    Write-Host '  El hosting NO puede servir la ROM todavia. Opciones:' -ForegroundColor Red
    Write-Host '   1. Subir la ROM por FTP con FileZilla (permite archivos grandes).' -ForegroundColor Yellow
    Write-Host '   2. Usar rom.php (proxy PHP) en vez del archivo estatico.' -ForegroundColor Yellow
    Write-Host '   3. Servir la ROM desde GitHub/Render y poner la URL absoluta' -ForegroundColor Yellow
    Write-Host '      en window.EJS_gameUrl (ya esta como 3er fallback automatico).' -ForegroundColor Yellow
}
Write-Host ''

if (-not $ok) { exit 1 }
exit 0
