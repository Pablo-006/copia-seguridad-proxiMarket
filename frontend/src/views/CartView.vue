<script setup>
import { onMounted, ref } from 'vue';
import api from '@/api/axios';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';
import router from '@/router';
const cartStore = useCartStore();

const pickupPoints = ref([]);
const selectedPickupId = ref(null);
const loading = ref(false);
const submitting = ref(false);
const error = ref('');

const confirmOrder = async () => {
    error.value = '';
    if(!selectedPickupId.value){
        // No quiero usar alert, puedo devolver un texto para mostrarlo en la vista o necesito una constante?
        // Sí, y la mejor manera para ello es usar la constante reactiva del error
        error.value = "No has seleccionado ningún punto de entrega, por favor, seleccione uno"; 
        return;
    }

    submitting.value = true;

    // Los datos que se le pasan al controlador para ser validados, tienen
    // que coincidir en nombre
    const data = {
        items: cartStore.items,
        pickup_point_id: selectedPickupId.value
    };

    try{
        // Cómo muestro un mensaje de "Se ha realizado el pedido" si redirecciono al usuario
        // a otra página? Significa que la constante response no me sirve para nada?
        // Hay 2 opciones para mostrar el mensaje. Uno es usar un setTimeout para mostrar el mensaje
        // y luego redireccionar al usuario, y la otra manera es mostrar el mensaje en la página destino
        // Opino que es mejor usar el setTimeout.

        // En cuanto al response, si quisieramos revisar los detalles del pedido que hemos hecho,
        // sí que sería útil, pero como se pretende devolver al usuario a la vista general de pedidos, no se debe poner
        
        // NO SE PONE, DIRECTAMENTE SE HACE LA PETICIÓN AL SERVIDOR -> const response = 
        
        await api.post(`/orders/store/`, data);

        cartStore.clearCart();

        router.push('/my-purchases');
    }catch(e){
        error.value = e.response?.data?.message || 'Error al realizar el pedido';
    }finally{
        submitting.value = false;
    }
} 

onMounted(async () => {
    if(cartStore.isEmpty){
        return;
    }

    // Aquí se están cargando tanto los productos del carrito como los puntos destino
    // Entonces, por qué el loading solamente se usa en el template para mostrar el mensaje
    // "Cargando" con los puntos destino?

    // Es porque los productos del carrito están cargados en la RAM, mientras que los puntos
    // de destino vienen del servidor, por lo que tardan un poco más en llegar
    loading.value = true;

    try{
        const sellerId = cartStore.items[0].seller_id;

        const response = await api.get(`/seller/pickup-points/${sellerId}`);
        pickupPoints.value = response.data;
    }catch(e){
        error.value = "Error al obtener los puntos destino";
    }finally{
        loading.value = false;
    }

});
</script>

<template>
    <div class="cart-page">
        <h2>Mi Pedido</h2>

        <div v-if="cartStore.isEmpty" class="empty-state">
            <p>No tienes productos en la cesta</p>
            <router-link to="/marketplace">Volver a la tienda</router-link>
        </div>

        <div v-else class="cart-content">
            <section class="items-summary">
                <div v-for="item in cartStore.items" :key="item.id" class="cart-item">
                    <span>{{ item.title }}</span>
                    <span>{{ item.quantity }} x {{ item.price }}€</span>
                    <p>Subtotal: <strong>{{ (item.price * item.quantity).toFixed(2) }}€</strong></p>
                </div>
                <div class="cart-total">
                    <p>Total de la compra: {{ cartStore.totalPrice }}€</p>
                </div>
            </section>

            <section class="pickup-selection">
                <div v-if="loading">Cargando puntos de entrega...</div>

                <div v-else class="points-list">
                    <!-- Estoy tratando de confirmar un pedido de un vendedor que no tiene puntos de entrega pero no se muestra el aviso de abajo -->
                    <!-- En JavaScript, un array vacío no es falso, por lo que hay que comprobar su longitud  -->
                    <div v-if="pickupPoints.length === 0">
                        <p>Este vendedor no tiene puntos de entrega establecidos, no se puede realizar la compra</p>
                    </div>

                    <div v-else>
                        <h3>¿Dónde quieres recogerlo?</h3>
                        <label v-for="point in pickupPoints" :key="point.id" class="point-option">

                            <input 
                                type="radio"
                                :value="point.id"
                                v-model="selectedPickupId"
                                name="pickup"
                            >

                            <div class="point-info">
                                <p class="address">{{ point.address }}</p>
                                <p class="city">{{ point.city }} ({{ point.postal_code }})</p>
                            </div>
                        </label>
                    </div>

                </div>
            </section>

            <div v-if="error">
                <p>{{ error }}</p>
            </div>

            <!-- Por qué disabled tiene ':'? Es porque es una propiedad que reacciona según al valor de una constante como ':src' en las imágenes? -->
             <!-- Si no se ponen los ':' HTML interpreta el valor de la propiedad como texto literal. Si se ponen, se da a entender que son variables  -->
              <!-- Quito el v-if para comprobar si se ha seleccionado un punto de recogida para poder mostrar el error definido en la función -->
            <button
                @click="confirmOrder"
                :disabled="submitting"
            >
                <p>{{ submitting ? "Procesando..." : "Confirmar pedido" }}</p>
            </button>
        </div>
    </div>
</template>

<style scoped>

</style>