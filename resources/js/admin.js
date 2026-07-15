const adminState = {
    token: localStorage.getItem('subarg_token'),
    tokenExpiresAt: localStorage.getItem('subarg_token_expires_at'),
    user: null,
    dashboard: null,
    products: [],
    categories: [],
    users: [],
    orders: [],
    movements: [],
    aiInteractions: [],
};

let adminExpiryTimeout;
let adminCountdownInterval;

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function escapeHtml(value = '') {
    return String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);
}

function money(value, currency = 'MXN') {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency }).format(Number(value || 0));
}

function shortId(value) {
    const text = String(value || '');
    return text.length > 10 ? `${text.slice(0, 8)}…` : text;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('es-MX') : '—';
}

function showAdminToast(message, type = 'success') {
    const toast = $('#admin-toast');
    toast.textContent = message;
    toast.className = `toast show ${type === 'error' ? 'error' : ''}`;
    window.clearTimeout(showAdminToast.timer);
    showAdminToast.timer = window.setTimeout(() => { toast.className = 'toast'; }, 3500);
}

function clearAdminSession() {
    window.clearTimeout(adminExpiryTimeout);
    window.clearInterval(adminCountdownInterval);
    adminState.token = null;
    adminState.tokenExpiresAt = null;
    adminState.user = null;
    localStorage.removeItem('subarg_token');
    localStorage.removeItem('subarg_token_expires_at');
}

function showAdminLogin(message = null) {
    clearAdminSession();
    $('#admin-app').hidden = true;
    $('#admin-login-view').hidden = false;
    if (message) showAdminToast(message, 'error');
}

async function adminApi(path, options = {}) {
    const method = options.method || 'GET';
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (adminState.token) headers.Authorization = `Bearer ${adminState.token}`;
    if (options.body && typeof options.body !== 'string') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }

    const response = await fetch(`/api/v1${path}`, { ...options, method, headers });
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 401 && adminState.token) showAdminLogin('La sesión administrativa terminó.');
        const validation = data?.errors ? Object.values(data.errors).flat().join(' ') : null;
        throw new Error(validation || data?.message || `Error HTTP ${response.status}`);
    }

    return data;
}

function scheduleAdminExpiry() {
    window.clearTimeout(adminExpiryTimeout);
    window.clearInterval(adminCountdownInterval);
    const expiresAt = new Date(adminState.tokenExpiresAt).getTime();

    const update = () => {
        const secondsLeft = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
        $('#admin-countdown').textContent = `Sesión: ${Math.floor(secondsLeft / 60)}:${String(secondsLeft % 60).padStart(2, '0')}`;
    };

    if (!expiresAt || expiresAt <= Date.now()) {
        showAdminLogin('La sesión administrativa terminó.');
        return;
    }

    update();
    adminCountdownInterval = window.setInterval(update, 1000);
    adminExpiryTimeout = window.setTimeout(() => showAdminLogin('La sesión administrativa terminó.'), expiresAt - Date.now());
}

function enterAdmin() {
    $('#admin-login-view').hidden = true;
    $('#admin-app').hidden = false;
    $('#admin-user-name').textContent = adminState.user.name;
    scheduleAdminExpiry();
    selectAdminSection('dashboard');
}

const sectionTitles = {
    dashboard: 'Resumen', products: 'Productos', categories: 'Categorías', users: 'Usuarios',
    orders: 'Órdenes', inventory: 'Inventario', ai: 'Actividad de IA',
};

const sectionLoaders = {
    dashboard: loadDashboard,
    products: loadProducts,
    categories: loadCategories,
    users: loadUsers,
    orders: loadOrders,
    inventory: loadInventory,
    ai: loadAiInteractions,
};

function selectAdminSection(section) {
    $$('.admin-nav-button').forEach((button) => button.classList.toggle('active', button.dataset.adminSection === section));
    $$('.admin-section').forEach((element) => { element.hidden = element.id !== `admin-${section}-section`; });
    $('#admin-section-title').textContent = sectionTitles[section];
    sectionLoaders[section]?.();
}

async function loadDashboard() {
    try {
        const response = await adminApi('/admin/dashboard');
        adminState.dashboard = response.data;
        renderDashboard();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function renderDashboard() {
    const data = adminState.dashboard;
    const metrics = [
        ['Usuarios', data.users.total, `${data.users.active} activos`],
        ['Productos', data.products.total, `${data.products.low_stock} con existencia baja`],
        ['Órdenes', data.orders.total, `${data.orders.cancelled} canceladas`],
        ['Ingresos', money(data.orders.revenue, data.orders.currency), 'Órdenes pagadas'],
        ['Sin existencias', data.products.out_of_stock, 'Productos agotados'],
        ['Movimientos', data.inventory_movements, 'Registros de inventario'],
    ];
    $('#admin-metrics').innerHTML = metrics.map(([label, value, detail]) => `<article class="metric-card"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(detail)}</small></article>`).join('');
    $('#dashboard-orders').innerHTML = ordersTable(adminState.dashboard.recent_orders || [], false);
}

async function ensureCategories() {
    if (adminState.categories.length) return;
    const response = await adminApi('/admin/categories?per_page=50');
    adminState.categories = response.data;
    renderCategoryOptions();
}

async function loadProducts() {
    try {
        await ensureCategories();
        const response = await adminApi('/admin/products?per_page=50');
        adminState.products = response.data;
        renderProducts();
        renderInventoryProductOptions();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function renderProducts() {
    $('#admin-products-body').innerHTML = adminState.products.map((product) => `<tr>
        <td><strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.sku)}</small></td>
        <td>${money(product.price, product.currency)}</td>
        <td>${product.stock}</td>
        <td><span class="status-badge ${product.is_active ? '' : 'cancelled'}">${product.is_active ? 'Activo' : 'Inactivo'}</span></td>
        <td><div class="card-actions"><button class="ghost-button" data-action="edit-product" data-id="${product.id}" type="button">Editar</button><button class="danger-button" data-action="delete-product" data-id="${product.id}" type="button">Eliminar</button></div></td>
    </tr>`).join('') || '<tr><td colspan="5">No hay productos.</td></tr>';
}

function renderCategoryOptions() {
    const options = adminState.categories.map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`).join('');
    $('#admin-product-category').innerHTML = `<option value="">Sin categoría</option>${options}`;
}

function resetProductForm() {
    $('#admin-product-form').reset();
    $('#admin-product-id').value = '';
    $('#admin-product-form-title').textContent = 'Nuevo producto';
    $('#admin-product-form').hidden = true;
}

function editProduct(id) {
    const product = adminState.products.find((item) => item.id === id);
    if (!product) return;
    $('#admin-product-id').value = id;
    $('#admin-product-sku').value = product.sku;
    $('#admin-product-name').value = product.name;
    $('#admin-product-price').value = product.price;
    $('#admin-product-stock').value = product.stock;
    $('#admin-product-category').value = product.category_id || '';
    $('#admin-product-active').value = String(product.is_active);
    $('#admin-product-description').value = product.description || '';
    $('#admin-product-form-title').textContent = 'Editar producto';
    $('#admin-product-form').hidden = false;
}

async function loadCategories() {
    try {
        const response = await adminApi('/admin/categories?per_page=50');
        adminState.categories = response.data;
        renderCategories();
        renderCategoryOptions();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function renderCategories() {
    $('#admin-categories-body').innerHTML = adminState.categories.map((category) => `<tr>
        <td><strong>${escapeHtml(category.name)}</strong><small>${escapeHtml(category.description || '')}</small></td>
        <td>${escapeHtml(category.slug)}</td>
        <td><span class="status-badge ${category.is_active ? '' : 'cancelled'}">${category.is_active ? 'Activa' : 'Inactiva'}</span></td>
        <td><div class="card-actions"><button class="ghost-button" data-action="edit-category" data-id="${category.id}" type="button">Editar</button><button class="danger-button" data-action="delete-category" data-id="${category.id}" type="button">Eliminar</button></div></td>
    </tr>`).join('') || '<tr><td colspan="4">No hay categorías.</td></tr>';
}

function resetCategoryForm() {
    $('#admin-category-form').reset();
    $('#admin-category-id').value = '';
    $('#admin-cancel-category').hidden = true;
}

function editCategory(id) {
    const category = adminState.categories.find((item) => item.id === id);
    if (!category) return;
    $('#admin-category-id').value = id;
    $('#admin-category-name').value = category.name;
    $('#admin-category-description').value = category.description || '';
    $('#admin-category-active').value = String(category.is_active);
    $('#admin-cancel-category').hidden = false;
}

async function loadUsers() {
    try {
        const response = await adminApi('/admin/users?per_page=50');
        adminState.users = response.data;
        renderUsers();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function renderUsers() {
    $('#admin-users-body').innerHTML = adminState.users.map((user) => {
        const isSelf = user.id === adminState.user.id;
        return `<tr data-user-id="${user.id}">
            <td><strong>${escapeHtml(user.name)}</strong><small>${escapeHtml(user.email)}</small></td>
            <td><select data-field="role" ${isSelf ? 'disabled' : ''}><option value="buyer" ${user.role === 'buyer' ? 'selected' : ''}>Cliente</option><option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrador</option></select></td>
            <td><select data-field="status" ${isSelf ? 'disabled' : ''}><option value="active" ${user.status === 'active' ? 'selected' : ''}>Activo</option><option value="disabled" ${user.status === 'disabled' ? 'selected' : ''}>Deshabilitado</option></select></td>
            <td><div class="card-actions"><button class="ghost-button" data-action="save-user" type="button" ${isSelf ? 'disabled' : ''}>Guardar</button><button class="danger-button" data-action="delete-user" type="button" ${isSelf ? 'disabled' : ''}>Eliminar</button></div></td>
        </tr>`;
    }).join('') || '<tr><td colspan="4">No hay usuarios.</td></tr>';
}

const statusNames = { pending: 'Pendiente', confirmed: 'Confirmada', completed: 'Completada', shipped: 'Enviada', delivered: 'Entregada', cancelled: 'Cancelada' };
const statusTransitions = {
    pending: ['confirmed', 'cancelled'], confirmed: ['completed', 'shipped', 'cancelled'],
    completed: ['shipped', 'cancelled'], shipped: ['delivered', 'cancelled'], delivered: [], cancelled: [],
};

async function loadOrders() {
    try {
        const filter = $('#admin-order-filter').value;
        const response = await adminApi(`/admin/orders?per_page=50${filter ? `&status=${filter}` : ''}`);
        adminState.orders = response.data;
        renderOrders();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function orderItemsSummary(order) {
    return order.items.map((item) => `${escapeHtml(item.name)} × ${item.quantity}`).join('<br>');
}

function statusOptions(order) {
    const available = [order.status, ...(statusTransitions[order.status] || [])];
    return available.map((status) => `<option value="${status}" ${status === order.status ? 'selected' : ''}>${statusNames[status]}</option>`).join('');
}

function renderOrders() {
    $('#admin-orders-body').innerHTML = adminState.orders.map((order) => `<tr data-order-id="${order.id}">
        <td><strong>#${escapeHtml(shortId(order.id))}</strong><small>${formatDate(order.created_at)}</small></td>
        <td>${orderItemsSummary(order)}</td>
        <td>${money(order.total, order.currency)}</td>
        <td><span class="status-badge ${order.status === 'cancelled' ? 'cancelled' : ''}">${statusNames[order.status] || order.status}</span></td>
        <td><div class="card-actions"><select data-field="order-status" ${statusTransitions[order.status]?.length ? '' : 'disabled'}>${statusOptions(order)}</select><button class="ghost-button" data-action="save-order" type="button" ${statusTransitions[order.status]?.length ? '' : 'disabled'}>Actualizar</button></div></td>
    </tr>`).join('') || '<tr><td colspan="5">No hay órdenes.</td></tr>';
}

function ordersTable(orders, includeActions = false) {
    if (!orders.length) return '<div class="empty-state">No hay órdenes recientes.</div>';
    return `<table><thead><tr><th>Orden</th><th>Productos</th><th>Total</th><th>Estado</th>${includeActions ? '<th>Acciones</th>' : ''}</tr></thead><tbody>${orders.map((order) => `<tr><td>#${escapeHtml(shortId(order.id))}<small>${formatDate(order.created_at)}</small></td><td>${orderItemsSummary(order)}</td><td>${money(order.total, order.currency)}</td><td>${statusNames[order.status] || escapeHtml(order.status)}</td>${includeActions ? '<td></td>' : ''}</tr>`).join('')}</tbody></table>`;
}

function renderInventoryProductOptions() {
    const select = $('#admin-inventory-product');
    if (!select) return;
    select.innerHTML = adminState.products.map((product) => `<option value="${product.id}">${escapeHtml(product.name)} (${product.stock})</option>`).join('');
}

async function loadInventory() {
    try {
        if (!adminState.products.length) await loadProducts();
        const response = await adminApi('/admin/inventory-movements?per_page=50');
        adminState.movements = response.data;
        renderInventory();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function renderInventory() {
    const names = Object.fromEntries(adminState.products.map((product) => [product.id, product.name]));
    $('#admin-inventory-body').innerHTML = adminState.movements.map((movement) => `<tr>
        <td>${formatDate(movement.created_at)}</td><td>${escapeHtml(names[movement.product_id] || shortId(movement.product_id))}</td>
        <td>${escapeHtml(movement.type)}</td><td class="${movement.quantity_delta >= 0 ? 'positive-value' : 'negative-value'}">${movement.quantity_delta > 0 ? '+' : ''}${movement.quantity_delta}</td>
        <td>${movement.stock_before} → ${movement.stock_after}</td><td>${escapeHtml(movement.reason || '—')}</td>
    </tr>`).join('') || '<tr><td colspan="6">No hay movimientos.</td></tr>';
}

async function loadAiInteractions() {
    try {
        const filter = $('#admin-ai-filter').value;
        const response = await adminApi(`/admin/ai-interactions?per_page=50${filter ? `&status=${filter}` : ''}`);
        adminState.aiInteractions = response.data;
        renderAiInteractions();
    } catch (error) { showAdminToast(error.message, 'error'); }
}

function renderAiInteractions() {
    $('#admin-ai-body').innerHTML = adminState.aiInteractions.map((interaction) => `<tr>
        <td>${formatDate(interaction.created_at)}</td><td>${escapeHtml(interaction.query)}</td><td>${escapeHtml(interaction.response || interaction.metadata?.error || 'Sin respuesta')}</td>
        <td>${escapeHtml(interaction.provider)}<small>${escapeHtml(interaction.model)}</small></td>
        <td><span class="status-badge ${interaction.status === 'error' ? 'cancelled' : ''}">${interaction.status === 'success' ? 'Correcto' : 'Error'}</span></td><td>${interaction.duration_ms} ms</td>
    </tr>`).join('') || '<tr><td colspan="6">No hay interacciones de IA.</td></tr>';
}

$('#admin-login-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        const response = await adminApi('/auth/login', { method: 'POST', body: { email: $('#admin-email').value, password: $('#admin-password').value } });
        adminState.token = response.data.token;
        adminState.tokenExpiresAt = response.data.expires_at;
        adminState.user = response.data.user;

        if (adminState.user.role !== 'admin') {
            await adminApi('/auth/logout', { method: 'POST' });
            showAdminLogin('Esta cuenta no tiene permisos de administrador.');
            return;
        }

        localStorage.setItem('subarg_token', adminState.token);
        localStorage.setItem('subarg_token_expires_at', adminState.tokenExpiresAt);
        enterAdmin();
        showAdminToast(`Bienvenido, ${adminState.user.name}.`);
    } catch (error) { showAdminToast(error.message, 'error'); }
});

$('#admin-logout-button').addEventListener('click', async () => {
    try { await adminApi('/auth/logout', { method: 'POST' }); } catch (_) { /* La sesión local se limpia siempre. */ }
    showAdminLogin();
});

$$('.admin-nav-button').forEach((button) => button.addEventListener('click', () => selectAdminSection(button.dataset.adminSection)));
$$('[data-go-section]').forEach((button) => button.addEventListener('click', () => selectAdminSection(button.dataset.goSection)));

$('#admin-new-product').addEventListener('click', () => { resetProductForm(); $('#admin-product-form').hidden = false; });
$('#admin-cancel-product').addEventListener('click', resetProductForm);
$('#admin-product-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = $('#admin-product-id').value;
    try {
        await adminApi(id ? `/admin/products/${id}` : '/admin/products', { method: id ? 'PATCH' : 'POST', body: {
            sku: $('#admin-product-sku').value, name: $('#admin-product-name').value,
            price: Number($('#admin-product-price').value), stock: Number($('#admin-product-stock').value),
            category_id: $('#admin-product-category').value || null, is_active: $('#admin-product-active').value === 'true',
            description: $('#admin-product-description').value || null, currency: 'MXN',
        } });
        showAdminToast(id ? 'Producto actualizado.' : 'Producto creado.');
        resetProductForm();
        loadProducts();
    } catch (error) { showAdminToast(error.message, 'error'); }
});

$('#admin-products-body').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    if (button.dataset.action === 'edit-product') editProduct(button.dataset.id);
    if (button.dataset.action === 'delete-product' && window.confirm('¿Eliminar este producto?')) {
        try { await adminApi(`/admin/products/${button.dataset.id}`, { method: 'DELETE' }); showAdminToast('Producto eliminado.'); loadProducts(); }
        catch (error) { showAdminToast(error.message, 'error'); }
    }
});

$('#admin-category-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = $('#admin-category-id').value;
    try {
        await adminApi(id ? `/admin/categories/${id}` : '/admin/categories', { method: id ? 'PATCH' : 'POST', body: {
            name: $('#admin-category-name').value, description: $('#admin-category-description').value || null,
            is_active: $('#admin-category-active').value === 'true',
        } });
        showAdminToast(id ? 'Categoría actualizada.' : 'Categoría creada.');
        resetCategoryForm();
        loadCategories();
    } catch (error) { showAdminToast(error.message, 'error'); }
});
$('#admin-cancel-category').addEventListener('click', resetCategoryForm);
$('#admin-categories-body').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    if (button.dataset.action === 'edit-category') editCategory(button.dataset.id);
    if (button.dataset.action === 'delete-category' && window.confirm('¿Eliminar esta categoría?')) {
        try { await adminApi(`/admin/categories/${button.dataset.id}`, { method: 'DELETE' }); showAdminToast('Categoría eliminada.'); loadCategories(); }
        catch (error) { showAdminToast(error.message, 'error'); }
    }
});

$('#admin-user-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await adminApi('/admin/users', { method: 'POST', body: {
            name: $('#admin-new-user-name').value, email: $('#admin-new-user-email').value,
            password: $('#admin-new-user-password').value, role: $('#admin-new-user-role').value,
        } });
        event.target.reset();
        $('#admin-new-user-password').value = 'password123';
        showAdminToast('Cuenta creada.'); loadUsers();
    } catch (error) { showAdminToast(error.message, 'error'); }
});

$('#admin-users-body').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const row = button.closest('[data-user-id]');
    try {
        if (button.dataset.action === 'save-user') {
            await adminApi(`/admin/users/${row.dataset.userId}`, { method: 'PATCH', body: { role: row.querySelector('[data-field="role"]').value, status: row.querySelector('[data-field="status"]').value } });
            showAdminToast('Usuario actualizado.');
        }
        if (button.dataset.action === 'delete-user' && window.confirm('¿Eliminar esta cuenta?')) {
            await adminApi(`/admin/users/${row.dataset.userId}`, { method: 'DELETE' });
            showAdminToast('Usuario eliminado.');
        }
        loadUsers();
    } catch (error) { showAdminToast(error.message, 'error'); }
});

$('#admin-order-filter').addEventListener('change', loadOrders);
$('#admin-orders-body').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action="save-order"]');
    if (!button) return;
    const row = button.closest('[data-order-id]');
    try {
        await adminApi(`/admin/orders/${row.dataset.orderId}/status`, { method: 'PATCH', body: { status: row.querySelector('[data-field="order-status"]').value } });
        showAdminToast('Estado actualizado.'); loadOrders();
    } catch (error) { showAdminToast(error.message, 'error'); }
});

$('#admin-inventory-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await adminApi('/admin/inventory-adjustments', { method: 'POST', body: {
            product_id: $('#admin-inventory-product').value, quantity_delta: Number($('#admin-inventory-delta').value), reason: $('#admin-inventory-reason').value,
        } });
        event.target.reset();
        showAdminToast('Inventario ajustado.');
        await loadProducts();
        loadInventory();
    } catch (error) { showAdminToast(error.message, 'error'); }
});

$('#admin-ai-filter').addEventListener('change', loadAiInteractions);

async function resumeAdminSession() {
    if (!adminState.token || !adminState.tokenExpiresAt || new Date(adminState.tokenExpiresAt).getTime() <= Date.now()) {
        showAdminLogin();
        return;
    }

    try {
        const response = await adminApi('/me');
        adminState.user = response.data;
        if (adminState.user.role !== 'admin') {
            showAdminLogin('Esta cuenta no tiene permisos de administrador.');
            return;
        }
        enterAdmin();
    } catch (_) { showAdminLogin(); }
}

resumeAdminSession();
