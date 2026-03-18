<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $menus = Menu::when($request->boolean('available'), fn($query) => $query->where('available', true))
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => MenuResource::collection($menus),
            'pagination' => [
                'total' => $menus->total(),
                'per_page' => $menus->perPage(),
                'current_page' => $menus->currentPage(),
                'last_page' => $menus->lastPage(),
            ]
        ]);
    }

    public function store(StoreMenuRequest $request)
    {
        $menu = Menu::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item created successfully',
            'data' => MenuResource::make($menu)
        ], 201);
    }

    public function show(Menu $menu)
    {
        return response()->json([
            'status' => 'success',
            'data' => MenuResource::make($menu)
        ]);
    }

    public function update(StoreMenuRequest $request, Menu $menu)
    {
        $menu->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item updated successfully',
            'data' => MenuResource::make($menu)
        ]);
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item deleted successfully'
        ]);
    }

    public function restore($id)
    {
        $menu = Menu::withTrashed()->findOrFail($id);
        $menu->restore();

        return response()->json([
            'status' => 'success',
            'message' => 'Menu item restored successfully',
            'data' => MenuResource::make($menu)
        ]);
    }
}
