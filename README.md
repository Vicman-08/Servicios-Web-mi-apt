# Servicios Web Subarg

API REST y panel web de demostración construido con Laravel 13, MongoDB y Sanctum. El proyecto permite probar un CRUD completo, autenticación por token y una experiencia tipo tienda: observador público, cliente registrado y administrador.

La API pública actual está versionada bajo el prefijo `/api/v1`. Las respuestas de recursos usan la propiedad `data`; los listados incluyen también `links` y `meta` para la paginación.

## Funciones incluidas

- CRUD de productos con `GET`, `POST`, `PATCH/PUT` y `DELETE`.
- CRUD de usuarios exclusivo para administradores.
- Catálogo público para observadores sin cuenta.
- Registro público que crea siempre una cuenta de cliente, nunca de administrador.
- Inicio y cierre de sesión mediante tokens almacenados en MongoDB.
- Tokens con duración de 5 minutos, contador visible y cierre automático de sesión.
- Carrito persistente por cliente: agregar, cambiar cantidades, eliminar y vaciar.
- Checkout de varios productos con cálculo de subtotal y total.
- Compra y cancelación de órdenes con actualización segura de existencias.
- Reversión automática del inventario si algún producto del carrito ya no tiene existencias.
- Historial de compras y movimientos de inventario.
- Respuestas JSON uniformes y listados paginados.
- Perfil de cliente preparado para teléfono y direcciones.
- Modelo documental implementado para categorías y carrito; preparado para interacciones con IA.
- CRUD administrativo de categorías y consulta pública de categorías activas.
- Filtros administrativos de productos, usuarios, compras e inventario.
- Cambios controlados del estado de las órdenes.
- Ajustes de existencias con historial y protección contra inventario negativo.
- Dashboard administrativo con usuarios, catálogo, compras, ingresos e inventario.
- Recomendaciones inteligentes del catálogo mediante la API externa de OpenAI.
- Historial de consultas de IA en MongoDB, disponible para el administrador.
- Consola web separada en `/admin`, exclusiva para cuentas administrativas.
- Gestión visual de productos, categorías, usuarios, órdenes, inventario y actividad de IA.
- Interfaz gráfica adaptable a computadora y celular.

## Colecciones de MongoDB

- `users`
- `categories`
- `products`
- `carts`
- `orders`
- `inventory_movements`
- `ai_interactions`
- `personal_access_tokens`

Las migraciones también crean índices únicos, validaciones de documentos y las colecciones auxiliares de Laravel.

## Preparación inicial

Se necesita PHP 8.3 o posterior, Composer, Node.js, la extensión `mongodb` de PHP y un servidor MongoDB local.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Para activar las recomendaciones externas, agrega una clave de API únicamente en el `.env` del servidor:

```dotenv
OPENAI_API_KEY=tu_clave_del_proyecto
OPENAI_MODEL=gpt-5.6-luna
```

Después limpia la configuración almacenada:

```bash
php artisan config:clear
```

La clave nunca debe escribirse en JavaScript, subirse a GitHub ni incluirse dentro de la aplicación móvil.

> `migrate:fresh` elimina los datos anteriores de la base configurada. Debe utilizarse solamente para preparar o reiniciar la demostración.

## Abrirlo desde otra computadora

En la computadora donde está el proyecto, inicia MongoDB y después ejecuta:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Consulta la dirección IP local de esa computadora:

- macOS: `ipconfig getifaddr en0`
- Windows: `ipconfig`
- Linux: `hostname -I`

Si la dirección obtenida fuera `192.168.1.25`, desde la otra laptop abre:

```text
http://192.168.1.25:8000
```

Las dos computadoras deben estar conectadas a la misma red. Si el sistema pregunta si PHP puede aceptar conexiones entrantes, hay que permitirlo en la red privada.

MongoDB no necesita exponerse a la red: solamente la computadora que ejecuta Laravel se conecta a `127.0.0.1:27017`.

## Accesos y registro

| Rol | Correo | Contraseña | Permisos |
|---|---|---|---|
| Administrador | `admin@subarg.test` | `password123` | CRUD de productos y usuarios; consulta y cancela compras |
| Observador | No necesita cuenta | No necesita contraseña | Consulta el catálogo público |

Los clientes se crean desde el botón **Crear cuenta**. Después del registro pueden comprar y cancelar sus propias compras.

La tienda pública se abre en `/`. La consola administrativa se abre por separado en:

```text
http://DIRECCION_DEL_SERVIDOR:8000/admin
```

Si se intenta entrar a esa consola con una cuenta de cliente, el acceso se rechaza. Aunque la página de inicio de sesión es pública, todos sus datos y operaciones permanecen protegidos por token y por el permiso `admin-access` del backend.

## Rutas principales

| Método | Ruta | Uso | Permiso |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | Iniciar sesión | Público |
| `POST` | `/api/v1/auth/register` | Registrar cuenta de cliente | Público |
| `POST` | `/api/v1/auth/logout` | Cerrar la sesión actual | Autenticado |
| `GET` | `/admin` | Abrir la consola web administrativa | Inicio de sesión administrativo |
| `GET/PATCH` | `/api/v1/me` | Consultar o actualizar perfil | Autenticado |
| `GET` | `/api/v1/products` | Listar productos activos y paginados | Público |
| `GET` | `/api/v1/products/{id}` | Consultar producto | Público |
| `GET` | `/api/v1/categories` | Listar categorías activas | Público |
| `GET` | `/api/v1/categories/{id}` | Consultar categoría activa | Público |
| `POST` | `/api/v1/ai/recommendations` | Obtener recomendaciones con OpenAI | Público, máximo 10 solicitudes/minuto |
| `GET` | `/api/v1/admin/dashboard` | Consultar estadísticas generales | Administrador |
| `GET` | `/api/v1/admin/ai-interactions` | Consultar historial de IA | Administrador |
| `GET/POST/PATCH/DELETE` | `/api/v1/admin/categories` | CRUD de categorías | Administrador |
| `GET` | `/api/v1/admin/products` | Listar productos activos e inactivos | Administrador |
| `POST` | `/api/v1/admin/products` | Crear producto | Administrador |
| `PATCH/PUT` | `/api/v1/admin/products/{id}` | Actualizar producto | Administrador |
| `DELETE` | `/api/v1/admin/products/{id}` | Eliminar producto | Administrador |
| `GET/POST/PATCH/DELETE` | `/api/v1/admin/users` | CRUD de usuarios | Administrador |
| `GET` | `/api/v1/admin/orders` | Listar y filtrar todas las compras | Administrador |
| `PATCH` | `/api/v1/admin/orders/{id}/status` | Cambiar el estado de una compra | Administrador |
| `GET` | `/api/v1/admin/inventory-movements` | Consultar movimientos de inventario | Administrador |
| `POST` | `/api/v1/admin/inventory-adjustments` | Ajustar existencias | Administrador |
| `GET` | `/api/v1/cart` | Consultar carrito y totales | Cliente y administrador |
| `POST` | `/api/v1/cart/items` | Agregar un producto | Cliente y administrador |
| `PATCH` | `/api/v1/cart/items/{productId}` | Cambiar una cantidad | Cliente y administrador |
| `DELETE` | `/api/v1/cart/items/{productId}` | Quitar un producto | Cliente y administrador |
| `DELETE` | `/api/v1/cart` | Vaciar carrito | Cliente y administrador |
| `POST` | `/api/v1/checkout` | Confirmar carrito y crear una compra | Cliente y administrador |
| `GET` | `/api/v1/orders` | Consultar compras propias | Cliente y administrador |
| `DELETE` | `/api/v1/orders/{id}` | Cancelar compra | Propietario o administrador |

Las rutas protegidas reciben el token en el encabezado:

```text
Authorization: Bearer TOKEN
Accept: application/json
```

## Pruebas automáticas

Las pruebas utilizan la base separada `servicios_web_subarg_test`:

```bash
php artisan test
```

Cubren registro automático como cliente, inicio de sesión real con token MongoDB, catálogo público paginado, restricciones por rol, actualización parcial, CRUD de productos, usuarios y categorías, carrito, checkout multiproducto, inventario insuficiente, cancelación, estados de órdenes, ajustes de inventario, dashboard, recomendaciones externas simuladas e historial de interacciones de IA.
