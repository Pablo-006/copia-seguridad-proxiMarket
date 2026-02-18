<script setup>
// ==========================================
// 1. IMPORTS Y CONFIGURACIÓN
// ==========================================
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/stores/user';
const userStore = useUserStore();
import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore();

const router = useRouter();

// URL para guardar las imágenes en el servidor
const img_url = 'http://localhost:8000/storage/';

// ==========================================
// 2. ESTADO (VARIABLES REACTIVAS)
// ==========================================

// --- Estado Global de la Vista ---
// Variable para diferenciar entre mostrar la información del usuario o editarla
const isEditing = ref(false);

// --- Estado para Imágenes ---
// Variable para mostrar la visualización de la foto de perfil del usuario
const imagePreview = ref(null);
// Variable que sirve para accionar el explorador de archivos del sistema operativo
// y poder seleccionar una foto de perfil
const fileInput = ref(null);

// --- Formularios ---
// Variable que recoge la info por defecto que tienen todos los usuarios
// para poder visualizarla y editarla
const form = ref({
    name: '',
    surname: '',
    email: '',
    avatar_file: null
});

// Similar a la variable anterior pero para vendedores
const sellerForm = ref({
    store_name: '',
    nif: '',
    description: ''
});

// Variable para determinar si un miembro quiere convertirse en vendedor
// y mostrar el formulario para rellenar los datos de vendedor
const showSellerModal = ref(false);

// ==========================================
// 3. FUNCIONES AUXILIARES (HELPERS)
// ==========================================

// Función para formatear la fecha de cuando un usuario creó una cuenta y mostrarla
// por pantalla
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};

// Función para resetear los campos del formulario en caso de que el usuario
// cancele la edición
const resetForm = () => {
    if  (userStore.user) {
        form.value.name = userStore.user.name;
        form.value.surname = userStore.user.surname || '';
        form.value.email = userStore.user.email;
        form.value.avatar_file = null;
        imagePreview.value = null;
    }
};

// Función que interactua con la variable fileInput mediante un click, para
// poder abrir el explorador de archivos del sistema
const triggerFileInput = () => {
    fileInput.value.click();
};

// ==========================================
// 4. LÓGICA DE NEGOCIO (API)
// ==========================================

// Obtener datos del usuario para mostrar el perfil de dicho usuario
// La función para obtener los datos del usuario se encuentra en user.js

// Guardar cambios del perfil
const saveProfile = async () => {
    // Variable con toda la info de los nuevos cambios. Se formatea de manera
    // que no tenga formato JSON, sino en formato FormData (debido a que JSON no
    // soporta el envío de archivos binarios) de manera que también acepte archivos 
    // (la foto de perfil) y poder enviar la info a Laravel posteriormente
    const formData = new FormData();
    
    formData.append('name', form.value.name);
    formData.append('surname', form.value.surname);
    formData.append('email', form.value.email);
    
    // Si al editar el perfil, se ha asignado o cambiado la foto de perfil,
    // se asigna al respectivo campo el archivo
    if (form.value.avatar_file instanceof File) {
        formData.append('avatar_url', form.value.avatar_file);
    }

    // Se hace la petición a Laravel para actualizar el perfil del usuario
    const exito = await userStore.updateProfile(formData);

    if(exito){
        // Se sale del modo edición
        isEditing.value = false;

        // Ya no necesitamos la previsualización temporal generada antes (en la función handleFileChange), ya que
        // tenemos la foto real, se debe borrar de la memoria RAM y se establece a null
        if (imagePreview.value) {
            // Se le avisa al navegador que hay que borrar esta previsualización de la RAM
            URL.revokeObjectURL(imagePreview.value);
            
            // Se limpia la variable ya que no es necesario que tenga un valor
            imagePreview.value = null;
        }            
    }
};

// Convertirse en vendedor
const becomeSeller = async () => {
    const exito = await userStore.becomeSeller(sellerForm.value);
    if(exito){
        showSellerModal.value = false;
    }
};

// Función para cerrar sesión
const handleLogout = async () => {
    // Se ejecuta la lógica de cierre de sesión del store
    await authStore.logout();
    // Se envía al usuario a la página de inicio
    router.push('/');    
};

// ==========================================
// 5. MANEJADORES DE EVENTOS (INTERACCIÓN)
// ==========================================

// Función para alternar entre modo edición y visualización de datos
const toggleEdit = () => {
    // Se cargan los datos del usuario en el formulario
    resetForm();

    // Se le invierte el valor a isEditing para diferenciar entre modo edición y modo lectura
    isEditing.value = !isEditing.value;
};

// Función para obtener el archivo de la foto de perfil y crear una previsualización
// temporal en la RAM para luego poder gestionar la imagen y poder guardarla en la base
// de datos
// El parámetro event viene del navegador, no es una variable local de este archivo.
// Este parámetro se crea al hacer clic en "Abrir" en la selección de archivos cuando se 
// escoge una foto de perfil.
const handleFileChange = (event) => {
    // Se obtiene la imagen a partir del parámetro que nos pasa el navegador.
    // La propiedad target, indica quién ha accionado el evento y trae consigo
    // una lista con los archivos proporcionados (sólo hay 1)
    const file = event.target.files[0];

    // Comprobamos que se haya obtenido un archivo (porque si el usuario luego
    // no quiere escoger ninguna imagen y le da a "Cancelar" el evento se dispara
    // igualmente, y se trataría de obtener un archivo de una lista vacía ocasionando
    // un error)
    if (file) {
        // Se le asigna el archivo a la variable form para preparar el almacenamiento
        // de la imagen (no se visualiza, solo se prepara). Este archivo es el real
        // que se enviará al servidor, distinto de la URL de previsualización
        form.value.avatar_file = file;

        // Si existía una previsualización de la imagen, se borra de la RAM
        if (imagePreview.value){
            URL.revokeObjectURL(imagePreview.value);
        } 
        // Se le asigna a la constante de la previsualización de la imagen,
        // una URL temporal para poder ver la imagen y además también se guarda en
        // la RAM
        imagePreview.value = URL.createObjectURL(file);
    }
};

// Navegación
const goToInventory = () => router.push('/seller/inventory');
const goToPickupPoints = () => router.push('/seller/pickup-points');

// ==========================================
// 6. CICLO DE VIDA (LIFECYCLE)
// ==========================================

// Luego de cargar el html, se cargan los datos del usuario
onMounted(async () => {
    await userStore.fetchUser();
});
</script>

<template>
  <div class="profile-wrapper">
    
    <div v-if="userStore.loading" class="loading-state">
        <div class="spinner"></div> Cargando perfil...
    </div>

    <div v-else-if="userStore.user" class="profile-card">
      
      <div class="profile-header">
        <router-link to="/" class="back-home-btn" title="Volver al Inicio">
            ← Inicio
        </router-link>

        <div class="avatar-container" :class="{ 'editable': isEditing }" @click="isEditing ? triggerFileInput() : null">
            
            <img v-if="imagePreview" :src="imagePreview" class="avatar-img" />
            
            <img v-else-if="userStore.user.avatar_url" 
                 :src="userStore.user.avatar_url.startsWith('http') ? userStore.user.avatar_url : img_url + userStore.user.avatar_url" 
                 class="avatar-img" />
            
            <div v-else class="avatar-placeholder">
                {{ userStore.user.name.charAt(0).toUpperCase() }}
            </div>

            <div v-if="isEditing" class="avatar-overlay">
                <span>📷 Cambiar</span>
            </div>
        </div>
        
        <input type="file" ref="fileInput" @change="handleFileChange" style="display: none" accept="image/*">

        <div v-if="!isEditing" class="header-info">
            <h2 class="user-name">{{ userStore.user.name }} {{ userStore.user.surname }}</h2>
            
            <div class="role-badge-container">
                <span v-if="userStore.user.role === 'seller' || userStore.user.role === 'vendedor'" class="badge seller">
                    ✅ Vendedor Verificado
                </span>
                <span v-else class="badge member">Miembro de ProxiMarkt</span>
            </div>
        </div>
      </div>

      <div class="profile-body">
        
        <form v-if="isEditing" @submit.prevent="saveProfile" class="edit-form">
            <div class="form-group">
                <label>Nombre</label>
                <input v-model="form.name" type="text" required class="input-field" />
            </div>
            <div class="form-group">
                <label>Apellidos</label>
                <input v-model="form.surname" type="text" required class="input-field" />
            </div>
            <div class="form-group">
                <label>Email</label>
                <input v-model="form.email" type="email" required class="input-field" />
            </div>

            <div class="action-buttons">
                <button type="button" @click="toggleEdit" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>

        <div v-else class="info-view">
            <div class="info-row">
                <label>Email</label>
                <p>{{ userStore.user.email }}</p>
            </div>

            <div v-if="userStore.user.seller" class="store-box">
                <div class="store-icon">🏪</div>
                <div>
                    <p class="store-name">{{ userStore.user.seller.store_name }}</p>
                    <small class="store-nif">NIF: {{ userStore.user.seller.nif }}</small>
                </div>
            </div>
            
            <div class="info-row">
                <label>Miembro desde</label>
                <p>{{ formatDate(userStore.user.created_at) }}</p>
            </div>

            <hr class="divider">

            <div class="main-actions">
                <button @click="toggleEdit" class="btn btn-outline">✏️ Editar Perfil</button>
                
                <template v-if="userStore.user.role === 'seller' || userStore.user.role === 'vendedor'">
                    
                    <button @click="goToInventory" class="btn btn-inventory">
                        📦 Gestionar Inventario
                    </button>
                    
                    <button @click="goToPickupPoints" class="btn btn-pickup">
                        📍 Gestionar Puntos de Recogida
                    </button>

                </template>

                <button v-if="userStore.user.role !== 'seller' && userStore.user.role !== 'vendedor'" 
                        @click="showSellerModal = true" 
                        class="btn btn-become-seller">
                    🚀 ¡Quiero Vender!
                </button>

                <button @click="handleLogout" class="btn btn-danger">🚪 Cerrar Sesión</button>
            </div>
        </div>
      </div>
    </div>

    <div v-if="showSellerModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✨ Crea tu Tienda</h3>
                <p>Rellena estos datos para empezar a vender.</p>
            </div>
            <form @submit.prevent="becomeSeller" class="modal-body">
                <div class="form-group">
                    <label>Nombre de la Tienda</label>
                    <input v-model="sellerForm.store_name" type="text" required placeholder="Ej: Huerto de Juan" class="input-field">
                </div>
                <div class="form-group">
                    <label>NIF / DNI</label>
                    <input v-model="sellerForm.nif" type="text" required placeholder="12345678X" class="input-field">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea v-model="sellerForm.description" rows="3" placeholder="Vendo frutas ecológicas..." class="input-field"></textarea>
                </div>

                <div v-if="userStore.error" class="error-alert">
                    {{ userStore.error }}
                </div>

                <div class="modal-actions">
                    <button type="button" @click="showSellerModal = false" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Tienda</button>
                </div>
            </form>
        </div>
    </div>

  </div>
</template>

<style scoped>
/* Estructura General */
.profile-wrapper { display: flex; justify-content: center; padding: 40px 20px; background-color: #f8fafc; min-height: 90vh; font-family: 'Segoe UI', sans-serif; }
.profile-card { background: white; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; display: flex; flex-direction: column; }
.profile-header { background: linear-gradient(to right, #f1f5f9, #e2e8f0); padding: 30px 20px; text-align: center; position: relative; }
.back-home-btn { position: absolute; top: 15px; left: 15px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 0.9rem; }
.back-home-btn:hover { color: #334155; }

/* Avatar */
.avatar-container { width: 100px; height: 100px; margin: 0 auto 15px; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); position: relative; overflow: hidden; background: #cbd5e1; }
.avatar-container.editable { cursor: pointer; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; color: white; background-color: #3b82f6; }
.avatar-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; opacity: 0; transition: opacity 0.2s; }
.avatar-container:hover .avatar-overlay { opacity: 1; }

.user-name { margin: 0; color: #1e293b; font-size: 1.4rem; }
.role-badge-container { margin-top: 8px; }
.badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge.seller { background-color: #dcfce7; color: #166534; }
.badge.member { background-color: #f1f5f9; color: #64748b; }

/* Cuerpo */
.profile-body { padding: 25px 30px; }
.info-row { margin-bottom: 15px; }
.info-row label { display: block; font-size: 0.85rem; color: #94a3b8; font-weight: 600; margin-bottom: 2px; }
.info-row p { margin: 0; font-size: 1rem; color: #334155; font-weight: 500; }

/* Caja de Tienda */
.store-box { background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.store-icon { font-size: 1.5rem; }
.store-name { margin: 0; font-weight: bold; color: #166534; }
.store-nif { color: #15803d; }
.divider { border: 0; border-top: 1px solid #f1f5f9; margin: 20px 0; }

/* Botones y Estilos */
.main-actions { display: flex; flex-direction: column; gap: 10px; }
.btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
.btn:hover { transform: translateY(-2px); }

.btn-primary { background-color: #3b82f6; color: white; }
.btn-primary:hover { background-color: #2563eb; }
.btn-secondary { background-color: #e2e8f0; color: #475569; }
.btn-secondary:hover { background-color: #cbd5e1; }
.btn-outline { background-color: white; border: 1px solid #cbd5e1; color: #475569; }
.btn-outline:hover { background-color: #f8fafc; border-color: #94a3b8; }

.btn-inventory { background-color: #e0f2fe; color: #0284c7; }
.btn-inventory:hover { background-color: #bae6fd; }

/* NUEVO ESTILO: Botón de Puntos de Recogida (Tono naranja/amarillo suave) */
.btn-pickup { background-color: #fef3c7; color: #d97706; }
.btn-pickup:hover { background-color: #fde68a; }

.btn-become-seller { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
.btn-become-seller:hover { box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3); }

.btn-success { background-color: #10b981; color: white; }
.btn-danger { background-color: #fee2e2; color: #dc2626; margin-top: 10px; }
.btn-danger:hover { background-color: #fecaca; }

/* Formularios */
.form-group { margin-bottom: 15px; }
.input-field { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; color: #1e293b; }
.input-field:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.action-buttons { display: flex; gap: 10px; margin-top: 20px; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-content { background: white; width: 90%; max-width: 400px; border-radius: 12px; box-shadow: 0 20px 25px rgba(0,0,0,0.1); overflow: hidden; }
.modal-header { padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.modal-header h3 { margin: 0; color: #1e293b; }
.modal-header p { margin: 5px 0 0; color: #64748b; font-size: 0.9rem; }
.modal-body { padding: 20px; }
.modal-actions { display: flex; gap: 10px; margin-top: 10px; }

/* Loading Spinner */
.loading-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 50vh; color: #64748b; }
.spinner { width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 10px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* Mensaje de error */
.error-alert {
    background-color: #fee2e2; /* Fondo rojito claro */
    color: #dc2626; /* Texto rojo oscuro */
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    border: 1px solid #f87171;
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>