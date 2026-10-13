<?php
declare(strict_types=1);

/**
 * Shared presentation helpers for the report tables (Reports, Working Hours,
 * Payroll). Kept out of the data classes so HTML stays in the view layer.
 */

if (!function_exists('render_break_starts')) {
    /**
     * Render the Break Start times for a day, one per break, each tagged Meal or
     * Short. Kept in its own column so Break Start and Break End show separately.
     *
     * @param array<int,array{start:string,end:?string,minutes:int,type:string,ongoing:bool}> $breaks
     */
    function render_break_starts(array $breaks): string
    {
        if (!$breaks) return '<span class="text-mute">&mdash;</span>';
        $out = [];
        foreach ($breaks as $b) {
            $isMeal = ($b['type'] ?? 'short') === 'meal';
            $tag    = '<span class="badge ' . ($isMeal ? 'present' : '') . '" style="font-size:10px">'
                    . ($isMeal ? 'Meal' : 'Short') . '</span>';
            $out[]  = '<div style="white-space:nowrap">' . htmlspecialchars($b['start']) . ' ' . $tag . '</div>';
        }
        return implode('', $out);
    }
}

if (!function_exists('render_break_ends')) {
    /**
     * Render the Break End times for a day, aligned row-for-row with the starts.
     * An unclosed break (forgotten Break End) is flagged so it can be corrected
     * from the Attendance log.
     *
     * @param array<int,array{start:string,end:?string,minutes:int,type:string,ongoing:bool}> $breaks
     */
    function render_break_ends(array $breaks): string
    {
        if (!$breaks) return '<span class="text-mute">&mdash;</span>';
        $out = [];
        foreach ($breaks as $b) {
            if (!empty($b['ongoing'])) {
                $out[] = '<div style="white-space:nowrap"><span class="badge late" title="No Break End punched — edit it in Attendance">open</span></div>';
            } else {
                $out[] = '<div style="white-space:nowrap">' . htmlspecialchars((string)$b['end'])
                       . ' <span class="text-mute" style="font-size:10px">(' . (int)$b['minutes'] . 'm)</span></div>';
            }
        }
        return implode('', $out);
    }
}
