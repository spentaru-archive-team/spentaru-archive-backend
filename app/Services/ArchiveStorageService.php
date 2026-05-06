<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\ArchivePhysicalLocation;
use App\Models\ArchiveStorageRule;
use App\Models\Cabinet;
use App\Models\Rack;

class ArchiveStorageService
{
    public function findAvailableSlot(int $categoryId, ?int $subcategoryId = null): ?array
    {
        $rule = $this->findStorageRule($categoryId, $subcategoryId);

        if (! $rule) {
            $rule = $this->findStorageRule($categoryId, null);
        }

        if (! $rule) {
            $rule = $this->getAnyAvailableRule();
        }

        if (! $rule) {
            return null;
        }

        $cabinet = Cabinet::find($rule->cabinet_id);

        if (! $cabinet) {
            return null;
        }

        $rack = $this->findAvailableRackInCabinet($cabinet->id);

        if (! $rack) {
            return null;
        }

        $slotNumber = $this->findNextAvailableSlot($rack);

        return [
            'cabinet_id' => $cabinet->id,
            'rack_id' => $rack->id,
            'slot_number' => $slotNumber,
            'label_code' => $this->generateLabelCode($cabinet, $rack, $slotNumber),
        ];
    }

    private function findStorageRule(int $categoryId, ?int $subcategoryId): ?ArchiveStorageRule
    {
        if ($subcategoryId) {
            $subcategoryRule = ArchiveStorageRule::with(['cabinet'])
                ->where('category_id', $categoryId)
                ->where('subcategory_id', $subcategoryId)
                ->first();

            if ($subcategoryRule) {
                return $subcategoryRule;
            }
        }

        return ArchiveStorageRule::with(['cabinet'])
            ->where('category_id', $categoryId)
            ->whereNull('subcategory_id')
            ->first();
    }

    private function getAnyAvailableRule(): ?ArchiveStorageRule
    {
        return ArchiveStorageRule::with(['cabinet'])
            ->orderBy('priority', 'desc')
            ->first();
    }

    private function findAvailableRackInCabinet(int $cabinetId): ?Rack
    {
        $racks = Rack::where('cabinet_id', $cabinetId)
            ->orderBy('rack_number')
            ->get();

        foreach ($racks as $rack) {
            $currentSlot = ArchivePhysicalLocation::where('rack_id', $rack->id)->count();
            if ($currentSlot < $rack->capacity) {
                return $rack;
            }
        }

        return null;
    }

    private function findNextAvailableSlot(Rack $rack): int
    {
        $lastSlot = ArchivePhysicalLocation::where('rack_id', $rack->id)
            ->max('slot_number');

        return ($lastSlot ?? 0) + 1;
    }

    private function generateLabelCode(Cabinet $cabinet, Rack $rack, int $slotNumber): string
    {
        return 'L' . $cabinet->cabinet_number . '-R' . $rack->rack_number . '-S' . $slotNumber;
    }

    public function assignLocation(Archive $archive, int $categoryId, ?int $subcategoryId = null): ?ArchivePhysicalLocation
    {
        $slot = $this->findAvailableSlot($categoryId, $subcategoryId);

        if (! $slot) {
            return null;
        }

        $location = $archive->physicalLocation()->create([
            'cabinet_id' => $slot['cabinet_id'],
            'rack_id' => $slot['rack_id'],
            'slot_number' => $slot['slot_number'],
            'label_code' => $slot['label_code'],
        ]);

        Rack::find($slot['rack_id'])->increment('used_capacity');

        return $location;
    }
}
