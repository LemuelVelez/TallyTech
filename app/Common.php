<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('format_points')) {
    /**
     * Display a DECIMAL(8,2) point value without losing meaningful fractions.
     */
    function format_points(mixed $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

if (! function_exists('ui_icon')) {
    /**
     * Render a reusable SVG icon from the local TallyTech icon sprite.
     */
    function ui_icon(string $name, string $class = 'ui-icon'): string
    {
        $safeName = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?: 'circle';
        $safeClass = esc($class, 'attr');
        $href = esc(base_url('assets/icons/ui.svg') . '#' . $safeName, 'attr');

        return '<svg class="' . $safeClass . '" aria-hidden="true" focusable="false"><use href="' . $href . '"></use></svg>';
    }
}
