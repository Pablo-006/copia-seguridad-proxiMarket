<script setup>
// IMPORTS Y CONEXIONES CON LOS STORES
import { ref, onMounted } from 'vue';
import { useProductStore } from '@/stores/product';
const productStore = useProductStore();

// CONSTANTES (esta constante opino que debería ir en el .env ya que también se usa
// en el perfil)
const img_url = 'http://localhost:8000/storage/';

// VARIABLES REACTIVAS
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const deleteId = ref(null);
const form = ref({
    title: '',
    price: '',
    unit: 'kg', 
    stock: '',
    estimated_weight: '',
    image_file: null,
    current_image: null
});

// Variable para la previsualización local de la imagen
// preguntar acerca de las diferencias sobre la carga de imágenes en esta vista
// y en la vista del perfil
const imagePreview = ref(null);

// INSTRUCCIONES/FUNCIONES DE LA VISTA

// Carga de los productos
const loadProducts = async () => {
    await productStore.getSellerProducts();
};

// Abir el modo edicion?
const openModal = (product = null) => {
    showEditModal.value = true;
    form.value.image_file = null; // Reiniciar archivo
    
    if (product) {
        isEditing.value = true;
        editingId.value = product.id;
        form.value.title = product.title;
        form.value.price = product.price;
        form.value.unit = product.unit; 
        form.value.stock = product.stock;
        form.value.estimated_weight = product.estimated_weight;
        form.value.current_image = product.image_url;

    } else {
        isEditing.value = false;
        editingId.value = null;
        form.value.title = ''; 
        form.value.price = ''; 
        form.value.unit = 'kg'; 
        form.value.stock = ''; 
        form.value.estimated_weight = '';
        form.value.current_image = null;
    }
};

// Función para capturar el archivo cuando el usuario lo selecciona
// (pararse a ver esta función por las diferencias con la vista del perfil
// relacionadas con las imagenes comentado anteriormente)
const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.value.image_file = file;

        if(imagePreview.value){
            URL.revokeObjectURL(file);
        }

        imagePreview.value = URL.createObjectURL(file);
    }
};

// Guardar un producto
const handleSaveProduct = async () => {
    // Para enviar archivos, necesitamos FormData
    let formData = new FormData();
    formData.append('title', form.value.title);
    formData.append('price', form.value.price);
    formData.append('unit', form.value.unit);
    formData.append('stock', form.value.stock);
    formData.append('estimated_weight', form.value.estimated_weight);

    // Solo adjuntamos la imagen si el usuario seleccionó una nueva
    if (form.value.image_file) {
        formData.append('image', form.value.image_file);
    }

    // Como le puedo pasar el id del producto por parámetro si en esta función
    // no tengo dicho id?
    await productStore.saveProduct(formData, isEditing.value, editingId.value);

    editingId.value = null;
    imagePreview.value = null;
    showEditModal.value = false;  
};

// Borrar un producto

// De donde sale el id por parámetro?: sale del template, cuando se recorre el listado
// de productos con un for, sobre cada producto se crea un botón que tiene asignado el 
// respectivo id del producto (hacer énfasis para recordar el funcionamiento de Vue en cuanto
// a los template)
const remove = async (id) => {
    deleteId.value = id;
    showDeleteModal.value = true;
};

const handleDeleteProduct = async () => {
    await productStore.deleteProduct(deleteId.value);
    showDeleteModal.value = false;
    deleteId.value = null;
};

// onMounted/Carga de los datos
onMounted(() => {
    loadProducts();
});
</script>

<template>
    <div class="inventory-wrapper">
        <div class="inventory-card">
            <div class="inventory-header">
                <div>
                    <h2>📦 Gestión de Inventario</h2>
                    <p class="subtitle">Administra tus productos en venta</p>
                </div>
                <button @click="openModal()" class="btn-create">
                    <span class="icon">+</span> Nuevo Producto
                </button>
            </div>

            <div v-if="productStore.loading" class="loading-state">
                <div class="spinner"></div> Cargando inventario...
            </div>

            <ul v-else-if="productStore.products.length > 0" class="product-list">
                <li v-for="p in productStore.products" :key="p.id" class="product-item">

                    <div class="product-img-wrapper">
                        <img :src="p.image_url ? (p.image_url.startsWith('http') ? p.image_url : img_url + p.image_url) : 'https://via.placeholder.com/150?text=Sin+Foto'" 
                             alt="Producto" class="product-thumb">
                    </div>

                    <div class="product-info">
                        <h4 class="product-title">{{ p.title }}</h4>
                        <div class="product-meta">
                            <span class="badge price">{{ p.price }}€ / {{ p.unit }}</span>
                            <span class="badge stock" :class="{ 'low-stock': p.stock < 5 }">
                                Stock: {{ p.stock }}
                            </span>
                            <span class="badge weight">{{ p.estimated_weight }} Kg</span>
                        </div>
                    </div>

                    <div class="product-actions">
                        <button @click="openModal(p)" class="btn-icon edit">Editar</button>
                        <button @click="remove(p.id)" class="btn-icon delete">Eliminar</button>
                    </div>
                </li>
            </ul>
            <div v-else class="empty-state">
                <div class="empty-icon">🌱</div>
                <h3>Tu inventario está vacío</h3>
                <button @click="openModal()" class="btn-create-small">Crear Producto</button>
            </div>
        </div>

        <transition name="fade">
            <div v-if="showEditModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>{{ isEditing ? '✏️ Editar Producto' : '✨ Nuevo Producto' }}</h3>
                        <button @click="showEditModal = false" class="close-btn">×</button>
                    </div>
                    
                    <form @submit.prevent="handleSaveProduct" class="modal-body">
                        <div class="form-group">
                            <label>Nombre del Producto</label>
                            <input v-model="form.title" type="text" required class="input-field">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label>Precio (€)</label>
                                <input v-model="form.price" type="number" step="0.01" required class="input-field">
                            </div>
                            <div class="form-group half">
                                <label>Unidad</label>
                                <select v-model="form.unit" class="input-field">
                                    <option value="kg">Kilogramo (kg)</option>
                                    <option value="unit">Unidad (unit)</option>
                                    <option value="box">Caja (box)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Stock Disponible</label>
                            <input v-model="form.stock" type="number" step="0.001" required class="input-field">
                        </div>

                        <div class="form-group half">
                                <label>Peso estimado (kg/ud)</label>
                                <input v-model="form.estimated_weight" type="number" step="0.01" required class="input-field">
                        </div>

                        <div class="form-group">
                            <label>Imagen del Producto</label>
                            <input type="file" @change="handleFileUpload" accept="image/*" class="input-field file-input">
                            <small class="helper-text">Formatos: JPG, PNG. Máx 2MB</small>
                            
                            <div class="image-preview-box">
                                <img v-if="imagePreview" :src="imagePreview" alt="Vista previa">
                                <img v-else-if="form.current_image" :src="form.current_image.startsWith('http') ? form.current_image : img_url + form.current_image" alt="Imagen actual">
                            </div>


                        </div>

                        <div class="modal-actions">
                            <button type="button" @click="showEditModal = false, imagePreview = null" class="btn-cancel">Cancelar</button>
                            <button type="submit" class="btn-save">Guardar Producto</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <transition name="fade">
            <div v-if="showDeleteModal" class="modal-overlay">
                
                <div class="modal-content modal-delete">
                    <div class="modal-icon">⚠️</div>
                    <h3 class="modal-title">¿Estás seguro?</h3>
                    <p class="modal-text">Esta acción eliminará el producto permanentemente.</p>
                    
                    <div class="modal-actions-center">
                        <button @click="showDeleteModal = false" class="btn-cancel">Cancelar</button>
                        <button @click="handleDeleteProduct()" class="btn-danger">Sí, eliminar</button>
                    </div>
                </div>

            </div>
        </transition>

    </div>
</template>

<style scoped>
/* Agrega este estilo extra para el input de tipo archivo si quieres que se vea mejor */
.file-input {
    padding: 6px;
    background: white;
}
/* Mantenemos el resto de estilos igual... */
.inventory-wrapper { display: flex; justify-content: center; padding: 40px 20px; background-color: #f8fafc; min-height: 80vh; font-family: 'Segoe UI', sans-serif; }
.inventory-card { background: white; width: 100%; max-width: 700px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); overflow: hidden; display: flex; flex-direction: column; }
.inventory-header { padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: white; }
.inventory-header h2 { margin: 0; color: #1e293b; font-size: 1.5rem; }
.subtitle { margin: 5px 0 0; color: #64748b; font-size: 0.9rem; }
.btn-create { background-color: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
.btn-create:hover { background-color: #059669; transform: translateY(-2px); }
.product-list { list-style: none; padding: 0; margin: 0; }
.product-item { display: flex; align-items: center; padding: 20px 30px; border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
.product-item:hover { background-color: #f8fafc; }
.product-img-wrapper { width: 60px; height: 60px; border-radius: 10px; overflow: hidden; margin-right: 20px; border: 1px solid #e2e8f0; background: #fff; }
.product-thumb { width: 100%; height: 100%; object-fit: cover; }
.product-info { flex: 1; }
.product-title { margin: 0 0 8px 0; color: #334155; font-size: 1.1rem; }
.product-meta { display: flex; gap: 10px; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge.price { background-color: #eff6ff; color: #3b82f6; }
.badge.stock { background-color: #f1f5f9; color: #64748b; }
.badge.stock.low-stock { background-color: #fef2f2; color: #ef4444; }
.badge.weight { background-color: #fef3c7; color: #d97706; }
.product-actions { display: flex; gap: 10px; }
.btn-icon { padding: 6px 12px; border-radius: 6px; border: none; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-icon.edit { background-color: #e0f2fe; color: #0284c7; }
.btn-icon.delete { background-color: #fee2e2; color: #dc2626; }
.empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: flex; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
.modal-content { background: white; width: 90%; max-width: 450px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; animation: slideUp 0.3s ease-out; }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.modal-header { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.close-btn { background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 25px; }
.modal-delete {max-width: 400px; text-align: center; padding: 30px;}
.modal-icon {font-size: 3rem; margin-bottom: 15px;}
.modal-title {margin-bottom: 10px; color: #1e293b;}
.modal-text {color: #64748b; margin-bottom: 25px;}
.modal-actions-center {display: flex; gap: 15px; justify-content: center;}
.form-group { margin-bottom: 18px; }
.form-row { display: flex; gap: 15px; }
.form-group.half { flex: 1; }
label { display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 0.9rem; }
.input-field { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; color: #1e293b; transition: border-color 0.2s; }
.input-field:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.helper-text { display: block; margin-top: 5px; color: #94a3b8; font-size: 0.8rem; }
.image-preview-box { margin-top: 10px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; width: 100px; height: 100px; }
.image-preview-box img { width: 100%; height: 100%; object-fit: cover; }
.modal-actions { display: flex; gap: 10px; margin-top: 25px; }
.btn-save { flex: 2; background-color: #3b82f6; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-cancel { flex: 1; background-color: white; border: 1px solid #cbd5e1; color: #475569; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.btn-danger {background-color: #dc2626; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;}
.btn-danger:hover {background-color: #b91c1c;}
.loading-state { padding: 40px; text-align: center; color: #64748b; }
.spinner { border: 3px solid #f3f3f3; border-top: 3px solid #3b82f6; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 0 auto 10px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>