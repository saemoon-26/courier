<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    // POST: Create new address
    public function store(Request $request)
    {
        $request->validate([
            'city' => 'required|string|max:100',
            'address' => 'required|string',
            'country' => 'required|string|max:100',
    'state' => 'required|string|max:100',
    'zipcode' => 'required|string|max:20',
        ]);

        $address = Address::create([
            'user_id' => $request->user()->id, // logged in user
            'city' => $request->city,
            'address' => $request->address,
            'Country'        => $request->country ,
    'State'          => $request->state ,
    'ZipCode'        => $request->zipcode ,
        ]);

        return response()->json([
            'message' => 'Address created successfully',
            'address' => $address
        ], 201);
    }

    // GET: Show one address by ID
    public function show($id)
    {
        $address = Address::findOrFail($id);

        return response()->json($address);
    }

    // GET: List all addresses of current user
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)->get();

        return response()->json($addresses);
    }
}
