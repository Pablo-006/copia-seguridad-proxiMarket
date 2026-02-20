<script setup>
import { onMounted, ref } from 'vue';
import api from '@/api/axios';
import { useCartStore } from '@/stores/cart';
const cartStore = useCartStore();

const pickupPoints = ref([]);
const selectedPickupId = ref(null);
const loading = ref(false);
const submitting = ref(false);
const error = ref('');

const confirmOrder = () => {
    if(!selectedPickupId){
        return; // No quiero usar alert, puedo devolver un texto para mostrarlo en la vista o necesito una constante?
    }

    submitting.value = true;

    const data = {
        products: cartStore.items,
        pickup_id: selectedPickupId
    };

    try{
        const response = api.post(`/orders/store/`, data);
    }catch(e){

    }finally{

    }
} 

onMounted(async () => {
    if(cartStore.isEmpty){
        return;
    }

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
                    <p>Total del producto: <strong>{{ (item.price * item.quantity).toFixed(2) }}</strong></p>
                </div>
                <div class="cart-total">
                    <p>Total de la compra: {{ cartStore.totalPrice }}€</p>
                </div>
            </section>

            <section class="pickup-selection">
                <h3>¿Dónde quieres recogerlo?</h3>

                <div v-if="loading">Cargando puntos de entrega...</div>

                <div v-else class="points-list">
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
            </section>

            <button></button>
        </div>
    </div>
</template>

<style scoped>

</style>