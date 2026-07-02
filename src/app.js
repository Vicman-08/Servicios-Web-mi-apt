const express = require('express');
const cors = require('cors');

const authRoutes = require('./routes/auth.routes');
const userRoutes = require('./routes/user.routes');

const app = express();

// Middlewares globales
app.use(cors()); // habilita CORS para todos los orígenes
app.use(express.json());

// Rutas
app.use('/api/auth', authRoutes);
app.use('/api/users', userRoutes);

// Ruta de salud
app.get('/', (req, res) => {
  res.json({ mensaje: 'API de usuarios funcionando' });
});

// 404
app.use((req, res) => {
  res.status(404).json({ mensaje: 'Ruta no encontrada' });
});

module.exports = app;
