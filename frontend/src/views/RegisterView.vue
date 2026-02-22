<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore();

// Constante con todos los datos reactivos del formulario
const form = ref({
  name: '',
  surname: '',
  email: '',
  password: '',
  password_confirmation: ''
});

const submitting = ref(false);
const errorLocal = ref('');
authStore.error = '';

// Función que se encarga del registro del usuario
const handleRegister = async () => {
  errorLocal.value = '';
  submitting.value = true;
  // Se comprueba primero si las contraseñas introducidas coinciden o no. Cómo no se necesita hacer
  // ninguna petición al servidor para esto, no es necesario ponerlo en el bloque try-catch, por eso
  // se pone primero
  if (form.value.password !== form.value.password_confirmation) {
    submitting.value = false;
    errorLocal.value = "Las contraseñas no coinciden";
    return;
  }

  try{
    await authStore.register(form.value);

    router.push('/'); 
  }catch(e){

  }finally{
    submitting.value = false;
  }
};
</script>

<template>
  <div class="auth-container">
    <div class="auth-card">
      <h2 class="auth-title">Crear Cuenta</h2>
      <p class="auth-subtitle">Únete a ProxiMarkt hoy mismo</p>

      <p v-if="errorLocal" style="color: red;">{{ errorLocal }}</p>
      <p v-if="authStore.error" style="color: red;">{{ authStore.error }}</p>

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

        <button type="submit" :disabled="submitting" class="submit-btn">{{submitting ? "Creando cuenta..." : "Registrarse"}}</button>
      </form>

      <div class="auth-footer">
        <p>¿Ya tienes cuenta? <router-link to="/login">Entrar aquí</router-link></p>
      </div>
    </div>
  </div>
</template>