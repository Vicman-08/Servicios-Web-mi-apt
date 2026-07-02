# API REST de Usuarios (Express + Mongoose + JWT)

CRUD completo de usuarios con autenticación JWT y CORS.

## Estructura del proyecto

```
api-usuarios/
├── server.js                 # Punto de entrada (arranca DB + servidor)
├── .env.example              # Variables de entorno de ejemplo
├── .gitignore
├── package.json
└── src/
    ├── app.js                # Configuración de Express, CORS y montaje de rutas
    ├── config/
    │   └── db.js             # Conexión a MongoDB con Mongoose
    ├── models/
    │   └── User.js           # Esquema Mongoose (usuario, password, rol) + hash
    ├── middlewares/
    │   └── auth.middleware.js# Verificación del token JWT
    ├── controllers/
    │   ├── auth.controller.js# login / register
    │   └── user.controller.js# CRUD principal
    └── routes/
        ├── auth.routes.js
        └── user.routes.js
```

## Uso local

```bash
npm install
cp .env.example .env      # edita JWT_SECRET y MONGO_URI
npm run dev               # o: npm start
```

## Endpoints

| Método | Ruta                | Protegida | Descripción                     |
|--------|---------------------|-----------|---------------------------------|
| POST   | /api/auth/register  | No        | Crea usuario y devuelve token   |
| POST   | /api/auth/login     | No        | Devuelve el JWT                 |
| GET    | /api/users          | Sí        | Lista todos los usuarios        |
| GET    | /api/users/:id      | Sí        | Un usuario por id               |
| POST   | /api/users          | Sí        | Crea un usuario                 |
| PUT    | /api/users/:id      | Sí        | Actualiza un usuario            |
| DELETE | /api/users/:id      | Sí        | Elimina un usuario              |

Las rutas protegidas requieren el header:
`Authorization: Bearer <token>`

### Ejemplos con curl

```bash
# 1. Crear el primer usuario
curl -X POST http://localhost:3000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"usuario":"admin","password":"123456","rol":"admin"}'

# 2. Login (guarda el token)
curl -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"usuario":"admin","password":"123456"}'

# 3. Listar usuarios (usando el token)
curl http://localhost:3000/api/users \
  -H "Authorization: Bearer TU_TOKEN_AQUI"
```

---

## Deploy en Ubuntu Server (solo CLI)

Estos pasos asumen Ubuntu 22.04/24.04 con un usuario con permisos `sudo`.

### 1. Actualizar el sistema

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Instalar Node.js (LTS vía NodeSource)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

### 3. Instalar Git

```bash
sudo apt install -y git
```

### 4. Instalar MongoDB Community (7.0)

```bash
# Importar la clave GPG oficial
curl -fsSL https://www.mongodb.org/static/pgp/server-7.0.asc | \
  sudo gpg -o /usr/share/keyrings/mongodb-server-7.0.gpg --dearmor

# Añadir el repositorio (para Ubuntu 22.04 "jammy"; usa "noble" si es 24.04)
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | \
  sudo tee /etc/apt/sources.list.d/mongodb-org-7.0.list

sudo apt update
sudo apt install -y mongodb-org

# Arrancar y habilitar el servicio
sudo systemctl start mongod
sudo systemctl enable mongod
sudo systemctl status mongod   # verificar que esté "active (running)"
```

### 5. Clonar el proyecto desde GitHub

```bash
# Reemplaza por la URL de tu repositorio
git clone https://github.com/TU_USUARIO/api-usuarios.git
cd api-usuarios
```

> Si el repo es privado, genera un Personal Access Token en GitHub
> (Settings > Developer settings > Tokens) y úsalo como contraseña,
> o configura una clave SSH.

### 6. Instalar dependencias y crear el .env

```bash
npm install --omit=dev

# Crear el archivo de entorno en el servidor
nano .env
```

Contenido del `.env` en producción:

```
PORT=3000
MONGO_URI=mongodb://127.0.0.1:27017/api_usuarios
JWT_SECRET=una_cadena_larga_y_secreta_generada_al_azar
JWT_EXPIRES_IN=1d
```

Puedes generar un secreto seguro con:

```bash
node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"
```

### 7. Ejecutar con PM2 (proceso persistente)

```bash
sudo npm install -g pm2

pm2 start server.js --name api-usuarios
pm2 save                      # guarda la lista de procesos
pm2 startup                   # muestra un comando: cópialo y ejecútalo
# ^ ese comando hace que PM2 arranque solo al reiniciar el servidor

pm2 logs api-usuarios         # ver logs
pm2 restart api-usuarios      # reiniciar tras un cambio
```

### 8. Abrir el puerto en el firewall

```bash
sudo ufw allow 3000/tcp
sudo ufw enable               # si aún no está activo
sudo ufw status
```

Con esto la API queda disponible en `http://IP_DEL_SERVIDOR:3000`.

### 9. (Opcional) Nginx como reverse proxy en el puerto 80

```bash
sudo apt install -y nginx
sudo nano /etc/nginx/sites-available/api-usuarios
```

Contenido:

```nginx
server {
    listen 80;
    server_name tu_dominio_o_ip;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

Activar y recargar:

```bash
sudo ln -s /etc/nginx/sites-available/api-usuarios /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo ufw allow 'Nginx Full'
```

### 10. Actualizar el código en el futuro

```bash
cd api-usuarios
git pull origin main
npm install --omit=dev
pm2 restart api-usuarios
```
