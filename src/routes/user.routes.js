const express = require('express');
const router = express.Router();
const verificarToken = require('../middlewares/auth.middleware');
const permitirRoles = require('../middlewares/role.middleware');
const {
  obtenerUsuarios,
  obtenerUsuarioPorId,
  crearUsuario,
  actualizarUsuario,
  eliminarUsuario,
} = require('../controllers/user.controller');

// Todas requieren estar logueado (token válido)
router.use(verificarToken);

// VER: cualquier usuario autenticado (admin o user)
router.get('/', obtenerUsuarios);
router.get('/:id', obtenerUsuarioPorId);

// CREAR, MODIFICAR, BORRAR: solo admin
router.post('/', permitirRoles('admin'), crearUsuario);
router.put('/:id', permitirRoles('admin'), actualizarUsuario);
router.delete('/:id', permitirRoles('admin'), eliminarUsuario);

module.exports = router;
