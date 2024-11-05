<?php

namespace JobMetric\Url\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

class UrlSlug extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string|null $value = null,
    )
    {
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @throws Throwable
     */
    public function render(): View|Closure|string
    {
        return $this->view('url::components.url-slug');
    }

}
