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
        // Normalize path and candidates
        $raw = $request->getPathInfo();
        // e.g. "/a/b/p1/" or "/"

        $trimmed = trim($raw, '/');
        // "a/b/p1" or ""

        $candidates = array_values(array_unique(array_filter([
            $trimmed,                                            // "a/b/p1"
            $trimmed === '' ? '' : $trimmed . '/',               // "a/b/p1/"
            '/' . $trimmed,                                      // "/a/b/p1"
            $trimmed === '' ? '/' : '/' . $trimmed . '/',        // "/a/b/p1/"
            $trimmed === '' ? '/' : null,                        // root special-case
        ])));

        // 1) Try active (latest version, not trashed)
        $active = Url::query()
            ->whereNull('deleted_at')
            ->whereIn('full_url', $candidates)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        // 2) Legacy → 301
        if (!$active) {
            $legacy = Url::withTrashed()
                ->whereNotNull('deleted_at')
                ->whereIn('full_url', $candidates)
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

            if ($legacy) {
                $canonical = Url::query()
                    ->ofUrlable($legacy->urlable_type, $legacy->urlable_id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('version')
                    ->orderByDesc('id')
                    ->first();

                if ($canonical) {
                    $target = '/' . ltrim($canonical->full_url, '/');
                    if ($qs = $request->getQueryString()) {
                        $target .= '?' . $qs;
                    }
                    return redirect($target, 301);
                }

                abort(404, trans('url::base.exceptions.not_found'));
            }

            abort(404, trans('url::base.exceptions.not_found'));
        }

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
