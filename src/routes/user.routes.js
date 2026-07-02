const express = require('express');
const router = express.Router();
const verificarToken = require('../middlewares/auth.middleware');
const {
  obtenerUsuarios,
  obtenerUsuarioPorId,
  crearUsuario,
  actualizarUsuario,
  eliminarUsuario,
} = require('../controllers/user.controller');

// Protege TODAS las rutas de este router con JWT
router.use(verificarToken);

router.get('/', obtenerUsuarios);
router.get('/:id', obtenerUsuarioPorId);
router.post('/', crearUsuario);
router.put('/:id', actualizarUsuario);
router.delete('/:id', eliminarUsuario);

module.exports = router;
