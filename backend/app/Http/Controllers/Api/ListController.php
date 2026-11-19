<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Models\Liste;
use App\Service\ListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListController extends Controller
{
    /** Each id costs one UPDATE — an unbounded array is a cheap way to tie up the database. */
    private const MAX_REORDER = 500;

    public function __construct(private readonly ListService $listService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Liste::class);

        return new JsonResponse($this->listService->getAllLists($request->user(), $request->only(['search', 'filter', 'sort', 'page'])));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $list = $this->listService->findList($id, $request->user());
        $this->authorize('view', $list);

        return new JsonResponse($this->listService->getList($list, $request->user()));
    }

    public function store(StoreListRequest $request): JsonResponse
    {
        $this->authorize('create', Liste::class);

        return new JsonResponse($this->listService->createList($request->validated(), $request->user()), 201);
    }

    public function update(UpdateListRequest $request, int $id): JsonResponse
    {
        $list = $this->listService->findList($id, $request->user());
        $this->authorize('update', $list);

        return new JsonResponse($this->listService->updateList($id, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $list = $this->listService->findList($id, $request->user());
        $this->authorize('delete', $list);
        $this->listService->deleteList($id, $request->user());

        return new JsonResponse(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => ['required', 'array', 'max:'.self::MAX_REORDER], 'order.*' => ['integer', 'distinct']]);
        $this->listService->reorderLists($request->input('order'), $request->user());

        return new JsonResponse(null, 204);
    }
}
