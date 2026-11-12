# Tomodachi Collection Â· Emulador web de Nintendo DS

Emulador de **Nintendo DS** dentro del navegador basado en [EmulatorJS](https://emulatorjs.org/)
(RetroArch compilado a WebAssembly), servido por **Apache de XAMPP** en local y desplegable
como **Static Site en Render**.

- URL local: <http://localhost/tomodachiweb/>
- NÃºcleo usado: `nds` â†’ **melonDS** (alternativas: `desmume`, `desmume2015`)
- ROM: **`tomodachi.nds`** (64 MiB) â€” se carga **cruda**, sin descomprimir
  (ver [La ROM](#1-la-rom))

---

## 0. Requisitos

| Requisito | Detalle |
|---|---|
| Servidor local | Apache de XAMPP (o cualquier servidor HTTP) en el puerto `80` |
| Navegador | Chrome, Edge o Firefox actualizados (WebAssembly + WebGL2) |
| ROM | `tomodachi.rar` (ya incluido) o tu propio `.nds` comprimido |
| Core | Se descarga automÃ¡ticamente desde el CDN oficial de EmulatorJS |

> No hace falta Node.js ni compilar nada: el HTML carga el nÃºcleo desde
> `https://cdn.emulatorjs.org/stable/data/`.

---

## 1. La ROM

### 1.1 Formato

`EJS_gameUrl` apunta a `tomodachi.nds`, la ROM **cruda**. El emulador la baja con
`fetch` â†’ `ArrayBuffer` y la entrega tal cual al nÃºcleo melonDS: **no hace falta
descomprimir nada en el navegador**.

Si en algÃºn momento preferÃ­s un archivo comprimido, EmulatorJS tambiÃ©n lo soporta:
detecta la firma del archivo y lo descomprime solo en un *Web Worker*.

| Formato | MÃ³dulo que usa EmulatorJS (servido por el CDN) |
|---|---|
| `.nds` (crudo) | â€” se entrega directo al nÃºcleo |
| `.rar` (RAR4/RAR5) | `compression/libunrar.js` + `compression/libunrar.wasm` |
| `.zip` | `compression/extractzip.js` |
| `.7z` | `compression/extract7z.js` |

### 1.2 Estructura del proyecto

```
tomodachiweb/
â”œâ”€â”€ index.html            # EmulatorJS + CSS responsivo a pantalla completa
â”œâ”€â”€ .htaccess             # MIME types (.nds / .wasm) y cabeceras CORS
â”œâ”€â”€ .gitignore            # ignora las ROMs (*.nds) y archivos temporales
â”œâ”€â”€ README.md
â””â”€â”€ tomodachi.nds         # LA ROM cruda (64 MiB)
```

### 1.3 Usar tu propia ROM

1. PonÃ© tu `.nds` en la carpeta del proyecto.
2. Si lo llamÃ¡s distinto, actualizÃ¡ **una sola lÃ­nea** de `index.html`:

```js
window.EJS_gameUrl = 'tomodachi.nds';   // â† ponÃ© acÃ¡ el nombre de tu archivo
```

Ejemplos: `'mi-juego.nds'`, `'Zelda - Phantom Hourglass.nds'`.

> Para **subirlo a GitHub** la ROM no se versiona (`.gitignore` la excluye). MirÃ¡ la
> [secciÃ³n 8](#8-trabajar-con-git) para las tres alternativas al desplegar.

### 1.4 ComprobaciÃ³n rÃ¡pida

```powershell
cd D:\XAMPP\htdocs\tomodachiweb
Get-ChildItem *.nds
# Debe listar: tomodachi.nds
```

Y en el navegador, `http://localhost/tomodachiweb/tomodachi.nds` debe devolver el archivo
binario (no un 404). En la pestaÃ±a *Network* deberÃ­as ver:

```
GET /tomodachi.nds     200     (67.108.864 bytes, application/octet-stream)
```

---

## 2. Puesta en marcha (local con XAMPP)

1. IniciÃ¡ **Apache** desde el *XAMPP Control Panel* (botÃ³n **Start**).
2. VerificÃ¡ que `tomodachi.nds` estÃ© en la carpeta del proyecto (secciÃ³n 1.2).
3. AbrÃ­ <http://localhost/tomodachiweb/>.
4. HacÃ© clic en **â€œINICIAR JUEGOâ€** (ese clic tambiÃ©n habilita el audio, que el navegador
   bloquea en reproducciÃ³n automÃ¡tica).

La primera carga tarda un poco mÃ¡s: se descargan el nÃºcleo (1,2 MB) y la ROM (64 MiB).
DespuÃ©s queda cacheada en el navegador (IndexedDB) y arranca al instante.

---

## 4. ConfiguraciÃ³n de EmulatorJS

Todo se configura en el bloque `<script>` de `index.html`, **antes** de cargar
`loader.js`:

| Variable | Valor | QuÃ© hace |
|---|---|---|
| `EJS_player` | `'#game'` | Elemento donde se monta el emulador |
| `EJS_core` | `'nds'` | Sistema: melonDS (`desmume`, `desmume2015` tambiÃ©n vÃ¡lidos) |
| `EJS_gameUrl` | `'tomodachi.nds'` | **Ruta de la ROM** (relativa al HTML) |
| `EJS_pathtodata` | `'https://cdn.emulatorjs.org/stable/data/'` | Origen de los cores y del descompresor |
| `EJS_controlScheme` | `'nds'` | Botones A/B/X/Y, L/R, SELECT/START |
| `EJS_language` | `'es-ES'` | Interfaz en espaÃ±ol |
| `EJS_gameName` | `'Tomodachi Collection'` | Nombre para save-states y capturas |
| `EJS_gameID` | `3873` | ID numÃ©rico (separa partidas y cachÃ©s) |
| `EJS_volume` | `0.7` | Volumen inicial |
| `EJS_fullscreenOnLoaded` | `false` | Pantalla completa al cargar (requiere un gesto del usuario) |

### Capa de pÃ¡gina propia

`EJS_mouse`, `EJS_touch` y `EJS_fullscreen` **no son opciones nativas de EmulatorJS**
(su loader solo lee un listado fijo de variables `window.EJS_*`). Por eso el proyecto
incluye una capa propia que las implementa de verdad:

| Flag | Efecto real |
|---|---|
| `EJS_mouse` | `false` â†’ el canvas deja de recibir eventos de puntero |
| `EJS_touch` | `true` â†’ autodetecta tÃ¡ctil y fuerza el gamepad virtual en pantalla |
| `EJS_fullscreen` | `true` â†’ botÃ³n â›¶ + pantalla completa automÃ¡tica al primer toque en mÃ³vil |

---

## 4. `.htaccess` (solo XAMPP/Apache)

Apache no conoce `.nds` ni `.wasm` por defecto. El `.htaccess` del proyecto los registra:

```apache
AddType application/octet-stream .nds   # ROM cruda
AddType application/wasm .wasm           # WebAssembly (cores auto-alojados)
```

AdemÃ¡s desactiva la compresiÃ³n de la ROM y expone cabeceras CORS. Como la ROM se sirve
desde el **mismo origen** (`http://localhost/tomodachiweb/`), CORS no es necesario para
jugar: las cabeceras solo hacen falta si movÃ©s la ROM a otro host o puerto.

Requisito en `httpd.conf`: `AllowOverride All` para la carpeta `htdocs` y los mÃ³dulos
`mod_mime` y `mod_headers` cargados (en XAMPP ya lo estÃ¡n de fÃ¡brica).

> **En Render el `.htaccess` no se procesa** (no hay Apache, hay CDN). No hace falta:
> la ROM se pide con `fetch` como `arraybuffer` y su `Content-Type` es irrelevante, y
> los cores `.wasm` llegan desde el CDN oficial con sus cabeceras correctas. Si aun asÃ­
> querÃ©s aÃ±adir cabeceras en Render, se hace desde el dashboard
> (*Settings â†’ Headers*), no con `.htaccess`.

---

## 5. Controles

| AcciÃ³n | Teclado | Gamepad |
|---|---|---|
| D-pad | Flechas | Cruceta |
| A / B | `X` / `Z` | A / B |
| X / Y | `S` / `A` | X / Y |
| START / SELECT | `Enter` / `Shift` | START / SELECT |
| L / R | `Q` / `E` | Shoulder |
| Pantalla completa | BotÃ³n â›¶ (esquina superior derecha) | â€” |

El mando virtual aparece automÃ¡ticamente en dispositivos tÃ¡ctiles.

---

## 7. SoluciÃ³n de problemas

| SÃ­ntoma | Causa y soluciÃ³n |
|---|---|
| 404 al cargar la ROM | El archivo no estÃ¡ en la carpeta del proyecto o el nombre no coincide con `EJS_gameUrl` |
| `Network Error` durante la carga | La ROM supera el tamaÃ±o o el tiempo de descarga del hosting: probÃ¡ con un `.zip` (38 MB) o servila desde un CDN externo |
| El juego no arranca | VerificÃ¡ en *Network* que la respuesta sea binaria y no una pÃ¡gina HTML de error del hosting |
| Pantalla negra | ProbÃ¡ `EJS_core = 'desmume'`; o activÃ¡ la aceleraciÃ³n por hardware en el navegador |
| `WebAssembly.instantiate(): expected magic word` | Apache sirve `.wasm` como `application/octet-stream`: revisÃ¡ el `AddType application/wasm .wasm` |
| `Access to fetch â€¦ blocked by CORS` | La ROM debe cargarse por HTTP desde el mismo origen, nunca como `file://`; si estÃ¡ en otro host, ese host debe enviar `Access-Control-Allow-Origin` |
| El juego va lento | `EJS_threads = true` + cabeceras `Cross-Origin-Opener-Policy` y `Cross-Origin-Embedder-Policy` |
| Sin audio | HacÃ© clic dentro del emulador: el navegador requiere un gesto del usuario |
| En Render no carga la ROM | La ROM debe estar en el **Publish Path** del Static Site (la raÃ­z del repo): ver [secciÃ³n 8.1](#8-trabajar-con-git) |

---

## 7. Trabajar con Git

La ROM **cruda** (`.nds`, 64 MiB) estÃ¡ fuera del repositorio mediante `.gitignore`:

```gitignore
*.nds      # no se versiona
!*.rar     # un comprimido sÃ­ se puede versionar
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

### 8.1 Â¿Y si quiero desplegar en Render/GitHub Pages?

El sitio desplegado necesita la ROM **dentro del repo**, porque `EJS_gameUrl` es una
ruta relativa. Hay tres caminos:

| OpciÃ³n | CÃ³mo | Peso en el repo | Notas |
|---|---|---|---|
| **A. Forzar la ROM cruda** | `git add -f tomodachi.nds` | 64 MiB | GitHub lo permite (lÃ­mite 100 MB) y Render lo sirve tal cual |
| **B. ROM comprimida** | `tomodachi.zip` + `EJS_gameUrl = 'tomodachi.zip'` | ~38 MB | El navegador la descomprime sola (`extractzip.js` del CDN) |
| **C. ROM en un host externo** | `EJS_gameUrl = 'https://.../tomodachi.nds'` | 0 | Requiere que ese host envÃ­e `Access-Control-Allow-Origin` |

Para la opciÃ³n **A**:

```powershell
git add -f tomodachi.nds
git commit -m "Versiona la ROM para el despliegue"
git push
```

Y si en algÃºn momento la querÃ©s sacar del Ã­ndice (sin borrarla del disco):

```powershell
git rm --cached tomodachi.nds
git commit -m "Deja de versionar la ROM cruda"
```

---

## 8. Desplegar en hosting compartido (InfinityFree)

### 8.1 El problema tÃ­pico: "Error de Red"

En InfinityFree el emulador suele mostrar **â€œError de Redâ€** (`Network Error`). No es un
fallo del emulador: en el cÃ³digo de EmulatorJS ese mensaje aparece **Ãºnicamente** cuando
la peticiÃ³n de la ROM falla:

```js
// emulator.min.js
const t = await this.downloadFile(this.config.gameUrl, ..., {responseType:"arraybuffer"});
if (-1 === t) return void this.startGameError(this.localization("Network Error"));
```

`downloadFile` devuelve `-1` si la respuesta es **4xx/5xx** o si la conexiÃ³n falla. Si
fallara el nÃºcleo, el mensaje serÃ­a otro (â€œError downloading core (melonds-wasm.data)â€).

Motivos habituales en InfinityFree:

| Causa | Detalle |
|---|---|
| **La ROM no llegÃ³ al servidor** | El plan gratuito limita el tamaÃ±o de cada archivo subido (~10 MB) y `tomodachi.nds` pesa **64 MiB** â†’ 404 |
| **nginx bloquea el binario** | InfinityFree sirve detrÃ¡s de **nginx** (`Server: nginx`), que puede interceptar la descarga directa de estÃ¡ticos â†’ 403 o pÃ¡gina HTML |
| **Tiempo de espera** | Transferir 64 MiB por un hosting compartido puede cortarse |

### 9.2 CÃ³mo diagnosticarlo en 20 segundos

1. AbrÃ­ en el navegador: `https://TU-SITIO/tomodachi.nds`
2. O usÃ¡ PowerShell (reemplazÃ¡ la URL):

```powershell
try {
  $r = Invoke-WebRequest "https://TU-SITIO/tomodachi.nds" -Method Head -UseBasicParsing
  "status=$($r.StatusCode) type=$($r.Headers['Content-Type']) len=$($r.Headers['Content-Length'])"
} catch {
  "HTTP $([int]$_.Exception.Response.StatusCode)  ->  $($_.Exception.Message)"
}
```

| Resultado | SoluciÃ³n |
|---|---|
| `404` | La ROM no estÃ¡ en el servidor â†’ opciÃ³n **C** (hosting externo) |
| `403` o devuelve HTML | nginx bloquea el binario â†’ opciÃ³n **A** (`rom.php`) |
| `200` con `Content-Length: 67108864` y `application/octet-stream` | Todo bien â†’ recargÃ¡ con **Ctrl+F5** (cachÃ© del navegador) |

### 8.3 Opciones de despliegue

#### A) Proxy PHP `rom.php` (incluido en el repo)

`rom.php` lee la ROM desde disco y la entrega con las cabeceras correctas
(`application/octet-stream`, `Content-Length`, `Access-Control-Allow-Origin: *`),
esquivando el bloqueo de nginx y el `Content-Type` incorrecto.

```js
window.EJS_gameUrl = 'rom.php';
```

SubÃ­ `rom.php` a la misma carpeta que `index.html` y la ROM al servidor (por FTP con
FileZilla, que permite subir archivos grandes en varios segmentos).

#### B) Hosting externo de la ROM (si no se puede subir el `.nds`)

`raw.githubusercontent.com` envÃ­a `Access-Control-Allow-Origin: *`, asÃ­ que sirve:

```js
window.EJS_gameUrl = 'https://raw.githubusercontent.com/medransfinformatica/tomodachiweb/main/tomodachi.nds';
```

(subÃ­ la ROM al repo con `git add -f tomodachi.nds`).

#### C) Render (recomendado si la ROM es grande)

Render no tiene lÃ­mite de tamaÃ±o por archivo y sirve el repo completo. Deploy del repo
como **Static Site** (Build Command vacÃ­o, Publish Path `/`) y apuntÃ¡ `EJS_gameUrl` a
`https://TU-SITIO.onrender.com/tomodachi.nds`.

### 9.4 Fallback automÃ¡tico (ya implementado)

`index.html` prueba las URLs de la ROM con un `HEAD` antes de iniciar y usa la primera
que responda `200`, asÃ­ el emulador no muere si la principal no estÃ¡ disponible:

```js
window.EJS_romUrls = [
  'tomodachi.nds',      // 1) archivo local
  'rom.php',            // 2) proxy PHP
  'https://raw.githubusercontent.com/.../tomodachi.nds'  // 3) repo GitHub
];
```

En la consola del navegador se ve la decisiÃ³n:

```
[ROM] tomodachi.nds -> HTTP 404
[ROM] "tomodachi.nds" no responde. Uso el fallback: rom.php
```

> âš ï¸ `EJS_romUrls` estÃ¡ pensado para **tÃº y tu equipo**. Si publicÃ¡s el sitio con el
> repo pÃºblico, recordÃ¡ que la URL de GitHub expone la ROM a cualquiera que la abra.

---

## 9. Desplegar en Render (Static Site)

1. SubÃ­ el repo a GitHub (`git push -u origin main`).
2. En <https://dashboard.render.com/> â†’ **New â†’ Static Site**.
3. **Connect** con GitHub y elegÃ­ el repositorio `tomodachiweb`.
4. ConfiguraciÃ³n:
   - **Branch**: `main`
   - **Build Command**: *(vacÃ­o â€” no hace falta compilar nada)*
   - **Publish Path**: `/` (o `.`)
   - **Environment**: `Production`
5. **Create Static Site**. Render despliega y te da una URL `https://<nombre>.onrender.com`.
6. Cada `git push` a `main` redespliega automÃ¡ticamente.

Notas:

- El `.htaccess` se sube al repo pero **Render lo ignora** (no usa Apache); no molesta.
- El sitio es estÃ¡tico: `index.html` en la raÃ­z se sirve como `/`.
- El nÃºcleo y el descompresor siguen viniendo del CDN de EmulatorJS, asÃ­ que no hace
  falta subir nada mÃ¡s.
- PodÃ©s agregar cabeceras HTTP desde *Settings â†’ Headers* en el dashboard.

---

## 10. Aviso legal

`Tomodachi Collection` es una obra de Nintendo / Intelligent Systems. Este repositorio
publica el **frontend del emulador** (HTML, CSS, `.htaccess`) y el archivo de ROM
**comprimido** que el usuario aporta al clonarlo. Cada usuario es responsable de su
copia y de cumplir la legislaciÃ³n de su paÃ­s; publicar este repositorio no concede
ningÃºn derecho sobre la obra original.

Este proyecto no se asocia ni responde por Nintendo, Intelligent Systems, EmulatorJS
ni RetroArch.
