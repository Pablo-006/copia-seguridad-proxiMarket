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

    // 2. GETTERS - Los getters son funciones que se comportan de la misma manera que las funciones computed. 
    // Cuando una variable cambia su valor, estado, etc... estas funciones se ejecutan automáticamente 
    // mostrando así el cambio en la vista donde se apliquen sin necesidad de recargar página. 
    // El uso que pueden tener los getters es principalmente para
    // hacer cálculos (precio, cantidad de items en una lista, etc), hacer nuevas variables
    // a partir de las existentes para mostrar un determinado cambio (como es el caso de los filtros, que no actualizan una lista
    // sino que crean una nueva con los productos que coinciden con el filtro) y por último, hacer validaciones rápidas
    // como comprobar si una lista esta llena o vacía, o si una variable booleana es verdadera o falsa 

    getters: {
        isLogged(state){
            // Se verifica que tanto el token como el usuario tengan valor, ya que si falla uno de los dos
            // el usuario no estaría totalmente verificado (si la variable user está null, luego en la vista
            // no se puede mostrar el nombre del usuario, ya que tarda unos segundos en obtener la info y no es instantáneo
            // como el token)
            return state.token !== null && state.user !== null;
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
                
            } catch (e) {
                this.error = e.response?.data?.message || "Error al iniciar sesión";
                // El throw sirve para cortar la ejecución de la función de la vista que llama a esta función. De esta manera todo lo que sigue 
                // a la ejecución de esta función, no se realiza
                throw e;
            }
        },
        async register(form){
            this.error = '';

            try{
                const response = await api.post('/register', form);

                localStorage.setItem('auth_token', response.data.access_token);
                this.user = response.data.user;
                this.token = response.data.access_token;
            }catch(e){
                this.error = "Credenciales incorrectas";
                console.log(this.error);
                throw e;
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
            const savedToken = localStorage.getItem('auth_token');

            if(savedToken){
                this.token = savedToken;

                try{
                    const response = await api.get('/user');
                    this.user = response.data;
                }catch(e){
                    this.token = null;
                    this.user = null;
                    localStorage.removeItem('auth_token');
                }
            }
        }
    }
});