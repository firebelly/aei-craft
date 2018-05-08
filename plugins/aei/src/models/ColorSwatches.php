<?php

namespace firebelly\aei\models;

class ColorSwatches
{
    public $label = '';
    public $color = '';

    public function __construct($value)
    {
        if (!empty($value)) {
            if (is_array($value)) {
                $this->label = $value['label'];
                $this->color = $value['color'];
            } else {
                $value = json_decode($value);
                $this->label = $value->label;
                $this->color = $value->color;
            }
        }
    }

    public function __toString()
    {
        return $this->label;
    }

    public function colors()
    {
        return explode(',', $this->color);
    }
}
