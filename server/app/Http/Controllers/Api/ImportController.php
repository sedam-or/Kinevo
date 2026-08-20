<?php

namespace App\Http\Controllers\Api;

use App\Application\Imports\ConfirmKrsImportUseCase;
use App\Application\Imports\DiscardKrsImportUseCase;
use App\Application\Imports\GetKrsImportUseCase;
use App\Application\Imports\UploadKrsImportUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * KRS PDF import (FR-24): upload + parse into a staged preview, confirm to
 * persist Hard Landscape, or discard. Parsed events are never persisted before
 * explicit user confirmation.
 */
final class ImportController extends Controller
{
    public function __construct(
        private readonly UploadKrsImportUseCase $upload,
        private readonly GetKrsImportUseCase $get,
        private readonly ConfirmKrsImportUseCase $confirm,
        private readonly DiscardKrsImportUseCase $discard,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null) {
            return response()->json(['errors' => ['file' => ['A PDF file is required.']]], 422);
        }

        try {
            $import = $this->upload->__invoke($request->user()->id, $file);

            return response()->json(['import' => $import->toArray()], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['file' => [$e->getMessage()]]], 422);
        }
    }

    public function show(Request $request, int $importId): JsonResponse
    {
        try {
            return response()->json(['import' => $this->get->__invoke($request->user()->id, $importId)->toArray()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['import' => [$e->getMessage()]]], 404);
        }
    }

    public function confirm(Request $request, int $importId): JsonResponse
    {
        try {
            $import = $this->confirm->__invoke($request->user()->id, $importId);

            return response()->json(['import' => $import->toArray()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['import' => [$e->getMessage()]]], 422);
        }
    }

    public function discard(Request $request, int $importId): JsonResponse
    {
        try {
            $import = $this->discard->__invoke($request->user()->id, $importId);

            return response()->json(['import' => $import->toArray()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['import' => [$e->getMessage()]]], 422);
        }
    }
}
