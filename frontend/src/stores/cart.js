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
        // Preguntar sobre como funcionan y qué hacen estas funciones
        totalItems: (state) => {
            return state.items.reduce((total, item) => total + item.quantity, 0);
        },

        totalPrice: (state) => {
            const total = state.items.reduce((acc, item) => acc + (item.price * item.quantity), 0);
            return total.toFixed(2);
        },

        isEmpty: (state) => {
            state.items.length === 0;
        },
    },

    actions: {
        
    }
});