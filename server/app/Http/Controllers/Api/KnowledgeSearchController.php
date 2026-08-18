<?php

namespace App\Http\Controllers\Api;

use App\Application\Knowledge\SearchNotesUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class KnowledgeSearchController extends Controller
{
    public function __construct(
        private readonly SearchNotesUseCase $searchNotesUseCase,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = $validator->validated()['q'];

        $notes = $this->searchNotesUseCase->__invoke(
            $request->user()->id,
            $query,
        );

        return response()->json([
            'notes' => array_map(fn ($note) => $note->toArray(), $notes),
            'query' => $query,
        ]);
    }
}
