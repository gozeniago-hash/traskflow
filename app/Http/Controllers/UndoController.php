<?php

namespace App\Http\Controllers;

use App\Actions\Undo\RestoreDeleted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UndoController extends Controller
{
    public function __invoke(Request $request, string $token, RestoreDeleted $restoreDeleted): JsonResponse
    {
        abort_unless(Str::isUuid($token), 404);
        $result = $restoreDeleted->handle($token, $request->user());
        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }
}
