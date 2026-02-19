import { defineStore } from 'pinia';
// import axios ?

export const useCartStore = defineStore('cart', {
    state: () => ({
       items: [], 
    }),

    // En los getters para acceder a las variables de state, se pasa por parámetro
    // el objeto state (es el estándar de pinia por tema de que las funciones getter
    // son funciones flecha y el this no funciona bien en este tipo de funciones, 
    // por lo que al pasar el state por parámetro, pinia permite a estas funciones
    // usar las variables del state)
    getters: {
        // La función reduce es como hacer un bucle for. Tenemos las variables total e item
        // La variable item representa cada producto de la lista, y la variable total
        // es el total de productos que vas a añadir al carrito. Lo que hace esta función es recorrer
        // la lista y suma la cantidad que se ha añadido de cada item con el total
        totalItems: (state) => {
            return state.items.reduce((total, item) => total + item.quantity, 0);
        },

        // Muestra el total de la compra
        totalPrice: (state) => {
            const total = state.items.reduce((acc, item) => acc + (item.price * item.quantity), 0);
            // la función toFixed se refiere a la cantidad de decimales que tendrá el valor sobre
            // el que se aplica la función
            return total.toFixed(2);
        },

        // Devuelve true o false dependiendo de si el carrito está lleno o vacío
        isEmpty: (state) => {
            return state.items.length === 0;
        },
    },

    actions: {
        // Guardar el carrito en el almacenamiento local, para que cada vez
        // que se recargue la página no se pierdan los productos
        saveToLocalStorage(){
            localStorage.setItem('cart', JSON.stringify(this.items));
        },

        // Cargar los datos del carrito guardado en el almacenamiento local
        // para poder mostrarlos cada vez que se recargue la página
        loadFromLocalStorage(){
            const saved = localStorage.getItem('cart');
            if(saved){
                this.items = JSON.parse(saved);
            }
        },        

        addToCart(product, quantity){
            const quantityToAdd = parseInt(quantity);
            if (quantityToAdd <= 0 || isNaN(quantityToAdd)){
                return;
            }

            const existsItem = this.items.find(item => item.id === product.id);

            if(existsItem){
                existsItem.quantity += quantityToAdd;
            }else{
                this.items.push({
                    ...product,
                    quantity: quantityToAdd
                });
            }
            // Cómo se accede al localStorage? Hice una vez un carrito de prueba pero no usé esta función nunca y funcionaba igual
            this.saveToLocalStorage();
        },

        removeFromCart(productId){
            this.items = this.items.filter(item => item.id !== productId);
            this.saveToLocalStorage();
        },

        clearCart(){
            this.items = [];
            this.saveToLocalStorage();
        },
    }
});