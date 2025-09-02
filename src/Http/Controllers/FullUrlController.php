<?php

namespace JobMetric\Url\Http\Controllers;

use Illuminate\Http\Request;

class FullUrlController
{
    public function __invoke(Request $request)
    {
        $path = ltrim($request->path(), '/'); // normalized

        // TODO: lookup your "full urls" table (including locale/collection/type if needed)
        // $record = Url::query()->where('full_url', $path)->first();

        // Example pseudo-logic:
        // if ($record) { return $this->dispatchToTarget($record, $request); }

        abort(404);
    }
}
