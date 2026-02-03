<?php

namespace App\Http\Controllers\Api\Application;

use App\Data\PanelData;
use App\Extensions\Spatie\Fractalistic\Fractal;
use App\Http\Controllers\Controller;
use App\Transformers\Api\Application\BaseTransformer;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Webmozart\Assert\Assert;

abstract class ApplicationApiController extends Controller
{
    protected Request $request;

    protected Fractal $fractal;

    /**
     * ApplicationApiController constructor.
     */
    public function __construct()
    {
        Container::getInstance()->call([$this, 'loadDependencies']);

        // Parse all the includes to use on this request.
        $input = $this->request->input('include', []);
        $input = is_array($input) ? $input : explode(',', $input);

        $includes = (new Collection($input))->map(function ($value) {
            return trim($value);
        })->filter()->toArray();

        $this->fractal->parseIncludes($includes);
        $this->fractal->limitRecursion(2);
    }

    /**
     * Perform dependency injection of certain classes needed for core functionality
     * without littering the constructors of classes that extend this abstract.
     */
    public function loadDependencies(Fractal $fractal, Request $request): void
    {
        $this->fractal = $fractal;
        $this->request = $request;
    }

    /**
     * Return an instance of an application transformer.
     *
     * @template T of \App\Transformers\Api\Application\BaseTransformer
     *
     * @param  class-string<T>  $abstract
     * @return T
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function getTransformer(string $abstract)
    {
        Assert::subclassOf($abstract, BaseTransformer::class);

        return $abstract::fromRequest($this->request);
    }

    /**
     * Return a single Data object as a JSON response with Fractal compatibility.
     *
     * @param  PanelData  $data
     * @param  int  $status
     * @return JsonResponse|array<string, mixed>
     */
    protected function data(PanelData $data, int $status = 200): JsonResponse|array
    {
        $data->setFractal(true);
        
        if ($status === 200) {
            return $data->toArray();
        }

        return new JsonResponse($data->toArray(), $status);
    }

    /**
     * Return a collection of Data objects as a JSON response with Fractal compatibility.
     *
     * @param  array<PanelData>|Collection<int, PanelData>|LengthAwarePaginator  $items
     * @param  int  $status
     * @return JsonResponse|array<string, mixed>
     */
    protected function dataCollection($items, int $status = 200): JsonResponse|array
    {
        // Convert to array if it's a Collection or Paginator
        if ($items instanceof LengthAwarePaginator) {
            $items = $items->items();
        } elseif ($items instanceof Collection) {
            $items = $items->all();
        }

        // Get the first item to determine the class
        $result = !empty($items) && $items[0] instanceof PanelData
            ? $items[0]::collection($items, true)
            : ['object' => 'list', 'data' => []];

        if ($status === 200) {
            return $result;
        }

        return new JsonResponse($result, $status);
    }

    /**
     * Return an HTTP/204 response for the API.
     */
    protected function returnNoContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Return an HTTP/406 response for the API.
     */
    protected function returnNotAcceptable(): Response
    {
        return new Response('', Response::HTTP_NOT_ACCEPTABLE);
    }
}
