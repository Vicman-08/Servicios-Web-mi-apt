const state = {
    token: localStorage.getItem('subarg_token'),
    tokenExpiresAt: localStorage.getItem('subarg_token_expires_at'),
    user: null,
    products: [],
    cart: null,
    aiRecommendations: [],
    users: [],
    orders: [],
};

let expiryTimeout;
let countdownInterval;

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function escapeHtml(value = '') {
    return String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);
}

function decimalValue(value) {
    if (value && typeof value === 'object' && '$numberDecimal' in value) return value.$numberDecimal;
    return String(value ?? '0');
}

function money(value, currency = 'MXN') {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency }).format(Number(decimalValue(value)));
}

function showToast(message, type = 'success') {
    const toast = $('#toast');
    toast.textContent = message;
    toast.className = `toast show ${type === 'error' ? 'error' : ''}`;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => { toast.className = 'toast'; }, 3200);
}

async function api(path, options = {}) {
    const method = options.method || 'GET';
    const headers = { Accept: 'application/json', ...(options.headers || {}) };

    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    if (options.body && typeof options.body !== 'string') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }

    const response = await fetch(`/api/v1${path}`, { ...options, method, headers });
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 401 && state.token) {
            expireSession(false);
        }

        const validation = data.errors ? Object.values(data.errors).flat().join(' ') : null;
        throw new Error(response.status === 401 && !state.token
            ? 'Tu sesión expiró. Inicia sesión nuevamente.'
            : validation || data.message || `Error HTTP ${response.status}`);
    }

    return data;
}

function roleName(role) {
    return ({ admin: 'Administrador', buyer: 'Cliente' })[role] || role;
}

function showAuth(mode = 'login') {
    const registering = mode === 'register';
    $('#app-view').hidden = true;
    $('#login-view').hidden = false;
    $('#login-form').hidden = registering;
    $('#register-form').hidden = !registering;
    $('#show-login-button').classList.toggle('active', !registering);
    $('#show-register-button').classList.toggle('active', registering);
    $('#login-title').textContent = registering ? 'Crear cuenta de cliente' : 'Iniciar sesión';
    $('#auth-description').textContent = registering
        ? 'El registro crea automáticamente una cuenta con permiso para comprar.'
        : 'Entra con tu cuenta de cliente o utiliza el acceso administrativo.';
}

function enterObserver() {
    clearSessionTimers();
    state.token = null;
    state.tokenExpiresAt = null;
    state.user = { role: 'observer' };
    localStorage.removeItem('subarg_token');
    localStorage.removeItem('subarg_token_expires_at');
    enterApp();
}

function clearSessionTimers() {
    window.clearTimeout(expiryTimeout);
    window.clearInterval(countdownInterval);
    expiryTimeout = null;
    countdownInterval = null;
}

function expireSession(notify = true) {
    enterObserver();
    if (notify) showToast('Tu sesión de 5 minutos terminó.', 'error');
}

function scheduleSessionExpiry() {
    clearSessionTimers();
    const countdown = $('#session-countdown');

    if (!state.tokenExpiresAt || state.user.role === 'observer') {
        countdown.hidden = true;
        return;
    }

    const expiresAt = new Date(state.tokenExpiresAt).getTime();
    const updateCountdown = () => {
        const remainingSeconds = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = String(remainingSeconds % 60).padStart(2, '0');
        countdown.textContent = `Sesión: ${minutes}:${seconds}`;
    };
    const remainingMilliseconds = expiresAt - Date.now();

    if (remainingMilliseconds <= 0) {
        expireSession();
        return;
    }

    countdown.hidden = false;
    updateCountdown();
    countdownInterval = window.setInterval(updateCountdown, 1000);
    expiryTimeout = window.setTimeout(() => expireSession(), remainingMilliseconds);
}

function enterApp() {
    const role = state.user.role;
    const isAdmin = role === 'admin';
    const isObserver = role === 'observer';
    const canBuy = ['admin', 'buyer'].includes(role);

    $('#login-view').hidden = true;
    $('#app-view').hidden = false;
    $('#logout-button').textContent = isObserver ? 'Iniciar sesión' : 'Cerrar sesión';
    $('#top-register-button').hidden = !isObserver;
    $$('.admin-only').forEach((element) => { element.hidden = !isAdmin; });
    $('#users-tab').hidden = !isAdmin;
    $('#cart-tab').hidden = !canBuy;
    $('#orders-tab').hidden = !canBuy;
    scheduleSessionExpiry();

    selectTab('products');
    loadProducts();
}

async function loadProducts() {
    try {
        const path = state.user.role === 'admin' ? '/admin/products?per_page=50' : '/products?per_page=50';
        const response = await api(path);
        state.products = response.data;
        renderProducts();
    } catch (error) {
        showToast(error.message, 'error');
    }
}

function renderProducts() {
    const grid = $('#products-grid');
    if (!state.products.length) {
        grid.innerHTML = '<div class="empty-state">No hay productos registrados.</div>';
        return;
    }

    grid.innerHTML = state.products.map((product) => {
        const id = product.id || product._id;
        let actions = '';

        if (state.user.role === 'admin') {
            actions = `<div class="card-actions">
                <button class="ghost-button" data-action="edit-product" data-id="${id}" type="button">Editar</button>
                <button class="danger-button" data-action="delete-product" data-id="${id}" type="button">Eliminar</button>
            </div>`;
        }

        if (['admin', 'buyer'].includes(state.user.role)) {
            actions += `<form class="buy-form" data-product-id="${id}">
                <input name="quantity" type="number" min="1" max="${product.stock}" value="1" aria-label="Cantidad" required>
                <button class="primary-button compact" type="submit" ${product.stock < 1 || !product.is_active ? 'disabled' : ''}>Agregar</button>
            </form>`;
        }

        return `<article class="product-card">
            <div class="product-top"><span class="sku">${escapeHtml(product.sku)}</span><span class="stock">${product.stock} disponibles</span></div>
            <h3>${escapeHtml(product.name)}</h3>
            <p class="product-description">${escapeHtml(product.description || 'Sin descripción.')}</p>
            <div class="product-bottom"><span class="price">${money(product.price, product.currency)}</span>${actions}</div>
        </article>`;
    }).join('');
}

async function loadCart() {
    try {
        const response = await api('/cart');
        state.cart = response.data;
        renderCart();
    } catch (error) { showToast(error.message, 'error'); }
}

function renderCart() {
    const list = $('#cart-list');
    const items = state.cart?.items || [];
    const hasItems = items.length > 0;
    $('#cart-summary').hidden = !hasItems;
    $('#clear-cart-button').disabled = !hasItems;

    if (!hasItems) {
        list.innerHTML = '<div class="empty-state">Tu carrito está vacío. Agrega productos desde el catálogo.</div>';
        return;
    }

    list.innerHTML = items.map((item) => `<article class="order-card cart-item">
        <div><p><strong>${escapeHtml(item.name)}</strong></p><span class="order-meta">${escapeHtml(item.sku || 'No disponible')} · ${money(item.unit_price, item.currency)}</span></div>
        <form class="cart-quantity-form" data-product-id="${item.product_id}">
            <input name="quantity" type="number" min="1" max="${item.stock}" value="${item.quantity}" aria-label="Cantidad de ${escapeHtml(item.name)}" required>
            <button class="ghost-button" type="submit" ${item.is_available ? '' : 'disabled'}>Actualizar</button>
        </form>
        <div><strong>${money(item.subtotal, item.currency)}</strong><br><span class="status-badge ${item.is_available ? '' : 'cancelled'}">${item.is_available ? `${item.stock} disponibles` : 'No disponible'}</span></div>
        <button class="danger-button" data-action="remove-cart-item" data-id="${item.product_id}" type="button">Eliminar</button>
    </article>`).join('');

    $('#cart-item-count').textContent = state.cart.item_count;
    $('#cart-total').textContent = money(state.cart.total, state.cart.currency);
    $('#checkout-button').disabled = items.some((item) => !item.is_available);
}

function renderAiRecommendations(result) {
    state.aiRecommendations = result.recommendations || [];
    $('#ai-result').hidden = false;
    $('#ai-answer').textContent = result.answer;
    $('#ai-provider').textContent = `Proveedor: ${result.provider} · Modelo: ${result.model}`;

    if (!state.aiRecommendations.length) {
        $('#ai-products').innerHTML = '<div class="empty-state">La IA no encontró un producto adecuado para esa solicitud.</div>';
        return;
    }

    const canBuy = ['admin', 'buyer'].includes(state.user.role);
    $('#ai-products').innerHTML = state.aiRecommendations.map((product) => `<article class="product-card compact-card">
        <div class="product-top"><span class="sku">${escapeHtml(product.sku)}</span><span class="stock">${product.stock} disponibles</span></div>
        <h3>${escapeHtml(product.name)}</h3>
        <p class="product-description">${escapeHtml(product.description || 'Sin descripción.')}</p>
        <div class="product-bottom">
            <span class="price">${money(product.price, product.currency)}</span>
            ${canBuy ? `<button class="primary-button compact" data-action="ai-add" data-id="${product.id}" type="button">Agregar al carrito</button>` : ''}
        </div>
    </article>`).join('');
}

function resetProductForm() {
    $('#product-form').reset();
    $('#product-id').value = '';
    $('#product-form-title').textContent = 'Crear producto';
    $('#product-form').hidden = true;
}

function editProduct(id) {
    const product = state.products.find((item) => (item.id || item._id) === id);
    if (!product) return;
    $('#product-id').value = id;
    $('#product-sku').value = product.sku;
    $('#product-name').value = product.name;
    $('#product-price').value = decimalValue(product.price);
    $('#product-stock').value = product.stock;
    $('#product-description').value = product.description || '';
    $('#product-form-title').textContent = 'Editar producto';
    $('#product-form').hidden = false;
    $('#product-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function loadUsers() {
    try {
        const response = await api('/admin/users?per_page=50');
        state.users = response.data;
        renderUsers();
    } catch (error) { showToast(error.message, 'error'); }
}

function renderUsers() {
    $('#users-body').innerHTML = state.users.map((user) => {
        const id = user.id || user._id;
        const self = id === (state.user.id || state.user._id);
        return `<tr data-user-id="${id}">
            <td><strong>${escapeHtml(user.name)}</strong><small>${escapeHtml(user.email)}</small></td>
            <td><select data-field="role" ${self ? 'disabled' : ''}>
                ${['buyer', 'admin'].map((role) => `<option value="${role}" ${user.role === role ? 'selected' : ''}>${roleName(role)}</option>`).join('')}
            </select></td>
            <td><select data-field="status" ${self ? 'disabled' : ''}>
                <option value="active" ${user.status === 'active' ? 'selected' : ''}>Activo</option>
                <option value="disabled" ${user.status === 'disabled' ? 'selected' : ''}>Deshabilitado</option>
            </select></td>
            <td><div class="card-actions">
                <button class="ghost-button" data-action="save-user" type="button" ${self ? 'disabled' : ''}>Guardar</button>
                <button class="danger-button" data-action="delete-user" type="button" ${self ? 'disabled' : ''}>Eliminar</button>
            </div></td>
        </tr>`;
    }).join('');
}

async function loadOrders() {
    try {
        const path = state.user.role === 'admin' ? '/admin/orders?per_page=50' : '/orders?per_page=50';
        const response = await api(path);
        state.orders = response.data;
        renderOrders();
    } catch (error) { showToast(error.message, 'error'); }
}

function renderOrders() {
    const list = $('#orders-list');
    if (!state.orders.length) {
        list.innerHTML = '<div class="empty-state">Todavía no hay compras.</div>';
        return;
    }

    list.innerHTML = state.orders.map((order) => {
        const id = order.id || order._id;
        const itemSummary = order.items.map((item) => `${escapeHtml(item.name)} × ${item.quantity}`).join(', ');
        const cancelled = order.status === 'cancelled';
        return `<article class="order-card">
            <div><p><strong>${itemSummary}</strong></p><span class="order-meta">${order.items.length} producto(s) · ${new Date(order.created_at).toLocaleString('es-MX')}</span></div>
            <div><strong>${money(order.total, order.currency)}</strong><br><span class="status-badge ${cancelled ? 'cancelled' : ''}">${escapeHtml(order.status)}</span></div>
            <button class="danger-button" data-action="cancel-order" data-id="${id}" type="button" ${cancelled ? 'disabled' : ''}>Cancelar</button>
        </article>`;
    }).join('');
}

function selectTab(tab) {
    $$('.tab').forEach((button) => button.classList.toggle('active', button.dataset.tab === tab));
    $$('.tab-section').forEach((section) => { section.hidden = section.id !== `${tab}-section`; });
    if (tab === 'users') loadUsers();
    if (tab === 'cart') loadCart();
    if (tab === 'orders') loadOrders();
}

async function login(email, password) {
    const response = await api('/auth/login', { method: 'POST', body: { email, password } });
    state.token = response.data.token;
    state.tokenExpiresAt = response.data.expires_at;
    state.user = response.data.user;
    localStorage.setItem('subarg_token', state.token);
    localStorage.setItem('subarg_token_expires_at', state.tokenExpiresAt);

    if (state.user.role === 'admin') {
        window.location.href = '/admin';
        return;
    }

    enterApp();
}

$('#login-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await login($('#login-email').value, $('#login-password').value);
        showToast(`Bienvenido, ${state.user.name}.`);
    } catch (error) { showToast(error.message, 'error'); }
});

$('#register-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const credentials = {
        name: $('#register-name').value,
        email: $('#register-email').value,
        password: $('#register-password').value,
    };

    try {
        await api('/auth/register', { method: 'POST', body: credentials });
        await login(credentials.email, credentials.password);
        showToast('Tu cuenta de cliente fue creada. Ya puedes comprar.');
    } catch (error) { showToast(error.message, 'error'); }
});

$$('.demo-account').forEach((button) => button.addEventListener('click', () => {
    $('#login-email').value = button.dataset.email;
    $('#login-password').value = 'password123';
}));

$('#show-login-button').addEventListener('click', () => showAuth('login'));
$('#show-register-button').addEventListener('click', () => showAuth('register'));
$('#observer-button').addEventListener('click', enterObserver);
$('#top-register-button').addEventListener('click', () => showAuth('register'));

$('#logout-button').addEventListener('click', async () => {
    if (state.user.role === 'observer') {
        showAuth('login');
        return;
    }

    try { await api('/auth/logout', { method: 'POST' }); } catch (_) { /* La sesión local se limpia de cualquier manera. */ }
    enterObserver();
    showToast('Sesión cerrada. Continúas como observador.');
});

$$('.tab').forEach((button) => button.addEventListener('click', () => selectTab(button.dataset.tab)));
$('#new-product-button').addEventListener('click', () => { resetProductForm(); $('#product-form').hidden = false; });
$('#cancel-product-button').addEventListener('click', resetProductForm);

$('#product-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = $('#product-id').value;
    try {
        await api(id ? `/admin/products/${id}` : '/admin/products', {
            method: id ? 'PATCH' : 'POST',
            body: {
                sku: $('#product-sku').value,
                name: $('#product-name').value,
                price: Number($('#product-price').value),
                stock: Number($('#product-stock').value),
                description: $('#product-description').value || null,
                currency: 'MXN',
                is_active: true,
            },
        });
        showToast(id ? 'Producto actualizado.' : 'Producto creado.');
        resetProductForm();
        loadProducts();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#products-grid').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const { action, id } = button.dataset;
    if (action === 'edit-product') editProduct(id);
    if (action === 'delete-product' && window.confirm('¿Eliminar este producto?')) {
        try { await api(`/admin/products/${id}`, { method: 'DELETE' }); showToast('Producto eliminado.'); loadProducts(); }
        catch (error) { showToast(error.message, 'error'); }
    }
});

$('#products-grid').addEventListener('submit', async (event) => {
    const form = event.target.closest('.buy-form');
    if (!form) return;
    event.preventDefault();
    try {
        await api('/cart/items', { method: 'POST', body: { product_id: form.dataset.productId, quantity: Number(form.elements.quantity.value) } });
        showToast('Producto agregado al carrito.');
        form.reset();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#ai-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = $('#ai-submit-button');
    button.disabled = true;
    button.textContent = 'Consultando IA...';

    try {
        const response = await api('/ai/recommendations', {
            method: 'POST',
            body: { query: $('#ai-query').value },
        });
        renderAiRecommendations(response.data);
    } catch (error) {
        showToast(error.message, 'error');
    } finally {
        button.disabled = false;
        button.textContent = 'Obtener recomendaciones';
    }
});

$('#ai-products').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action="ai-add"]');
    if (!button) return;

    try {
        await api('/cart/items', {
            method: 'POST',
            body: { product_id: button.dataset.id, quantity: 1 },
        });
        showToast('Producto recomendado agregado al carrito.');
    } catch (error) { showToast(error.message, 'error'); }
});

$('#cart-list').addEventListener('submit', async (event) => {
    const form = event.target.closest('.cart-quantity-form');
    if (!form) return;
    event.preventDefault();
    try {
        await api(`/cart/items/${form.dataset.productId}`, {
            method: 'PATCH',
            body: { quantity: Number(form.elements.quantity.value) },
        });
        showToast('Cantidad actualizada.');
        loadCart();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#cart-list').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action="remove-cart-item"]');
    if (!button) return;
    try {
        await api(`/cart/items/${button.dataset.id}`, { method: 'DELETE' });
        showToast('Producto eliminado del carrito.');
        loadCart();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#clear-cart-button').addEventListener('click', async () => {
    if (!window.confirm('¿Vaciar todos los productos del carrito?')) return;
    try {
        await api('/cart', { method: 'DELETE' });
        showToast('Carrito vaciado.');
        loadCart();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#checkout-button').addEventListener('click', async () => {
    if (!window.confirm('¿Confirmar la compra de todos los productos?')) return;
    try {
        await api('/checkout', { method: 'POST' });
        showToast('Compra confirmada correctamente.');
        await Promise.all([loadCart(), loadProducts(), loadOrders()]);
        selectTab('orders');
    } catch (error) { showToast(error.message, 'error'); loadCart(); }
});

$('#user-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await api('/admin/users', { method: 'POST', body: {
            name: $('#user-name').value,
            email: $('#user-email').value,
            password: $('#user-password').value,
            role: $('#user-role').value,
        } });
        event.target.reset();
        $('#user-password').value = 'password123';
        showToast('Cuenta creada.');
        loadUsers();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#users-body').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const row = button.closest('[data-user-id]');
    const id = row.dataset.userId;
    try {
        if (button.dataset.action === 'save-user') {
            await api(`/admin/users/${id}`, { method: 'PATCH', body: {
                role: row.querySelector('[data-field="role"]').value,
                status: row.querySelector('[data-field="status"]').value,
            } });
            showToast('Permisos actualizados.');
        }
        if (button.dataset.action === 'delete-user' && window.confirm('¿Eliminar esta cuenta?')) {
            await api(`/admin/users/${id}`, { method: 'DELETE' });
            showToast('Cuenta eliminada.');
        }
        loadUsers();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#orders-list').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action="cancel-order"]');
    if (!button || !window.confirm('¿Cancelar la compra y devolver la existencia?')) return;
    try {
        await api(`/orders/${button.dataset.id}`, { method: 'DELETE' });
        showToast('Compra cancelada.');
        loadOrders();
        loadProducts();
    } catch (error) { showToast(error.message, 'error'); }
});

$('#refresh-orders-button').addEventListener('click', loadOrders);

async function resumeSession() {
    if (!state.token) {
        enterObserver();
        return;
    }

    if (state.tokenExpiresAt && new Date(state.tokenExpiresAt).getTime() <= Date.now()) {
        expireSession();
        return;
    }

    try {
        const response = await api('/me');
        state.user = response.data;

        if (state.user.role === 'admin') {
            window.location.href = '/admin';
            return;
        }

        enterApp();
    } catch (_) {
        enterObserver();
    }
}

resumeSession();
