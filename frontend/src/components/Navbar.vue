<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '@/stores/auth';
const authStore = useAuthStore();

const img_url = 'http://localhost:8000/storage/';

onMounted(() =>{
  authStore.fetchUser();
});
</script>

<template>
    <header class="main-header">
      <div class="header-content">
        <nav class="nav-left">
          <router-link to="/" class="nav-item active">Inicio</router-link>
          <router-link to="/marketplace" class="nav-item">Marketplace</router-link>
          <router-link to="/my-purchases" class="nav-item">Mis Compras</router-link>
          <router-link to="/my-sales" class="nav-item">Mis Ventas</router-link>
        </nav>

        <div class="logo-container">
          <h1 class="site-title">Proxi<span class="highlight">Markt</span></h1>
        </div>

        <div class="user-zone">
          <div v-if="authStore.isLogged">
            <router-link to="/perfil" class="profile-pill">
              <span class="user-name">{{ authStore.user.name }}</span>
              
              <img 
                v-if="authStore.user.avatar_url" 
                :src="authStore.user.avatar_url.startsWith('http') ? authStore.user.avatar_url : img_url + authStore.user.avatar_url" 
                class="avatar-circle-img" 
                alt="Avatar"
              >
              <div v-else class="avatar-circle">
                {{ authStore.user.name.charAt(0).toUpperCase() }}
              </div>
            </router-link>
          </div>
          <div v-else>
            <div class="auth-buttons">
                <router-link to="/login" class="login-link">Entrar</router-link>
                <router-link to="/register" class="register-btn">Crear Cuenta</router-link>
            </div>
          </div>
        </div>
      </div>
    </header>
</template>

<style scoped>

.main-header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 0 20px; position: sticky; top: 0; z-index: 100; }
.header-content { max-width: 1200px; margin: 0 auto; height: 70px; display: flex; align-items: center; justify-content: space-between; }

.nav-left { display: flex; gap: 20px; }
.nav-item { text-decoration: none; color: #64748b; font-weight: 500; transition: color 0.2s; }
.nav-item:hover, .nav-item.active { color: #3b82f6; }

.logo-container .site-title { margin: 0; font-size: 1.5rem; color: #1e293b; }
.highlight { color: #10b981; }

.user-zone { display: flex; align-items: center; gap: 15px; }
.auth-buttons { display: flex; gap: 15px; align-items: center; }
.login-link { text-decoration: none; color: #64748b; font-weight: 600; }
.register-btn { background-color: #3b82f6; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
.register-btn:hover { background-color: #2563eb; }

.profile-pill { display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 5px 10px 5px 15px; border-radius: 30px; text-decoration: none; color: #334155; font-weight: 600; transition: background 0.2s; }
.profile-pill:hover { background: #e2e8f0; }
.avatar-circle, .avatar-circle-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.avatar-circle { background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }

</style>