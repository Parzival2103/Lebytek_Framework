# Despliegue en hosting compartido (Apache)

> **Instalación y versionado:** el flujo de instalación (wizard web, CLI con tracking, página de estado y manifiestos de módulo) está documentado en [`instalacion-y-versionado.md`](instalacion-y-versionado.md).

La aplicación debe exponerse **solo** desde la carpeta [`public/`](../../public): ahí está el front controller (`index.php`) y los assets (`assets/`).

## 1. Directorio raíz del dominio (document root)

En el panel del hosting (cPanel, Plesk, DirectAdmin, etc.):

1. Abre la configuración del dominio o subdominio.
2. Establece el **directorio raíz del documento** / **document root** / **carpeta pública** apuntando a la carpeta **`public`** dentro del proyecto (no a la raíz del repositorio).

Ejemplo de ruta en el servidor:

`.../home/usuario/domains/kidslifeupmty.com/public_html/contraste/public`

Si el document root queda **por encima** de `public/` (por ejemplo en `.../contraste`), entonces:

- `https://tudominio.com/` puede mostrar listado de carpetas o no servir la app.
- Las rutas absolutas `/login`, `/admin/...` resuelven a la raíz del dominio y **no** pasan por `public/index.php`.
- Con `APP_URL` sin `/public`, los CSS se piden como `/assets/...` en la raíz del sitio; si no existe esa carpeta allí, **no cargan estilos**.

## 2. Variables de entorno (`.env` en el servidor)

Con el document root en `public/`:

- `APP_URL=https://tudominio.com` — **sin** sufijo `/public`.
- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_SECURE=true` si sirves solo por HTTPS.

Regenera o copia `APP_KEY` de forma segura en producción.

## 3. Comprobaciones

Tras el despliegue:

1. Abre `https://tudominio.com/login` (sin `/public`) y verifica que carga el mismo login que en local.
2. Los estilos deben cargar desde `https://tudominio.com/assets/...`.
3. Tras iniciar sesión, debe abrirse `https://tudominio.com/admin/dashboard`.

### Comprobación local (sin `/public` en la URL)

Con el servidor apuntando a `public/`:

```bash
php -S localhost:8000 -t public
```

En otra terminal, con la base de datos accesible según tu `.env`:

- `GET /assets/css/app.css` debe responder **200** (el archivo existe bajo `public/assets/`).
- `GET /admin/dashboard` sin cookie de sesión debe responder **302** con `Location` hacia `/login` (protección de rutas admin).
- `GET /login` debe responder **200** con el HTML del login (si la app puede conectar a la BD; el login usa configuración de empresa al renderizar).

## 4. Si no puedes cambiar el document root

Opciones (menos recomendadas): reglas de reescritura en la raíz del sitio para enrutar todo a `public/index.php`, o un refactor amplio para usar `ViewHelper::url()` en formularios, enlaces y redirecciones con `APP_URL` que incluya el subdirectorio base.
