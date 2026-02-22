<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore();

// Esta es una forma más cómoda de crear las variables reactivas.
// En lugar de crearlas por separado, se engloban dentro de una constante
// para hacer más fácil su envío a Laravel
const form = ref({ email: '', password: '' });
const submitting = ref(false);

const handleLogin = async () => {
  submitting.value = true;
  // En el store, como se hace un throw, es necesario poner también un try catch
  // en la vista para que el error no se salga de control
  try{
    await authStore.login(form.value);

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
      <h2 class="auth-title">Bienvenido de nuevo!</h2>
      <p class="auth-subtitle">Entra a tu cuenta de ProxiMarkt</p>

      <form @submit.prevent="handleLogin" class="auth-form">
        
        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="example@mail.com" required />
        </div>

        <div class="form-group">
          <label>Contraseña</label>
          <input v-model="form.password" type="password" placeholder="******" required />
        </div>

        <p v-if="authStore.error" style="color: #DC2626; text-align: center; margin-bottom: 1rem;">
            {{ authStore.error }}
        </p>
        
        <button type="submit" :disabled="submitting" class="submit-btn">
          {{submitting ? "Iniciando..." : "Iniciar sesión"}}
        </button>
      </form>

      <div class="auth-footer">
        <p>¿No tienes cuenta? <router-link to="/register">Registrate aquí</router-link></p>
      </div>
    </div>
  </div>
</template>