// Permite el acceso solo si el rol del usuario está en la lista permitida
const permitirRoles = (...rolesPermitidos) => {
  return (req, res, next) => {
    // req.usuario viene del auth.middleware (datos del token)
    if (!req.usuario || !rolesPermitidos.includes(req.usuario.rol)) {
      return res.status(403).json({
        mensaje: 'Acceso denegado: no tienes permisos suficientes',
      });
    }
    next();
  };
};

module.exports = permitirRoles;
