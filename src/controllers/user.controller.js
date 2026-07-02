const User = require('../models/User');

// GET /api/users  ->  listar todos
const obtenerUsuarios = async (req, res) => {
  try {
    const usuarios = await User.find();
    res.json(usuarios);
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

// GET /api/users/:id  ->  obtener uno por id
const obtenerUsuarioPorId = async (req, res) => {
  try {
    const usuario = await User.findById(req.params.id);
    if (!usuario) {
      return res.status(404).json({ mensaje: 'Usuario no encontrado' });
    }
    res.json(usuario);
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

// POST /api/users  ->  crear
const crearUsuario = async (req, res) => {
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
    res.status(201).json(nuevoUsuario);
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

// PUT /api/users/:id  ->  actualizar
const actualizarUsuario = async (req, res) => {
  try {
    const { usuario, password, rol } = req.body;
    const user = await User.findById(req.params.id);

    if (!user) {
      return res.status(404).json({ mensaje: 'Usuario no encontrado' });
    }

    if (usuario !== undefined) user.usuario = usuario;
    if (rol !== undefined) user.rol = rol;
    if (password !== undefined) user.password = password; // se re-hashea en el pre-save

    await user.save();
    res.json(user);
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

// DELETE /api/users/:id  ->  eliminar
const eliminarUsuario = async (req, res) => {
  try {
    const usuario = await User.findByIdAndDelete(req.params.id);
    if (!usuario) {
      return res.status(404).json({ mensaje: 'Usuario no encontrado' });
    }
    res.json({ mensaje: 'Usuario eliminado correctamente' });
  } catch (error) {
    res.status(500).json({ mensaje: 'Error en el servidor', error: error.message });
  }
};

module.exports = {
  obtenerUsuarios,
  obtenerUsuarioPorId,
  crearUsuario,
  actualizarUsuario,
  eliminarUsuario,
};
