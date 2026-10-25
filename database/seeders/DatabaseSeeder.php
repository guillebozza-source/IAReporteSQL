<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // 1. Crear 5 Categorías fijas
        $categories = collect(['Electrónica', 'Ropa', 'Hogar', 'Deportes', 'Libros'])->map(function ($name) {
            return Category::create(['name' => $name]);
        });

        // 2. Crear 20 productos repartidos entre las categorías
        foreach ($categories as $category) {
            Product::factory(4)->create([
                'category_id' => $category->id
            ]);
        }

        // 3. Crear 30 Clientes
        $customers = Customer::factory(30)->create();

        // 4. Crear Pedidos con fechas de los últimos 6 meses
        foreach ($customers as $customer) {
            $cantidadPedidos = rand(1, 4);

            for ($i = 0; $i < $cantidadPedidos; $i++) {
                // Fecha aleatoria entre hoy y hace 180 días
                $fechaAleatoria = now()->subDays(rand(1, 180));

                $order = Order::create([
                    'customer_id' => $customer->id,
                    'status' => fake()->randomElement(['completed', 'completed', 'completed', 'cancelled', 'pending']),
                    'total' => 0,
                    'created_at' => $fechaAleatoria,
                    'updated_at' => $fechaAleatoria,
                ]);

                // Asignar entre 1 y 3 productos al pedido
                $productos = Product::inRandomOrder()->take(rand(1, 3))->get();
                $totalOrden = 0;

                foreach ($productos as $producto) {
                    $cantidad = rand(1, 3);
                    $precioUnitario = $producto->price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $producto->id,
                        'quantity' => $cantidad,
                        'unit_price' => $precioUnitario,
                        'created_at' => $fechaAleatoria,
                        'updated_at' => $fechaAleatoria,
                    ]);

                    $totalOrden += ($precioUnitario * $cantidad);
                }

                // Actualizar el total recalculado de la orden
                $order->update(['total' => $totalOrden]);
            }
        }
    }
}
