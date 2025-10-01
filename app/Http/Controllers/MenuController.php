<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index() { return Menu::all(); }

    public function store(Request $request){
        $request->validate([
            'name'=>'required|string',
            'price'=>'required|numeric'
        ]);
        return Menu::create($request->all());
    }

    public function show(Menu $menu){ return $menu; }

    public function update(Request $request, Menu $menu){
        $menu->update($request->all());
        return $menu;
    }

    public function destroy(Menu $menu){
        $menu->delete();
        return response()->json(['message'=>'Deleted']);
    }
}
