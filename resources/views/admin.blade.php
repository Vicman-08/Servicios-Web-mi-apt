<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Panel web exclusivo para administrar el catálogo, usuarios, órdenes e inventario.">
        <title>Administración · Subarg</title>
        @vite(['resources/css/app.css', 'resources/js/admin.js'])
    </head>
    <body class="admin-page">
        <div id="admin-toast" class="toast" role="status" aria-live="polite"></div>

        <main id="admin-login-view" class="admin-login-page">
            <section class="auth-card" aria-labelledby="admin-login-title">
                <div>
                    <p class="eyebrow">Acceso restringido</p>
                    <h1 id="admin-login-title" class="admin-login-title">Administración</h1>
                    <p class="muted">Esta consola acepta únicamente cuentas con rol de administrador.</p>
                </div>
                <form id="admin-login-form" class="stack-form">
                    <label>Correo<input id="admin-email" type="email" autocomplete="username" required placeholder="admin@subarg.test"></label>
                    <label>Contraseña<input id="admin-password" type="password" autocomplete="current-password" required placeholder="Tu contraseña"></label>
                    <button class="primary-button" type="submit">Entrar a administración</button>
                </form>
                <a class="admin-store-link" href="/">Volver a la tienda</a>
            </section>
        </main>

        <div id="admin-app" class="admin-layout" hidden>
            <aside class="admin-sidebar">
                <div class="admin-brand">
                    <span class="brand-mark small">SW</span>
                    <div><strong>Subarg</strong><small>Administración</small></div>
                </div>
                <nav class="admin-navigation" aria-label="Módulos administrativos">
                    <button class="admin-nav-button active" type="button" data-admin-section="dashboard">Resumen</button>
                    <button class="admin-nav-button" type="button" data-admin-section="products">Productos</button>
                    <button class="admin-nav-button" type="button" data-admin-section="categories">Categorías</button>
                    <button class="admin-nav-button" type="button" data-admin-section="users">Usuarios</button>
                    <button class="admin-nav-button" type="button" data-admin-section="orders">Órdenes</button>
                    <button class="admin-nav-button" type="button" data-admin-section="inventory">Inventario</button>
                    <button class="admin-nav-button" type="button" data-admin-section="ai">Actividad IA</button>
                </nav>
                <a class="admin-store-link sidebar-link" href="/">Ver tienda</a>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <div><p class="eyebrow">Panel web</p><h1 id="admin-section-title">Resumen</h1></div>
                    <div class="session-box">
                        <span id="admin-countdown" class="session-countdown"></span>
                        <span id="admin-user-name" class="muted"></span>
                        <button id="admin-logout-button" class="ghost-button" type="button">Cerrar sesión</button>
                    </div>
                </header>

                <section id="admin-dashboard-section" class="admin-section">
                    <div id="admin-metrics" class="metric-grid" aria-live="polite"></div>
                    <div class="admin-panel">
                        <div class="section-heading"><div><h2>Órdenes recientes</h2><p class="muted">Últimos movimientos comerciales.</p></div><button class="ghost-button" data-go-section="orders" type="button">Ver todas</button></div>
                        <div id="dashboard-orders" class="admin-table-wrap"></div>
                    </div>
                </section>

                <section id="admin-products-section" class="admin-section" hidden>
                    <div class="section-heading"><div><h2>Productos</h2><p class="muted">Catálogo activo e inactivo.</p></div><button id="admin-new-product" class="primary-button compact" type="button">Nuevo producto</button></div>
                    <form id="admin-product-form" class="admin-panel" hidden>
                        <div class="editor-heading"><h3 id="admin-product-form-title">Nuevo producto</h3><button id="admin-cancel-product" class="ghost-button" type="button">Cancelar</button></div>
                        <input id="admin-product-id" type="hidden">
                        <div class="form-grid">
                            <label>SKU<input id="admin-product-sku" maxlength="40" required></label>
                            <label>Nombre<input id="admin-product-name" maxlength="120" required></label>
                            <label>Precio<input id="admin-product-price" type="number" min="0" step="0.01" required></label>
                            <label>Existencia<input id="admin-product-stock" type="number" min="0" step="1" required></label>
                            <label>Categoría<select id="admin-product-category"><option value="">Sin categoría</option></select></label>
                            <label>Estado<select id="admin-product-active"><option value="true">Activo</option><option value="false">Inactivo</option></select></label>
                            <label class="wide">Descripción<textarea id="admin-product-description" rows="3" maxlength="1000"></textarea></label>
                        </div>
                        <button class="primary-button compact" type="submit">Guardar producto</button>
                    </form>
                    <div class="admin-panel admin-table-wrap"><table><thead><tr><th>Producto</th><th>Precio</th><th>Existencia</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="admin-products-body"></tbody></table></div>
                </section>

                <section id="admin-categories-section" class="admin-section" hidden>
                    <div class="section-heading"><div><h2>Categorías</h2><p class="muted">Organización del catálogo público.</p></div></div>
                    <form id="admin-category-form" class="admin-panel inline-editor">
                        <input id="admin-category-id" type="hidden">
                        <label>Nombre<input id="admin-category-name" maxlength="100" required></label>
                        <label>Descripción<input id="admin-category-description" maxlength="500"></label>
                        <label>Estado<select id="admin-category-active"><option value="true">Activa</option><option value="false">Inactiva</option></select></label>
                        <button class="primary-button compact" type="submit">Guardar</button>
                        <button id="admin-cancel-category" class="ghost-button" type="button" hidden>Cancelar</button>
                    </form>
                    <div class="admin-panel admin-table-wrap"><table><thead><tr><th>Categoría</th><th>Slug</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="admin-categories-body"></tbody></table></div>
                </section>

                <section id="admin-users-section" class="admin-section" hidden>
                    <div class="section-heading"><div><h2>Usuarios</h2><p class="muted">Cuentas, roles y permisos.</p></div></div>
                    <form id="admin-user-form" class="admin-panel inline-editor users-editor">
                        <label>Nombre<input id="admin-new-user-name" minlength="2" required></label>
                        <label>Correo<input id="admin-new-user-email" type="email" required></label>
                        <label>Contraseña<input id="admin-new-user-password" type="password" minlength="8" value="password123" required></label>
                        <label>Rol<select id="admin-new-user-role"><option value="buyer">Cliente</option><option value="admin">Administrador</option></select></label>
                        <button class="primary-button compact" type="submit">Crear cuenta</button>
                    </form>
                    <div class="admin-panel admin-table-wrap"><table><thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="admin-users-body"></tbody></table></div>
                </section>

                <section id="admin-orders-section" class="admin-section" hidden>
                    <div class="section-heading"><div><h2>Órdenes</h2><p class="muted">Seguimiento y cambios de estado.</p></div><label class="compact-filter">Estado<select id="admin-order-filter"><option value="">Todos</option><option value="confirmed">Confirmadas</option><option value="completed">Completadas</option><option value="shipped">Enviadas</option><option value="delivered">Entregadas</option><option value="cancelled">Canceladas</option></select></label></div>
                    <div class="admin-panel admin-table-wrap"><table><thead><tr><th>Orden</th><th>Productos</th><th>Total</th><th>Estado</th><th>Actualizar</th></tr></thead><tbody id="admin-orders-body"></tbody></table></div>
                </section>

                <section id="admin-inventory-section" class="admin-section" hidden>
                    <div class="section-heading"><div><h2>Inventario</h2><p class="muted">Ajustes manuales y trazabilidad.</p></div></div>
                    <form id="admin-inventory-form" class="admin-panel inline-editor inventory-editor">
                        <label>Producto<select id="admin-inventory-product" required></select></label>
                        <label>Cambio<input id="admin-inventory-delta" type="number" step="1" required placeholder="Ej. 5 o -2"></label>
                        <label>Motivo<input id="admin-inventory-reason" minlength="3" maxlength="500" required></label>
                        <button class="primary-button compact" type="submit">Aplicar ajuste</button>
                    </form>
                    <div class="admin-panel admin-table-wrap"><table><thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cambio</th><th>Existencia</th><th>Motivo</th></tr></thead><tbody id="admin-inventory-body"></tbody></table></div>
                </section>

                <section id="admin-ai-section" class="admin-section" hidden>
                    <div class="section-heading"><div><h2>Actividad de IA</h2><p class="muted">Consultas externas, duración y resultados.</p></div><label class="compact-filter">Estado<select id="admin-ai-filter"><option value="">Todos</option><option value="success">Correctos</option><option value="error">Errores</option></select></label></div>
                    <div class="admin-panel admin-table-wrap"><table><thead><tr><th>Fecha</th><th>Consulta</th><th>Respuesta</th><th>Proveedor</th><th>Estado</th><th>Duración</th></tr></thead><tbody id="admin-ai-body"></tbody></table></div>
                </section>
            </main>
        </div>
    </body>
</html>
