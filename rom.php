<?php
/**
 * rom.php — Servidor/streamer de la ROM para hosting compartido (InfinityFree).
 *
 * POR QUE EXISTE
 * En InfinityFree (y otros hostings gratuitos con nginx delante de Apache) los
 * archivos binarios grandes suelen dar problemas:
 *   - limite de subida por archivo (~10 MB en el plan gratuito),
 *   - nginx/Apache bloquean o reescriben las descargas directas de estaticos,
 *   - el Content-Type no es el esperado.
 * Este script entrega la ROM al navegador como application/octet-stream,
 * con cabeceras correctas y soporte de HEAD.
 *
 * ORIGEN DE LA ROM (en orden de prioridad)
 *   1) Archivo local en la MISMA carpeta que este script
 *      (tomodachi.nds / tomodachi.zip / tomodachi.rar), si existe.
 *   2) Si no hay archivo local, se reenvia en streaming desde el enlace
 *      directo de Dropbox (REMOTE_ROM_URL). Asi el hosting no necesita
 *      almacenar los 64 MiB y el navegador recibe la ROM desde el mismo
 *      origen (sin problemas de CORS).
 *
 * USO
 *   En index.html:  window.EJS_gameUrl = 'rom.php';
 *   (En Render, que es Static Site y no ejecuta PHP, la ROM se carga
 *    directamente desde el enlace de Dropbox definido en index.html.)
 *
 * REQUISITOS
 *   - PHP con allow_url_fopen (fopen/readfile remotos) o la extension cURL.
 *   - La extension .php debe estar habilitada en el hosting (InfinityFree: si).
 */

@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@set_time_limit(0);
while (ob_get_level() > 0) { @ob_end_clean(); }

/* Enlace directo de Dropbox (dl=1). Cambiar aqui si el enlace rota. */
define('REMOTE_ROM_URL', 'https://www.dropbox.com/scl/fi/zm2jzelsooa13rsppv1e8/tomodachi.nds?rlkey=a3c0ercxgosz7o8d8z37o7w4h&st=6ubh3tme&dl=1');
define('REMOTE_ROM_NAME', 'tomodachi.nds');

// ------------------------------------------------------------------
// 1) Ubicar la ROM local (acepta varios nombres por si se cambia el archivo)
// ------------------------------------------------------------------
$candidates = array(
    'tomodachi.nds',
    'tomodachi.zip',
    'tomodachi.rar',
);

$file = null;
foreach ($candidates as $candidate) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $candidate;
    if (is_file($path)) { $file = $path; break; }
}

$useRemote = ($file === null);

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

// ------------------------------------------------------------------
// 2) Resolver tamano y nombre segun el origen
// ------------------------------------------------------------------
if (!$useRemote) {
    $size = filesize($file);
    $name = basename($file);
} else {
    // Preguntar a Dropbox el tamano con una peticion GET de 1 byte (Range),
    // siguiendo las redirecciones. Asi evitamos descargar nada en HEAD/OPTIONS.
    $size = remote_content_length(REMOTE_ROM_URL);
    $name = REMOTE_ROM_NAME;
}

/**
 * Devuelve el Content-Length remoto (tras redirecciones) o null si falla.
 */
function remote_content_length($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);          // HEAD
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        if ($code >= 200 && $code < 400 && $length > 0) {
            return (int) $length;
        }
        return null;
    }
    // Fallback sin cURL: GET con Range bytes=0-0 y parsear Content-Range.
    $ctx = stream_context_create(array(
        'http' => array(
            'method'          => 'GET',
            'header'          => "Range: bytes=0-0\r\n",
            'follow_location' => 1,
            'timeout'         => 30,
            'ignore_errors'   => true,
        ),
    ));
    $fp = @fopen($url, 'rb', false, $ctx);
    if ($fp === false) { return null; }
    $meta = stream_get_meta_data($fp);
    fclose($fp);
    foreach (isset($meta['wrapper_data']) ? (array) $meta['wrapper_data'] : array() as $h) {
        if (stripos($h, 'Content-Range:') === 0 && preg_match('/\/(\d+)\s*$/', $h, $m)) {
            return (int) $m[1];
        }
        if (stripos($h, 'Content-Length:') === 0) {
            return (int) trim(substr($h, strlen('Content-Length:')));
        }
    }
    return null;
}

// ------------------------------------------------------------------
// 3) Cabeceras
// ------------------------------------------------------------------
header('Content-Type: application/octet-stream');
if ($size !== null && $size !== false) {
    header('Content-Length: ' . $size);
}
header('Content-Disposition: inline; filename="' . $name . '"');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: none');   // el emulador descarga el archivo entero

// ------------------------------------------------------------------
// 4) Preflight CORS
// ------------------------------------------------------------------
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ------------------------------------------------------------------
// 5) HEAD (EmulatorJS lo usa para validar la cache antes de descargar)
// ------------------------------------------------------------------
if ($method === 'HEAD') {
    http_response_code((!$useRemote || $size !== null) ? 200 : 502);
    exit;
}

// ------------------------------------------------------------------
// 6) GET -> streaming (local o remoto, sin cargarlo en memoria)
// ------------------------------------------------------------------
http_response_code(200);

if (!$useRemote) {
    $fp = fopen($file, 'rb');
    if ($fp === false) {
        echo "Error al abrir la ROM.";
        exit;
    }
    while (!feof($fp)) {
        echo fread($fp, 1048576);   // bloques de 1 MB
        flush();
    }
    fclose($fp);
    exit;
}

// Origen remoto: reenviar el stream de Dropbox tal cual.
if (function_exists('curl_init')) {
    $ch = curl_init(REMOTE_ROM_URL);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);            // sin limite: 64 MiB
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) {
        echo $chunk;
        flush();
        return strlen($chunk);
    });
    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        // Las cabeceras ya se enviaron; no podemos cambiar el codigo HTTP.
        error_log('rom.php: error al reenviar desde Dropbox: ' . $err);
    }
    exit;
}

$ctx = stream_context_create(array(
    'http' => array(
        'method'          => 'GET',
        'follow_location' => 1,
        'timeout'         => 0,
        'ignore_errors'   => true,
    ),
));
$fp = @fopen(REMOTE_ROM_URL, 'rb', false, $ctx);
if ($fp === false) {
    http_response_code(502);
    echo "502 - No se pudo obtener la ROM desde Dropbox.\n";
    exit;
}
while (!feof($fp)) {
    echo fread($fp, 1048576);
    flush();
}
fclose($fp);
exit;
