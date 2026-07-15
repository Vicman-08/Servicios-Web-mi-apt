# Servicios Web Subarg

API REST y panel web de demostración construido con Laravel 13, MongoDB y Sanctum. El proyecto permite probar un CRUD completo, autenticación por token y una experiencia tipo tienda: observador público, cliente registrado y administrador.

## Funciones incluidas

- CRUD de productos con `GET`, `POST`, `PATCH/PUT` y `DELETE`.
- CRUD de usuarios exclusivo para administradores.
- Catálogo público para observadores sin cuenta.
- Registro público que crea siempre una cuenta de cliente, nunca de administrador.
- Inicio y cierre de sesión mediante tokens almacenados en MongoDB.
- Tokens con duración de 5 minutos, contador visible y cierre automático de sesión.
- Compra y cancelación de productos con actualización segura de existencias.
- Historial de compras y movimientos de inventario.
- Interfaz gráfica adaptable a computadora y celular.
- Inspector visual que muestra el método HTTP, la ruta y el código de respuesta de cada acción.

## Colecciones de MongoDB

- `users`
- `products`
- `orders`
- `inventory_movements`
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

## Rutas principales

| Método | Ruta | Uso | Permiso |
|---|---|---|---|
| `POST` | `/api/login` | Iniciar sesión | Público |
| `POST` | `/api/register` | Registrar cuenta de cliente | Público |
| `GET` | `/api/products` | Listar productos | Público |
| `GET` | `/api/products/{id}` | Consultar producto | Público |
| `POST` | `/api/products` | Crear producto | Administrador |
| `PATCH/PUT` | `/api/products/{id}` | Actualizar producto | Administrador |
| `DELETE` | `/api/products/{id}` | Eliminar producto | Administrador |
| `GET/POST/PATCH/DELETE` | `/api/users` | CRUD de usuarios | Administrador |
| `GET/POST` | `/api/orders` | Consultar o crear compras | Cliente y administrador |
| `DELETE` | `/api/orders/{id}` | Cancelar compra | Propietario o administrador |

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

Cubren registro automático como cliente, inicio de sesión real con token MongoDB, catálogo público del observador, restricciones de compra, CRUD de productos, CRUD de usuarios, compra, inventario insuficiente y cancelación.
