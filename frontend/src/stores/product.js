import { defineStore } from "pinia";
import api from '@/api/axios';

export const useProductStore = defineStore('product', {
    state: () => ({
        products: [],
        loading: false,
        error: '',
    }),

    actions: {
        async getSellerProducts(){
            this.loading = true;
            try{
                const response = await api.get('/seller/my-products');
                this.products = response.data;
            }catch(e){
                // Si obtengo el mensaje de error del catch, ¿qué mensaje se mostrará?
                this.error = e.response?.data?.message || "Error al obtener los productos";
            }finally{
                this.loading = false;
            }
        },

        // Por qué igualas productId a null? Y además, cómo obtengo el id del producto desde un primer momento?
        // Se obtiene de la vista de la variable openModal al editar un producto
        // Se le da valor nulo ya que por defecto si se está creando y no editando,
        // no existe aún un id del producto
        async saveProduct(formData, isEditing, productId = null){
            try{
                if(isEditing){
                    formData.append('_method', 'PUT');
                    await api.post(`/products/${productId}`, formData, {
                        headers: {'Content-Type': 'multipart/form-data'}
                    });
                }else{
                    await api.post('/products', formData, {
                        headers: {'Content-Type': 'multipart/form-data'}
                    });
                }
                // Por qué se ejecuta esta función? Es para cerrar el modo edición y listar los productos?
                // El modo edición se gestiona desde la vista, no por ejecutar esta función se cierra.
                // Sin embargo, sí que sirve para actualizar el listado de productos y mostrarlos todos
                await this.getSellerProducts();
            }catch(e){
                this.error = e.response?.data?.message || "Error al guardar el producto";
            }
        },

        // Habría que crear en la vista una variable llamada confirm para que cuando quieras borrar un producto
        // te muestre un mensaje de "Seguro que quieres eliminar?" en vez de usar una alerta. Si introduce "Sí"
        // entonces ejecutar la función
        async deleteProduct(productId){
            try{
                await api.delete(`/products/${productId}`);
                await this.getSellerProducts();
            }catch(e){
                this.error = e.response?.data?.message || "Error al borrar el producto";
            }
        }
    }
});