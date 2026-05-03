<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCabinetRequest;
use App\Http\Requests\UpdateCabinetRequest;
use App\Models\Cabinet;
use App\Models\Rack;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CabinetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cabinets = Cabinet::with('racks')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semua lemari',
            'data' => $cabinets,
        ]);
    }

    public function store(StoreCabinetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $cabinet = DB::transaction(function () use ($validated) {
            $cabinetData = $validated;
            unset($cabinetData['racks']);

            $cabinet = Cabinet::create($cabinetData);
            $this->syncRacks($cabinet, $validated['racks']);

            return $this->loadCabinetWithOrderedRacks($cabinet);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat lemari',
            'data' => $cabinet,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $cabinet = Cabinet::with(['racks' => function ($query) {
            $query->orderBy('rack_number');
        }])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail lemari',
            'data' => $cabinet,
        ]);
    }

    public function update(UpdateCabinetRequest $request, string $id): JsonResponse
    {
        $cabinet = Cabinet::findOrFail($id);
        $validated = $request->validated();

        $cabinet = DB::transaction(function () use ($cabinet, $validated) {
            $cabinetData = $validated;
            unset($cabinetData['racks']);

            if ($cabinetData !== []) {
                $cabinet->update($cabinetData);
            }

            if (array_key_exists('racks', $validated)) {
                $this->syncRacks($cabinet, $validated['racks']);
            }

            return $this->loadCabinetWithOrderedRacks($cabinet);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui lemari',
            'data' => $cabinet,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $cabinet = Cabinet::with('racks.physicalLocations')->findOrFail($id);

        $hasOccupiedSlots = $cabinet->racks->contains(function ($rack) {
            return $rack->physicalLocations->isNotEmpty();
        });

        if ($hasOccupiedSlots) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus lemari yang memiliki arsip tersimpan',
            ], 422);
        }

        $cabinet->racks()->delete();
        $cabinet->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus lemari',
        ]);
    }

    private function syncRacks(Cabinet $cabinet, array $racks): void
    {
        $existingRacks = $cabinet->racks()->withCount('physicalLocations')->get()->keyBy('id');
        $retainedRackIds = [];

        foreach ($this->normalizeRacks($racks) as $rackData) {
            $rackId = $rackData['id'] ?? null;

            if ($rackId !== null) {
                /** @var Rack|null $rack */
                $rack = $existingRacks->get($rackId);

                if (! $rack) {
                    throw new HttpResponseException(response()->json([
                        'status' => 'error',
                        'message' => 'Rak yang dipilih tidak ditemukan pada lemari ini',
                    ], 422));
                }

                if ($rack->physical_locations_count > $rackData['capacity']) {
                    throw new HttpResponseException(response()->json([
                        'status' => 'error',
                        'message' => 'Capacity rak tidak boleh lebih kecil dari jumlah arsip fisik yang sudah tersimpan',
                    ], 422));
                }

                $rack->update($rackData);
                $retainedRackIds[] = $rack->id;

                continue;
            }

            $newRack = $cabinet->racks()->create($rackData);
            $retainedRackIds[] = $newRack->id;
        }

        $racksToDelete = $existingRacks->filter(
            fn (Rack $rack) => ! in_array($rack->id, $retainedRackIds, true)
        );

        $blockedRack = $racksToDelete->first(fn (Rack $rack) => $rack->physical_locations_count > 0);

        if ($blockedRack) {
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus rack yang masih memiliki arsip tersimpan',
            ], 422));
        }

        if ($racksToDelete->isNotEmpty()) {
            $cabinet->racks()->whereIn('id', $racksToDelete->modelKeys())->delete();
        }
    }

    /**
     * @return Collection<int, array<string, int|null>>
     */
    private function normalizeRacks(array $racks): Collection
    {
        return collect($racks)->map(function (array $rack) {
            return [
                'id' => isset($rack['id']) ? (int) $rack['id'] : null,
                'rack_number' => (int) $rack['rack_number'],
                'capacity' => (int) $rack['capacity'],
                'used_capacity' => (int) ($rack['used_capacity'] ?? 0),
            ];
        });
    }

    private function loadCabinetWithOrderedRacks(Cabinet $cabinet): Cabinet
    {
        return $cabinet->load(['racks' => function ($query) {
            $query->orderBy('rack_number');
        }]);
    }
}
