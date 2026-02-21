// Este fue uno de los primeros store, así que aquí voy a hacer mención a la estructura
// de un store y explicar también un poco todo de manera genérica
import { defineStore } from 'pinia';
import api from '@/api/axios';

export const useAuthStore = defineStore('auth', {
    // 1. EL STATE - es un objeto que recoge todas las variables que son necesarias para todas
    // o la gran mayoría de las vistas, es decir, para el menú de navegación que está presente siempre, va a necesitar
    // siempre los datos del usuario así como el token de inicio de sesión
    state: () => ({
        user: null,
        token: null,
        error: ''
    }),

    // 2. GETTERS - aunque no hay getters en este store ya que no son necesarios 
    // (en caso de serlo más adelante, actualizaré este comentario), daré una vista
    // de lo que son (hay un ejemplo en el store de cart.js). Los getters son funciones
    // que se comportan de la misma manera que las funciones computed. Cuando una variable cambia su
    // valor, estado, etc... estas funciones se ejecutan automáticamente mostrando así el cambio en la vista
    // donde se apliquen sin necesidad de recargar página. El uso que pueden tener los getters es principalmente para
    // hacer cálculos (precio, cantidad de items en una lista, etc), hacer nuevas variables
    // a partir de las existentes para mostrar un determinado cambio (como es el caso de los filtros, que no actualizan una lista
    // sino que crean una nueva con los productos que coinciden con el filtro) y por último, hacer validaciones rápidas
    // como comprobar si una lista esta llena o vacía, o si una variable booleana es verdadera o falsa 
    // [PARA MÁS EJEMPLOS, CONSULTAR EL CART.JS]

    getters: {
        isLogged(state){
            return state.token !== null;
        }
    },

    // 3. LAS ACTIONS - son funciones que aplican lógica de la web, principalmente, aquella lógica
    // que se base en hacer peticiones al servidor y realizar varias cosas con la información que obtengan
    actions: {
        async login(credentials) {
            // Si se van a mostrar errores, siempre limpiar la variable del error, 
            // para no acumular mensajes de errores anteriores
            this.error = '';

            // Englobar en un try catch toda aquella lógica peligrosa (principalmente cuando
            // se hace una petición al servidor)
            try {
                // Se ejecuta la función de inicio de sesión del controlador y se guarda la respuesta
                // en una variable para así obtener el token y el usuario
                const response = await api.post('/login', credentials);

                // Se guarda el token de inicio de sesión
                this.token = response.data.access_token;

                // Se guarda el usuario que ha iniciado sesión
                this.user = response.data.user;

                // Se guarda el token en el almacenamiento local/RAM del navegador para no perder
                // el inicio de sesión (hasta que se cierre la sesión voluntariamente)
                localStorage.setItem('auth_token', this.token);
                // Luego de iniciar sesión, se redirecciona al usuario a la página de inicio
                // (No se supone que las redirecciones se hacen siempre en las vistas y nunca en los store?)
                
                return true; // Éxito
            } catch (e) {
                this.error = e.response?.data?.message || "Error al iniciar sesión"

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
                this.user = null;
                this.token = null;
            }
        },
        async fetchUser(){
            
        }
    }
});