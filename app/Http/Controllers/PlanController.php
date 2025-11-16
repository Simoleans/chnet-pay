<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\WisproApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    protected $wisproApi;

    public function __construct(WisproApiService $wisproApi)
    {
        $this->wisproApi = $wisproApi;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtener planes desde la API de Wispro
        $response = $this->wisproApi->getPlans(1, 100); // Obtener todos los planes

        $plans = [];
        if ($response['success'] && isset($response['data']['data'])) {
            // Mapear solo los datos necesarios: id, name, price
            $plans = collect($response['data']['data'])->map(function ($plan) {
                return [
                    'id' => $plan['id'],
                    'name' => $plan['name'],
                    'price' => (float) $plan['price']
                ];
            })->toArray();
        }

        return Inertia::render('Plans/Index', [
            'plans' => $plans
        ], [
            'title' => 'Lista de Planes'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'type' => 'required|string|in:tv,internet',
            'mbps' => 'nullable|integer|min:1',
            'status' => 'nullable|boolean'
        ]);

        Plan::create($validated);
        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Plan $plan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Plan $plan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'type' => 'required|string|in:tv,internet',
            'mbps' => 'nullable|integer|min:1',
            'status' => 'nullable|boolean'
        ]);

        $plan->update($validated);
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        //
    }
}
