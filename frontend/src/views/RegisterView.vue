<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/axios';
const router = useRouter();

// Constante con todos los datos reactivos del formulario
const form = ref({
  name: '',
  surname: '',
  email: '',
  password: '',
  password_confirmation: ''
});

// Constante reactiva de error para mostrar mensajes de error
const error = ref('');

// Función que se encarga del registro del usuario
const handleRegister = async () => {
  // Resetear el valor de la constante del error, para que no se acumulen los mensajes de error
  error.value = '';

  // Se comprueba primero si las contraseñas introducidas coinciden o no. Cómo no se necesita hacer
  // ninguna petición al servidor para esto, no es necesario ponerlo en el bloque try-catch, por eso
  // se pone primero
  if (form.value.password !== form.value.password_confirmation) {
    error.value = "Las contraseñas no coinciden";
    return;
  }

  try {
    // Se realiza la petición al servidor y se recogen los datos de respuesta 
    const response = await api.post('/register', form.value);
    
    // Console log para pruebas (al desplegar el servidor, no deberían de verse. Si se ven, quitarlos)
    console.log("Registro exitoso:", response.data);

    // Guardar el token para permitir al usuario navegar por la web
    localStorage.setItem('auth_token', response.data.access_token);
    
    // Redireccionar a la página de Inicio
    router.push('/'); 
    
  } catch (e) {
    console.error("Error al registrar:", e);
    if(e.response && e.response.data.message){
      // Si se ha producido un error relacionado con el registro del usuario, se recoge el mensaje de error y se muestra
      error.value = e.response.data.message;
    }else{
      // Si es algún otro error, se muestra un mensaje más genérico
      error.value = "Error al crear la cuenta";
    }
  }
};
</script>

<template>
  <div class="auth-container">
    <div class="auth-card">
      <h2 class="auth-title">Crear Cuenta</h2>
      <p class="auth-subtitle">Únete a ProxiMarkt hoy mismo</p>

      <p v-if="error" style="color: red;">{{ error }}</p>

      <form @submit.prevent="handleRegister" class="auth-form">
        
        <div class="form-group">
          <label>Nombre</label>
          <input v-model="form.name" type="text" placeholder="Nombre" required />
        </div>

        <div class="form-group">
          <label>Apellidos</label>
          <input v-model="form.surname" type="text" placeholder="Apellido" required>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="ejemplo@mail.com" required />
        </div>

        <div class="form-group">
          <label>Contraseña</label>
          <input v-model="form.password" type="password" placeholder="******" required />
        </div>

        <div class="form-group">
          <label>Confirmar Contraseña</label>
          <input v-model="form.password_confirmation" type="password" placeholder="******" required />
        </div>

        <button type="submit" class="submit-btn">Registrarse</button>
      </form>

      <div class="auth-footer">
        <p>¿Ya tienes cuenta? <router-link to="/login">Entrar aquí</router-link></p>
      </div>
    </div>
  </div>
</template>