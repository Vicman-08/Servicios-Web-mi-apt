<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Panel de demostración de CRUD, MongoDB y permisos por rol.">
        <title>Catálogo de productos</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="toast" class="toast" role="status" aria-live="polite"></div>

        <main id="login-view" class="auth-page">
            <section class="auth-card" aria-labelledby="login-title">
                <div>
                    <h2 id="login-title">Iniciar sesión</h2>
                    <p id="auth-description" class="muted">Entra como cliente o utiliza la cuenta administrativa.</p>
                </div>

                <div class="auth-switch" aria-label="Elegir acceso o registro">
                    <button id="show-login-button" class="auth-switch-button active" type="button">Iniciar sesión</button>
                    <button id="show-register-button" class="auth-switch-button" type="button">Crear cuenta</button>
                </div>

                <form id="login-form" class="stack-form">
                    <label>
                        Correo
                        <input id="login-email" type="email" autocomplete="username" required placeholder="correo@ejemplo.com">
                    </label>
                    <label>
                        Contraseña
                        <input id="login-password" type="password" autocomplete="current-password" required placeholder="Tu contraseña">
                    </label>
                    <button class="primary-button" type="submit">Entrar al panel</button>
                </form>

                <form id="register-form" class="stack-form" hidden>
                    <label>
                        Nombre
                        <input id="register-name" type="text" autocomplete="name" minlength="2" maxlength="100" required placeholder="Tu nombre">
                    </label>
                    <label>
                        Correo
                        <input id="register-email" type="email" autocomplete="email" required placeholder="cliente@ejemplo.com">
                    </label>
                    <label>
                        Contraseña
                        <input id="register-password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required placeholder="Mínimo 8 caracteres">
                    </label>
                    <button class="primary-button" type="submit">Crear cuenta de cliente</button>
                </form>

                <button id="observer-button" class="observer-button" type="button">Volver al catálogo</button>
            </section>
        </main>

        <div id="app-view" class="app-shell" hidden>
            <header class="topbar">
                <h1 class="store-title">Productos</h1>
                <div class="session-box">
                    <span id="session-countdown" class="session-countdown" hidden></span>
                    <button id="top-register-button" class="primary-button compact" type="button" hidden>Crear cuenta</button>
                    <button id="logout-button" class="ghost-button" type="button">Cerrar sesión</button>
                </div>
            </header>

            <main class="dashboard">
                <nav class="tabs" aria-label="Secciones del panel">
                    <button class="tab active" type="button" data-tab="products">Productos</button>
                    <button class="tab" type="button" data-tab="assistant">Asistente IA</button>
                    <button id="cart-tab" class="tab" type="button" data-tab="cart" hidden>Carrito</button>
                    <button id="users-tab" class="tab" type="button" data-tab="users" hidden>Usuarios</button>
                    <button id="orders-tab" class="tab" type="button" data-tab="orders" hidden>Compras</button>
                </nav>

                <section id="products-section" class="tab-section">
                    <div class="section-heading">
                        <div>
                            <h2>Catálogo de productos</h2>
                        </div>
                        <button id="new-product-button" class="primary-button compact admin-only" type="button" hidden>Nuevo producto</button>
                    </div>

                    <form id="product-form" class="editor-card admin-only" hidden>
                        <div class="editor-heading">
                            <div>
                                <h3 id="product-form-title">Crear producto</h3>
                            </div>
                            <button id="cancel-product-button" class="ghost-button" type="button">Cancelar</button>
                        </div>
                        <input id="product-id" type="hidden">
                        <div class="form-grid">
                            <label>SKU<input id="product-sku" required maxlength="40" placeholder="PROD-001"></label>
                            <label>Nombre<input id="product-name" required maxlength="120" placeholder="Nombre del producto"></label>
                            <label>Precio<input id="product-price" type="number" required min="0" step="0.01" placeholder="0.00"></label>
                            <label>Existencia<input id="product-stock" type="number" required min="0" step="1" placeholder="0"></label>
                            <label class="wide">Descripción<textarea id="product-description" maxlength="1000" rows="3" placeholder="Descripción breve"></textarea></label>
                        </div>
                        <button class="primary-button compact" type="submit">Guardar producto</button>
                    </form>

                    <div id="products-grid" class="product-grid" aria-live="polite"></div>
                </section>

                <section id="assistant-section" class="tab-section" hidden>
                    <div class="section-heading">
                        <div>
                            <h2>Recomendaciones inteligentes</h2>
                            <p class="muted">Describe qué necesitas y la IA buscará opciones dentro del catálogo disponible.</p>
                        </div>
                    </div>

                    <form id="ai-form" class="editor-card ai-form">
                        <label for="ai-query">¿Qué producto estás buscando?</label>
                        <textarea id="ai-query" minlength="2" maxlength="500" rows="3" required placeholder="Ejemplo: Necesito un teclado para trabajar y tengo un presupuesto de $1,000"></textarea>
                        <button id="ai-submit-button" class="primary-button" type="submit">Obtener recomendaciones</button>
                    </form>

                    <div id="ai-result" class="ai-result" hidden>
                        <div class="ai-answer-card">
                            <span class="eyebrow">Respuesta de IA</span>
                            <p id="ai-answer"></p>
                            <small id="ai-provider" class="muted"></small>
                        </div>
                        <div id="ai-products" class="product-grid" aria-live="polite"></div>
                    </div>
                </section>

                <section id="cart-section" class="tab-section" hidden>
                    <div class="section-heading">
                        <div>
                            <h2>Tu carrito</h2>
                            <p class="muted">Revisa las cantidades antes de confirmar la compra.</p>
                        </div>
                        <button id="clear-cart-button" class="danger-button" type="button">Vaciar carrito</button>
                    </div>
                    <div id="cart-list" class="orders-list" aria-live="polite"></div>
                    <aside id="cart-summary" class="cart-summary" hidden>
                        <div><span>Productos</span><strong id="cart-item-count">0</strong></div>
                        <div><span>Total</span><strong id="cart-total">$0.00</strong></div>
                        <button id="checkout-button" class="primary-button" type="button">Confirmar compra</button>
                    </aside>
                </section>

                <section id="users-section" class="tab-section" hidden>
                    <div class="section-heading">
                        <div>
                            <h2>Administración de usuarios</h2>
                        </div>
                    </div>

                    <form id="user-form" class="editor-card">
                        <div class="editor-heading">
                            <div><h3>Crear cuenta</h3></div>
                        </div>
                        <div class="form-grid users">
                            <label>Nombre<input id="user-name" required minlength="2"></label>
                            <label>Correo<input id="user-email" type="email" required></label>
                            <label>Contraseña<input id="user-password" type="password" required minlength="8" value="password123"></label>
                            <label>Rol
                                <select id="user-role"><option value="buyer">Cliente</option><option value="admin">Administrador</option></select>
                            </label>
                        </div>
                        <button class="primary-button compact" type="submit">Crear cuenta</button>
                    </form>

                    <div class="table-card">
                        <table>
                            <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody id="users-body"></tbody>
                        </table>
                    </div>
                </section>

                <section id="orders-section" class="tab-section" hidden>
                    <div class="section-heading">
                        <div>
                            <h2>Historial de compras</h2>
                        </div>
                        <button id="refresh-orders-button" class="ghost-button" type="button">Actualizar</button>
                    </div>
                    <div id="orders-list" class="orders-list" aria-live="polite"></div>
                </section>
            </main>
        </div>
    </body>
</html>
