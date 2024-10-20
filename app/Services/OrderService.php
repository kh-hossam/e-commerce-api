<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Exceptions\OrderProcessingException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function placeOrder($user, $products)
    {
        DB::beginTransaction();

        try {
            $order = Order::create(['user_id' => $user->id]);

            // get all products in the request from database once to optimize performance and avoid querying db for each product
            $dbProducts = Product::whereIn('id', collect($products)->pluck('product_id')->toArray())->get();
            foreach ($products as $index => $productData) {
                // this operation on already retrieved collection
                $product = $dbProducts->where('id', $productData['product_id'])->first();

                // Validate stock availability
                if ($product && $product->stock >= $productData['quantity']) {
                    $product->stock -= $productData['quantity'];
                    $product->save();

                    $order->products()->attach($product->id, ['quantity' => $productData['quantity']]);
                } else {
                    throw ValidationException::withMessages([
                        "products.$index.quantity" => ['Product stock insufficient or invalid product'],
                    ]);
                }
            }

            event(new OrderPlaced($order));

            DB::commit();

            return $order;

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new OrderProcessingException('Failed to place the order.', 0, $e);
        }

    }



    public function updateOrder(Order $order, array $products)
    {
        DB::beginTransaction();

        try {
            $products = collect($products);

            // Loop through current products in the order
            $order->load('products');
            foreach ($order->products as $existingProduct) {
                $newProductData = $products->firstWhere('product_id', $existingProduct->id);

                // 1- If the product exists in the new data, Check if the quantity has changed and adjust it
                if ($newProductData) {
                    if ($newProductData['quantity'] != $existingProduct->pivot->quantity) {
                        // Restore the previous stock first
                        $existingProduct->stock += $existingProduct->pivot->quantity;

                        // Deduct the new quantity
                        if ($existingProduct->stock >= $newProductData['quantity'])
                        {
                            $existingProduct->stock -= $newProductData['quantity'];
                            $existingProduct->save();

                            // Update the pivot table with the new quantity
                            $order->products()->updateExistingPivot($existingProduct->id, ['quantity' => $newProductData['quantity']]);
                        } else {
                            throw ValidationException::withMessages([
                                "products.{$existingProduct->id}.quantity" => ['Insufficient stock for product.'],
                            ]);
                        }
                    }
                } else {
                    // 2- If the product is not present in the new request, detach it and restore the stock
                    $existingProduct->stock += $existingProduct->pivot->quantity;
                    $existingProduct->save();

                    $order->products()->detach($existingProduct->id);
                }
            }

            // 3- Handle new products in the request
            $dbProducts = Product::whereIn('id', collect($products)->pluck('product_id'))->get();
            foreach ($products as $index => $newProductData) {
                $product = $dbProducts->where('id', $newProductData['product_id'])->first();

                // If the product is new to the order
                if (!$order->products->contains($product->id)) {
                    if ($product && $product->stock >= $newProductData['quantity']) {

                        $product->stock -= $newProductData['quantity'];
                        $product->save();

                        $order->products()->attach($product->id, ['quantity' => $newProductData['quantity']]);
                    } else {
                        throw ValidationException::withMessages([
                            "products.$index.quantity" => ['Product stock insufficient or invalid product.'],
                        ]);
                    }
                }
            }

            DB::commit();

            return $order;

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new OrderProcessingException('Failed to update the order.', 0, $e);
        }
    }



    public function deleteOrder(Order $order)
    {
        DB::beginTransaction();

        try {
            $order->load('products');

            // Restore stock for each product in the order
            foreach ($order->products as $product) {
                $product->stock += $product->pivot->quantity;
                $product->save();
            }

            $order->delete();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw new OrderProcessingException('Failed to delete the order.', 0, $e);
        }
    }

}
