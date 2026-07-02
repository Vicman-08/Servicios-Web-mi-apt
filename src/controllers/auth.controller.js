const jwt = require('jsonwebtoken');
const User = require('../models/User');

const generarToken = (user) =>
  jwt.sign(
    { id: user._id, usuario: user.usuario, rol: user.rol },
    process.env.JWT_SECRET,
    { expiresIn: process.env.JWT_EXPIRES_IN || '1d' }
  );

// POST /api/auth/register
// Sirve para crear el primer usuario sin necesitar token todavía.
const register = async (req, res) => {
  try {
    const { usuario, password, rol } = req.body;

    if (!usuario || !password) {
      return res.status(400).json({ mensaje: 'usuario y password son obligatorios' });
    }

    const existe = await User.findOne({ usuario });
    if (existe) {
      return res.status(400).json({ mensaje: 'El usuario ya existe' });
    }

    const nuevoUsuario = new User({ usuario, password, rol });
    await nuevoUsuario.save();

    const token = generarToken(nuevoUsuario);
    res.status(201).json({ usuario: nuevoUsuario, token });
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

// POST /api/auth/login  ->  devuelve el token
const login = async (req, res) => {
  try {
    const { usuario, password } = req.body;

    if (!usuario || !password) {
      return res.status(400).json({ mensaje: 'usuario y password son obligatorios' });
    }

    const user = await User.findOne({ usuario });
    if (!user) {
      return res.status(401).json({ mensaje: 'Credenciales inválidas' });
    }

    const passwordValido = await user.compararPassword(password);
    if (!passwordValido) {
      return res.status(401).json({ mensaje: 'Credenciales inválidas' });
    }

    const token = generarToken(user);
    res.json({ token, usuario: user });
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

module.exports = { register, login };
