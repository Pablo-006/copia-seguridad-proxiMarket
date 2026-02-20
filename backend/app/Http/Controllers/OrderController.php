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
    // 🛒 1. FUNCIÓN PARA COMPRAR
    public function store(Request $request)
    {
        // 1. Validamos datos de entrada
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
            'pickup_id'  => 'required|exists:pickup_points,id' // Obligatorio Sprint 4
        ]);

        // 2. Buscamos el producto
        $product = Product::findOrFail($request->product_id);

        // 3. Calculamos total_pricees
        $total_price = $product->price * $request->quantity;
        $sellerId = $product->seller_id; 

        // 4. Guardamos todo dentro de una transacción (si falla algo, no se guarda nada)
        return DB::transaction(function () use ($request, $product, $total_price, $sellerId) {
            
            // A. CREAR CABECERA DEL PEDIDO (Tabla 'orders')
            // Nota: Asegúrate de que en tu BD la columna sea 'total_price'. 
            // Usamos 'total_price' basándonos en la última migración corregida.
            $order = Order::create([
                'buyer_id'  => Auth::id(),
                'seller_id' => $sellerId,
                'pickup_id' => $request->pickup_id,
                'status'    => 'pending',
                'total_price'     => $total_price, 
            ]);

            // B. CREAR LÍNEA DE PEDIDO (Tabla 'order_lines')
            // Aquí usamos los nombres EXACTOS de tu modelo OrderLine
            OrderLine::create([
                'order_id'         => $order->id,
                'product_id'       => $product->id,
                'quantity'         => $request->quantity,
                
                // CORRECCIÓN 1: Usamos el nombre correcto de tu modelo
                'price_at_moment'  => $product->price, 
                
                // CORRECCIÓN 2: Campo obligatorio en tu BD. Ponemos 1.0 por defecto.
                'weight_at_moment' => 1.0, 
                
                // 'real_weight' lo dejamos null de momento
            ]);

            return response()->json([
                'message' => '¡Pedido realizado correctamente! 🎉',
                'order'   => $order
            ], 201);
        });
    }

    // 📦 2. HISTORIAL DE PEDIDOS
    public function myOrders()
    {
        $orders = Order::where('buyer_id', Auth::id())
                        ->with(['seller', 'lines.product', 'pickupPoint']) 
                        ->latest()
                        ->get();

        return response()->json($orders);
    }

    // Renombrar luego a store, no quiero tocar la función store actual
    // (aunque ya no puede funcionar)
    public function crearPedido(Request $request){
        $validated = $request->validate([
            'items' => 'required|array',
            'pickup_point_id' => 'required|exists:pickup_points,id',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        // Se crea esta variable para luego poder devolver el objeto del pedido
        // y mostrar cierta información en la vista
        $pedidoFinal = DB::transaction(function () use ($validated){
            //Por qué user y id van con paréntesis?
            // Cuando tratamos con arrays, se acceden a las propiedades con $array['propiedad']
            // Cuando se trata de objetos, se accede con $objeto->'propiedad'
            $userId = Auth::user()->id; // Equivale a Auth::id()
            $totalPrice = 0;
            $sellerId = null;

            foreach($validated['items'] as $item){
                $productId = $item['id'];
                $cantidad = $item['quantity'];

                $productoReal = Product::findOrFail($productId);

                if(!$sellerId){
                    $sellerId = $productoReal->seller_id;
                }else if($sellerId !== $productoReal->seller_id){
                    // No sé como lanzar una excepción
                    // Sería devolver un mensaje del estilo "No puedes comprar productos de vendedores diferentes"
                    abort(422, 'No puedes comprar productos de vendedores diferentes');
                    // El código 422 es un error de "Entidad no procesable"
                }

                if($cantidad > $productoReal->stock){
                    // Lanzar excepción diciendo "La cantidad demandada es superior al stock del producto"
                    abort(422, "La cantidad demandada de $productoReal->name es superior al stock del producto");
                }

                $totalPrice += ($productoReal->price * $cantidad);
            }

            $pedido = Order::create([
                'buyer_id' => $userId,
                'seller_id' => $sellerId,
                'pickup_point_id' => $validated['pickup_point_id'],
                'status' => 'new',
                'total_price' => $totalPrice
            ]);

            foreach($validated['items'] as $item){
                $producto = Product::find($item['id']);
                OrderLine::create([
                    // Por qué aquí para acceder al id lo haces con -> y no ['id']?
                    // Respuesta arriba del todo de la función
                    'order_id' => $pedido->id,
                    'product_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'weight_at_moment' => ($producto->estimated_weight * $producto->quantity),
                    'price_at_moment' => ($producto->quantity * $producto->price)
                ]);

                $producto->decrement('stock', $item['quantity']);
            }

            return $pedido;
        });

        return response()->json($pedidoFinal, 201);
    }
}