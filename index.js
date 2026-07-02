// index.js — API básica de usuarios SIN base de datos
// Los datos se guardan en un arreglo en memoria.
// OJO: se reinician cada vez que reinicies el servidor (es lo esperado en esta práctica).

const express = require('express');
const cors = require('cors');

const app = express();
const PORT = process.env.PORT || 3000;

// ---------- Middlewares ----------
app.use(cors());          // permite peticiones desde otros orígenes (tu frontend / Postman)
app.use(express.json());  // permite leer JSON enviado en el body de las peticiones

// ---------- "Base de datos" temporal en memoria ----------
let usuarios = [
  { id: 1, usuario: 'victor', password: '1234', rol: 'admin' },
  { id: 2, usuario: 'mario',  password: 'abcd', rol: 'usuario' },
  { id: 3, usuario: 'ana',    password: 'pass', rol: 'editor' },
];

let siguienteId = 4; // para asignar id a los nuevos usuarios

// ---------- Ruta raíz (comprobar que el servidor responde) ----------
app.get('/', (req, res) => {
  res.json({ mensaje: 'API de usuarios funcionando' });
});

// ---------- GET sin parámetro: listar todos ----------
app.get('/usuarios', (req, res) => {
  res.json(usuarios);
});

// ---------- GET con parámetro: un usuario por id ----------
app.get('/usuarios/:id', (req, res) => {
  const id = Number(req.params.id);
  const usuario = usuarios.find(u => u.id === id);
  if (!usuario) {
    return res.status(404).json({ error: 'Usuario no encontrado' });
  }
  res.json(usuario);
});

// ---------- POST: crear un usuario ----------
app.post('/usuarios', (req, res) => {
  const { usuario, password, rol } = req.body;
  if (!usuario || !password || !rol) {
    return res.status(400).json({ error: 'Faltan datos: usuario, password y rol son obligatorios' });
  }
  const nuevo = { id: siguienteId++, usuario, password, rol };
  usuarios.push(nuevo);
  res.status(201).json(nuevo); // 201 = creado
});

// ---------- PUT con parámetro: actualizar un usuario por id ----------
app.put('/usuarios/:id', (req, res) => {
  const id = Number(req.params.id);
  const usuario = usuarios.find(u => u.id === id);
  if (!usuario) {
    return res.status(404).json({ error: 'Usuario no encontrado' });
  }
  const { usuario: nuevoUsuario, password, rol } = req.body;
  if (nuevoUsuario !== undefined) usuario.usuario = nuevoUsuario;
  if (password !== undefined) usuario.password = password;
  if (rol !== undefined) usuario.rol = rol;
  res.json(usuario);
});

// ---------- DELETE con parámetro: eliminar un usuario por id ----------
app.delete('/usuarios/:id', (req, res) => {
  const id = Number(req.params.id);
  const indice = usuarios.findIndex(u => u.id === id);
  if (indice === -1) {
    return res.status(404).json({ error: 'Usuario no encontrado' });
  }
  const eliminado = usuarios.splice(indice, 1)[0];
  res.json({ mensaje: 'Usuario eliminado', usuario: eliminado });
});

// ---------- Arrancar el servidor ----------
app.listen(PORT, () => {
  console.log(`Servidor escuchando en el puerto ${PORT}`);
});
