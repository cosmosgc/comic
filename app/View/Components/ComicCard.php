<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ComicCard extends Component
{
    public $comic;
    public $minified;

    /**
     * Create a new component instance.
     *
     * @param $comic
     * @param bool $minified
     */
    public function __construct($comic, $minified = false) // Default to false
    {
        $this->comic = $comic;
        $this->minified = $minified;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.comic-card');
    }
}
