<?php

namespace App\Http\Controllers;

use App\Support\Undo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UndoController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        abort_unless(\Illuminate\Support\Str::isUuid($token), 404);
        $result = Undo::restore($token, $request->user());

        return response()->json($result, $result['ok'] ? 200 : 410);
    }
}
