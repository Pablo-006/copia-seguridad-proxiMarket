import { defineStore } from 'pinia';
import api from '@/api/axios';
import router from '@/router';

export const useAuthStore = defineStore('auth', {
    // 1. EL STATE (Ya te lo doy hecho con la sintaxis correcta)
    state: () => ({
        user: null,
        token: null,
        error: ''
    }),

    // 2. LAS ACTIONS
    actions: {
        async login(credentials) {
            // A. Lo primero, limpia el error (pon la variable error del state a '')
            // TU CÓDIGO AQUÍ: ...
            this.error = '';
            try {
                // B. Haz la petición POST pasándole las credentials
                // TU CÓDIGO AQUÍ: const response = await ...
                const response = await api.post('/login', credentials);

                // C. Guarda el token de la respuesta en el state
                // Pista: la respuesta trae response.data.access_token
                // TU CÓDIGO AQUÍ: ...
                this.token = response.data.access_token;

                // D. Guarda el usuario de la respuesta en el state (response.data.user)
                // TU CÓDIGO AQUÍ: ...
                this.user = response.data.user;

                // (Te regalo la parte del localStorage y redirección para no complicarlo hoy)
                localStorage.setItem('auth_token', this.token);
                router.push('/');
                
                return true; // Éxito
            } catch (e) {
                // E. Si falla (error 422), guarda este mensaje en el state: "Credenciales incorrectas"
                // TU CÓDIGO AQUÍ: ...

                // Si se produce un error de código 422, es debido a credenciales incorrectas
                if (e.response && e.response.status === 422) {
                    this.error = 'Credenciales incorrectas';
                // En otro caso, se devuelve un mensaje más genérico
                } else {
                    this.error = 'Error de conexión. Inténtalo de nuevo.';
                }

                return false; // Fallo
            }
        },
        async logout(){
            try{
                // Se realiza la petición al servidor
                await api.post('/logout');
            }catch(e){
                this.error = "Error al cerrar sesión";
            }finally{
                // Se borra el token del almacenamiento local del navegador
                localStorage.removeItem('auth_token');
            }
        },
    }
});