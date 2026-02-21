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
                    'weight_at_moment' => ($producto->estimated_weight * $item['quantity']),
                    'price_at_moment' => ($item['quantity'] * $producto->price)
                ]);

                $producto->decrement('stock', $item['quantity']);
            }

            return $pedido;
        });

        return response()->json($pedidoFinal, 201);
    }
}