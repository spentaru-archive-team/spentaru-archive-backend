<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('user')
            ->orderBy('created_at', 'desc');

        if ($request->boolean('all')) {
            $events = $query->get();
        } else {
            $perPage = $request->query('per_page', 10);
            $events = $query->paginate($perPage);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semua event',
            'data' => $events,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => ['required', Rule::in(['ongoing', 'done'])],
        ]);

        $event = Event::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat event',
            'data' => $event->load('user'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $event = Event::with(['user', 'archives'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail event',
            'data' => $event,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'user_id' => 'sometimes|required|exists:users,id',
            'description' => 'nullable|string',
            'date' => 'sometimes|required|date',
            'status' => ['sometimes', Rule::in(['ongoing', 'done'])],
        ]);

        $event->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui event',
            'data' => $event->load('user'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $event = Event::with('archives')->findOrFail($id);

        if ($event->archives()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus event yang memiliki arsip',
            ], 422);
        }

        $event->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus event',
        ]);
    }
}
