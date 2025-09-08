<?php

namespace JobMetric\Url\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JobMetric\Url\Events\UrlMatched;
use JobMetric\Url\Models\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class FullUrlController
 *
 * Fallback controller that resolves any unmatched request path against the
 * versioned `urls` table. If an active URL is found, it dispatches a
 * {@see UrlMatched} event so application listeners can return the appropriate
 * response (e.g., product/category/page controllers). If only a soft-deleted
 * (legacy) URL is found, it issues a 301 redirect to the current canonical URL.
 * Otherwise, a translated 404 response is returned.
 */
class FullUrlController extends Controller
{
    /**
     * Handle fallback requests:
     *  1) Normalize path and lookup an active URL.
     *  2) If not found, check soft-deleted URL and redirect (301) to the current canonical URL.
     *  3) If active URL found, dispatch {@see UrlMatched} so listeners can return a response.
     *  4) If no listener handled it, return a translated 404.
     *
     * @param Request $request Incoming HTTP request.
     *
     * @return RedirectResponse|Response
     * @throws NotFoundHttpException When no URL matches.
     */
    public function __invoke(Request $request)
    {
        $path = ltrim($request->path(), '/');

        // Active URL lookup
        $active = Url::query()
            ->where('full_url', $path)
            ->whereNull('deleted_at')
            ->first();

        // If not active, try legacy (soft-deleted) URL -> redirect to current canonical
        if (!$active) {
            $trashed = Url::query()
                ->where('full_url', $path)
                ->onlyTrashed()
                ->latest('id')
                ->first();

            if ($trashed) {
                $current = Url::query()
                    ->ofUrlable($trashed->urlable_type, $trashed->urlable_id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('version')
                    ->first();

                if ($current && $current->full_url !== $path) {
                    $target = '/' . ltrim($current->full_url, '/');
                    if ($qs = $request->getQueryString()) {
                        $target .= '?' . $qs;
                    }

                    return redirect($target, 301);
                }
            }

            abort(404, trans('url::base.exceptions.not_found'));
        }

        // Ensure relation is loaded
        $active->load('urlable');

        if (!$active->urlable) {
            abort(404, trans('url::base.exceptions.not_found'));
        }

        // Dispatch event so listeners can route to the proper controller/action
        $event = new UrlMatched($request, $active);
        event($event);

        if ($event->response !== null) {
            return $event->response;
        }

        // No listener handled the URL
        abort(404, trans('url::base.exceptions.not_found'));
    }
}
