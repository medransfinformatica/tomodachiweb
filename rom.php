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
 * Este script lee la ROM desde disco y la entrega al navegador como
 * application/octet-stream, con cabeceras correctas y soporte de HEAD.
 *
 * USO
 *   En index.html:  window.EJS_gameUrl = 'rom.php';
 *   Si la ROM esta en otro host: window.EJS_gameUrl = 'https://tu-host/tomodachi.nds';
 *
 * REQUISITOS
 *   - El archivo .nds debe estar en la MISMA carpeta que este script.
 *   - La extension .php debe estar habilitada en el hosting (InfinityFree: si).
 */

@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@set_time_limit(0);
while (ob_get_level() > 0) { @ob_end_clean(); }

// ------------------------------------------------------------------
// 1) Ubicar la ROM (acepta varios nombres por si se cambia el archivo)
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

if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 - La ROM no esta en el servidor.\n"
       . "Subi tomodachi.nds (o .zip/.rar) a la misma carpeta que rom.php.\n";
    exit;
}

$size  = filesize($file);
$mime  = 'application/octet-stream';
$name  = basename($file);

// ------------------------------------------------------------------
// 2) Cabeceras
// ------------------------------------------------------------------
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: inline; filename="' . $name . '"');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: none');   // el emulador descarga el archivo entero

// ------------------------------------------------------------------
// 3) Preflight CORS
// ------------------------------------------------------------------
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ------------------------------------------------------------------
// 4) HEAD (EmulatorJS lo usa para validar la cache antes de descargar)
// ------------------------------------------------------------------
if ($method === 'HEAD') {
    http_response_code(200);
    exit;
}

// ------------------------------------------------------------------
// 5) GET -> streaming del archivo (sin cargarlo en memoria)
// ------------------------------------------------------------------
http_response_code(200);
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
