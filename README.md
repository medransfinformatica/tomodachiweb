# Tomodachi Collection · Emulador web de Nintendo DS

Emulador de **Nintendo DS** dentro del navegador basado en [EmulatorJS](https://emulatorjs.org/)
(RetroArch compilado a WebAssembly), servido por **Apache de XAMPP** en local y desplegable
como **Static Site en Render**.

- URL local: <http://localhost/tomodachiweb/>
- Núcleo usado: `nds` → **melonDS** (alternativas: `desmume`, `desmume2015`)
- ROM: **`tomodachi.nds`** (64 MiB) — se carga **cruda**, sin descomprimir
  (ver [La ROM](#1-la-rom))

---

## 0. Requisitos

| Requisito | Detalle |
|---|---|
| Servidor local | Apache de XAMPP (o cualquier servidor HTTP) en el puerto `80` |
| Navegador | Chrome, Edge o Firefox actualizados (WebAssembly + WebGL2) |
| ROM | `tomodachi.nds` (64 MiB, incluida en el proyecto) |
| Core | Se descarga automáticamente desde el CDN oficial de EmulatorJS |

> No hace falta Node.js ni compilar nada: el HTML carga el núcleo desde
> `https://cdn.emulatorjs.org/stable/data/`.

---

## 1. La ROM

### 1.1 Formato

`EJS_gameUrl` apunta a `tomodachi.nds`, la ROM **cruda**. El emulador la baja con
`fetch` → `ArrayBuffer` y la entrega tal cual al núcleo melonDS: **no hace falta
descomprimir nada en el navegador**.

Si en algún momento preferís un archivo comprimido, EmulatorJS también lo soporta:
detecta la firma del archivo y lo descomprime solo en un *Web Worker*.

| Formato | Módulo que usa EmulatorJS (servido por el CDN) |
|---|---|
| `.nds` (crudo) | — se entrega directo al núcleo |
| `.rar` (RAR4/RAR5) | `compression/libunrar.js` + `compression/libunrar.wasm` |
| `.zip` | `compression/extractzip.js` |
| `.7z` | `compression/extract7z.js` |

### 1.2 Estructura del proyecto

```
tomodachiweb/
├── index.html            # EmulatorJS + CSS responsivo a pantalla completa
├── rom.php               # proxy PHP que sirve la ROM (hosting compartido)
├── .htaccess             # MIME types (.nds / .wasm) y cabeceras CORS
├── .gitignore            # ignora las ROMs (*.nds) y archivos temporales
├── README.md
└── tomodachi.nds         # LA ROM cruda (64 MiB)
```

### 1.3 Usar tu propia ROM

1. Poné tu `.nds` en la carpeta del proyecto.
2. Si lo llamás distinto, actualizá **una sola línea** de `index.html`:

```js
window.EJS_gameUrl = 'tomodachi.nds';   // ← poné acá el nombre de tu archivo
```

Ejemplos: `'mi-juego.nds'`, `'Zelda - Phantom Hourglass.nds'`.

> Para **subirlo a GitHub** la ROM no se versiona (`.gitignore` la excluye). Mirá la
> [sección 7](#7-trabajar-con-git) para las tres alternativas al desplegar.

### 1.4 Comprobación rápida

```powershell
cd D:\XAMPP\htdocs\tomodachiweb
Get-ChildItem *.nds
# Debe listar: tomodachi.nds
```

Y en el navegador, `http://localhost/tomodachiweb/tomodachi.nds` debe devolver el archivo
binario (no un 404). En la pestaña *Network* deberías ver:

```
GET /tomodachi.nds     200     (67.108.864 bytes, application/octet-stream)
```

---

## 2. Puesta en marcha (local con XAMPP)

1. Iniciá **Apache** desde el *XAMPP Control Panel* (botón **Start**).
2. Verificá que `tomodachi.nds` esté en la carpeta del proyecto (sección 1.2).
3. Abrí <http://localhost/tomodachiweb/>.
4. Hacé clic en **“INICIAR JUEGO”** (ese clic también habilita el audio, que el navegador
   bloquea en reproducción automática).

La primera carga tarda un poco más: se descargan el núcleo (1,2 MB) y la ROM (64 MiB).
Después queda cacheada en el navegador (IndexedDB) y arranca al instante.

---

## 3. Configuración de EmulatorJS

Todo se configura en el bloque `<script>` de `index.html`, **antes** de cargar
`loader.js`:

| Variable | Valor | Qué hace |
|---|---|---|
| `EJS_player` | `'#game'` | Elemento donde se monta el emulador |
| `EJS_core` | `'nds'` | Sistema: melonDS (`desmume`, `desmume2015` también válidos) |
| `EJS_gameUrl` | `'tomodachi.nds'` | **Ruta de la ROM** (relativa al HTML) |
| `EJS_pathtodata` | `'https://cdn.emulatorjs.org/stable/data/'` | Origen de los cores y del descompresor |
| `EJS_controlScheme` | `'nds'` | Botones A/B/X/Y, L/R, SELECT/START |
| `EJS_language` | `'es-ES'` | Interfaz en español |
| `EJS_gameName` | `'Tomodachi Collection'` | Nombre para save-states y capturas |
| `EJS_gameID` | `3873` | ID numérico (separa partidas y cachés) |
| `EJS_volume` | `0.7` | Volumen inicial |
| `EJS_fullscreenOnLoaded` | `false` | Pantalla completa al cargar (requiere un gesto del usuario) |

### Capa de página propia

`EJS_mouse`, `EJS_touch` y `EJS_fullscreen` **no son opciones nativas de EmulatorJS**
(su loader solo lee un listado fijo de variables `window.EJS_*`). Por eso el proyecto
incluye una capa propia que las implementa de verdad:

| Flag | Efecto real |
|---|---|
| `EJS_mouse` | `false` → el canvas deja de recibir eventos de puntero |
| `EJS_touch` | `true` → autodetecta táctil y fuerza el gamepad virtual en pantalla |
| `EJS_fullscreen` | `true` → botón ⛶ + pantalla completa automática al primer toque en móvil |

---

## 4. `.htaccess` (solo XAMPP/Apache)

Apache no conoce `.nds` ni `.wasm` por defecto. El `.htaccess` del proyecto los registra:

```apache
AddType application/octet-stream .nds   # ROM cruda
AddType application/wasm .wasm           # WebAssembly (cores auto-alojados)
```

Además desactiva la compresión de la ROM y expone cabeceras CORS. Como la ROM se sirve
desde el **mismo origen** (`http://localhost/tomodachiweb/`), CORS no es necesario para
jugar: las cabeceras solo hacen falta si movés la ROM a otro host o puerto.

Requisito en `httpd.conf`: `AllowOverride All` para la carpeta `htdocs` y los módulos
`mod_mime` y `mod_headers` cargados (en XAMPP ya lo están de fábrica).

> **En Render el `.htaccess` no se procesa** (no hay Apache, hay CDN). No hace falta:
> la ROM se pide con `fetch` como `arraybuffer` y su `Content-Type` es irrelevante, y
> los cores `.wasm` llegan desde el CDN oficial con sus cabeceras correctas. Si aun así
> querés añadir cabeceras en Render, se hace desde el dashboard
> (*Settings → Headers*), no con `.htaccess`.

---

## 5. Controles

| Acción | Teclado | Gamepad |
|---|---|---|
| D-pad | Flechas | Cruceta |
| A / B | `X` / `Z` | A / B |
| X / Y | `S` / `A` | X / Y |
| START / SELECT | `Enter` / `Shift` | START / SELECT |
| L / R | `Q` / `E` | Shoulder |
| Pantalla completa | Botón ⛶ (esquina superior derecha) | — |

El mando virtual aparece automáticamente en dispositivos táctiles.

---

## 6. Solución de problemas

| Síntoma | Causa y solución |
|---|---|
| 404 al cargar la ROM | El archivo no está en la carpeta del proyecto o el nombre no coincide con `EJS_gameUrl` |
| `Network Error` durante la carga | La ROM supera el tamaño o el tiempo de descarga del hosting: probá con un `.zip` (38 MB) o servila desde un CDN externo |
| El juego no arranca | Verificá en *Network* que la respuesta sea binaria y no una página HTML de error del hosting |
| Pantalla negra | Probá `EJS_core = 'desmume'`; o activá la aceleración por hardware en el navegador |
| `WebAssembly.instantiate(): expected magic word` | Apache sirve `.wasm` como `application/octet-stream`: revisá el `AddType application/wasm .wasm` |
| `Access to fetch … blocked by CORS` | La ROM debe cargarse por HTTP desde el mismo origen, nunca como `file://`; si está en otro host, ese host debe enviar `Access-Control-Allow-Origin` |
| El juego va lento | `EJS_threads = true` + cabeceras `Cross-Origin-Opener-Policy` y `Cross-Origin-Embedder-Policy` |
| Sin audio | Hacé clic dentro del emulador: el navegador requiere un gesto del usuario |
| En Render no carga la ROM | La ROM debe estar en el **Publish Path** del Static Site (la raíz del repo): ver [sección 7.1](#7-trabajar-con-git) |

---

## 7. Trabajar con Git

La ROM **cruda** (`.nds`, 64 MiB) está fuera del repositorio mediante `.gitignore`:

```gitignore
*.nds      # no se versiona
!*.rar     # un comprimido sí se puede versionar
!*.zip
```

Puesta en marcha desde cero:

```powershell
cd D:\XAMPP\htdocs\tomodachiweb
git init
git add .
git commit -m "Emulador web Nintendo DS con EmulatorJS"
git branch -M main
git remote add origin https://github.com/<usuario>/<repo>.git
git push -u origin main
```

### 7.1 ¿Y si quiero desplegar en Render/GitHub Pages?

El sitio desplegado necesita la ROM **dentro del repo**, porque `EJS_gameUrl` es una
ruta relativa. Hay tres caminos:

| Opción | Cómo | Peso en el repo | Notas |
|---|---|---|---|
| **A. Forzar la ROM cruda** | `git add -f tomodachi.nds` | 64 MiB | GitHub lo permite (límite 100 MB) y Render lo sirve tal cual |
| **B. ROM comprimida** | `tomodachi.zip` + `EJS_gameUrl = 'tomodachi.zip'` | ~38 MB | El navegador la descomprime sola (`extractzip.js` del CDN) |
| **C. ROM en un host externo** | `EJS_gameUrl = 'https://.../tomodachi.nds'` | 0 | Requiere que ese host envíe `Access-Control-Allow-Origin` |

Para la opción **A**:

```powershell
git add -f tomodachi.nds
git commit -m "Versiona la ROM para el despliegue"
git push
```

Y si en algún momento la querés sacar del índice (sin borrarla del disco):

```powershell
git rm --cached tomodachi.nds
git commit -m "Deja de versionar la ROM cruda"
```

---

## 8. Desplegar en hosting compartido (InfinityFree)

### 8.1 El problema típico: "Error de Red"

En InfinityFree el emulador suele mostrar **“Error de Red”** (`Network Error`). No es un
fallo del emulador: en el código de EmulatorJS ese mensaje aparece **únicamente** cuando
la petición de la ROM falla:

```js
// emulator.min.js
const t = await this.downloadFile(this.config.gameUrl, ..., {responseType:"arraybuffer"});
if (-1 === t) return void this.startGameError(this.localization("Network Error"));
```

`downloadFile` devuelve `-1` si la respuesta es **4xx/5xx** o si la conexión falla. Si
fallara el núcleo, el mensaje sería otro (“Error downloading core (melonds-wasm.data)”).

Motivos habituales en InfinityFree:

| Causa | Detalle |
|---|---|
| **La ROM no llegó al servidor** | El plan gratuito limita el tamaño de cada archivo subido (~10 MB) y `tomodachi.nds` pesa **64 MiB** → 404 |
| **nginx bloquea el binario** | InfinityFree sirve detrás de **nginx** (`Server: nginx`), que puede interceptar la descarga directa de estáticos → 403 o página HTML |
| **Tiempo de espera** | Transferir 64 MiB por un hosting compartido puede cortarse |

### 8.2 Cómo diagnosticarlo en 20 segundos

1. Abrí en el navegador: `https://TU-SITIO/tomodachi.nds`
2. O usá PowerShell (reemplazá la URL):

```powershell
try {
  $r = Invoke-WebRequest "https://TU-SITIO/tomodachi.nds" -Method Head -UseBasicParsing
  "status=$($r.StatusCode) type=$($r.Headers['Content-Type']) len=$($r.Headers['Content-Length'])"
} catch {
  "HTTP $([int]$_.Exception.Response.StatusCode)  ->  $($_.Exception.Message)"
}
```

| Resultado | Solución |
|---|---|
| `404` | La ROM no está en el servidor → opción **C** (hosting externo) |
| `403` o devuelve HTML | nginx bloquea el binario → opción **A** (`rom.php`) |
| `200` con `Content-Length: 67108864` y `application/octet-stream` | Todo bien → recargá con **Ctrl+F5** (caché del navegador) |

### 8.3 Opciones de despliegue

#### A) Proxy PHP `rom.php` (incluido en el repo)

`rom.php` lee la ROM desde disco y la entrega con las cabeceras correctas
(`application/octet-stream`, `Content-Length`, `Access-Control-Allow-Origin: *`),
esquivando el bloqueo de nginx y el `Content-Type` incorrecto.

```js
window.EJS_gameUrl = 'rom.php';
```

Subí `rom.php` a la misma carpeta que `index.html` y la ROM al servidor (por FTP con
FileZilla, que permite subir archivos grandes en varios segmentos).

#### B) Hosting externo de la ROM (si no se puede subir el `.nds`)

`raw.githubusercontent.com` envía `Access-Control-Allow-Origin: *`, así que sirve:

```js
window.EJS_gameUrl = 'https://raw.githubusercontent.com/medransfinformatica/tomodachiweb/main/tomodachi.nds';
```

(subí la ROM al repo con `git add -f tomodachi.nds`).

#### C) Render (recomendado si la ROM es grande)

Render no tiene límite de tamaño por archivo y sirve el repo completo. Deploy del repo
como **Static Site** (Build Command vacío, Publish Path `/`) y apuntá `EJS_gameUrl` a
`https://TU-SITIO.onrender.com/tomodachi.nds`.

### 8.4 Fallback automático (ya implementado)

`index.html` prueba las URLs de la ROM con un `HEAD` antes de iniciar y usa la primera
que responda `200`, así el emulador no muere si la principal no está disponible:

```js
window.EJS_romUrls = [
  'tomodachi.nds',      // 1) archivo local
  'rom.php',            // 2) proxy PHP
  'https://raw.githubusercontent.com/.../tomodachi.nds'  // 3) repo GitHub
];
```

En la consola del navegador se ve la decisión:

```
[ROM] tomodachi.nds -> HTTP 404
[ROM] "tomodachi.nds" no responde. Uso el fallback: rom.php
```

> ⚠️ `EJS_romUrls` está pensado para **tú y tu equipo**. Si publicás el sitio con el
> repo público, recordá que la URL de GitHub expone la ROM a cualquiera que la abra.

---

## 9. Desplegar en Render (Static Site)

1. Subí el repo a GitHub (`git push -u origin main`).
2. En <https://dashboard.render.com/> → **New → Static Site**.
3. **Connect** con GitHub y elegí el repositorio `tomodachiweb`.
4. Configuración:
   - **Branch**: `main`
   - **Build Command**: *(vacío — no hace falta compilar nada)*
   - **Publish Path**: `/` (o `.`)
   - **Environment**: `Production`
5. **Create Static Site**. Render despliega y te da una URL `https://<nombre>.onrender.com`.
6. Cada `git push` a `main` redespliega automáticamente.

Notas:

- El `.htaccess` se sube al repo pero **Render lo ignora** (no usa Apache); no molesta.
- El sitio es estático: `index.html` en la raíz se sirve como `/`.
- El núcleo y el descompresor siguen viniendo del CDN de EmulatorJS, así que no hace
  falta subir nada más.
- Podés agregar cabeceras HTTP desde *Settings → Headers* en el dashboard.

---

## 10. Aviso legal

`Tomodachi Collection` es una obra de Nintendo / Intelligent Systems. Este repositorio
publica el **frontend del emulador** (HTML, CSS, `.htaccess`) y el archivo de ROM
**comprimido** que el usuario aporta al clonarlo. Cada usuario es responsable de su
copia y de cumplir la legislación de su país; publicar este repositorio no concede
ningún derecho sobre la obra original.

Este proyecto no se asocia ni responde por Nintendo, Intelligent Systems, EmulatorJS
ni RetroArch.
