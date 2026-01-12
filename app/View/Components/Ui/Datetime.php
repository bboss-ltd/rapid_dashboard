<?php

namespace App\View\Components\Ui;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\View\Component;

class Datetime extends Component
{
    public function __construct(
        public mixed $value,
        public string $format = '',
        public string $fallback = '—',
    ) {}

    public function render()
    {
        return view('components.ui.datetime');
    }

    public function formatted(): string
    {
        if (!$this->value) return $this->fallback;

        // Accept Carbon, DateTimeInterface, or parseable string
        try {
            $dt = $this->value instanceof \DateTimeInterface
                ? $this->value
                : Carbon::parse($this->value);

            $fmt = $this->format ?: config('display.datetime');

            return $dt->format($fmt);
        } catch (\Throwable) {
            return $this->fallback;
        }
    }
}
