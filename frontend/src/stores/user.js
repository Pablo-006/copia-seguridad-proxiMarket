// En los archivos store, se almacena la lógica que tiene que ver con peticiones a la base de datos
import api from "@/api/axios";
import { defineStore } from "pinia";

export const useUserStore = defineStore('user', {
   state: () => ({
        // Variable para guardar los datos del usuario
        user: null,
        // Variable para hacer un estado de carga antes de cargar el usuario
        loading: false,
        // Variable para mensajes de error
        error: ''
   }), 

   actions: {
        async fetchUser(){
            this.loading = true;
            try{
                const response = await api.get('/user');
                this.user = response.data;
            }catch(e){
                this.error = "Error al obtener el usuario";
            }finally{
                this.loading = false;
            }
        }, 

        async updateProfile(formData) {
            // A. Encendemos el estado de carga y limpiamos errores antiguos
            this.loading = true;
            this.error = '';

            try {
                // B. Hacemos la petición POST a Laravel pasándole la "caja" (formData)
                // ¡IMPORTANTE! Cuando enviamos archivos, hay que avisar a Laravel con las cabeceras (headers)
                const response = await api.post('/user/update', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                // Preguntar sobre la nueva sintaxis del api.post y para qué sirven estas cabeceras
                // Respuesta: primero, se pasa el endpoint que conecta con la función deseada, luego, se le
                // pasa la variable que va a recibir por parámetro (formData) y por último, debido
                // a que esta variable incluye imágenes, normalmente se trata de convertir la info a JSON,
                // pero no se puede hacer esto con imágenes, así que se definen esas cabeceras para hacer
                // saber al servidor que va a recibir texto plano y archivos como imágenes

                // C. Laravel nos devuelve el usuario actualizado (response.data.user).
                // Guárdalo en la variable del state correspondiente para que la vista se entere.
                // TU CÓDIGO AQUÍ: ...
                this.user = response.data.user;
                

                return true; // Éxito
            } catch (e) {
                // D. Si falla, guardamos el mensaje de error en el state
                // TU CÓDIGO AQUÍ: ... (ej: this.error = error.response?.data?.message || "Error al actualizar")
                this.error = e.response?.data?.message || "Error al actualizar el perfil";

                // Preguntar acerca de como se accede al mensaje de error más detalladamente.
                // Respuesta: los interrogante son como un if else. Si el error trae consigo una respuesta,
                // se continua al siguiente nivel (si la respuesta tiene datos, entonces saca el mensaje). Si por
                // lo que sea el error no trae consigo esta información, se muestra el mensaje entre comillas
                
                return false; // Fallo
            } finally {
                // E. Apagamos el estado de carga
                // TU CÓDIGO AQUÍ: ...
                this.loading = false;
            }
        },

        async becomeSeller(sellerData){
            try{
                // Se realiza la petición al servidor con la información que ha introducido
                // el usuario para ser un vendedor
                const response = await api.post('/user/become-seller', sellerData);

                // Se le asigna la info al usuario en la vista para visualizarla
                this.user = response.data.user;

                return true;
            }catch(e){
                this.error = e.response?.data?.message || "Error al convertir a vendedor";
                return false;
            }
        },
   }
});


const becomeSeller = async () => {
    try {

        const response = await api.post('/user/become-seller', sellerForm.value);
        
        userStore.user = response.data.user;
        // Se sale del formulario para rellenar los datos del vendedor y así
        // mostrar la información que ha introducido
        showSellerModal.value = false;
        alert("¡Felicidades! Tu tienda ha sido creada.");
    } catch (error) {
        console.error(error);
        const msg = error.response?.data?.message || "Error al crear la tienda.";
        alert("Error: " + msg);
    }
};