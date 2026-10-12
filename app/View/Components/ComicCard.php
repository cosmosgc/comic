<?php

namespace App\View\Components;

use App\Models\Comic;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ComicCard extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public Comic $comic,
        public bool $minified = false,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.comic-card');
    }
}
