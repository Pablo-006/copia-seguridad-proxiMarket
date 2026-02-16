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
        }
   }
});
