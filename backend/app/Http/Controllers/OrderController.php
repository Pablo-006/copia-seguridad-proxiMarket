<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    

    // 📦 2. HISTORIAL DE PEDIDOS
    public function myOrders()
    {
        $orders = Order::where('buyer_id', Auth::id())
                        ->with(['seller', 'lines.product', 'pickupPoint']) 
                        ->latest()
                        ->get();

        return response()->json($orders);
    }

    public function store(Request $request){
        // 1. Validamos solo 3 cosas: Qué producto, cuántos y dónde se recoge
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:1',
            'pickup_point_id' => 'required|exists:pickup_points,id',
        ]);

        $pedidoFinal = DB::transaction(function () use ($validated){
            $producto = Product::findOrFail($validated['product_id']);
            $cantidad = $validated['quantity'];

            // 2. Comprobamos stock
            if($cantidad > $producto->stock){
                abort(422, "La cantidad demandada supera el stock disponible.");
            }

            // 3. Calculamos precio total de esta única línea
            $totalPrice = $producto->price * $cantidad;

            // 4. Creamos el pedido (Estado 'new', el pago se hará en mano)
            $pedido = Order::create([
                'buyer_id' => Auth::id(),
                'seller_id' => $producto->seller_id,
                'pickup_point_id' => $validated['pickup_point_id'],
                'status' => 'new',
                'total_price' => $totalPrice
            ]);

            // 5. Creamos la única línea de pedido
            OrderLine::create([
                'order_id' => $pedido->id,
                'product_id' => $producto->id,
                'quantity' => $cantidad,
                'weight_at_moment' => ($producto->estimated_weight * $cantidad),
                'price_at_moment' => ($cantidad * $producto->price)
            ]);

            // 6. Restamos el stock
            // decrement() a parte de hacer la resta del stock, se asegura
            // de que dos usuarios no puedan realizar una compra de manera simultánea
            // cuando quedan pocas existencias de un producto, de manera que así un cliente
            // realizaría la compra y al otro cliente se le denegaría debido a que no quedarían
            // existencias
            $producto->decrement('stock', $cantidad);

            return $pedido;
        });

        return response()->json($pedidoFinal, 201);
    }
}