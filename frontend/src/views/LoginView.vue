<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore();

// Esta es una forma más cómoda de crear las variables reactivas.
// En lugar de crearlas por separado, se engloban dentro de una constante
// para hacer más fácil su envío a Laravel
const form = ref({ email: '', password: '' });

const handleLogin = async () => {
  await authStore.login(form.value);
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

        <button type="submit" class="submit-btn">Entrar</button>
      </form>

      <div class="auth-footer">
        <p>¿No tienes cuenta? <router-link to="/register">Registrate aquí</router-link></p>
      </div>
    </div>
  </div>
</template>