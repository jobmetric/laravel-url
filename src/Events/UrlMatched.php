<?php

namespace JobMetric\Url\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use JobMetric\Url\Models\Url;

/**
 * Class UrlMatched
 *
 * Event emitted when the fallback route matches an active entry in the `urls` table.
 * Listeners can inspect the matched URL record, its related model (urlable), the
 * optional collection, and the incoming request, and then set a response.
 *
 * Usage in a listener:
 *  $event->respond(app(ProductController::class)->show($event->urlable));
 */
class UrlMatched
{
    /**
     * Incoming HTTP request.
     *
     * @var Request
     */
    public Request $request;

    /**
     * Matched active URL row.
     *
     * @var Url
     */
    public Url $url;

    /**
     * The related model resolved via polymorphic relation.
     *
     * @var Model
     */
    public Model $urlable;

    /**
     * Optional URL collection scope.
     *
     * @var string|null
     */
    public ?string $collection;

    /**
     * Response provided by listeners (view/redirect/json/etc.).
     * Set to null by default; listeners should assign a concrete response.
     *
     * @var mixed
     */
    public mixed $response = null;

    /**
     * Create a new event instance.
     *
     * @param Request $request The incoming HTTP request.
     * @param Url $url The matched active URL record.
     */
    public function __construct(Request $request, Url $url)
    {
        $this->request = $request;
        $this->url = $url;
        $this->urlable = $url->urlable;
        $this->collection = $url->collection;
    }

    /**
     * Set the response to be returned by the fallback handler.
     *
     * @param mixed $response A controller/action result (view/redirect/json/etc.).
     *
     * @return void
     */
    public function respond(mixed $response): void
    {
        $this->response = $response;
    }
}
